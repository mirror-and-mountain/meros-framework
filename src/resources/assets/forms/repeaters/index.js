import './style.scss';

function isLivewireSyncEnabledForRepeater(element) {
    const table = element?.closest?.('table.meros-repeater-table--interactive');

    if (!table) {
        return false;
    }

    return table.dataset.livewireSyncEnabled === 'true';
}

function rewriteRepeaterIndexedName(name, newIndex) {
    if (!name) {
        return name;
    }

    return name.replace(/^(.*\[)(\d+)(\]\[[^\]]+\](?:\[\])?)$/, `$1${newIndex}$3`);
}

function rewriteRepeaterIndexedId(id, newIndex) {
    if (!id) {
        return id;
    }

    return id.replace(/^(.*_)(\d+)(_.*)$/, `$1${newIndex}$3`);
}

function reindexRepeaterRowFields(tableElement) {
    const rows = Array.from(tableElement.querySelectorAll('tbody tr.meros-repeater-row'));

    rows.forEach((rowElement, newIndex) => {
        rowElement.dataset.repeaterRowIndex = String(newIndex);

        const fieldElements = rowElement.querySelectorAll('input, select, textarea, label');

        fieldElements.forEach((fieldElement) => {
            if ('name' in fieldElement && fieldElement.name) {
                fieldElement.name = rewriteRepeaterIndexedName(fieldElement.name, newIndex);
            }

            if ('id' in fieldElement && fieldElement.id) {
                fieldElement.id = rewriteRepeaterIndexedId(fieldElement.id, newIndex);
            }

            if (fieldElement instanceof HTMLLabelElement && fieldElement.htmlFor) {
                fieldElement.htmlFor = rewriteRepeaterIndexedId(fieldElement.htmlFor, newIndex);
            }
        });
    });
}

function moveFieldRepeaterRowsInDom(gapElement, sourceIndex, targetIndex) {
    const tableElement = gapElement?.closest?.('table.meros-repeater-table--interactive');
    const tbodyElement = tableElement?.querySelector?.('tbody.meros-repeater-body');

    if (!tableElement || !tbodyElement) {
        return;
    }

    const rowElements = Array.from(tbodyElement.querySelectorAll('tr.meros-repeater-row'));

    if (!rowElements[sourceIndex]) {
        return;
    }

    fixRadioValuesinRepeaterRows(rowElements);

    const rowBlocks = rowElements.map((rowElement) => ({
        row: rowElement,
        gap: rowElement.nextElementSibling,
    }));

    const [movingBlock] = rowBlocks.splice(sourceIndex, 1);

    let insertAt = Math.max(0, Math.min(targetIndex, rowBlocks.length + 1));

    if (targetIndex > sourceIndex) {
        insertAt -= 1;
    }

    insertAt = Math.max(0, Math.min(insertAt, rowBlocks.length));
    rowBlocks.splice(insertAt, 0, movingBlock);

    const startGapElement = tbodyElement.firstElementChild
        && !tbodyElement.firstElementChild.classList.contains('meros-repeater-row')
        ? tbodyElement.firstElementChild
        : null;

    if (startGapElement) {
        tbodyElement.appendChild(startGapElement);
    }

    rowBlocks.forEach(({ row, gap }) => {
        tbodyElement.appendChild(row);

        if (gap) {
            tbodyElement.appendChild(gap);
        }
    });

    reindexRepeaterRowFields(tableElement);
}

function extractRepeaterCellValue(cellElement, event) {
    const target = event?.target;

    if (!target) {
        return null;
    }

    if (target.type === 'checkbox') {
        const checkboxInputs = cellElement
            ? Array.from(cellElement.querySelectorAll('input[type="checkbox"]'))
            : [];

        if (checkboxInputs.length > 1) {
            return checkboxInputs
                .filter((checkboxInput) => checkboxInput.checked)
                .map((checkboxInput) => checkboxInput.value);
        }

        return target.checked;
    }

    if (target.type === 'radio') {
        const checkedRadio = cellElement
            ? cellElement.querySelector('input[type="radio"]:checked')
            : null;

        return checkedRadio ? checkedRadio.value : null;
    }

    if (target.multiple) {
        return Array.from(target.options)
            .filter((option) => option.selected)
            .map((option) => option.value);
    }

    return target.value;
}

function parseRepeaterIndex(value) {
    if (value === null || value === undefined || value === '' || value === 'null') {
        return null;
    }

    const parsedValue = Number(value);

    return Number.isNaN(parsedValue) ? null : parsedValue;
}

let repeaterChangeDelegationBound = false;

function handleRepeaterCellChange(event) {
    const interactiveTable = event.target?.closest?.('table.meros-repeater-table--interactive');

    if (!interactiveTable) {
        return;
    }

    const cellElement = event.target?.closest?.('td.meros-repeater-data-cell[data-field-name]');

    if (!cellElement) {
        return;
    }

    if (!isLivewireSyncEnabledForRepeater(cellElement)) {
        return;
    }

    const livewireRoot = cellElement.closest?.('[wire\\:id]');
    const livewireComponentId = livewireRoot?.getAttribute?.('wire:id');
    const livewireComponent = livewireComponentId && window.Livewire?.find ? window.Livewire.find(livewireComponentId) : null;

    if (!livewireComponent) {
        return;
    }

    const repeaterRow = cellElement.closest?.('tr[data-repeater-row-index]');

    livewireComponent.call(
        'updateFieldRepeaterRowValue',
        parseRepeaterIndex(cellElement.dataset.locationRowIndex),
        parseRepeaterIndex(cellElement.dataset.locationFieldIndex),
        parseRepeaterIndex(cellElement.dataset.locationGroupRowIndex),
        parseRepeaterIndex(repeaterRow?.dataset?.repeaterRowIndex),
        cellElement.dataset.fieldName,
        extractRepeaterCellValue(cellElement, event),
        repeaterRow?.dataset?.repeaterRowKey ?? null,
    );
}

export function bindRepeaterCellChangeDelegation(rootElement = document) {
    if (repeaterChangeDelegationBound || !rootElement?.addEventListener) {
        return;
    }

    repeaterChangeDelegationBound = true;
    rootElement.addEventListener('change', handleRepeaterCellChange, true);
}

/**
 * Add repeater-specific drag/drop helpers and handlers to the shared formDrag store.
 *
 * The base store is expected to provide:
 * - canDropField(), hasDataTransferType(), getDataTransferNumber(), getDataTransferNumberFromTypes()
 * - showRowGap(), hideRowGap()
 */
export function extendFormDragWithRepeaterHandlers(formDragStore) {
    return {
        ...formDragStore,

        // -----------------------------------------------------------------
        // Repeater builder visual helpers
        // Used in: src/resources/views/toolbox/form-builder/canvas/repeater-builder.blade.php
        // -----------------------------------------------------------------
        showRepeaterColumnDropHighlight(zoneElement) {
            if (!zoneElement || !this.canDropField()) {
                return;
            }

            zoneElement.classList.add('border-blue-400', 'bg-blue-50', 'text-blue-600');
        },

        hideRepeaterColumnDropHighlight(zoneElement) {
            if (!zoneElement) {
                return;
            }

            zoneElement.classList.remove('border-blue-400', 'bg-blue-50', 'text-blue-600');
        },

        // -----------------------------------------------------------------
        // Repeater builder handlers
        // Used in: src/resources/views/toolbox/form-builder/canvas/repeater-builder.blade.php
        // -----------------------------------------------------------------
        canDropOnRepeaterColumnGap(event) {
            return this.canDropField() || this.hasDataTransferType(event, 'application/x-meros-repeater-column');
        },

        handleRepeaterColumnGapDragOver(event, gapElement) {
            if (!this.canDropOnRepeaterColumnGap(event)) {
                return;
            }

            this.showRowGap(gapElement);
        },

        handleRepeaterColumnDrop(zoneElement, wire, targetIndex) {
            this.hideRepeaterColumnDropHighlight(zoneElement);

            if (!this.canDropField()) {
                return;
            }

            wire.addRepeaterColumnAt(targetIndex, this.itemHandle ?? '');
        },

        handleRepeaterColumnGapDrop(event, gapElement, wire, targetIndex) {
            this.hideRowGap(gapElement);

            if (this.canDropField()) {
                wire.addRepeaterColumnAt(targetIndex, this.itemHandle ?? '');
                return;
            }

            const sourceIndex = this.getDataTransferNumber(event, 'application/x-meros-repeater-column');

            if (sourceIndex !== null) {
                wire.moveRepeaterColumn(sourceIndex, targetIndex);
            }
        },

        canDropOnRepeaterRowGap(event) {
            return this.hasDataTransferType(event, 'application/x-meros-repeater-row');
        },

        handleRepeaterRowGapDragOver(event, gapElement) {
            if (!this.canDropOnRepeaterRowGap(event)) {
                return;
            }

            this.showRowGap(gapElement);
        },

        handleRepeaterRowGapDrop(event, gapElement, wire, targetIndex) {
            this.hideRowGap(gapElement);

            const sourceIndex = this.getDataTransferNumber(event, 'application/x-meros-repeater-row');

            if (sourceIndex !== null) {
                wire.moveRepeaterDefaultRow(sourceIndex, targetIndex);
            }
        },

        // -----------------------------------------------------------------
        // Field repeater handlers
        // Used in: src/resources/views/fields/repeater.blade.php
        // -----------------------------------------------------------------
        handleFieldRepeaterRowGapDragOver(gapElement) {
            this.showRowGap(gapElement);
        },

        handleFieldRepeaterRowGapDrop(event, gapElement, wire, rowIndex, fieldIndex, groupRowIndex, targetIndex) {
            this.hideRowGap(gapElement);

            const sourceIndex = this.getDataTransferNumberFromTypes(event, [
                'application/x-meros-field-repeater-row',
                'text/plain',
            ]);

            if (sourceIndex !== null) {
                if (!isLivewireSyncEnabledForRepeater(gapElement)) {
                    moveFieldRepeaterRowsInDom(gapElement, sourceIndex, targetIndex);
                    return;
                }

                const tableElement = gapElement?.closest?.('table.meros-repeater-table--interactive');
                const sourceRowElement = tableElement
                    ? tableElement.querySelector(`tr.meros-repeater-row[data-repeater-row-index="${sourceIndex}"]`)
                    : null;
                const sourceRowKey = sourceRowElement?.dataset?.repeaterRowKey ?? null;

                wire.moveFieldRepeaterRow(rowIndex, fieldIndex, groupRowIndex, sourceIndex, targetIndex, sourceRowKey);
            }
        },
    };
}

/**
 * Minimal fallback store for contexts where formDrag was never registered
 * (for example, standalone SiteForm on the frontend without Vite builder boot).
 */
function createFallbackFormDragStore() {
    return {
        itemKind: null,
        itemHandle: null,

        canDropField() {
            return this.itemKind === 'field';
        },

        hasDataTransferType(event, type) {
            return event?.dataTransfer?.types?.includes(type) ?? false;
        },

        getDataTransferNumber(event, type) {
            const value = Number(event?.dataTransfer?.getData(type));

            if (Number.isNaN(value)) {
                return null;
            }

            return value;
        },

        getDataTransferNumberFromTypes(event, types) {
            for (const type of types) {
                const value = this.getDataTransferNumber(event, type);

                if (value !== null) {
                    return value;
                }
            }

            return null;
        },

        showRowGap(gapElement) {
            if (!gapElement) {
                return;
            }

            gapElement.style.height = '2rem';
            gapElement.classList.add('bg-blue-200');
        },

        hideRowGap(gapElement) {
            if (!gapElement) {
                return;
            }

            gapElement.style.height = '';
            gapElement.classList.remove('bg-blue-200');
        },
    };
}

export function fixRadioValuesinRepeaterRows(rowElements = null) {
    if (!rowElements) {
        rowElements = Array.from(document.querySelectorAll('.meros-repeater-row'));
    }

    rowElements.forEach((rowElement) => {
        const radios = Array.from(rowElement.querySelectorAll('input[type="radio"]'));

        radios.forEach((radioInput) => {
            const checked = radioInput.checked || radioInput.defaultChecked;
            if (checked) {
                radioInput.checked = false;
                requestAnimationFrame(() => {
                    radioInput.checked = true;
                });
            }
        });
    });
}

/**
 * Ensure Alpine's formDrag store has repeater handlers in any runtime context.
 * Safe to call repeatedly.
 */
export function ensureRepeaterHandlersOnFormDragStore() {
    const AlpineRuntime = window.Alpine;

    if (!AlpineRuntime || typeof AlpineRuntime.store !== 'function') {
        return;
    }

    let formDragStore = AlpineRuntime.store('formDrag');

    if (!formDragStore) {
        AlpineRuntime.store('formDrag', createFallbackFormDragStore());
        formDragStore = AlpineRuntime.store('formDrag');
    }

    if (!formDragStore || formDragStore.__merosRepeaterHandlersApplied) {
        return;
    }

    const extendedStore = extendFormDragWithRepeaterHandlers(formDragStore);
    extendedStore.__merosRepeaterHandlersApplied = true;

    AlpineRuntime.store('formDrag', extendedStore);
}
