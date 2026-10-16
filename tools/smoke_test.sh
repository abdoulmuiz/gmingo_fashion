#!/usr/bin/env bash
# Hit the live Apache site and confirm private folders stay blocked
# and the language form can switch the login page to Hausa.
set -uo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PHP_BIN="$(command -v php || true)"
if [[ -z "$PHP_BIN" && -x /opt/lampp/bin/php ]]; then
    PHP_BIN="/opt/lampp/bin/php"
fi
if [[ -z "$PHP_BIN" ]]; then
    echo "FAIL: php is not available" >&2
    exit 1
fi
if ! command -v curl >/dev/null 2>&1; then
    echo "FAIL: curl is not available" >&2
    exit 1
fi

BASE_PATH="$("$PHP_BIN" -r ' $c = require $argv[1]; $base = rtrim((string) ($c["base_path"] ?? ""), "/"); if ($base === "" || $base[0] !== "/") { fwrite(STDERR, "base_path is missing\n"); exit(1); } echo $base;' "$ROOT/config/config.php")" || {
    echo "FAIL: could not read base_path from config/config.php" >&2
    exit 1
}

HAUSA="$("$PHP_BIN" -r ' $ha = require $argv[1]; $en = require $argv[2]; $key = "password"; if (!isset($ha[$key], $en[$key]) || $ha[$key] === "" || $ha[$key] === $en[$key]) { fwrite(STDERR, "Hausa password label is not distinct from English\n"); exit(1); } echo $ha[$key];' "$ROOT/lang/ha.php" "$ROOT/lang/en.php")" || {
    echo "FAIL: could not read the Hausa password label" >&2
    exit 1
}

tmpdir="$(mktemp -d "$ROOT/storage/logs/smoke.XXXXXX")"
trap 'rm -rf "$tmpdir"' EXIT
jar="$tmpdir/cookies"

fail() {
    echo "FAIL: $1" >&2
    if [[ -n "${2:-}" ]]; then
        echo "Saw:" >&2
        printf '%s\n' "$2" >&2
    fi
    exit 1
}

http_code() {
    local url="$1"
    local body="$2"
    local code
    code="$(curl -sS --connect-timeout 5 --max-time 15 -o "$body" -w '%{http_code}' "$url" 2>"$tmpdir/curl.err" || true)"
    if [[ ! "$code" =~ ^[0-9]{3}$ ]]; then
        code="000"
    fi
    printf '%s' "$code"
}

ORIGIN=""
for candidate in "http://127.0.0.1" "http://localhost"; do
    code="$(http_code "${candidate}${BASE_PATH}/login" "$tmpdir/probe.html")"
    if [[ "$code" == "200" ]] && grep -q 'name="csrf"' "$tmpdir/probe.html" && grep -q 'name="lang"' "$tmpdir/probe.html"; then
        ORIGIN="$candidate"
        break
    fi
    echo "probe ${candidate}${BASE_PATH}/login -> ${code}"
done
if [[ -z "$ORIGIN" ]]; then
    fail "no working Apache base URL for ${BASE_PATH}" "$(cat "$tmpdir/curl.err" 2>/dev/null || true)"
fi
echo "Using ${ORIGIN}${BASE_PATH}"

expect_open() {
    local path="$1"
    local url="${ORIGIN}${BASE_PATH}${path}"
    local code
    code="$(http_code "$url" "$tmpdir/open.html")"
    case "$code" in
        200|301|302|303)
            echo "OK ${code} ${url}"
            ;;
        *)
            fail "${url} should return 200 or a redirect" "status ${code}"$'\n'"$(head -c 400 "$tmpdir/open.html" 2>/dev/null || true)"
            ;;
    esac
}

expect_blocked() {
    local path="$1"
    local url="${ORIGIN}${BASE_PATH}${path}"
    local code
    code="$(http_code "$url" "$tmpdir/blocked.html")"
    case "$code" in
        403|404)
            echo "OK ${code} ${url}"
            ;;
        *)
            fail "${url} should return 403 or 404" "status ${code}"$'\n'"$(head -c 400 "$tmpdir/blocked.html" 2>/dev/null || true)"
            ;;
    esac
}

expect_open "/"
expect_open "/login"
expect_open "/register"
expect_blocked "/config/config.php"
expect_blocked "/lang/en.php"
expect_blocked "/.git/config"
expect_blocked "/tools/"
expect_blocked "/docs/"

login_code="$(curl -sS --connect-timeout 5 --max-time 15 -c "$jar" -b "$jar" -o "$tmpdir/login.html" -w '%{http_code}' "${ORIGIN}${BASE_PATH}/login" || true)"
if [[ "$login_code" != "200" ]]; then
    fail "login page did not return 200 before the language switch" "status ${login_code}"$'\n'"$(head -c 400 "$tmpdir/login.html" 2>/dev/null || true)"
fi

form="$("$PHP_BIN" -r '
$html = (string) file_get_contents($argv[1]);
if (!preg_match_all("#<form method=\"post\" action=\"([^\"]+)\"[^>]*>(.*?)</form>#s", $html, $forms, PREG_SET_ORDER)) {
    fwrite(STDERR, "no POST forms on the login page\n");
    exit(1);
}
$found = null;
foreach ($forms as $form) {
    if (str_contains($form[2], "name=\"lang\"")) {
        $found = $form;
        break;
    }
}
if ($found === null) {
    fwrite(STDERR, "language form not found\n");
    exit(1);
}
if (!preg_match("#name=\"csrf\" value=\"([a-f0-9]{64})\"#", $found[2], $csrf)) {
    fwrite(STDERR, "CSRF token not found in the language form\n");
    exit(1);
}
if (!preg_match("#name=\"back\" value=\"([^\"]*)\"#", $found[2], $back)) {
    fwrite(STDERR, "return path not found in the language form\n");
    exit(1);
}
$action = html_entity_decode($found[1], ENT_QUOTES | ENT_HTML5, "UTF-8");
$return = html_entity_decode($back[1], ENT_QUOTES | ENT_HTML5, "UTF-8");
if ($action === "" || str_contains($action, "://") || str_starts_with($action, "//")) {
    fwrite(STDERR, "language form action is not an in-app path: {$action}\n");
    exit(1);
}
echo $action, "\n", $csrf[1], "\n", $return, "\n";
' "$tmpdir/login.html")" || fail "could not read the language form" "$(head -c 500 "$tmpdir/login.html")"

action="$(printf '%s\n' "$form" | sed -n '1p')"
token="$(printf '%s\n' "$form" | sed -n '2p')"
back="$(printf '%s\n' "$form" | sed -n '3p')"
case "$action" in
    */language) ;;
    *) fail "language form does not post to /language" "$action" ;;
esac
if [[ "$action" == /* ]]; then
    post_url="${ORIGIN}${action}"
else
    fail "language form action is not a path" "$action"
fi

post_code="$(curl -sS --connect-timeout 5 --max-time 15 -c "$jar" -b "$jar" -D "$tmpdir/post.hdr" -o "$tmpdir/post.body" -w '%{http_code}' \
    -X POST \
    --data-urlencode "csrf=${token}" \
    --data-urlencode "lang=ha" \
    --data-urlencode "back=${back}" \
    "$post_url" || true)"
location="$(grep -i '^Location:' "$tmpdir/post.hdr" | head -n 1 | cut -d' ' -f2- | tr -d '\r')"
case "$post_code" in
    301|302|303) ;;
    *)
        fail "language POST should redirect" "status ${post_code}"$'\n'"$(cat "$tmpdir/post.hdr")"$'\n'"$(head -c 400 "$tmpdir/post.body")"
        ;;
esac
if [[ -z "$location" ]]; then
    fail "language POST did not send a Location header" "$(cat "$tmpdir/post.hdr")"
fi
case "$location" in
    http://*|https://*)
        case "$location" in
            "${ORIGIN}${BASE_PATH}/"*) follow_url="$location" ;;
            "${ORIGIN}${BASE_PATH}") follow_url="$location" ;;
            *) fail "language redirect left the app" "$location" ;;
        esac
        ;;
    /*)
        case "$location" in
            "${BASE_PATH}/"*|"${BASE_PATH}") follow_url="${ORIGIN}${location}" ;;
            *) fail "language redirect is outside the base path" "$location" ;;
        esac
        ;;
    *)
        fail "language redirect is not an in-app path" "$location"
        ;;
esac

final_code="$(curl -sS --connect-timeout 5 --max-time 15 -c "$jar" -b "$jar" -o "$tmpdir/final.html" -w '%{http_code}' "$follow_url" || true)"
if [[ "$final_code" != "200" ]]; then
    fail "page after the language switch was not 200" "status ${final_code} from ${follow_url}"$'\n'"$(head -c 400 "$tmpdir/final.html" 2>/dev/null || true)"
fi
if ! grep -F -q "$HAUSA" "$tmpdir/final.html"; then
    fail "page after switching to Hausa did not contain ${HAUSA}" "status ${final_code} from ${follow_url}"$'\n'"$(head -c 500 "$tmpdir/final.html")"
fi
echo "OK Hausa (${HAUSA}) after POST ${action} -> ${location}"
echo "Smoke test passed"
