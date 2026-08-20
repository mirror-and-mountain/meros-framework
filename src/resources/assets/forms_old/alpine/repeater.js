import { createMerosField } from './field-data.js';

export const merosRepeaterField = (id, placeholder, fieldCount, ajaxUrl, rules = {}) => {
    const fieldContract = createMerosField(id, rules);

    return {
        ...fieldContract,

        placeholder: placeholder || 'Nothing to show.',
        showPlaceholder: true,
        canAddRows: true,
        rowCount: 0,
        fieldCount: fieldCount,
        ajaxUrl: ajaxUrl,

        onRefresh: null,

        rowDialogActive: false,
        rowDialogIndex: null,
        rowDialogEl: null,

        init() {
            fieldContract.init.call(this);
            this.onRefresh = () => {
                if (this.rowDialogActive) {
                    return;
                }

                this.$nextTick(() => {
                    this.__initialise();
                });
            };

            this.__initialise();
            window.addEventListener('mforms:form-canvas-updated', this.onRefresh);
        },

        destroy() {
            if (this.onRefresh) {
                window.removeEventListener('mforms:form-canvas-updated', this.onRefresh);
            }

            fieldContract.destroy.call(this);
            this.element = null;
        },

        addRow(configuring = false, callback = null, removeRowCallback = null) {
            if (!this.element) {
                this.__initialise();
            }

            if (!this.canAddRows && !configuring) {
                return;
            }

            if (this.fieldCount === 0) {
                return;
            }

            const templateRow = this.element.querySelector('tr.meros-repeater-template-row');
            if (!templateRow) return;

            const rowCount = this.__getRows().length;

            const newRow = templateRow.cloneNode(true);
            newRow.removeAttribute('id');
            newRow.removeAttribute('style');

            newRow.classList.remove('meros-repeater-template-row');
            newRow.classList.add('meros-repeater-row');

            newRow.setAttribute('x-sort:item', rowCount);
            newRow.setAttribute('data-repeater-row-index', rowCount);

            const sortHandleCell = newRow.querySelector('.meros-repeater-move-cell');

            if (sortHandleCell && !sortHandleCell.hasAttribute('x-sort:handle')) {
                sortHandleCell.setAttribute('x-sort:handle', '');
            }

            const removeButton = newRow.querySelector('.meros-repeater-button--remove');

            if (removeButton && !removeButton.hasAttribute('@click.stop')) {
                if (typeof removeRowCallback === 'function') {
                    removeButton.__merosRemoveRowCallback = removeRowCallback;
                }

                removeButton.setAttribute('@click.stop', 'removeRow($event, null, $event.currentTarget.__merosRemoveRowCallback || null)');
            }

            this.previousValue = this.value;

            this.__enableTemplateRowFields(newRow);
            this.element.querySelector('tbody').insertBefore(newRow, templateRow);

            this.__reindexRowFields();
            this.__initAlpineTree(newRow);

            this.__setRowCount();
            this.__setCanAddRows();
            this.__togglePlaceholder();

            this.value = this.getValue();
            this.applyHints();

            this.dispatchUpdate({
                action: 'add',
                rowIndex: this.__getRows().length - 1,
            });

            if (typeof callback === 'function') {
                const rowData = this.getRowValue(rowCount) || {};
                callback({ repeater: this.element, rowElement: newRow, rowIndex: rowCount, rowData });
            }
        },

        removeRow(event = null, rowIndex = null, callback = null) {
            if (event === null && rowIndex === null) return;

            let row = null;
            if (event) {
                row = event.target.closest('tr.meros-repeater-row');
            } 
            
            else if (rowIndex !== null) {
                row = this.__getRowByIndex(rowIndex);
            }

            if (row && row.closest('fieldset.meros-repeater-field') !== this.element) {
                row = null;
            }

            if (!row) return;

            const index = rowIndex !== null 
                ? rowIndex 
                : Number.parseInt(row.dataset.repeaterRowIndex || '-1', 10);

            if (typeof callback === 'function') {
                const rowData = this.getRowValue(index) || {};
                const shouldContinue = callback({ event, repeater: this.element, rowIndex: index, rowElement: row, rowData });

                if (shouldContinue === false) {
                    return;
                }
            }

            if (this.rowDialogEl === row) {
                this.closeRowDialog();
            }

            this.previousValue = this.value;
            row.remove();

            this.__reindexRowFields();
            this.__setRowCount();
            this.__setCanAddRows();
            this.__togglePlaceholder();

            this.value = this.getValue();
            this.applyHints();

            this.dispatchUpdate({
                action: 'remove',
                oldIndex: index,
            });
        },

        openRowDialog(event, callback = null) {
            if (this.rowDialogActive) {
                return;
            }

            const trigger = event?.currentTarget || event?.target;
            const row = trigger ? trigger.closest('tr.meros-repeater-row') : null;

            if (!row || row.classList.contains('meros-repeater-template-row')) {
                return;
            }

            if (row.closest('fieldset.meros-repeater-field') !== this.element) {
                return;
            }

            const rowIndex = Number.parseInt(row.dataset.repeaterRowIndex || '-1', 10);
            const rowData = this.getRowValue(rowIndex) || {};

            if (typeof callback === 'function') {
                const shouldContinue = callback({ event, repeater: this.element, rowIndex, rowElement: row, rowData });

                if (shouldContinue === false) {
                    return;
                }
            }

            if (typeof callback === 'string' && callback === 'ajax') {
                const data = new FormData();
                data.append('action', 'meros_handle_repeater_row_config_call');
                data.append('repeater_id', this.id);
                data.append('row_data', JSON.stringify(rowData));
                data.append('nonce', this.__getRowNonce(row) || '');

                fetch(this.ajaxUrl, {
                    method: 'POST',
                    body: data,
                })
                .then(r => r.json())
                .then(res => {
                    if (!res.success || !res.data || !res.data.html) {
                        console.error('Error in AJAX response:', res);
                        return;
                    }

                    if (!this.$refs.rowDialog) {
                        console.error('Row dialog reference not found');
                        return;
                    }

                    this.rowDialogEl      = row;
                    this.rowDialogIndex   = rowIndex;
                    this.rowDialogActive  = true;
                    document.body.classList.add('meros-repeater-row-dialog-open');

                    this.$nextTick(() => {
                        const dialogContentContainer = document.querySelector('.meros-repeater-row-dialog-content');

                        if (!dialogContentContainer) {
                            console.error('Row dialog content container not found');
                            return;
                        }
                        dialogContentContainer.innerHTML = res.data.html || '';
                        this.__initAlpineTree(dialogContentContainer);
                    });

                    return;
                })
                .catch(() => {
                    console.error('An unknown error occurred while retrieving row dialog content via AJAX.');
                });
            }
        },

        submitRowDialog(event) {
            if (!this.element || !this.rowDialogActive || !this.rowDialogEl || this.rowDialogIndex === null) {
                return;
            }

            const form = event.target;

            if (!form || !form.classList.contains('meros-repeater-row-dialog-form')) {
                return;
            }

            const fields = form.querySelectorAll('.meros-field');

            const normaliseFieldName = (name) => {
                if (!name) return name;

                const repeaterPrefix = 'repeater_form_';
                if (name.startsWith(repeaterPrefix)) {
                    name = name.substring(repeaterPrefix.length);
                }

                if (name.endsWith('[]')) {
                    name = name.substring(0, name.length - 2);
                }

                return name;
            };

            const updateRowDataField = (fieldName, value) => {
                const rowDataField = this.__getRowDataField(this.rowDialogEl);

                if (!rowDataField) return;

                const nextValue = this.__sanitiseRowDataValue(mforms.getFieldValue(rowDataField) || {});
                nextValue[fieldName] = value;

                mforms.setFieldValue(rowDataField, nextValue);
            };

            const updateRowField = (container, fieldName, value) => {
                const field = 
                    container.querySelector(`[data-field-name="${fieldName}"]`) ||
                    container.querySelector('[data-field-type]');

                if (!field) return;

                mforms.setFieldValue(field, value);
            };

            this.previousValue = this.value;

            fields.forEach((field) => {
                const input = field.querySelector('[data-field-type]');
                if (!input) return;

                const fieldType = input.dataset.fieldType;

                if (input.dataset.fieldType === 'repeater') {
                    const nestedRepeater = mforms.getFieldComponent(input);

                    if (!nestedRepeater) return;

                    const nestedRepeaterValue = nestedRepeater.getValue();
                    const nestedRepeaterFieldName = normaliseFieldName(input.getAttribute('data-field-name'));

                    updateRowDataField(nestedRepeaterFieldName, nestedRepeaterValue);
                    return;
                }

                const name  = normaliseFieldName(input.getAttribute('name') || input.getAttribute('data-field-name') || '');

                // Ignore nested repeater meta fields and indexed child fields.
                if (name.includes('[__row_nonce]') || name.includes('[__row_data]') || /\[\d+\]\[/.test(name)) {
                    return;
                }

                const value = mforms.getFieldValue(input);

                const tableFieldContainer = this.rowDialogEl.querySelector(`[data-field-name="${name}"]`);

                if (tableFieldContainer) {
                    updateRowField(tableFieldContainer, name, value);
                }
                
                else {
                    updateRowDataField(name, value);
                }
            });

            // Defensive cleanup to remove stale flattened nested keys from row_data.
            const rowDataField = this.__getRowDataField(this.rowDialogEl);
            if (rowDataField) {
                const sanitisedValue = this.__sanitiseRowDataValue(mforms.getFieldValue(rowDataField) || {});
                mforms.setFieldValue(rowDataField, sanitisedValue);
            }

            this.value = this.getValue();;

            this.dispatchUpdate({
                action: 'updateRow',
                rowIndex: this.rowDialogIndex,
            });

            this.closeRowDialog();
        },

        closeRowDialog() {
            if (!this.rowDialogActive) {
                return;
            }

            this.rowDialogActive = false;
            this.rowDialogEl = null;
            this.rowDialogIndex = null;
            document.body.classList.remove('meros-repeater-row-dialog-open');
        },

        handleReorder(item, position) {
            this.previousValue = this.value;
            this.__reindexRowFields();

            this.value = this.getValue();
            this.dispatchUpdate({
                action: 'reorder',
                oldIndex: item,
                newIndex: position,
            });
        },

        getValue() {
            if (!this.element) return [];
            
            const rows = this.__getRows();
            const data = [];

            rows.forEach((row) => {
                const rowIndex = Number.parseInt(row.dataset.repeaterRowIndex || '-1', 10);
                const rowData = this.getRowValue(rowIndex);

                if (rowData) {
                    data.push(rowData);
                }
            });

            return data;
        },

        getRowValue(rowIndex) {
            if (!this.element) return null;
            const row = this.__getRowByIndex(rowIndex);

            if (!row) return null;

            const rowData = {};
            const cells = row.querySelectorAll('.meros-repeater-data-cell');

            cells.forEach((cell) => {
                const fieldName = cell.dataset.fieldName;
                const isNonceField = fieldName === '__row_nonce';
                const isRowDataField = fieldName === '__row_data';

                if (!fieldName || isNonceField) {
                    return;
                }

                if (isRowDataField) {
                    const rowDataField = cell.querySelector('input[type="hidden"]');

                    if (rowDataField) {
                        try {
                            const parsedValue = JSON.parse(rowDataField.value);
                            Object.assign(rowData, parsedValue);
                        } catch (e) {
                            return;
                        }
                    }

                    return;
                }

                rowData[fieldName] = this.getCellValue(row, fieldName);
            });

            return rowData;
        },

        getCellValue(rowIndexOrRow, fieldName) {
            if (!this.element) return null;
            const row = rowIndexOrRow instanceof HTMLElement ? rowIndexOrRow : this.__getRowByIndex(rowIndexOrRow);

            if (!row) return null;
            const cell = row.querySelector(`.meros-repeater-data-cell[data-field-name="${fieldName}"]`);

            if (!cell) return null;
            const input = cell.querySelector('[data-field-type]');

            if (!input) return null;

            return mforms.getFieldValue(input);
        },

        __initialise() {
            this.element = this.$el && this.$el.classList.contains('meros-repeater-field') 
                ? this.$el 
                : this.$el.closest('fieldset.meros-repeater-field') || null;

            if (this.element?.id) {
                this.id = this.element.id;
            }

            if (!this.element || !this.id) return;

            // Re-sync rules from the live x-data attribute. 
            try {
                const xData = this.element.getAttribute('x-data') ?? '';
                const match = xData.match(/merosRepeaterField\([^,]+,[^,]+,\s*\d+,\s*({.*})\)/);
                if (match) {
                    this.rules = match[1];
                }
            } catch (e) {
                // Fall back to the existing this.rules value.
            }

            this.value = this.getValue();
            this.previousValue = this.value;

            this.__clearRowsWhenNoFields();
            this.__setRowCount();
            this.__setCanAddRows();
            this.__togglePlaceholder();
        },

        __initAlpineTree(element) {
            if (window.Alpine && typeof window.Alpine.initTree === 'function') {
                window.Alpine.initTree(element);
            }
        },

        __getRowNonce(row) {
            const nonceField = row.querySelector('.meros-repeater-data-cell[data-field-name="__row_nonce"] input[type="hidden"]');
            return nonceField ? nonceField.dataset.defaultValue : null;
        },

        __getRowDataField(row) {
            return row.querySelector('.meros-repeater-data-cell[data-field-name="__row_data"] input[type="hidden"]') || null;
        },

        __sanitiseRowDataValue(value) {
            if (!value || Object.prototype.toString.call(value) !== '[object Object]') {
                return {};
            }

            const cleaned = {};

            Object.entries(value).forEach(([key, entryValue]) => {
                const isMetaKey =
                    key === '__row_nonce' ||
                    key === '__row_data' ||
                    key.includes('[__row_nonce]') ||
                    key.includes('[__row_data]');
                const isIndexedNestedKey = /\[\d+\]\[/.test(key);

                if (isMetaKey || isIndexedNestedKey) {
                    return;
                }

                cleaned[key] = entryValue;
            });

            return cleaned;
        },

        __getRows(repeaterElement = null) {
            const element = repeaterElement || this.element;
            if (!element) return [];

            const rows = element.querySelectorAll('tr.meros-repeater-row:not(.meros-repeater-template-row)');

            return Array.from(rows).filter((row) => {
                return row.closest('fieldset.meros-repeater-field') === element;
            });
        },

        __getRowByIndex(rowIndex, repeaterElement = null) {
            const parsedIndex = Number.parseInt(rowIndex, 10);
            if (Number.isNaN(parsedIndex)) return null;

            const rows = this.__getRows(repeaterElement);

            return rows.find((row) => {
                const candidateIndex = Number.parseInt(row.dataset.repeaterRowIndex || '-1', 10);
                return candidateIndex === parsedIndex;
            }) || null;
        },

        __setCanAddRows() {
            if (!this.element) {
                this.canAddRows = false;
                return;
            }

            if (this.fieldCount === null || this.fieldCount === undefined || this.fieldCount <= 0) {
                this.canAddRows = false;
                return;
            }

            const rules = this.__getParsedRules();
            const maxRows = rules['max-items']?.value ? parseInt(rules['max-items'].value, 10) : null;
            if (!maxRows) {
                this.canAddRows = true;
                return;
            }

            const currentCount = this.rowCount || 0;

            this.$nextTick(() => {

                if (currentCount >= maxRows) {
                    this.canAddRows = false;
                } else {
                    this.canAddRows = true;
                }
            });
        },

        __setRowCount() {
            if (!this.element) {
                this.rowCount = 0;
                return;
            }

            this.rowCount = this.__getRows().length;
        },

        __clearRowsWhenNoFields() {
            if (!this.element || this.fieldCount > 0) {
                return;
            }

            const rows = this.__getRows();

            rows.forEach((row) => {
                row.remove();
            });
        },

        __togglePlaceholder() {
            let rowCount = 0;
            
            if (this.element) {
                rowCount = this.__getRows().length;
            } else {
                rowCount = this.rowCount || 0;
            }

            if (rowCount > 0) {
                this.showPlaceholder = false;
            } else {
                this.showPlaceholder = true;
            }
        },

        __enableTemplateRowFields(row) {
            const fields = row.querySelectorAll('[data-field-type]');

            fields.forEach((field) => {
                field.removeAttribute('disabled');
                field.setAttribute('aria-disabled', 'false');
                field.removeAttribute('data-disabled-for-template-only');
            });
        },

        __reindexRowFields() {
            if (!this.element) return;
            const rows = this.__getRows();

            const escapeRegExp = (value) => String(value).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            const buildReindexedName = (currentName, nextIndex, nextFieldName) => {
                const escapedFieldName = escapeRegExp(nextFieldName);
                const trailingRowPattern = new RegExp(`\\[[^\\[\\]]+\\]\\[${escapedFieldName}\\](\\[\\])?$`);

                if (trailingRowPattern.test(currentName)) {
                    return currentName.replace(trailingRowPattern, `[${nextIndex}][${nextFieldName}]$1`);
                }

                const firstBracket = currentName.indexOf('[');

                if (firstBracket === -1) {
                    return `${currentName}[${nextIndex}][${nextFieldName}]`;
                }

                const rootName = currentName.substring(0, firstBracket);
                return `${rootName}[${nextIndex}][${nextFieldName}]`;
            };

            rows.forEach((row, rowIndex) => {
                row.setAttribute('data-repeater-row-index', rowIndex);

                const cells = row.querySelectorAll('.meros-repeater-data-cell');

                cells.forEach((cell) => {
                    const fieldName = cell.dataset.fieldName;
                    const inputs = cell.querySelectorAll(`[data-base-field-name="${fieldName}"]`);

                    inputs.forEach((input) => {
                        if (typeof input.name === 'string' && input.name !== '') {
                            input.name = buildReindexedName(input.name, rowIndex, fieldName);
                        }

                        if (input.id) {
                            input.id = input.id.replace(/-\d+-/, `-${rowIndex}-`);
                            input.id = input.id.replace('-template', '');
                        }

                        input.setAttribute('data-row-index', rowIndex);
                    });
                });
            });
        },
    };
};