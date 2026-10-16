(function () {
    var editor = document.querySelector('[data-field-editor]');
    if (!editor) {
        return;
    }
    var list = editor.querySelector('[data-field-list]');
    var bin = editor.querySelector('[data-field-bin]');
    var bar = editor.querySelector('[data-undo]');
    var undoButton = editor.querySelector('[data-undo-button]');
    var drag = null;
    var dragMoved = false;
    var undoTimer = null;
    var undoRow = null;

    function saveOrder() {
        var form = editor.querySelector('[data-field-order]');
        if (!form || !list) {
            return;
        }
        form.querySelectorAll('input[name="ids[]"]').forEach(function (input) {
            input.remove();
        });
        list.querySelectorAll('[data-field-id]').forEach(function (row) {
            if (row.hidden) {
                return;
            }
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'ids[]';
            input.value = row.getAttribute('data-field-id') || '';
            form.appendChild(input);
        });
        form.submit();
    }

    function removeRow(row) {
        if (!row) {
            return;
        }
        row.hidden = true;
        if (!bar) {
            var immediate = row.querySelector('[data-remove-form]');
            if (immediate) {
                immediate.submit();
            }
            return;
        }
        bar.hidden = false;
        undoRow = row;
        window.clearTimeout(undoTimer);
        undoTimer = window.setTimeout(function () {
            bar.hidden = true;
            var form = row.querySelector('[data-remove-form]');
            if (form) {
                form.submit();
            }
        }, 4000);
    }

    if (list) {
        list.addEventListener('pointerdown', function (event) {
            var handle = event.target.closest('[data-drag-handle]');
            if (!handle || event.button !== 0) {
                return;
            }
            var row = handle.closest('[data-field-id]');
            if (!row) {
                return;
            }
            drag = { row: row, pointer: event.pointerId };
            dragMoved = false;
            handle.setPointerCapture(event.pointerId);
            row.classList.add('dragging');
            if (bin) {
                bin.hidden = false;
            }
            event.preventDefault();
        });
        list.addEventListener('pointermove', function (event) {
            if (!drag || event.pointerId !== drag.pointer) {
                return;
            }
            var rows = Array.prototype.slice.call(list.querySelectorAll('[data-field-id]'));
            var placed = false;
            rows.forEach(function (row) {
                if (placed || row === drag.row || row.hidden) {
                    return;
                }
                var box = row.getBoundingClientRect();
                if (event.clientY < box.top + box.height / 2) {
                    if (row.previousElementSibling !== drag.row) {
                        list.insertBefore(drag.row, row);
                        dragMoved = true;
                    }
                    placed = true;
                }
            });
            if (!placed && list.lastElementChild !== drag.row) {
                list.appendChild(drag.row);
                dragMoved = true;
            }
        });
        function finish(event) {
            if (!drag || event.pointerId !== drag.pointer) {
                return;
            }
            var row = drag.row;
            var target = document.elementFromPoint(event.clientX, event.clientY);
            var onBin = bin && target && bin.contains(target);
            row.classList.remove('dragging');
            if (bin) {
                bin.hidden = true;
            }
            drag = null;
            if (onBin) {
                removeRow(row);
                return;
            }
            if (dragMoved) {
                saveOrder();
            }
        }
        list.addEventListener('pointerup', finish);
        list.addEventListener('pointercancel', finish);
        list.addEventListener('click', function (event) {
            var button = event.target.closest('[data-field-remove]');
            if (!button) {
                return;
            }
            event.preventDefault();
            removeRow(button.closest('[data-field-id]'));
        });
    }
    if (undoButton) {
        undoButton.addEventListener('click', function () {
            window.clearTimeout(undoTimer);
            if (undoRow) {
                undoRow.hidden = false;
            }
            if (bar) {
                bar.hidden = true;
            }
        });
    }
}());
