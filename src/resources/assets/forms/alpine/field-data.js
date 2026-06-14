import { 
    getFieldComponent, 
    getFieldValue, 
    setFieldValue,
    validateFieldValue,
    getContext,
    snapshotValue
} from './helpers.js';

import TomSelect from 'tom-select';
import Quill from 'quill';

document.addEventListener('alpine:init', () => {
    const createMerosField = (id, rules = {}) => ({
        id: id,
        rules: rules,
        element: null,
        value: null,
        previousValue: null,

        init() {
            if (this.id) {
                this.element = document.getElementById(this.id);
                this.value = snapshotValue(this.getValue());
            }
        },

        destroy() {},

        getValue() {
            if (!this.element) return null;

            const value = getFieldValue(this.element);
            this.value = snapshotValue(value);

            return this.value;
        },

        setValue(value) {
            if (!this.element) return;

            this.previousValue = snapshotValue(this.getValue());

            setFieldValue(this.element, value);

            this.value = snapshotValue(this.getValue());
            this.__dispatchUpdate();
        },

        getErrorMessage(rule) {
            const rules = this.__getParsedRules();
            return rules[rule] && rules[rule].message ? rules[rule].message : null;
        },

        __dispatchUpdate(context = {}) {
            const oldValue = snapshotValue(this.previousValue);
            const newValue = snapshotValue(this.value);

            this.$dispatch('mforms:field-updated', {
                formId: this.element ? this.element.closest('form')?.id || null : null,
                type: this.element ? this.element.dataset.fieldType || null : null,
                id: this.id,
                name: this.element ? this.element.name : null,
                element: this.element,
                oldValue,
                value: newValue,
                context: getContext(this.element, context),
                field: this
            });
        },

        __getParsedRules() {
            try {
                return JSON.parse(this.rules);
            } catch (e) {
                return this.rules;
            }
        }
    });

    Alpine.data('merosField', (id, rules = {}) => ({
        ...createMerosField(id, rules)
    }));

    Alpine.data('merosFieldWrapper', () => ({
        control: null,
        hasHints: false,
        isValid: true,
        onRefresh: null,
        onFieldUpdated: null,

        init() {
            this.control = this.$el.querySelector('[data-field-type]');
            if (!this.control) return;

            const isDefaultValueControl = this.control.getAttribute('data-default-value-control') === 'true';
            if (isDefaultValueControl) return;

            this.hasHints = this.$el.querySelector('.char-count-hint, .word-count-hint') !== null;

            this.onRefresh = () => {
                this.$nextTick(() => {
                    this.__syncValidity();
                });
            };

            this.onFieldUpdated = (event) => {
                const element = event?.detail?.element;

                if (!element || !this.$el.contains(element)) {
                    return;
                }

                this.__syncValidity();
            };

            window.addEventListener('mforms:field-settings-opened', this.onRefresh);
            window.addEventListener('mforms:field-settings-refreshed', this.onRefresh);
            window.addEventListener('mforms:field-settings-closed', this.onRefresh);
            window.addEventListener('mforms:form-canvas-updated', this.onRefresh);
            window.addEventListener('mforms:field-updated', this.onFieldUpdated);

            this.$nextTick(() => {
                this.__syncValidity();
            });
        },

        destroy() {
            if (this.onRefresh) {
                window.removeEventListener('mforms:field-settings-opened', this.onRefresh);
                window.removeEventListener('mforms:field-settings-refreshed', this.onRefresh);
                window.removeEventListener('mforms:field-settings-closed', this.onRefresh);
                window.removeEventListener('mforms:form-canvas-updated', this.onRefresh);
                this.onRefresh = null;
            }

            if (this.onFieldUpdated) {
                window.removeEventListener('mforms:field-updated', this.onFieldUpdated);
                this.onFieldUpdated = null;
            }
        },

        __syncValidity() {
            if (!this.control) return;

            const isValid = validateFieldValue(this.control);
            this.isValid = typeof isValid === 'boolean' ? isValid : true;
            this.$el.classList.toggle('invalid', !this.isValid);
        },

        getControlCharCount() {
            if (!this.control || !this.hasHints) return 0;

            const value = getFieldValue(this.control);
            return value ? value.length : 0;
        },

        getControlWordCount() {
            if (!this.control || !this.hasHints) return 0;

            const value = getFieldValue(this.control);
            return value ? value.trim().split(/\s+/).length : 0;
        }
    }));

    /**
     * Alpine component for input fields.
     */
    Alpine.data('merosInputField', (id, rules = {}) => {
        const fieldContract = createMerosField(id, rules);

        return {
            ...fieldContract,
            type: null,

            init() {
                fieldContract.init.call(this);

                if (this.element) {
                    this.type = this.element.dataset.fieldType || (this.element.tagName === 'INPUT' ? this.element.type : null);
                    this.previousValue = snapshotValue(this.value);
                }
            },

            getValue() {
                if (!this.element) return null;

                if (this.type === 'checkbox') {
                    return this.element.checked ? true : false;
                }

                if (this.type === 'number') {
                    const value = this.element.value;
                    return value === '' ? null : Number(value);
                }

                if (this.type === 'hidden' && this.element.getAttribute('value-as-json') === 'true') {
                    try {
                        return JSON.parse(this.element.value);
                    } catch (e) {
                        return this.element.value;
                    }
                }

                return this.element.value;
            },

            setValue(value) {
                if (!this.element) return;
                this.previousValue = snapshotValue(this.getValue());

                if (this.type === 'checkbox') {
                    this.element.checked = !!value;
                } else {
                    this.element.value = value;
                }

                this.value = snapshotValue(this.getValue());
                this.__dispatchUpdate();
            },

            destroy() {
                fieldContract.destroy.call(this);
                this.type = null;
            },

            onChange() {
                this.previousValue = snapshotValue(this.value);
                validateFieldValue(this.element);

                this.__dispatchUpdate();
            },

            onInput() {
                this.applyHints();
            },

            applyHints() {
                const fieldWrapper = this.element.closest('.meros-field');
                if (!fieldWrapper) return;

                const charCountHint = fieldWrapper.querySelector('.char-count-hint');
                const wordCountHint = fieldWrapper.querySelector('.word-count-hint');

                if (charCountHint || wordCountHint) {
                    const rules = this.__getParsedRules();
                
                    const type  = charCountHint ? 'max-chars' : 'max-words';
                    const value = snapshotValue(this.getValue());

                    const max = rules && rules[type] ? parseInt(rules[type]?.value, 10) : null;
                    let count = 0;

                    if (type === 'max-chars') {
                        count = value ? value.length : 0;
                    } else if (type === 'max-words') {
                        count = value ? value.trim().split(/\s+/).length : 0;
                    }

                    if (type === 'max-chars') {
                        charCountHint.textContent = `${count}/${max || '∞'}`;
                    } else if (type === 'max-words') {
                        wordCountHint.textContent = `${count}/${max || '∞'}`;
                    }
                }
            },

            __dispatchUpdate(context = {}) {
                const newValue = snapshotValue(this.getValue());
                const oldValue = snapshotValue(this.previousValue);

                if (newValue !== oldValue) {
                    this.$dispatch('mforms:field-updated', {
                        formId: this.element ? this.element.closest('form')?.id || null : null,
                        type: this.type,
                        id: this.id,
                        name: this.element ? this.element.name : null,
                        element: this.element,
                        oldValue,
                        value: newValue,
                        context: getContext(this.element, context),
                        field: this,
                    });

                    this.value = snapshotValue(newValue);
                } else {
                    this.value = snapshotValue(newValue);
                }
            }
        };
    });

    /**
     * Alpine component for TomSelect fields.
     */
    Alpine.data('merosTomSelectField', (id, rules = {}) => {
        const fieldContract = createMerosField(id, rules);

        return {
            ...fieldContract,
            isInstantiating: false,
            onRefresh: null,

            init() {
                this.onRefresh = () => {
                    this.element = this.$el.querySelector('select');
                    this.__instantiate();
                };

                this.element = this.$el.querySelector('select');
                this.value = snapshotValue(this.getValue());
                this.previousValue = snapshotValue(this.value);
                this.__instantiate();

                window.addEventListener('mforms:field-settings-opened', this.onRefresh);
                window.addEventListener('mforms:field-settings-refreshed', this.onRefresh);
                window.addEventListener('mforms:field-settings-closed', this.onRefresh);
                window.addEventListener('mforms:form-canvas-updated', this.onRefresh);
            },

            getValue() {
                if (!this.element) return null;

                if (this.element.tomselect) {
                    return this.element.tomselect.getValue();
                }

                if (this.element.hasAttribute('multiple')) {
                    return Array.from(this.element.selectedOptions).map(option => option.value);
                }

                return this.element.value;
            },

            setValue(value) {
                if (!this.element || !this.element.tomselect) return;

                // Use silent=true to suppress onChange so we don't double-dispatch.
                this.element.tomselect.setValue(value, true);
                this.__dispatchUpdate();
            },

            destroy() {
                if (this.element && this.element.tomselect) {
                    this.element.tomselect.destroy();
                }

                window.removeEventListener('mforms:field-settings-opened', this.onRefresh);
                window.removeEventListener('mforms:field-settings-refreshed', this.onRefresh);
                window.removeEventListener('mforms:field-settings-closed', this.onRefresh);
                window.removeEventListener('mforms:form-canvas-updated', this.onRefresh);

                fieldContract.destroy.call(this);
                this.element = null;
                this.onRefresh = null;
            },

            __instantiate() {
                if (!this.element) return;
                if (this.element.closest('.meros-repeater-template-row')) return;

                this.isInstantiating = true;

                const isReinstantiation = !!this.element.tomselect;

                // Capture the underlying <select> value BEFORE calling destroy().
                // TomSelect.destroy() restores revertSettings.innerHTML from init time,
                // reverting any value changes made since (by the user or Livewire morph).
                // Reading element.selectedOptions/element.value here captures the correct
                // intended value before that state is overwritten.
                const preDestroyValue = isReinstantiation
                    ? (this.element.hasAttribute('multiple')
                        ? Array.from(this.element.selectedOptions).map(o => o.value)
                        : this.element.value)
                    : null;

                if (this.element.tomselect) {
                    this.element.tomselect.destroy();
                }

                const multiple = this.element.hasAttribute('multiple');
                const allowAdd = this.element.dataset.allowAdd === 'true';

                new TomSelect(this.element, {
                    plugins: multiple ? {
                        remove_button: {
                            title: 'Remove',
                        }
                    } : {},
                    create: allowAdd ? (input) => {
                        return {
                            value: input.toLowerCase().replace(/\s+/g, '-'),
                            text: input,
                        };
                    } : false,
                    sortField: [{ field: '$order' }, { field: '$score' }],
                    maxItems: multiple ? null : 1,
                    onChange: (value) => {
                        this.element.tomselect.blur();
                        this.__dispatchUpdate();
                    }
                });

                const domValue = snapshotValue(this.getValue());
                const isEmpty = (v) => v === null || v === '' || (Array.isArray(v) && v.length === 0);

                // After destroy/recreate, the DOM reflects original init-time HTML.
                // Restore preDestroyValue (captured before destroy) to recover the correct state.
                if (isReinstantiation && !isEmpty(preDestroyValue)) {
                    this.element.tomselect.setValue(preDestroyValue, true);
                    this.value = snapshotValue(this.getValue());
                } else {
                    this.value = domValue;
                }

                this.value = snapshotValue(this.value);
                this.previousValue = snapshotValue(this.value);
                this.isInstantiating = false;
            },

            __dispatchUpdate(context = {}) {
                const oldValue = snapshotValue(this.value);
                const value = this.getValue();
                const valueSnapshot = snapshotValue(value);

                this.$dispatch('mforms:field-updated', {
                    formId: this.element ? this.element.closest('form')?.id || null : null,
                    type: this.element ? this.element.dataset.fieldType || 'select' : 'select',
                    id: this.element ? this.element.id : null,
                    name: this.element ? this.element.name : null,
                    element: this.element,
                    oldValue,
                    value: valueSnapshot,
                    context: getContext(this.element, context),
                    field: this,
                });

                this.previousValue = snapshotValue(oldValue);
                this.value = valueSnapshot;
            }
        };
    });

    /**
     * Alpine component for Quill rich text editors.
     */
    Alpine.data('merosRichTextField', (id) => ({
        id: id,
        init() {
            const quill = new Quill(this.$el, {
                readOnly: this.$el.hasAttribute('aria-disabled') || this.$el.hasAttribute('disabled'),
                theme: 'snow',
                modules: {
                    toolbar: ['bold', 'italic', 'underline', 'link']
                }
            });

            this.$el._quill = quill;
        },

        getValue() {
            return this.$el?._quill?.root?.innerHTML ?? '';
        },

        destroy() {
            if (this.$el._quill) {
                this.$el._quill = null;
            }
        }
    }));

    /**
     * Alpine component for repeater fields, handling row addition, removal, and reordering.
     */
    Alpine.data('merosRepeaterField', (id, placeholder, rules = {}) => {
        const fieldContract = createMerosField(id, rules);

        return {
            ...fieldContract,

            placeholder: placeholder || 'Nothing to display',
            showPlaceholder: false,
            bodyScrollLockClass: 'meros-repeater-dialog-open',
            
            openDelayMs: 180,
            updateDelayMs: 180,
            rowDialogOpen: false,
            isUsingCustomConfigurationDialog: false,
            customConfigurationDialogHtml: '',

            isOpeningRowDialog: false,
            isUpdatingRowDialog: false,
            pendingDialogRowIndex: null,
            activeDialogRowIndex: null,
            activeDialogRowEl: null,
            activeDialogFieldMounts: [],

            init() {
                if (this.id) {
                    this.element = document.getElementById(this.id);

                    if (this.element) {
                        this.value = snapshotValue(this.getValue());
                        this.previousValue = snapshotValue(this.value);
                        this.__togglePlaceholder();
                    }
                }
            },

            destroy() {
                this.__finalizeRowDialogClose();
                fieldContract.destroy.call(this);
                this.element = null;
            },

            addRow() {
                if (!this.element) return;
                const templateRow = this.element.querySelector('tr.meros-repeater-template-row');

                if (!templateRow) return;

                const rowCount = this.element.querySelectorAll('tr.meros-repeater-row:not(.meros-repeater-template-row)').length;

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
                    removeButton.setAttribute('@click.stop', 'removeRow($event)');
                }

                this.__enableTemplateRowFields(newRow);
                this.element.querySelector('tbody').insertBefore(newRow, templateRow);

                if (window.Alpine && typeof window.Alpine.initTree === 'function') {
                    window.Alpine.initTree(newRow);
                }

                this.__reindexRowFields();
                this.__togglePlaceholder();

                this.__dispatchUpdate({
                    action: 'add',
                    rowIndex: this.element.querySelectorAll('tr.meros-repeater-row:not(.meros-repeater-template-row)').length - 1,
                });

            },

            openRowDialog(event, customDialogs) {
                if (this.isOpeningRowDialog || this.isUpdatingRowDialog || this.rowDialogOpen) {
                    return;
                }

                const trigger = event?.currentTarget || event?.target;
                const row = trigger ? trigger.closest('tr.meros-repeater-row') : null;

                if (!row || row.classList.contains('meros-repeater-template-row')) {
                    return;
                }

                const rowIndex = Number.parseInt(row.dataset.repeaterRowIndex || '-1', 10);
                const rowData = this.getRowValue(rowIndex) || {};

                if (Array.isArray(customDialogs) && customDialogs.length > 0) {
                    for (const entry of customDialogs) {
                        const { rule, html } = entry || {};

                        if (!Array.isArray(rule) || rule.length < 3 || typeof html !== 'string' || html.trim() === '') {
                            continue;
                        }

                        const [field, operator, value] = rule;
                        const fieldValue = rowData[field];
                        let isMatch = false;

                        switch (operator) {
                            case '=':
                                isMatch = fieldValue == value;
                                break;
                            case '!=':
                                isMatch = fieldValue != value;
                                break;
                            case '>':
                                isMatch = fieldValue > value;
                                break;
                            case '<':
                                isMatch = fieldValue < value;
                                break;
                            case '>=':
                                isMatch = fieldValue >= value;
                                break;
                            case '<=':
                                isMatch = fieldValue <= value;
                                break;
                        }

                        if (!isMatch) {
                            continue;
                        }

                        let currentConfig = null;
                        const configurationField = row.querySelector('.meros-repeater-data-cell[data-field-name="__configuration"] [data-field-type="hidden"]');

                        if (configurationField) {
                            currentConfig = configurationField.value ? JSON.parse(configurationField.value) : {};
                        }

                        this.isUsingCustomConfigurationDialog = true;
                        this.customConfigurationDialogHtml = html;
                        this.isOpeningRowDialog = true;
                        this.pendingDialogRowIndex = rowIndex;

                        window.setTimeout(() => {
                            this.activeDialogRowEl = row;
                            this.activeDialogRowIndex = rowIndex;
                            this.rowDialogOpen = true;
                            this.isOpeningRowDialog = false;
                            this.pendingDialogRowIndex = null;
                            document.body.classList.add(this.bodyScrollLockClass);

                            this.$nextTick(() => {
                                const dialogBody = document.getElementById('meros-repeater-config-dialog-custom-body');

                                if (dialogBody && window.Alpine && typeof window.Alpine.initTree === 'function') {
                                    window.Alpine.initTree(dialogBody);
                                }

                                if (!currentConfig) return;

                                const fields = dialogBody ? dialogBody.querySelectorAll('[data-field-type]') : [];

                                fields.forEach((field) => {
                                    const name = field.dataset.fieldName;

                                    if (!name) return;
                                    if (!currentConfig[name]) return;

                                    setFieldValue(field, currentConfig[name]);
                                });
                            });
                        }, this.openDelayMs);

                        return;
                    }
                }

                this.isUsingCustomConfigurationDialog = false;
                this.customConfigurationDialogHtml = '';

                this.isOpeningRowDialog = true;
                this.pendingDialogRowIndex = rowIndex;

                window.setTimeout(() => {
                    this.activeDialogRowEl = row;
                    this.activeDialogRowIndex = rowIndex;
                    this.__mountRowDialogFields();
                    this.rowDialogOpen = true;
                    this.isOpeningRowDialog = false;
                    this.pendingDialogRowIndex = null;
                    document.body.classList.add(this.bodyScrollLockClass);
                }, this.openDelayMs);
            },

            closeRowDialog() {
                this.updateRowDialog();
            },

            updateRowDialog() {
                if (this.isUpdatingRowDialog || !this.rowDialogOpen) {
                    return;
                }

                const rowIndex = this.activeDialogRowIndex;
                this.isUpdatingRowDialog = true;

                window.setTimeout(() => {
                    this.__finalizeRowDialogClose();
                    this.isUpdatingRowDialog = false;

                    this.__dispatchUpdate({
                        action: 'update',
                        rowIndex,
                    });
                }, this.updateDelayMs);
            },

            removeRow(event) {
                const row = event.target.closest('tr.meros-repeater-row');

                if (!row) return;

                const rowIndex = Number.parseInt(row.dataset.repeaterRowIndex || '-1', 10);

                if (this.activeDialogRowEl === row) {
                    this.closeRowDialog();
                }

                row.remove();
                this.__reindexRowFields();
                this.__togglePlaceholder();

                this.__dispatchUpdate({
                    action: 'remove',
                    oldIndex: rowIndex,
                });
            },

            handleReorder(item, position) {
                this.__reindexRowFields();
                this.__dispatchUpdate({
                    action: 'reorder',
                    oldIndex: item,
                    newIndex: position,
                });
            },

            getValue() {
                if (!this.element) return [];
                const rows = this.element.querySelectorAll('.meros-repeater-row:not(.meros-repeater-template-row)');
                const data = [];

                rows.forEach((row) => {
                    const rowIndex = Number.parseInt(row.dataset.repeaterRowIndex || '-1', 10);
                    const rowData = this.__readRowData(row, rowIndex);

                    if (rowData) {
                        data.push(rowData);
                    }
                });

                return data;
            },

            getRowValue(rowIndex) {
                if (!this.element) return null;
                const row = this.element.querySelector(`.meros-repeater-row[data-repeater-row-index="${rowIndex}"]`);

                if (!row) return null;

                return this.__readRowData(row, rowIndex);
            },

            getCellValue(rowIndex, fieldName) {
                if (!this.element) return null;
                const row = this.element.querySelector(`.meros-repeater-row[data-repeater-row-index="${rowIndex}"]`);

                if (!row) return null;
                const cell = row.querySelector(`.meros-repeater-data-cell[data-field-name="${fieldName}"]`);

                if (!cell) return null;
                return this.__readCellData(row, cell, rowIndex, fieldName);
            },

            __togglePlaceholder() {
                const hasRows = this.element.querySelectorAll('.meros-repeater-row:not(.meros-repeater-template-row)').length > 0;
                if (hasRows) {
                    this.showPlaceholder = false;
                } else {
                    this.showPlaceholder = true;
                }
            },

            __readRowData(row, rowIndex) {
                const rowData = {};
                const cells = row.querySelectorAll('.meros-repeater-data-cell');

                cells.forEach((cell) => {
                    const fieldName = cell.dataset.fieldName;
                    rowData[fieldName] = this.__readCellData(row, cell, rowIndex, fieldName);
                });

                return rowData;
            },

            __readCellData(row, cell, rowIndex, fieldName) {
                const input = cell.querySelector('[data-field-type]');

                if (!input) return null;
                const component = getFieldComponent(input.id);

                if (
                    component
                    && component !== this
                    && !(component.element && component.element === this.element)
                    && typeof component.getValue === 'function'
                ) {
                    return component.getValue();
                }

                if (input.tagName === 'SELECT') {
                    if (input.hasAttribute('multiple')) {
                        return Array.from(input.selectedOptions).map(option => option.value);
                    } else {
                        return input.value;
                    }
                }

                if (input.tagName === 'INPUT' && input.type === 'checkbox') {
                    return input.checked ? true : false;
                }

                if (input.tagName === 'INPUT' && input.type === 'radio') {
                    const checkedRadio = cell.querySelector('input[type="radio"]:checked');
                    return checkedRadio ? checkedRadio.value : null;
                }

                return input.value;
            },

            __dispatchUpdate(context = {}) {
                const oldValue = snapshotValue(this.value);
                const value = snapshotValue(this.getValue());

                this.$dispatch('mforms:field-updated', {
                    formId: this.element ? this.element.closest('form')?.id || null : null,
                    type: 'repeater',
                    id: this.id,
                    name: this.element ? this.element.dataset.repeaterName : null,
                    element: this.element,
                    oldValue,
                    value,
                    context: getContext(this.element, context),
                    field: this,
                });

                this.previousValue = snapshotValue(oldValue);
                this.value = snapshotValue(value);
            },

            __finalizeRowDialogClose() {
                if (this.isUsingCustomConfigurationDialog) {
                    this.__saveCustomConfigurationDialogValues();
                } else {
                    this.__restoreRowDialogFields();
                }

                this.rowDialogOpen = false;
                this.isOpeningRowDialog = false;
                this.activeDialogRowEl = null;
                this.activeDialogRowIndex = null;
                this.pendingDialogRowIndex = null;
                this.isUsingCustomConfigurationDialog = false;
                this.customConfigurationDialogHtml = '';
                document.body.classList.remove(this.bodyScrollLockClass);
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
                const rows = this.element.querySelectorAll('.meros-repeater-row');

                rows.forEach((row, rowIndex) => {
                    row.setAttribute('data-repeater-row-index', rowIndex);

                    const cells = row.querySelectorAll('.meros-repeater-data-cell');

                    cells.forEach((cell) => {
                        const fieldName = cell.dataset.fieldName;
                        const input = cell.querySelector(`[data-base-field-name="${fieldName}"]`);

                        if (input) {
                            const nameIndex = input.name.indexOf('[');
                            const baseName = input.name.substring(0, nameIndex);
                            const newName = `${baseName}[${rowIndex}][${fieldName}]`;
                            input.name = newName;

                            if (input.id) {
                                input.id = input.id.replace(/-\d+-field-/, `-${rowIndex}-field-`);
                                input.id = input.id.replace('-template', '');
                            }

                            input.setAttribute('data-row-index', rowIndex);
                        }
                    });
                });
            },

            __mountRowDialogFields() {
                if (!this.activeDialogRowEl || !this.$refs?.rowConfigDialogBody) {
                    return;
                }

                const dialogBody = this.$refs.rowConfigDialogBody;
                const placeholders = dialogBody.querySelectorAll('.meros-repeater-config-dialog__field-input[data-field-name]');

                this.activeDialogFieldMounts = [];

                placeholders.forEach((placeholder) => {
                    const fieldName = placeholder.dataset.fieldName;
                    const sourceCell = this.activeDialogRowEl.querySelector(`.meros-repeater-data-cell[data-field-name="${fieldName}"]`);

                    placeholder.innerHTML = '';

                    if (!sourceCell) {
                        return;
                    }

                    const sourceField = sourceCell.querySelector('[data-field-type], .meros-field');

                    if (!sourceField) {
                        return;
                    }

                    const anchor = document.createElement('div');
                    anchor.hidden = true;
                    anchor.dataset.repeaterDialogAnchor = fieldName;

                    sourceCell.insertBefore(anchor, sourceField);
                    placeholder.appendChild(sourceField);

                    this.activeDialogFieldMounts.push({
                        anchor,
                        fieldEl: sourceField,
                        placeholder,
                    });
                });
            },

            __restoreRowDialogFields() {
                this.activeDialogFieldMounts.forEach(({ anchor, fieldEl, placeholder }) => {
                    if (anchor.parentNode) {
                        anchor.parentNode.insertBefore(fieldEl, anchor);
                        anchor.remove();
                    }

                    if (placeholder && placeholder.contains(fieldEl)) {
                        placeholder.innerHTML = '';
                    }
                });

                this.activeDialogFieldMounts = [];
            },

            __saveCustomConfigurationDialogValues() {
                if (!this.activeDialogRowEl) {
                    return;
                }

                const dialogBody = document.getElementById('meros-repeater-config-dialog-custom-body');
                if (!dialogBody) return;

                const configurationField = this.activeDialogRowEl.querySelector('.meros-repeater-data-cell[data-field-name="__configuration"] [data-field-type="hidden"]');
                if (!configurationField) return;

                const configuration = {};
                const inputs = dialogBody.querySelectorAll('[data-field-type]');

                inputs.forEach((input) => {
                    const fieldName = input.name || input.dataset.fieldName;

                    if (!fieldName) return;
                    configuration[fieldName] = getFieldValue(input);
                });

                const configurationJson = JSON.stringify(configuration);
                const component = getFieldComponent(configurationField.id);

                if (component && typeof component.setValue === 'function') {
                    component.setValue(configurationJson);
                } else {
                    configurationField.value = configurationJson;
                }
            }
        };
    });
});