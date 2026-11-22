(function (global) {
    'use strict';

    var MAX_VISIBLE = 3;
    var host = null;
    var shownIds = Object.create(null);

    function ensureHost() {
        if (host && document.body.contains(host)) {
            return host;
        }
        host = document.getElementById('ui-toast-host');
        if (!host) {
            host = document.createElement('div');
            host.id = 'ui-toast-host';
            host.className = 'ui-toast-host';
            host.setAttribute('aria-live', 'polite');
            document.body.appendChild(host);
        }
        return host;
    }

    function iconSvg(type) {
        var paths = {
            success: '<circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>',
            error: '<circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/>',
            warning: '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
            info: '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>'
        };
        var inner = paths[type] || paths.info;
        return '<svg class="ui-toast-icon-svg" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + inner + '</svg>';
    }

    function normalizeType(type) {
        type = String(type || 'info');
        if (type === 'danger') {
            return 'error';
        }
        if (['success', 'error', 'warning', 'info'].indexOf(type) === -1) {
            return 'info';
        }
        return type;
    }

    function markSeen(id) {
        if (!id) {
            return false;
        }
        try {
            var key = 'gmingo_toast:' + id;
            if (global.sessionStorage.getItem(key) === '1' || shownIds[id]) {
                return true;
            }
            global.sessionStorage.setItem(key, '1');
        } catch (e) {
            if (shownIds[id]) {
                return true;
            }
        }
        shownIds[id] = true;
        return false;
    }

    function trimStack() {
        var root = ensureHost();
        while (root.children.length > MAX_VISIBLE) {
            root.removeChild(root.lastElementChild);
        }
    }

    function dismiss(el) {
        if (!el || el.getAttribute('data-leaving') === '1') {
            return;
        }
        el.setAttribute('data-leaving', '1');
        el.classList.add('is-leaving');
        global.setTimeout(function () {
            if (el.parentNode) {
                el.parentNode.removeChild(el);
            }
        }, 200);
    }

    function show(type, title, detail, opts) {
        opts = opts || {};
        type = normalizeType(type);
        title = String(title || '').trim();
        if (title === '') {
            return null;
        }
        detail = detail == null ? '' : String(detail).trim();
        var id = opts.id ? String(opts.id) : '';
        if (id && markSeen(id)) {
            return null;
        }

        var root = ensureHost();
        var el = document.createElement('div');
        el.className = 'ui-toast ui-toast-' + type;
        el.setAttribute('role', type === 'error' ? 'alert' : 'status');
        if (type !== 'error') {
            el.setAttribute('aria-live', 'polite');
        }

        var icon = document.createElement('span');
        icon.className = 'ui-toast-icon';
        icon.innerHTML = iconSvg(type);
        el.appendChild(icon);

        var body = document.createElement('div');
        body.className = 'ui-toast-body';
        var titleEl = document.createElement('p');
        titleEl.className = 'ui-toast-title';
        titleEl.textContent = title;
        body.appendChild(titleEl);
        if (detail !== '') {
            var detailEl = document.createElement('p');
            detailEl.className = 'ui-toast-detail';
            detailEl.textContent = detail;
            body.appendChild(detailEl);
        }
        el.appendChild(body);

        var close = document.createElement('button');
        close.type = 'button';
        close.className = 'ui-toast-close';
        close.setAttribute('aria-label', 'Close');
        close.innerHTML = '&times;';
        close.addEventListener('click', function () {
            dismiss(el);
        });
        el.appendChild(close);

        var ttl = opts.duration != null
            ? Number(opts.duration)
            : (type === 'error' ? 7000 : 4000);
        var timer = null;
        function arm() {
            clear();
            if (!(ttl > 0)) {
                return;
            }
            timer = global.setTimeout(function () {
                dismiss(el);
            }, ttl);
        }
        function clear() {
            if (timer) {
                global.clearTimeout(timer);
                timer = null;
            }
        }
        el.addEventListener('mouseenter', clear);
        el.addEventListener('mouseleave', arm);

        root.insertBefore(el, root.firstChild);
        trimStack();
        global.requestAnimationFrame(function () {
            el.classList.add('is-in');
        });
        arm();
        return el;
    }

    function readBootNodes() {
        var nodes = document.querySelectorAll('[data-toast-boot]');
        var list = [];
        nodes.forEach(function (node) {
            var raw = node.getAttribute('data-toast-boot') || node.textContent || '[]';
            node.remove();
            try {
                var parsed = JSON.parse(raw);
                if (Array.isArray(parsed)) {
                    parsed.forEach(function (item) {
                        if (item && typeof item === 'object') {
                            list.push(item);
                        }
                    });
                }
            } catch (e) {}
        });
        return list;
    }

    function boot() {
        readBootNodes().forEach(function (item) {
            show(item.type || item.tone || 'info', item.title || item.message || '', item.detail || '', {
                id: item.id || '',
                duration: item.duration
            });
        });
    }

    var api = {
        show: show,
        success: function (title, detail, opts) {
            return show('success', title, detail, opts);
        },
        error: function (title, detail, opts) {
            return show('error', title, detail, opts);
        },
        warning: function (title, detail, opts) {
            return show('warning', title, detail, opts);
        },
        info: function (title, detail, opts) {
            return show('info', title, detail, opts);
        },
        boot: boot
    };

    global.toast = api;

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') {
            return;
        }
        var root = document.getElementById('ui-toast-host');
        if (!root || !root.firstElementChild) {
            return;
        }
        dismiss(root.firstElementChild);
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})(window);
