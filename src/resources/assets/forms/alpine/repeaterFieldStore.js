import { initTomSelects } from '../tom-select/index.js';

export default function registerRepeaterFieldStore() {
    const store = {
        isEditor: false,
        tomSelectOptionCache: {},
        pendingTomSelectValuesByElement: null,

        setIsEditor(isEditor) {
            this.isEditor = Boolean(isEditor);
        },

        // Helper to parse indices
        parseIndex(value) {
            if (value === null || value === undefined || value === '' || value === 'null') {
                return null;
            }

            const parsedValue = Number(value);

            return Number.isInteger(parsedValue) ? parsedValue : null;
        },

        // Retrieves the root repeater element based on the provided anchor element (e.g. a button within the row)
        getRepeaterRoot(anchorElement) {
            if (anchorElement?.classList?.contains('meros-repeater')) {
                return anchorElement;
            }

            return anchorElement?.closest?.('.meros-repeater') ?? null;
        },

        // Retrieves the repeater body element which contains the rows, based on the provided anchor element
        getRepeaterBody(anchorElement) {
            return this.getRepeaterRoot(anchorElement)?.querySelector('.meros-repeater-body') ?? null;
        },

        // Retrieves a reusable gap-row template from the current repeater DOM.
        getGapRowTemplate(anchorElement) {
            return this.getRepeaterBody(anchorElement)?.querySelector('tr.meros-repeater-gap-row')?.cloneNode(true) ?? null;
        },

        // Retrieves all the row elements within the repeater body based on the provided anchor element
        getRowElements(anchorElement) {
            return Array.from(this.getRepeaterBody(anchorElement)?.querySelectorAll(':scope > tr.meros-repeater-row:not(.meros-repeater-template-row)') ?? []);
        },

        // Retrieves the hidden template row used when the repeater has no rows yet.
        getTemplateRowElement(anchorElement) {
            return this.getRepeaterBody(anchorElement)?.querySelector('tr.meros-repeater-template-row') ?? null;
        },

        // Extracts a normalised value for a repeater field cell.
        getFieldCellValue(fieldCellElement) {
            if (!fieldCellElement) {
                return null;
            }

            const advancedSelect = fieldCellElement.querySelector('select.meros-select-field[data-advanced="true"]');

            if (advancedSelect) {
                if (advancedSelect?.tomselect) {
                    return advancedSelect.tomselect.getValue();
                }

                if (advancedSelect.multiple) {
                    return Array.from(advancedSelect.selectedOptions).map(option => option.value);
                }

                return advancedSelect.value;
            }

            const controls = Array.from(fieldCellElement.querySelectorAll('input, select, textarea')).filter(control => {
                return !control.closest('.meros-select-field__placeholder') && control.type !== 'hidden';
            });

            if (controls.length === 0) {
                return null;
            }

            const radioControls = controls.filter(control => control.type === 'radio');

            if (radioControls.length > 0) {
                return radioControls.find(control => control.checked)?.value ?? null;
            }

            const checkboxControls = controls.filter(control => control.type === 'checkbox');

            if (checkboxControls.length > 0) {
                const resolveCheckboxValue = checkboxControl => {
                    return checkboxControl.getAttribute('data-option-value') ?? checkboxControl.value;
                };

                const isCheckboxGroupField = checkboxControls.some(control => {
                    const controlName = control.getAttribute('name') ?? '';
                    return controlName.endsWith('[]');
                });

                if (isCheckboxGroupField) {
                    return checkboxControls
                        .filter(control => control.checked)
                        .map(control => resolveCheckboxValue(control));
                }

                if (checkboxControls.length === 1) {
                    const checkbox = checkboxControls[0];
                    return checkbox.checked ? resolveCheckboxValue(checkbox) : null;
                }

                return checkboxControls
                    .filter(control => control.checked)
                    .map(control => resolveCheckboxValue(control));
            }

            const selectControl = controls.find(control => control.tagName === 'SELECT');

            if (selectControl) {
                if (selectControl.multiple) {
                    return Array.from(selectControl.selectedOptions).map(option => option.value);
                }

                return selectControl.value;
            }

            return controls[0]?.value ?? null;
        },

        // Returns the current repeater payload as an array of row objects keyed by field name.
        getRepeaterValue(anchorElement) {
            const rowElements = this.getRowElements(anchorElement);
            const templateRow = this.getTemplateRowElement(anchorElement);

            const hasRows = rowElements.length > 0;
            const hasFields = templateRow?.querySelector('.meros-repeater-data-cell') !== null;

            if (!hasFields || !hasRows) {
                alert('Cannot get repeater value: No field cells found in the repeater template row.');
                return false;
            }

            return rowElements.map(rowElement => {
                const rowValue = {};

                rowElement.querySelectorAll('td[data-field-name]').forEach(fieldCellElement => {
                    const fieldName = fieldCellElement.getAttribute('data-field-name');

                    if (!fieldName) {
                        return;
                    }

                    rowValue[fieldName] = this.getFieldCellValue(fieldCellElement);
                });

                return rowValue;
            });
        },

        // Retrieves advanced select elements within a repeater root.
        getTomSelectElements(anchorElement) {
            return Array.from(this.getRepeaterRoot(anchorElement)?.querySelectorAll('select.meros-select-field[data-advanced="true"]') ?? []);
        },

        // Convert TomSelect option objects into a serialisable array for persistence across DOM rebuilds.
        serialiseTomSelectOptions(options = {}) {
            return Object.values(options)
                .map(option => {
                    const nextOption = {};

                    Object.entries(option ?? {}).forEach(([key, value]) => {
                        // Skip TomSelect internals prefixed with "$".
                        if (!String(key).startsWith('$')) {
                            nextOption[key] = value;
                        }
                    });

                    return nextOption;
                })
                .filter(option => option?.value !== undefined && option?.text !== undefined);
        },

        // Normalises row-indexed repeater field names to a stable cache key.
        normaliseTomSelectName(name) {
            return String(name ?? '').replace(/\[\d+\](?=\[[^\[\]]+\](?:\[\])?$)/, '[*]');
        },

        // Builds a stable cache key for an advanced select.
        getTomSelectCacheKey(selectElement) {
            const fieldName = selectElement?.getAttribute?.('name');

            if (fieldName) {
                return `name:${this.normaliseTomSelectName(fieldName)}`;
            }

            const repeaterFieldName = selectElement?.closest?.('[data-field-name]')?.getAttribute?.('data-field-name');

            if (repeaterFieldName) {
                return `field:${repeaterFieldName}`;
            }

            const fieldId = selectElement?.getAttribute?.('id');

            if (fieldId) {
                return `id:${fieldId}`;
            }

            return null;
        },

        // Reads cached options for a select key.
        getCachedTomSelectOptions(selectElement) {
            const cacheKey = this.getTomSelectCacheKey(selectElement);

            if (!cacheKey) {
                return [];
            }

            return Array.isArray(this.tomSelectOptionCache?.[cacheKey]) ? this.tomSelectOptionCache[cacheKey] : [];
        },

        // Captures TomSelect values keyed by row element identity for one reorder operation.
        captureTomSelectValuesByElement(rowElements = []) {
            if (!Array.isArray(rowElements) || rowElements.length === 0) {
                return null;
            }

            const valuesByElement = new WeakMap();

            rowElements.forEach(rowElement => {
                if (!rowElement || rowElement.classList?.contains('meros-repeater-template-row')) {
                    return;
                }

                const rowValues = {};

                rowElement.querySelectorAll('td[data-field-name] select.meros-select-field[data-advanced="true"]').forEach(selectElement => {
                    const fieldName = selectElement.closest('td[data-field-name]')?.getAttribute('data-field-name');

                    if (!fieldName) {
                        return;
                    }

                    if (selectElement?.tomselect) {
                        rowValues[fieldName] = selectElement.tomselect.getValue();
                        return;
                    }

                    if (selectElement.multiple) {
                        rowValues[fieldName] = Array.from(selectElement.selectedOptions).map(option => option.value);
                        return;
                    }

                    rowValues[fieldName] = selectElement.value;
                });

                valuesByElement.set(rowElement, rowValues);
            });

            return valuesByElement;
        },

        // Destroys TomSelect instances within a repeater so DOM cloning/mutation works on native <select> markup.
        destroyTomSelectInstances(anchorElement) {
            this.getTomSelectElements(anchorElement).forEach(selectElement => {
                if (selectElement?.tomselect) {
                    const cacheKey = this.getTomSelectCacheKey(selectElement);
                    const persistedOptions = this.serialiseTomSelectOptions(selectElement.tomselect.options ?? {});

                    if (cacheKey) {
                        if (persistedOptions.length > 0) {
                            this.tomSelectOptionCache[cacheKey] = persistedOptions;
                        } else {
                            delete this.tomSelectOptionCache[cacheKey];
                        }
                    }

                    selectElement.tomselect.destroy();
                }
            });
        },

        // Re-initialises TomSelects after rows are rebuilt.
        reinitTomSelectInstances(anchorElement) {
            initTomSelects();

            const pendingValuesByElement = this.pendingTomSelectValuesByElement instanceof WeakMap
                ? this.pendingTomSelectValuesByElement
                : null;

            this.getTomSelectElements(anchorElement).forEach(selectElement => {
                const persistedOptions = this.getCachedTomSelectOptions(selectElement);

                if (!selectElement?.tomselect) {
                    return;
                }

                if (persistedOptions.length > 0) {
                    selectElement.tomselect.addOptions(persistedOptions);
                    selectElement.tomselect.refreshOptions(false);
                }

                if (!pendingValuesByElement) {
                    return;
                }

                const rowElement = selectElement.closest('tr.meros-repeater-row');

                if (!rowElement || rowElement.classList.contains('meros-repeater-template-row')) {
                    return;
                }

                const fieldName = selectElement.closest('td[data-field-name]')?.getAttribute('data-field-name') ?? null;

                if (!fieldName) {
                    return;
                }

                const nextValue = pendingValuesByElement.get(rowElement)?.[fieldName];

                if (nextValue !== undefined) {
                    selectElement.tomselect.setValue(nextValue, true);
                }
            });

            this.pendingTomSelectValuesByElement = null;
        },

        // Ensures cloned row and gap directives remain interactive after manual DOM replacement.
        bindRepeaterInteractions(anchorElement) {
            const repeaterRoot = this.getRepeaterRoot(anchorElement);

            if (!repeaterRoot) {
                return;
            }

            repeaterRoot.querySelectorAll('tr.meros-repeater-row .meros-repeater-move-button').forEach(moveButton => {
                if (moveButton.dataset.boundRowDrag === 'true') {
                    return;
                }

                moveButton.dataset.boundRowDrag = 'true';
                moveButton.addEventListener('dragstart', event => {
                    const rowIndex = Number(moveButton.closest('tr')?.dataset.repeaterRowIndex ?? -1);

                    this.setRepeaterDragging(moveButton, true);

                    if (!Number.isNaN(rowIndex) && rowIndex >= 0) {
                        this.startRowDrag(event, rowIndex);
                    }
                });

                moveButton.addEventListener('dragend', () => {
                    this.setRepeaterDragging(moveButton, false);
                });
            });

            repeaterRoot.querySelectorAll('tr.meros-repeater-row .meros-repeater-button--danger').forEach(removeButton => {
                if (removeButton.dataset.boundRowRemove === 'true') {
                    return;
                }

                removeButton.dataset.boundRowRemove = 'true';
                removeButton.addEventListener('click', event => {
                    event.preventDefault();
                    event.stopPropagation();
                    this.removeRow(removeButton);
                });
            });

            repeaterRoot.querySelectorAll('tr.meros-repeater-gap-row .meros-repeater-row-gap').forEach(gapElement => {
                if (gapElement.dataset.boundGapDrop === 'true') {
                    return;
                }

                gapElement.dataset.boundGapDrop = 'true';

                gapElement.addEventListener('dragover', event => {
                    event.preventDefault();
                    this.handleRowGapDragOver(gapElement);
                });

                gapElement.addEventListener('dragleave', () => {
                    this.hideRowGap(gapElement);
                });

                gapElement.addEventListener('drop', event => {
                    event.preventDefault();
                    this.handleRowGapDrop(event, gapElement);
                    this.setRepeaterDragging(gapElement, false);
                });
            });
        },

        // Toggles drag-active UI state for all row gaps in the current repeater.
        setRepeaterDragging(anchorElement, isDragging) {
            const repeaterRoot = this.getRepeaterRoot(anchorElement);

            if (!repeaterRoot) {
                return;
            }

            repeaterRoot.querySelectorAll('tr.meros-repeater-gap-row .meros-repeater-row-gap').forEach(gapElement => {
                gapElement.classList.toggle('is-active', Boolean(isDragging));

                if (!isDragging) {
                    this.hideRowGap(gapElement);
                }
            });
        },

        // Clears internal "already bound" flags so cloned nodes can be rebound safely.
        resetBindingMarkers(containerElement) {
            if (!containerElement) {
                return;
            }

            if (containerElement.dataset) {
                delete containerElement.dataset.boundRowDrag;
                delete containerElement.dataset.boundRowRemove;
                delete containerElement.dataset.boundGapDrop;
            }

            containerElement.querySelectorAll('[data-bound-row-drag], [data-bound-row-remove], [data-bound-gap-drop]').forEach(element => {
                delete element.dataset.boundRowDrag;
                delete element.dataset.boundRowRemove;
                delete element.dataset.boundGapDrop;
            });
        },

        // Retrieves the gap element immediately following a row element, if it exists
        getGapAfterRow(rowElement) {
            const nextSibling = rowElement?.nextElementSibling ?? null;

            return nextSibling && !nextSibling.classList?.contains('meros-repeater-row') ? nextSibling : null;
        },

        // Determines the target row index for a given gap element, which is used during drag-and-drop operations
        getGapTargetIndex(gapElement) {
            const gapRow = gapElement?.closest?.('tr');

            if (!gapRow) {
                return null;
            }

            let targetIndex = 0;
            let sibling = gapRow.previousElementSibling;

            while (sibling) {
                if (sibling.classList?.contains('meros-repeater-row')) {
                    targetIndex += 1;
                }

                sibling = sibling.previousElementSibling;
            }

            return targetIndex;
        },

        // Clears the input values within a repeater row element, resetting them to their default empty state
        clearRowInputs(rowElement) {
            if (!rowElement) {
                return;
            }

            rowElement.querySelectorAll('input, select, textarea').forEach(input => {
                if (input.type === 'checkbox' || input.type === 'radio') {
                    input.checked = false;
                    return;
                }

                if (input.tagName === 'SELECT' && input.multiple) {
                    Array.from(input.options).forEach(option => {
                        option.selected = false;
                    });
                    return;
                }

                input.value = '';
            });
        },

        // Parse a repeater field default value from its attribute payload.
        parseDefaultValue(defaultValue) {
            if (defaultValue === null || defaultValue === undefined || defaultValue === '') {
                return null;
            }

            if (Array.isArray(defaultValue)) {
                return defaultValue;
            }

            if (typeof defaultValue !== 'string') {
                return defaultValue;
            }

            const trimmedValue = defaultValue.trim();

            if (trimmedValue === '') {
                return null;
            }

            if ((trimmedValue.startsWith('[') && trimmedValue.endsWith(']')) || (trimmedValue.startsWith('{') && trimmedValue.endsWith('}'))) {
                try {
                    return JSON.parse(trimmedValue);
                } catch (error) {
                    return defaultValue;
                }
            }

            return defaultValue;
        },

        // Applies a parsed default value to a form control.
        applyDefaultValueToControl(control, defaultValue) {
            if (!control) {
                return;
            }

            const parsedValue = this.parseDefaultValue(defaultValue);

            if (parsedValue === null || parsedValue === undefined) {
                return;
            }

            if (control.tagName === 'SELECT') {
                const values = Array.isArray(parsedValue) ? parsedValue.map(item => String(item)) : [String(parsedValue)];

                Array.from(control.options).forEach(option => {
                    option.selected = values.includes(option.value);
                });

                if (!control.multiple && values.length > 0) {
                    control.value = values[0];
                }

                return;
            }

            if (control.type === 'checkbox') {
                const optionValue = control.getAttribute('data-option-value') ?? control.value;

                if (Array.isArray(parsedValue)) {
                    control.checked = parsedValue.map(item => String(item)).includes(String(optionValue));
                } else {
                    const normalisedValue = String(parsedValue).toLowerCase();

                    if (['0', 'false', 'off', 'no', ''].includes(normalisedValue)) {
                        control.checked = false;
                    } else if (['1', 'true', 'on', 'yes'].includes(normalisedValue)) {
                        control.checked = true;
                    } else {
                        control.checked = String(parsedValue) === String(optionValue);
                    }
                }

                return;
            }

            if (control.type === 'radio') {
                control.checked = String(parsedValue) === control.value;
                return;
            }

            control.value = Array.isArray(parsedValue) ? JSON.stringify(parsedValue) : String(parsedValue);
        },

        // Copies default values from the source row into the cloned row.
        applyRowDefaultValues(sourceRowElement, targetRowElement) {
            if (!sourceRowElement || !targetRowElement) {
                return;
            }

            const sourceControls = Array.from(sourceRowElement.querySelectorAll('[data-default-value]'));
            const targetControls = Array.from(targetRowElement.querySelectorAll('[data-default-value]'));

            sourceControls.forEach((sourceControl, index) => {
                const targetControl = targetControls[index];

                if (!targetControl) {
                    return;
                }

                const defaultValue = targetControl.getAttribute('data-default-value')
                    ?? sourceControl.getAttribute('data-default-value');

                this.applyDefaultValueToControl(targetControl, defaultValue);
            });
        },

        // Rewrites repeater field names, ids, and label targets so they match the current row index.
        rewriteRowFieldAttributes(rowElement, rowIndex) {
            if (!rowElement) {
                return;
            }

            const rowIndexValue = String(rowIndex);
            const previousRowIndex = String(rowElement.dataset.repeaterRowIndex ?? rowIndexValue);
            const escapedPreviousRowIndex = previousRowIndex.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

            rowElement.querySelectorAll('[name], [id], [for]').forEach(node => {
                for (const attribute of ['name', 'id', 'for']) {
                    const attributeValue = node.getAttribute(attribute);

                    if (!attributeValue) {
                        continue;
                    }

                    let nextValue = attributeValue;

                    if (attribute === 'name') {
                        // Match the repeater row index segment before the final field key, allowing an optional [] suffix.
                        nextValue = nextValue.replace(
                            new RegExp(`\\[${escapedPreviousRowIndex}\\](?=\\[[^\\[\\]]+\\](?:\\[\\])?$)`),
                            `[${rowIndexValue}]`
                        );
                    } else {
                        nextValue = nextValue.replaceAll(previousRowIndex, rowIndexValue);
                    }

                    node.setAttribute(attribute, nextValue);
                }
            });

            rowElement.dataset.repeaterRowIndex = rowIndexValue;
            rowElement.setAttribute('wire:key', `repeater-row-${rowIndexValue}`);
        },

        // Rebuilds the repeater tbody so rows and gaps stay in a stable alternating structure.
        rebuildRepeaterBody(anchorElement, rowElements = null) {
            const bodyElement = this.getRepeaterBody(anchorElement);
            const rootElement = this.getRepeaterRoot(anchorElement);

            if (!bodyElement || !rootElement) {
                this.pendingTomSelectValuesByElement = null;
                return;
            }

            this.destroyTomSelectInstances(anchorElement);

            const rows = Array.isArray(rowElements) ? rowElements : this.getRowElements(anchorElement);
            const gapTemplate = this.getGapRowTemplate(anchorElement);

            if (!gapTemplate) {
                this.pendingTomSelectValuesByElement = null;
                return;
            }

            const templateRow = this.getTemplateRowElement(anchorElement)?.cloneNode(true) ?? null;

            const fragment = document.createDocumentFragment();

            const firstGap = gapTemplate.cloneNode(true);
            this.resetBindingMarkers(firstGap);
            fragment.appendChild(firstGap);

            if (rows.length === 0) {
                if (templateRow) {
                    this.resetBindingMarkers(templateRow);
                    fragment.appendChild(templateRow);
                }

                const emptyStateRow = document.createElement('tr');
                const emptyStateCell = document.createElement('td');

                emptyStateCell.className = 'meros-repeater-empty-state';
                emptyStateCell.setAttribute('colspan', String(templateRow?.children?.length ?? 1));
                emptyStateCell.textContent = 'No rows yet. Use "Add Row" to create repeater data.';
                emptyStateRow.appendChild(emptyStateCell);
                fragment.appendChild(emptyStateRow);
            } else {
                rows.forEach((rowElement, rowIndex) => {
                    this.rewriteRowFieldAttributes(rowElement, rowIndex);
                    this.resetBindingMarkers(rowElement);
                    fragment.appendChild(rowElement);
                    const gapClone = gapTemplate.cloneNode(true);
                    this.resetBindingMarkers(gapClone);
                    fragment.appendChild(gapClone);
                });

                if (templateRow) {
                    this.resetBindingMarkers(templateRow);
                    fragment.appendChild(templateRow);
                }
            }

            bodyElement.replaceChildren(fragment);

            // Use a stable repeater root reference because the original anchor may be detached after replaceChildren.
            this.bindRepeaterInteractions(rootElement);

            // Rebind advanced selects so hidden/value inputs are regenerated with updated row indices.
            this.reinitTomSelectInstances(rootElement);
        },

        // Refreshes the data attributes for row indices on all row elements, which is important for maintaining correct references after adding, removing, or moving rows
        refreshRowIndices(anchorElement) {
            this.rebuildRepeaterBody(anchorElement);
        },

        // Creates a deep clone of a row element for insertion
        cloneRowForInsertion(rowElement) {
            const clonedRow = rowElement?.cloneNode(true);

            if (!clonedRow) {
                return null;
            }

            this.resetBindingMarkers(clonedRow);
            clonedRow.removeAttribute('id');
            clonedRow.removeAttribute('style');
            clonedRow.classList.remove('meros-repeater-template-row');
            this.clearRowInputs(clonedRow);

            clonedRow.querySelectorAll('input, select, textarea, button').forEach(control => {
                control.removeAttribute('disabled');
            });

            this.applyRowDefaultValues(rowElement, clonedRow);

            return clonedRow;
        },

        // Show the row gap element during drag over
        showRowGap(gapElement) {
            if (!gapElement) {
                return;
            }

            gapElement.style.height = '2rem';
            gapElement.classList.add('bg-blue-200');
        },

        // Hide the row gap element
        hideRowGap(gapElement) {
            if (!gapElement) {
                return;
            }

            gapElement.style.height = '';
            gapElement.classList.remove('bg-blue-200');
        },

        // Sets data transfer information when starting to drag a row
        startRowDrag(event, rowIndex) {
            const safeRowIndex = this.parseIndex(rowIndex);

            if (!event?.dataTransfer || safeRowIndex === null) {
                return;
            }

            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('application/x-meros-field-repeater-row', String(safeRowIndex));
            event.dataTransfer.setData('text/plain', String(safeRowIndex));
        },

        // Retrieves the row index from the data transfer during a drop event
        getRowIndexFromTransfer(event) {
            if (!event?.dataTransfer) {
                return null;
            }

            const transferTypes = [
                'application/x-meros-field-repeater-row',
                'text/plain',
            ];

            for (const type of transferTypes) {
                const value = Number(event.dataTransfer.getData(type));

                if (!Number.isNaN(value)) {
                    return value;
                }
            }

            return null;
        },

        // Generates a unique row key for identifying repeater rows, using crypto.randomUUID if available
        createRowKey() {
            if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
                return `rk-${crypto.randomUUID()}`;
            }

            return `rk-${Date.now()}-${Math.floor(Math.random() * 1_000_000)}`;
        },

        // Creates an empty repeater row object based on the field payload, initialising all columns to null
        createEmptyRepeaterRow(fieldPayload) {
            const row = {
                __rowKey: this.createRowKey(),
            };

            const columns = Array.isArray(fieldPayload.fields) ? fieldPayload.fields : [];

            columns.forEach(column => {
                const columnName = column?.properties?.name;

                if (typeof columnName === 'string' && columnName.trim() !== '') {
                    row[columnName] = null;
                }
            });

            return row;
        },

        // Adds a new row to the repeater field
        addRow(anchorElement) {
            const rowElements = this.getRowElements(anchorElement);
            const templateRow = this.getTemplateRowElement(anchorElement);

            const hasFields = templateRow?.querySelector('.meros-repeater-data-cell') !== null;

            if (!hasFields) {
                alert('Cannot add row: No field cells found in the repeater template row.');
                return;
            }

            // Preserve current row values through the rebuild for existing rows.
            this.pendingTomSelectValuesByElement = this.captureTomSelectValuesByElement(rowElements);
            this.destroyTomSelectInstances(anchorElement);

            if (rowElements.length === 0) {
                this.pendingTomSelectValuesByElement = null;

                if (!templateRow) {
                    return;
                }

                const firstRow = this.cloneRowForInsertion(templateRow);

                if (!firstRow) {
                    return;
                }

                this.rebuildRepeaterBody(anchorElement, [firstRow]);
                return;
            }

            const lastRowElement = rowElements[rowElements.length - 1];
            const insertedRow = this.cloneRowForInsertion(lastRowElement);

            if (!insertedRow) {
                this.pendingTomSelectValuesByElement = null;
                return;
            }

            rowElements.push(insertedRow);
            this.rebuildRepeaterBody(anchorElement, rowElements);
        },

        // Removes a row from the repeater field based on the provided index
        removeRow(anchorElement) {
            const rowElement = anchorElement?.closest?.('tr.meros-repeater-row') ?? null;
            const rowElements = this.getRowElements(anchorElement);

            if (!rowElement) {
                return;
            }

            if (rowElements.length <= 1) {
                this.pendingTomSelectValuesByElement = null;
                this.rebuildRepeaterBody(anchorElement, []);
                return;
            }

            const rowIndex = rowElements.indexOf(rowElement);

            if (rowIndex === -1) {
                this.pendingTomSelectValuesByElement = null;
                return;
            }

            rowElements.splice(rowIndex, 1);

            // Preserve current values for remaining rows only.
            this.pendingTomSelectValuesByElement = this.captureTomSelectValuesByElement(rowElements);
            this.rebuildRepeaterBody(anchorElement, rowElements);
        },

        // Handles the drag over event on a row gap, showing the visual indicator
        handleRowGapDragOver(gapElement) {
            this.showRowGap(gapElement);
        },

        // Handles the drag leave event on a row gap, hiding the visual indicator
        handleRowGapDrop(event, gapElement, rowIndex, fieldIndex, groupRowIndex = null, targetIndex = null) {
            this.hideRowGap(gapElement);

            const sourceIndex = this.getRowIndexFromTransfer(event);
            const safeTargetIndex = this.getGapTargetIndex(gapElement);

            if (sourceIndex === null || safeTargetIndex === null) {
                return;
            }

            this.moveRow(gapElement, sourceIndex, safeTargetIndex);
        },

        // Moves a row within the repeater field from the source index to the target index
        moveRow(anchorElement, sourceIndex = null, targetIndex = null) {
            const repeaterRoot = this.getRepeaterRoot(anchorElement);
            const rowElements = this.getRowElements(anchorElement);
            const safeSourceIndex = this.parseIndex(sourceIndex);
            const safeTargetIndex = this.parseIndex(targetIndex);

            if (safeSourceIndex === null || safeTargetIndex === null) {
                return;
            }

            if (safeSourceIndex < 0 || safeSourceIndex >= rowElements.length) {
                return;
            }

            const [movingRow] = rowElements.splice(safeSourceIndex, 1);

            if (!movingRow) {
                return;
            }

            let insertAt = safeTargetIndex;

            if (insertAt > safeSourceIndex) {
                insertAt -= 1;
            }

            insertAt = Math.max(0, Math.min(insertAt, rowElements.length));
            rowElements.splice(insertAt, 0, movingRow);

            // Preserve advanced-select values for reorder only.
            this.pendingTomSelectValuesByElement = this.captureTomSelectValuesByElement(rowElements);
            this.rebuildRepeaterBody(anchorElement, rowElements);
        },
    };

    Alpine.store('repeaterField', store);
}