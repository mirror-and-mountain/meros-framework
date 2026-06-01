export default function registerFieldsStore() {
    const store = {
        // Fields in id:{name, label, type, value, options, required, disabled, hidden} format
        fields: {},
        valueUpdateCallbacks: {},
        optionsUpdateCallbacks: {},

        // Sets the fields property
        setFields(fields) {
            this.fields = fields;
        },

        // returns all fields in id:label format
        getFields() {
            return this.fields;
        },

        // returns the label for a specific field
        getFieldLabel(fieldId) {
            return this.fields[fieldId] || null;
        },

        // Returns the type for a specific field
        getFieldType(fieldId) {
            const fieldElement = this.getFieldElement(fieldId);

            if (fieldElement) {
                return fieldElement.dataset.fieldType || null;
            }

            return null;
        },

        // Returns the field element for a specific field if possible
        getFieldElement(fieldId) {
            if (this.fields[fieldId]) {
                return document.getElementById(fieldId) || null;
            }

            return null;
        },

        // Returns the value for a specific field if possible
        getFieldValue(fieldId) {
            const fieldElement = this.getFieldElement(fieldId);

            if (fieldElement) {
                const fieldType = fieldElement.dataset.fieldType;

                if (!fieldType) {
                    return null;
                }

                if (fieldElement.tomselect) {
                    return fieldElement.tomselect.getValue();
                }

                if (fieldType === 'checkbox') {
                    return fieldElement.checked ? true : false;
                }

                if (fieldType === 'checkboxes') {
                    const checkboxes = fieldElement.querySelectorAll('input[type="checkbox"]');
                    const values = {};

                    checkboxes.forEach(checkbox => {
                        const checked = checkbox.checked ? true : false;
                        const name = checkbox.name.replace('[]', '');
                        values[name] = checked;
                    });

                    return values;
                }

                if (fieldType === 'repeater') {
                    const repeaterStore = Alpine.store('repeaterField');

                    if (repeaterStore) {
                        const value = repeaterStore.getRepeaterValue(fieldId);
                        return value;
                    }

                    return null;
                }

                return fieldElement.value;
            }

            return null;
        },

        // Makes a specific field required
        makeRequired(fieldId) {
            const fieldElement = this.getFieldElement(fieldId);

            if (fieldElement) {
                fieldElement.setAttribute('required', 'required');
            }
        },

        // Makes a specific field optional
        makeOptional(fieldId) {
            const fieldElement = this.getFieldElement(fieldId);

            if (fieldElement) {
                fieldElement.removeAttribute('required');
            }
        },

        // Disables a specific field
        disable(fieldId) {
            const fieldElement = this.getFieldElement(fieldId);

            if (fieldElement) {
                fieldElement.setAttribute('disabled', 'disabled');
            }
        },

        // Enables a specific field
        enable(fieldId) {
            const fieldElement = this.getFieldElement(fieldId);

            if (fieldElement) {
                fieldElement.removeAttribute('disabled');
            }
        },

        // Shows a specific field
        show(fieldId) {
            const fieldElement = this.getFieldElement(fieldId);

            if (fieldElement) {
                fieldElement.style.display = '';
            }
        },

        // Hides a specific field
        hide(fieldId) {
            const fieldElement = this.getFieldElement(fieldId);

            if (fieldElement) {
                fieldElement.style.display = 'none';
                if (fieldElement.required) {
                    fieldElement.removeAttribute('required');
                }
            }
        },

        // Sets the value for a specific field if possible
        setFieldValue(fieldId, value) {
            const fieldElement = this.getFieldElement(fieldId);
            
            if (fieldElement) {
                const fieldType = fieldElement.dataset.fieldType;

                if (!fieldType) {
                    return;
                }

                if (fieldElement.tomselect) {
                    fieldElement.tomselect.setValue(value);
                    return;
                }

                if (fieldType === 'checkbox') {
                    fieldElement.checked = value ? true : false;
                    return;
                }

                if (fieldType === 'checkboxes') {
                    const checkboxes = fieldElement.querySelectorAll('input[type="checkbox"]');

                    checkboxes.forEach(checkbox => {
                        const name = checkbox.name.replace('[]', '');
                        checkbox.checked = value[name] ? true : false;
                    });

                    return;
                }

                if (fieldType === 'repeater') {
                    // Not implementing just now. Individual repeater cells could be updated via their own ids.
                }
            }
        },

        // Updates the options for a specific field if possible (only select, multi_select, radio and checkboxes)
        setFieldOptions(fieldId, options) {
            const fieldElement = this.getFieldElement(fieldId);

            if (fieldElement) {
                const fieldType = fieldElement.dataset.fieldType;

                const validTypes = ['select', 'multi_select', 'radio', 'checkboxes'];

                if (!fieldType || !validTypes.includes(fieldType)) {
                    return;
                }

                if (fieldElement.tomselect) {
                    fieldElement.tomselect.clearOptions();
                    fieldElement.tomselect.addOptions(options);
                    return;
                }

                if (fieldType === 'select') {
                    fieldElement.innerHTML = '';

                    options.forEach(option => {
                        const optionElement = document.createElement('option');
                        optionElement.value = option.value;
                        optionElement.textContent = option.label;
                        fieldElement.appendChild(optionElement);
                    });
                }

                if (fieldType === 'radio' || fieldType === 'checkboxes') {
                    const normalisedOptions = Array.isArray(options)
                        ? options
                        : Object.entries(options ?? {}).map(([value, label]) => ({ value, label }));

                    const firstChoiceInput = fieldElement.querySelector(
                        fieldType === 'radio' ? 'input[type="radio"]' : 'input[type="checkbox"]'
                    );

                    const sourceName = firstChoiceInput?.name
                        ?? `${fieldId}${fieldType === 'checkboxes' ? '[]' : ''}`;

                    const optionContainer = (() => {
                        const firstWrapper = firstChoiceInput?.closest?.('.nice-form-group') ?? null;
                        const wrapperParent = firstWrapper?.parentElement ?? null;

                        if (wrapperParent && wrapperParent !== fieldElement) {
                            return wrapperParent;
                        }

                        return fieldElement;
                    })();

                    Array.from(optionContainer.querySelectorAll(':scope > .nice-form-group')).forEach(wrapper => {
                        wrapper.remove();
                    });

                    normalisedOptions.forEach(option => {
                        const optionValue = String(option?.value ?? '');
                        const optionLabel = String(option?.label ?? optionValue);
                        const optionId = `${fieldId}_${optionValue}`;

                        const wrapper = document.createElement('div');
                        wrapper.className = 'nice-form-group';

                        const input = document.createElement('input');
                        input.type = fieldType === 'radio' ? 'radio' : 'checkbox';
                        input.name = sourceName;
                        input.id = optionId;
                        input.value = optionValue;
                        input.checked = false;
                        input.setAttribute('data-option-value', optionValue);

                        const label = document.createElement('label');
                        label.htmlFor = optionId;
                        label.textContent = optionLabel;

                        wrapper.appendChild(input);
                        wrapper.appendChild(label);
                        optionContainer.appendChild(wrapper);
                    });
                }
            }
        }
    };
}