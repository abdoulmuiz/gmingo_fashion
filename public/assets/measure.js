(function () {
    var root = document.querySelector('[data-measure]');
    if (!root) {
        return;
    }
    root.addEventListener('click', function (event) {
        var target = event.target;
        if (!(target instanceof Element)) {
            return;
        }
        if (target.closest('[data-add-other]')) {
            var template = document.getElementById('other-row');
            var list = root.querySelector('[data-other-list]');
            if (template && list && 'content' in template) {
                list.appendChild(template.content.cloneNode(true));
            }
            return;
        }
        var remove = target.closest('[data-remove-other]');
        if (remove) {
            var row = remove.closest('.other-row');
            if (row) {
                row.remove();
            }
            return;
        }
        var clearOthers = target.closest('[data-clear-others]');
        if (clearOthers) {
            if (!window.confirm(clearOthers.getAttribute('data-confirm') || '')) {
                return;
            }
            root.querySelectorAll('.other-row').forEach(function (item) {
                item.remove();
            });
            return;
        }
        var clearAll = target.closest('[data-clear-all]');
        if (!clearAll) {
            return;
        }
        if (!window.confirm(clearAll.getAttribute('data-confirm') || '')) {
            return;
        }
        var form = clearAll.closest('form');
        if (!form) {
            return;
        }
        form.querySelectorAll('input[name^="values"], input[name^="others"]').forEach(function (input) {
            input.value = '';
        });
        form.querySelectorAll('.other-row').forEach(function (item) {
            item.remove();
        });
    });
}());
