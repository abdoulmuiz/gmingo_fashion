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

    function postForm(form) {
        return fetch(form.getAttribute('action') || '', {
            method: 'POST',
            body: new FormData(form),
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin'
        }).then(function (response) {
            return response.json().then(function (body) {
                if (!response.ok || !body || !body.ok) {
                    var failed = new Error('save failed');
                    failed.body = body;
                    throw failed;
                }
                return body;
            });
        });
    }

    function markSaved(button) {
        if (!button) {
            return;
        }
        var original = button.getAttribute('data-label') || button.textContent;
        button.setAttribute('data-label', original);
        button.textContent = editor.getAttribute('data-saved') || original;
        window.setTimeout(function () {
            button.textContent = original;
        }, 1200);
    }

    function showError(form, error) {
        var text = error && error.body && error.body.error ? error.body.error : '';
        if (!text || !form) {
            return;
        }
        var note = form.querySelector('[data-live-note]');
        if (!note) {
            note = document.createElement('span');
            note.className = 'live-note';
            note.setAttribute('data-live-note', '');
            form.appendChild(note);
        }
        note.textContent = text;
    }

    function shiftRow(row, direction) {
        if (!list || !row) {
            return false;
        }
        var sibling = direction < 0 ? row.previousElementSibling : row.nextElementSibling;
        while (sibling && sibling.hidden) {
            sibling = direction < 0 ? sibling.previousElementSibling : sibling.nextElementSibling;
        }
        if (!sibling || !sibling.hasAttribute('data-field-id')) {
            return false;
        }
        if (direction < 0) {
            list.insertBefore(row, sibling);
        } else {
            list.insertBefore(sibling, row);
        }
        return true;
    }

    function applySaved(form, body) {
        if (body.name) {
            var nameInput = form.querySelector('input[name="name"]');
            if (nameInput) {
                nameInput.value = body.name;
            }
            if (form.classList.contains('name-bar')) {
                var heading = editor.querySelector('h1');
                if (heading) {
                    heading.textContent = body.name;
                }
            }
        }
        if (typeof body.unit === 'string') {
            var unitInput = form.querySelector('input[name="unit"]');
            if (unitInput) {
                unitInput.value = body.unit;
            }
        }
    }

    function addRow(body) {
        var template = editor.querySelector('[data-field-template]');
        if (!template || !list || !body.id) {
            return;
        }
        var row = template.content.firstElementChild.cloneNode(true);
        row.setAttribute('data-field-id', body.id);
        row.querySelectorAll('input[name="id"]').forEach(function (input) {
            input.value = body.id;
        });
        var nameInput = row.querySelector('.field-edit input[name="name"]');
        var unitInput = row.querySelector('.field-edit input[name="unit"]');
        if (nameInput) {
            nameInput.value = body.name || '';
        }
        if (unitInput) {
            unitInput.value = body.unit || '';
        }
        list.appendChild(row);
    }

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
        postForm(form).catch(function () {
            window.location.reload();
        });
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
            if (!form) {
                return;
            }
            postForm(form).then(function (body) {
                if (body.archived) {
                    row.hidden = false;
                    row.classList.add('is-off');
                    var activate = row.querySelector('[data-activate-form]');
                    if (activate) {
                        activate.hidden = false;
                    }
                    return;
                }
                row.remove();
            }).catch(function (error) {
                row.hidden = false;
                showError(form, error);
            });
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
    editor.addEventListener('submit', function (event) {
        var form = event.target;
        if (!(form instanceof HTMLFormElement) || form.hasAttribute('data-confirm')) {
            return;
        }
        var actionInput = form.querySelector('input[name="action"]');
        var action = actionInput ? actionInput.value : '';
        if (action === '' || action === 'garment_remove') {
            return;
        }
        event.preventDefault();
        var row = form.closest('[data-field-id]');
        var moved = false;
        if (action === 'field_up') {
            moved = shiftRow(row, -1);
        } else if (action === 'field_down') {
            moved = shiftRow(row, 1);
        }
        var button = form.querySelector('button[type="submit"]');
        postForm(form).then(function (body) {
            if (action === 'field_add') {
                addRow(body);
                var addedName = form.querySelector('input[name="name"]');
                var addedUnit = form.querySelector('input[name="unit"]');
                if (addedName) {
                    addedName.value = '';
                }
                if (addedUnit) {
                    addedUnit.value = '';
                }
            } else if (action === 'field_activate' && row) {
                row.classList.remove('is-off');
                form.hidden = true;
            } else {
                applySaved(form, body);
            }
            markSaved(button);
            var note = form.querySelector('[data-live-note]');
            if (note) {
                note.textContent = '';
            }
        }).catch(function (error) {
            if (moved) {
                shiftRow(row, action === 'field_up' ? 1 : -1);
            }
            showError(form, error);
        });
    });
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
