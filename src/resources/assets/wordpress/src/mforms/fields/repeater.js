import MerosFormProcessor from '../form-processor.js';
import MerosModal from '../../classes/modal.js';

const mformsRepeater = () => {
    return {
        container: null,
        numRows: 0,
        id: null,
        name: null,
        ajaxurl: null,
        ajaxNonce: null,
        editingRowIndex: null,
        onFormSubmit: null,
        onInit: null,
        onRemove: null,

        // =========================================================================
        // Initialisation
        // =========================================================================

        init() {
            this.container = this.$el.classList.contains('meros-repeater-field')
                ? this.$el
                : this.$el.closest('.meros-repeater-field');

            if (this.container) {
                this.id = this.container.id || null;
                this.name = this.container.dataset.name || null;

                this.ajaxurl = this.container.dataset.ajaxUrl || null;
                this.ajaxNonce = this.container.dataset.ajaxNonce || null;

                this.onInit = this.container.dataset.onInit && this.container.dataset.onInit !== 'false'
                    ? this.container.dataset.onInit
                    : null;

                this.onRemove = this.container.dataset.onRemove && this.container.dataset.onRemove !== 'false'
                    ? this.container.dataset.onRemove
                    : null;

                this.container.removeAttribute('data-ajax-url');
                this.container.removeAttribute('data-ajax-nonce');
                this.container.removeAttribute('data-on-remove');
                this.numRows = this.resolveRows().length;

                if (this.onInit && typeof window[this.onInit] === 'function') {
                    const onInitFunc = window[this.onInit];
                    onInitFunc(this);
                }
            }

            this.onFormSubmit = (event) => {
                if (!this.name || !this.id || this.editingRowIndex === null) return;

                const data = event.detail;
                const { formId, formName, formData } = data;

                if (!formId || !formName || !formData) return;

                if (formName === this.name + '_edit_form') {
                    this.handleRowFormSubmit(formData);
                }
            };

            window.addEventListener('mforms::meros_repeater_form_submit', this.onFormSubmit);
        },

        destroy() {
            if (this.onFormSubmit) {
                window.removeEventListener('mforms::meros_repeater_form_submit', this.onFormSubmit);
            }
        },

        // =========================================================================
        // Operations
        // =========================================================================

        handleAddRow() {
            const tableBody = this.resolveTableBody();
            if (!tableBody) return;

            const templateRow = tableBody.querySelector('tr.meros-repeater-table-row--template');
            if (!templateRow) return;

            const newRow = templateRow.cloneNode(true);
            newRow.classList.remove('meros-repeater-table-row--template');
            newRow.removeAttribute('data-repeater-template-row');

            const newRowIndex = this.resolveRows().length;
            const fields = this.resolveRowFields(newRow);

            const updateName = (index, name) => {
                return name.replace('[-1]', `[${index}]`).replace('__template', '');
            };

            const updateId = (index, id) => {
                return id.replace(/-row-\d+/, `-row-${index}`).replace('-template', '');
            }

            fields.forEach((field) => {
                const hasNameAttr = field.hasAttribute('name');
                const fieldName = hasNameAttr ? field.getAttribute('name') : field.getAttribute('data-name');
                const fieldId = field.getAttribute('id');

                // Replace the template index with the new row index
                const newFieldName = updateName(newRowIndex, fieldName);
                const newFieldId = updateId(newRowIndex, fieldId);

                if (hasNameAttr) {
                    field.setAttribute('name', newFieldName);
                } else {
                    field.setAttribute('data-name', newFieldName);
                    if (field.classList.contains('meros-choice-field')) {
                        const choiceInputs = field.querySelectorAll('input.meros-choice-field-input');

                        choiceInputs.forEach((input) => {
                            const inputName = input.getAttribute('name');
                            const inputId = input.getAttribute('id');

                            const newInputName = updateName(newRowIndex, inputName);
                            const newInputId = updateId(newRowIndex, inputId);

                            input.setAttribute('name', newInputName);
                            input.setAttribute('id', newInputId);

                            const label = input.nextElementSibling;

                            if (label && label.tagName === 'LABEL') {
                                label.setAttribute('for', newInputId);
                            }
                        });
                    }
                }
                field.setAttribute('data-repeater-row-index', newRowIndex);
                field.setAttribute('id', newFieldId);

                const baseName = field.getAttribute('data-repeater-field-name');
                if (baseName) {
                    const newBaseName = baseName.replace('__template', '');
                    field.setAttribute('data-repeater-field-name', newBaseName);
                }
            });

            newRow.setAttribute('data-row-index', newRowIndex);

            newRow.removeAttribute('x-sort:item');
            newRow.setAttribute('x-sort:item', newRowIndex);

            tableBody.appendChild(newRow);
            this.numRows++

            // Ensure Alpine components inside the row are initialised.
            if (window.Alpine && typeof window.Alpine.initTree === 'function') {
                window.Alpine.initTree(tableBody);
            }
        },

        handleEditRow(event) {
            if (!this.name || !this.ajaxurl || !this.ajaxNonce) return;

            const row = event.target.closest('tr.meros-repeater-table-row');
            if (!row) return;

            const rowData = this.getRowData(row);

            const formData = new FormData();
            formData.append('action', 'meros_repeater_edit_form_' + this.name);
            formData.append('nonce', this.ajaxNonce);
            formData.append('row_data', JSON.stringify(rowData));

            fetch(this.ajaxurl, {
                method: 'POST',
                body: formData,
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data && data.data.html) {
                        const modal = new MerosModal(
                            'Edit Row',
                            data.data.html,
                            'Save',
                            'Cancel',
                            false
                        );

                        modal.onConfirm((modalElement) => {
                            const form = modalElement.querySelector('form')
                            if (!form) return;

                            const component = this.getComponent(form);
                            if (component && typeof component.submitForm === 'function') {
                                const { success, invalid, error } = component.submitForm();

                                if (success) {
                                    modal.hide();
                                } else if (invalid === undefined) {
                                    modal.enableButtons();
                                    alert(error);
                                } else {
                                    modal.setExtraContent(error, 'red', '1rem');
                                    modal.enableButtons();
                                }

                                return;
                            }
                        });

                        this.editingRowIndex = row.getAttribute('data-row-index');
                        modal.show();
                    }

                    else if (data.data && data.data.message) {
                        console.error('Error fetching edit form:', data.data.message);
                    }

                    else {
                        console.error('Unexpected response:', data);
                    }
                })
                .catch((error) => {
                    console.error('Error fetching edit form:', error);
                });
        },

        handleRowFormSubmit(formData) {
            const rowIndex = this.editingRowIndex;
            if (rowIndex === null) return;

            const row = this.resolveRow(rowIndex);
            if (!row) return;

            const formDataField = this.resolveRowFormDataField(row);
            if (!formDataField) return;

            // Update any table fields with new values from the form data.
            Object.keys(formData).forEach((key) => {
                const field = row.querySelector(`[data-repeater-field-name="${key}"]`);
                if (field) {
                    field.value = formData[key];
                    delete formData[key];
                }
            });

            formDataField.value = JSON.stringify(formData);
            this.editingRowIndex = null;
        },

        handleReorderRows() {
            this.reindexTableFields();
        },

        handleRemoveRow(event) {
            const row = event.target.closest('tr.meros-repeater-table-row');
            if (!row) return;

            if (this.onRemove && typeof window[this.onRemove] === 'function') {
                const onRemoveFunc = window[this.onRemove];
                const remove = onRemoveFunc(row);
                
                if (remove === false) {
                    return;
                }
            }

            row.remove();
            this.reindexTableFields();
            this.numRows = this.resolveRows().length;
        },

        reindexTableFields() {
            const rows = this.resolveRows();

            const updateName = (index, name) => {
                return name.replace(/\[\d+\]/, `[${index}]`);
            };

            const updateId = (currentIndex, index, id) => {
                return id.replace(`-row-${currentIndex}`, `-row-${index}`);
            }

            rows.forEach((row, index) => {
                // Update the row's data-row-index attribute
                row.setAttribute('data-row-index', index);

                // Collect all the field cells in the row
                const fields = this.resolveRowFields(row);

                fields.forEach((field) => {
                    const currentIndex = field.getAttribute('data-repeater-row-index');

                    const hasNameAttr = field.hasAttribute('name');
                    const fieldName = hasNameAttr ? field.getAttribute('name') : field.getAttribute('data-name');
                    const fieldId = field.getAttribute('id');

                    const newFieldName = updateName(index, fieldName);
                    const newFieldId = updateId(currentIndex, index, fieldId);

                    if (hasNameAttr) {
                        field.setAttribute('name', newFieldName);
                    } else {
                        field.setAttribute('data-name', newFieldName);
                        if (field.classList.contains('meros-choice-field')) {
                            const choiceInputs = field.querySelectorAll('input.meros-choice-field-input');

                            choiceInputs.forEach((input) => {
                                const inputName = input.getAttribute('name');
                                const inputId = input.getAttribute('id');

                                input.setAttribute('name', updateName(index, inputName));
                                input.setAttribute('id', updateId(currentIndex, index, inputId));

                                const newInputId = input.getAttribute('id');
                                const label = input.nextElementSibling;

                                if (label && label.tagName === 'LABEL') {
                                    label.setAttribute('for', newInputId);
                                }
                            });
                        }
                    }
                    field.setAttribute('data-repeater-row-index', index);
                    field.setAttribute('id', newFieldId);
                })
            })
        },

        getValue() {
            const rows = this.resolveRows();
            const allRowData = [];

            rows.forEach((row) => {
                const rowData = this.getRowData(row);
                allRowData.push(rowData);
            });

            return allRowData;
        },

        showFieldTooltip(event) {
            const description = event.target?.dataset?.description;
            if (!description) return;

            // Create a tooltip element
            const tooltip = document.createElement('div');
            tooltip.className = 'meros-repeater-tooltip';
            tooltip.textContent = description;

            // Position the tooltip near the mouse cursor
            const offsetX = 10;
            const offsetY = 10;
            tooltip.style.left = `${event.pageX + offsetX}px`;
            tooltip.style.top = `${event.pageY + offsetY}px`;

            // Start with 0 opacity
            tooltip.style.opacity = '0';
            tooltip.style.transition = 'opacity 0.2s ease-in-out';

            // Append the tooltip to the body
            document.body.appendChild(tooltip);

            // Fade in the tooltip
            requestAnimationFrame(() => {
                tooltip.style.opacity = '1';
            });

            // Remove the tooltip when the mouse leaves the element
            event.target.addEventListener('mouseleave', () => {
                if (tooltip.parentNode) {
                    tooltip.style.opacity = '0';
                    setTimeout(() => {
                        if (tooltip.parentNode) {
                            tooltip.parentNode.removeChild(tooltip);
                        }
                    }, 200);
                }
            }, { once: true });
        },

        // =========================================================================
        // Helpers
        // =========================================================================

        resolveTable() {
            if (!this.container) return;
            return this.container.querySelector('.meros-repeater-table');
        },

        resolveTableBody() {
            const table = this.resolveTable();
            if (!table) return;
            return table.querySelector('tbody');
        },

        resolveRow(rowIndex) {
            const rows = this.resolveRows();
            if (!rows || rows.length <= rowIndex) return null;
            return rows[rowIndex];
        },

        resolveRows() {
            const table = this.resolveTable();
            if (!table) return [];

            return table.querySelectorAll(
                'tr.meros-repeater-table-row' +
                ':not(.meros-repeater-table-row--template)' +
                ':not(.meros-repeater-table-row--empty)'
            );
        },

        resolveRowFields(row) {
            if (!row) return [];
            return row.querySelectorAll('.meros-repeater-table-cell--field [data-field-type]');
        },

        getRowData(row) {
            if (!row) return null;

            const fields = this.resolveRowFields(row);
            const rowData = {};
            let serializedFormData = null;

            fields.forEach((field) => {
                const fieldName = field.getAttribute('data-repeater-field-name');
                if (!fieldName) return;

                const fieldProcessor = new MerosFormProcessor(row);
                const fieldValue     = fieldProcessor.getFieldValue(field);

                // The hidden field contains the edited row form payload.
                if (fieldName === '__form_data') {
                    serializedFormData = fieldValue;
                    return;
                }

                rowData[fieldName] = fieldValue;
            });

            // Merge edited form values with the table-field values.
            // Edited form values take precedence when keys overlap.
            if (serializedFormData) {
                try {
                    const editedRowData = JSON.parse(serializedFormData);

                    if (
                        editedRowData &&
                        typeof editedRowData === 'object' &&
                        !Array.isArray(editedRowData)
                    ) {
                        return {
                            ...rowData,
                            form_data: { ...editedRowData },
                        };
                    }
                } catch (error) {
                    console.warn('Unable to decode repeater row form data:', error);
                }
            }

            return rowData;
        },

        resolveRowFormDataField(row) {
            if (!row) return null;
            return row.querySelector('input[type="hidden"][data-repeater-field-name="__form_data"]');
        },

        getComponent(item) {
            const alpineData = item.closest('[x-data]');
            if (!alpineData) return null;

            const component = Alpine.$data(alpineData);
            return component || null;
        }
    }
};

export default mformsRepeater;