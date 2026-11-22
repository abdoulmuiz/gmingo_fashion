<?php
/** @var bool $orders_off */
/** @var list<array> $groups */
/** @var array{production:int,overdue:int,week:int,ready:int} $summary */
/** @var string $filter */
/** @var string $query */
/** @var int $total_cards */
/** @var bool $can_move */
/** @var string $csrf */
/** @var string $stage_move_base */
/** @var string $board_path */

$summary = $summary ?? ['production' => 0, 'overdue' => 0, 'week' => 0, 'ready' => 0];
$filter = $filter ?? 'all';
$query = $query ?? '';
$groups = $groups ?? [];
$total_cards = (int) ($total_cards ?? 0);
$can_move = (bool) ($can_move ?? false);
$csrf = (string) ($csrf ?? '');
$board_path = $board_path ?? '/board';
$previewLimit = 15;

$filterLabels = [
    'all' => t('board_filter_all'),
    'urgent' => t('urgent'),
    'overdue' => t('overdue'),
    'mine' => t('board_filter_mine'),
    'week' => t('board_filter_week'),
];
$summaryCards = [
    [
        'key' => 'all',
        'label' => t('board_summary_production'),
        'value' => number_format((int) $summary['production']),
        'tone' => 'blue',
        'icon' => 'package',
        'href' => stage_board_path('all', $query),
    ],
    [
        'key' => 'overdue',
        'label' => t('overdue'),
        'value' => number_format((int) $summary['overdue']),
        'tone' => 'red',
        'icon' => 'clock',
        'href' => stage_board_path('overdue', $query),
    ],
    [
        'key' => 'week',
        'label' => t('board_summary_week'),
        'value' => number_format((int) $summary['week']),
        'tone' => 'orange',
        'icon' => 'calendar',
        'href' => stage_board_path('week', $query),
    ],
    [
        'key' => 'ready',
        'label' => t('board_summary_ready'),
        'value' => number_format((int) $summary['ready']),
        'tone' => 'green',
        'icon' => 'circle-check',
        'href' => stage_board_path('all', $query),
    ],
];
?>
<?php if ($orders_off): ?>
    <section class="card board-orders-off">
        <h1><?= e(t('board_title')) ?></h1>
        <p><?= e(t('board_needs_orders')) ?></p>
    </section>
<?php else: ?>
<section
    class="board-page"
    data-board
    data-can-move="<?= $can_move ? '1' : '0' ?>"
    data-csrf="<?= e($csrf) ?>"
    data-move-base="<?= e($stage_move_base) ?>"
    data-return-to="<?= e($board_path) ?>"
    data-moved-msg="<?= e(t('moved')) ?>"
    data-move-fail="<?= e(t('board_move_failed')) ?>"
>
    <header class="board-header">
        <div class="board-header-copy">
            <h1><?= e(t('board_title')) ?></h1>
            <p><?= e(t('board_subtitle')) ?></p>
        </div>
        <div class="board-header-actions">
            <form class="board-search-form" method="get" action="<?= e(url('/board')) ?>">
                <?php if ($filter !== 'all'): ?>
                    <input type="hidden" name="filter" value="<?= e($filter) ?>">
                <?php endif; ?>
                <label class="board-search">
                    <span class="sr-only"><?= e(t('search')) ?></span>
                    <?= ui_icon('search', 'ui-icon board-search-icon') ?>
                    <input
                        type="search"
                        name="q"
                        value="<?= e($query) ?>"
                        placeholder="<?= e(t('board_search_placeholder')) ?>"
                        autocomplete="off"
                    >
                </label>
                <label class="board-filter">
                    <span class="sr-only"><?= e(t('board_filter')) ?></span>
                    <select name="filter" onchange="this.form.submit()">
                        <?php foreach ($filterLabels as $value => $label): ?>
                            <option value="<?= e($value) ?>"<?= $filter === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <noscript>
                    <button class="btn btn-small" type="submit"><?= e(t('search')) ?></button>
                </noscript>
            </form>
            <a class="btn board-btn-nowrap" href="<?= e(url('/orders/new')) ?>">
                <?= ui_icon('plus', 'ui-icon') ?>
                <span><?= e(t('new_order')) ?></span>
            </a>
        </div>
    </header>

    <section class="dash-kpis board-summary" aria-label="<?= e(t('board_title')) ?>">
        <?php foreach ($summaryCards as $card): ?>
            <a
                class="dash-kpi dash-kpi-<?= e((string) $card['tone']) ?><?= $filter === $card['key'] ? ' is-active' : '' ?>"
                href="<?= e(url((string) $card['href'])) ?>"
                <?php if ($card['key'] === 'ready'): ?>data-board-focus-ready<?php endif; ?>
            >
                <span class="dash-kpi-top">
                    <span class="dash-kpi-icon" aria-hidden="true"><?= ui_icon((string) $card['icon'], 'ui-icon') ?></span>
                    <span class="dash-kpi-body">
                        <span class="dash-kpi-label"><?= e((string) $card['label']) ?></span>
                        <strong class="dash-kpi-value"><?= e((string) $card['value']) ?></strong>
                    </span>
                </span>
            </a>
        <?php endforeach; ?>
    </section>

    <?php if ($total_cards === 0): ?>
        <section class="board-empty card">
            <span class="board-empty-icon" aria-hidden="true"><?= ui_icon('scissors', 'ui-icon') ?></span>
            <h2><?= e($query !== '' || $filter !== 'all' ? t('board_empty_filtered') : t('board_empty_title')) ?></h2>
            <p><?= e($query !== '' || $filter !== 'all' ? t('board_empty_filtered_help') : t('board_empty_help')) ?></p>
            <?php if ($query !== '' || $filter !== 'all'): ?>
                <a class="btn" href="<?= e(url('/board')) ?>"><?= e(t('orders_clear_filters')) ?></a>
            <?php else: ?>
                <a class="btn" href="<?= e(url('/orders/new')) ?>"><?= e(t('new_order')) ?></a>
            <?php endif; ?>
        </section>
    <?php else: ?>
        <div class="board-mobile-tabs" data-board-mobile-tabs>
            <?php foreach ($groups as $index => $group): ?>
                <button
                    type="button"
                    class="board-mobile-tab<?= $index === 0 ? ' is-active' : '' ?>"
                    data-board-tab="<?= e((string) $group['id']) ?>"
                >
                    <span><?= e((string) $group['name']) ?></span>
                    <span class="board-chip-count"><?= e(number_format((int) $group['count'])) ?></span>
                </button>
            <?php endforeach; ?>
        </div>

        <div class="board-kanban" data-board-kanban>
            <?php foreach ($groups as $index => $group): ?>
                <?php
                $orders = is_array($group['orders'] ?? null) ? $group['orders'] : [];
                $visible = array_slice($orders, 0, $previewLimit);
                $hidden = array_slice($orders, $previewLimit);
                $nextId = $group['next_stage_id'] ?? null;
                ?>
                <section
                    class="board-column tone-<?= e((string) $group['tone']) ?><?= $index === 0 ? ' is-mobile-active' : '' ?>"
                    data-board-column
                    data-stage-id="<?= e((string) $group['id']) ?>"
                    data-stage-code="<?= e((string) ($group['code'] ?? '')) ?>"
                    data-next-stage-id="<?= e((string) ($nextId ?? '')) ?>"
                    data-stage-role="<?= e((string) ($group['role'] ?? '')) ?>"
                >
                    <header class="board-column-head">
                        <h2><?= e((string) $group['name']) ?></h2>
                        <span class="board-chip-count" data-column-count><?= e(number_format((int) $group['count'])) ?></span>
                    </header>
                    <div
                        class="board-column-body"
                        data-board-list
                        data-stage-id="<?= e((string) $group['id']) ?>"
                    >
                        <?php if ($orders === []): ?>
                            <p class="board-column-empty"><?= e(t('board_column_empty')) ?></p>
                        <?php endif; ?>
                        <?php foreach ($visible as $order): ?>
                            <?php require __DIR__ . '/partials/board_card.php'; ?>
                        <?php endforeach; ?>
                        <?php if ($hidden !== []): ?>
                            <div class="board-more-wrap" data-board-more-wrap hidden>
                                <?php foreach ($hidden as $order): ?>
                                    <?php require __DIR__ . '/partials/board_card.php'; ?>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" class="board-show-more" data-board-show-more>
                                <?= e(t('board_show_more', ['n' => (string) count($hidden)])) ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<script>
(function () {
    var root = document.querySelector('[data-board]');
    if (!root) return;
    var canMove = root.getAttribute('data-can-move') === '1';
    var csrf = root.getAttribute('data-csrf') || '';
    var moveBase = root.getAttribute('data-move-base') || '';
    var returnTo = root.getAttribute('data-return-to') || '/board';
    var movedMsg = root.getAttribute('data-moved-msg') || 'Moved';
    var failMsg = root.getAttribute('data-move-fail') || 'Could not move';
    var isMobile = function () { return window.matchMedia('(max-width: 767px)').matches; };

    function showToast(message, isError) {
        if (!window.toast) return;
        if (isError) {
            window.toast.error(message);
        } else {
            window.toast.success(message);
        }
    }

    // Highlight last opened order card.
    try {
        var lastId = window.sessionStorage.getItem('gmingo_board_last_order');
        if (lastId) {
            var lastCard = root.querySelector('[data-order-id="' + lastId.replace(/"/g, '') + '"]');
            if (lastCard) lastCard.classList.add('is-last-opened');
        }
    } catch (e) {}

    root.querySelectorAll('[data-board-card-link]').forEach(function (link) {
        link.addEventListener('click', function () {
            var id = link.getAttribute('data-order-id') || '';
            try { window.sessionStorage.setItem('gmingo_board_last_order', id); } catch (e) {}
        });
    });

    // Show more
    root.querySelectorAll('[data-board-show-more]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var wrap = btn.parentElement && btn.parentElement.querySelector('[data-board-more-wrap]');
            if (wrap) wrap.hidden = false;
            btn.remove();
        });
    });

    // Mobile stage tabs
    var tabs = root.querySelectorAll('[data-board-tab]');
    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            var id = tab.getAttribute('data-board-tab');
            tabs.forEach(function (t) { t.classList.toggle('is-active', t === tab); });
            root.querySelectorAll('[data-board-column]').forEach(function (col) {
                col.classList.toggle('is-mobile-active', col.getAttribute('data-stage-id') === id);
            });
        });
    });

    // Focus ready column when summary ready clicked
    root.querySelectorAll('[data-board-focus-ready]').forEach(function (a) {
        a.addEventListener('click', function (e) {
            var readyCol = root.querySelector('[data-stage-role="ready"]');
            if (!readyCol) return;
            e.preventDefault();
            if (isMobile()) {
                var id = readyCol.getAttribute('data-stage-id');
                var tab = root.querySelector('[data-board-tab="' + id + '"]');
                if (tab) tab.click();
            } else {
                readyCol.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
                readyCol.classList.add('is-focus');
                window.setTimeout(function () { readyCol.classList.remove('is-focus'); }, 1600);
            }
        });
    });

    function advanceOrder(orderId, onOk, onFail) {
        var body = new FormData();
        body.append('csrf', csrf);
        body.append('ajax', '1');
        body.append('return_to', returnTo);
        fetch(moveBase + '/' + encodeURIComponent(orderId) + '/stage', {
            method: 'POST',
            body: body,
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        }).then(function (res) {
            return res.json().then(function (data) {
                return { res: res, data: data };
            }).catch(function () {
                return { res: res, data: null };
            });
        }).then(function (pack) {
            if (pack.res.ok && pack.data && pack.data.ok) {
                onOk(pack.data);
            } else {
                onFail((pack.data && pack.data.message) || failMsg);
            }
        }).catch(function () {
            onFail(failMsg);
        });
    }

    // Menu: move next
    root.querySelectorAll('[data-board-move-next]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            if (!canMove) return;
            var card = btn.closest('[data-board-card]');
            if (!card) return;
            var orderId = card.getAttribute('data-order-id');
            var col = card.closest('[data-board-column]');
            var nextId = col && col.getAttribute('data-next-stage-id');
            if (!nextId) {
                showToast(failMsg, true);
                return;
            }
            var menu = btn.closest('details');
            if (menu) menu.open = false;
            advanceOrder(orderId, function (data) {
                var target = root.querySelector('[data-board-column][data-stage-id="' + data.stage_id + '"] [data-board-list]');
                if (target) {
                    var empty = target.querySelector('.board-column-empty');
                    if (empty) empty.remove();
                    target.insertBefore(card, target.querySelector('[data-board-more-wrap], [data-board-show-more]'));
                    refreshCounts();
                }
                showToast(movedMsg, false);
            }, function (msg) {
                showToast(msg, true);
            });
        });
    });

    function refreshCounts() {
        root.querySelectorAll('[data-board-column]').forEach(function (col) {
            var n = col.querySelectorAll('[data-board-card]').length;
            var pill = col.querySelector('[data-column-count]');
            if (pill) pill.textContent = String(n);
            var list = col.querySelector('[data-board-list]');
            if (list && n === 0 && !list.querySelector('.board-column-empty')) {
                var p = document.createElement('p');
                p.className = 'board-column-empty';
                p.textContent = <?= json_encode(t('board_column_empty'), JSON_UNESCAPED_UNICODE) ?>;
                list.prepend(p);
            }
        });
    }

    // HTML5 drag and drop — only onto the next stage column; calls existing advance endpoint.
    if (!canMove) return;
    var dragCard = null;
    var dragFrom = null;

    function bindDrag(card) {
        card.setAttribute('draggable', 'true');
        card.addEventListener('dragstart', function (e) {
            if (isMobile()) {
                e.preventDefault();
                return;
            }
            dragCard = card;
            dragFrom = card.parentElement;
            card.classList.add('is-dragging');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', card.getAttribute('data-order-id') || '');
        });
        card.addEventListener('dragend', function () {
            card.classList.remove('is-dragging');
            root.querySelectorAll('.is-drop-target').forEach(function (el) { el.classList.remove('is-drop-target'); });
            dragCard = null;
            dragFrom = null;
        });
    }

    root.querySelectorAll('[data-board-card]').forEach(bindDrag);

    root.querySelectorAll('[data-board-list]').forEach(function (list) {
        list.addEventListener('dragover', function (e) {
            if (isMobile() || !dragCard) return;
            var col = list.closest('[data-board-column]');
            var fromCol = dragCard.closest('[data-board-column]');
            if (!col || !fromCol) return;
            var nextId = fromCol.getAttribute('data-next-stage-id') || '';
            if (col.getAttribute('data-stage-id') !== nextId) return;
            e.preventDefault();
            col.classList.add('is-drop-target');
        });
        list.addEventListener('dragleave', function () {
            var col = list.closest('[data-board-column]');
            if (col) col.classList.remove('is-drop-target');
        });
        list.addEventListener('drop', function (e) {
            if (isMobile() || !dragCard) return;
            var col = list.closest('[data-board-column]');
            var fromCol = dragCard.closest('[data-board-column]');
            if (!col || !fromCol) return;
            var nextId = fromCol.getAttribute('data-next-stage-id') || '';
            if (col.getAttribute('data-stage-id') !== nextId) {
                showToast(failMsg, true);
                return;
            }
            e.preventDefault();
            col.classList.remove('is-drop-target');
            var orderId = dragCard.getAttribute('data-order-id');
            var card = dragCard;
            var origin = dragFrom;
            var empty = list.querySelector('.board-column-empty');
            if (empty) empty.remove();
            list.insertBefore(card, list.querySelector('[data-board-more-wrap], [data-board-show-more]'));
            refreshCounts();
            advanceOrder(orderId, function () {
                showToast(movedMsg, false);
            }, function (msg) {
                if (origin) origin.insertBefore(card, origin.querySelector('[data-board-more-wrap], [data-board-show-more]'));
                refreshCounts();
                showToast(msg, true);
            });
        });
    });
})();
</script>
<?php endif; ?>
