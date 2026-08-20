/**
 * Resolves a field identifier (string or HTMLElement) to the corresponding field element in the DOM.
 * 
 * @param {string|HTMLElement} fieldIdentifier 
 * @returns {HTMLElement|null}
 */
function __mforms_resolveField(fieldIdentifier) {
    let field = null;

    if (typeof fieldIdentifier === 'string') {
        field = document.getElementById(fieldIdentifier);

        if (!field) {
            field = document.querySelector(`[data-field-name="${fieldIdentifier}"]`);
        }

        if (!field) {
            field = document.querySelector(`[name="${fieldIdentifier}"]`);
        }

    } else if (fieldIdentifier instanceof HTMLElement) {
        field = fieldIdentifier;
    }

    return field;
}

/**
 * Retrieves the Alpine component instance associated with a given field ID
 * if available.
 * 
 * @param {string|HTMLElement} fieldIdentifier - The ID of the field, the field name, or the field element itself.
 * @returns {object|null}
 */
function  __mforms_getFieldComponent(fieldIdentifier) {
    const field = __mforms_resolveField(fieldIdentifier);

    if (!field) {
        return null;
    }

    const data = field.closest('[x-data]') ?? null;

    if (!data) {
        return null;
    }

    const component = Alpine.$data(data);

    if (component) {
        return component;
    }

    return null;
}

/**
 * Retrieves the field element or its Alpine component instance based on the provided identifier.
 * 
 * @param {string|HTMLElement} fieldIdentifier - The ID of the field, the field name, or the field element itself.
 * @returns {object|HTMLElement|null} The Alpine component instance if available, otherwise the field element, or null if not found.
 */
function __mforms_getField(fieldIdentifier) {
    const field = __mforms_resolveField(fieldIdentifier);

    const component = __mforms_getFieldComponent(field);
    
    if (component) {
        return component;
    }

    return field;
}

/**
 * Retrieves the value of a field based on its identifier.
 * 
 * @param {string|HTMLElement} fieldIdentifier - The ID of the field, the field name, or the field element itself.
 * @returns {*} The value of the field, or null if the field is not found.
 */
function __mforms_getFieldValue(fieldIdentifier) {
    const field = __mforms_resolveField(fieldIdentifier);

    if (!field) return null;
    const component = __mforms_getFieldComponent(field);

    if (component && component.hasOwnProperty('getValue') && typeof component.getValue === 'function') {
        return component.getValue();
    }

    if (field.tagName === 'INPUT' || field.tagName === 'TEXTAREA') {
        if (field.type === 'checkbox') {
            return field.checked ? true : false;
        }

        if (field.type === 'radio') {
            console.log('here');
            const checkedRadio = document.querySelector(`[name="${field.name}"]:checked`);
            return checkedRadio ? checkedRadio.value : null;
        }

        if (field.type === 'number') {
            return field.value ? parseFloat(field.value) : null;
        }

        if (field.type === 'hidden' && field.getAttribute('data-has-json') === 'true') {
            try {
                return JSON.parse(field.value);
            } catch (e) {
                return field.value;
            }
        }

        return field.value;
    }

    if (field.tagName === 'SELECT') {
        if (field.multiple) {
            return Array.from(field.selectedOptions).map(option => option.value);
        }

        return field.value;
    }

    const fieldType = field.dataset.fieldType;

    if (fieldType === 'checkboxes') {
        const checkboxes = field.querySelectorAll('input[type="checkbox"]');
        return Array.from(checkboxes)
            .filter(checkbox => checkbox.checked)
            .map(checkbox => checkbox.value);
    }

    if (fieldType === 'radio') {
        const checkedRadio = field.querySelector('input[type="radio"]:checked');
        return checkedRadio ? checkedRadio.value : null;
    }

    return field.value;
}

/**
 * Sets the value of a field based on its identifier.
 * 
 * @param {string|HTMLElement} fieldIdentifier The identifier of the field, either a string or an HTML element.
 * @param {*} value The value to set for the field.
 * @param {boolean} validate Whether to validate the field after setting its value.
 * @returns 
 */
function __mforms_setFieldValue(fieldIdentifier, value, validate = true) {
    const field = __mforms_resolveField(fieldIdentifier);

    if (!field) return;
    const component = __mforms_getFieldComponent(field);

    if (component && typeof component.setValue === 'function') {
        component.setValue(value);
        
        if (typeof component.dispatchUpdate === 'function') {
            component.dispatchUpdate();
        }
        
        return;
    }

    if (field.tagName === 'INPUT' || field.tagName === 'TEXTAREA') {
        if (field.type === 'checkbox' && typeof value === 'boolean') {
            field.checked = value;
        }

        else if (field.type === 'radio') {
            if (typeof value === 'string') {
                const radioToCheck = field.querySelector(`[name="${field.name}"][value="${value}"]`);
                
                if (radioToCheck) {
                    radioToCheck.checked = true;
                }
            } 
            
            else if (value === null || value === undefined) {
                const checkedRadio = field.querySelector(`[name="${field.name}"]:checked`);
                
                if (checkedRadio) {
                    checkedRadio.checked = false;
                }
            }
        }

        else if (field.type === 'hidden' && field.getAttribute('data-has-json') === 'true') {
            if (typeof value === 'object' && value !== null) {
                try {
                    field.value = JSON.stringify(value);
                } catch (e) {
                    field.value = value;
                }
            } else if (value === null || value === undefined) {
                field.value = '';
            } else if (typeof value === 'string') {
                field.value = value;
            } else {
                field.value = '';
            }
        }

        else if (value === null || value === undefined || value === '') {
            field.value = '';
        }

        else {
            field.value = value;
        }

        if (validate) {
            __mforms_validateFieldValue(field, value);
        }

        if (component && typeof component.dispatchUpdate === 'function') {
            component.dispatchUpdate();
        }

        return;
    }

    if (field.tagName === 'SELECT') {
        if (field.multiple && Array.isArray(value)) {
            Array.from(field.options).forEach(option => {
                option.selected = value.includes(option.value);
            });
        }

        else if (value === null || value === undefined || value === '') {
            field.value = '';
        }

        else {
            field.value = value;
        }

        if (validate) {
            __mforms_validateFieldValue(field, value);
        }

        if (component && typeof component.dispatchUpdate === 'function') {
            component.dispatchUpdate();
        }

        return;
    }

    const fieldType = field.dataset.fieldType;

    if (fieldType === 'checkboxes' && Array.isArray(value)) {
        const checkboxes = field.querySelectorAll('input[type="checkbox"]');

        checkboxes.forEach(checkbox => {
            checkbox.checked = value.includes(checkbox.value);
        });

        if (validate) {
            __mforms_validateFieldValue(field, value);
        }

        if (component && typeof component.dispatchUpdate === 'function') {
            component.dispatchUpdate();
        }

        return;
    }

    if (validate) {
        __mforms_validateFieldValue(field, value);
    }

    field.value = value;
}

/**
 * Validates the value of a field based on its identifier and the provided value.
 * 
 * @param {string|HTMLElement} fieldIdentifier The identifier of the field, either a string or an HTML element.
 * @param {*} value The value to validate. If null, the current field value will be used.
 * @param {boolean} showError Whether to show error messages for invalid fields.
 * 
 * @returns {boolean} Returns true if the value is valid, false otherwise.
 */
function __mforms_validateFieldValue(fieldIdentifier, value = null, showError = false) {
    const field = __mforms_resolveField(fieldIdentifier);

    const isDefaultValueControl = field?.getAttribute('data-default-value-control') === 'true';
    if (isDefaultValueControl) {
        return true;
    }

    const wrapper = field?.closest('.meros-field');
    const wrapperMessageContainer = wrapper?.querySelector('.meros-field-validation-messages');

    if (!field || !wrapper) return;
    const component = __mforms_getFieldComponent(field);

    const getErrorMessage = (rule, backup = 'Invalid value.') => {
        if (component && typeof component.getErrorMessage === 'function') {
            return component.getErrorMessage(rule) || backup;
        }

        return backup;
    };

    if (component && typeof component.isValid === 'function') {
        const valid = component.isValid();

        if (typeof valid === 'boolean') {
            field.classList.toggle('invalid', !valid);

            if (!valid) {
                const errorMessage = showError ? getErrorMessage() : null;
                __mforms_markField(field, false, errorMessage);
            } 
            
            else if (valid) {
                __mforms_markField(field, true);
            }

            return valid;
        }
    }

    value = value !== null ? value : __mforms_getFieldValue(field);
    const __mforms_getRuleValue = (rule, returnValue = 'value') => {
        if (!component || typeof component.getValidationRule !== 'function') {
            return null;
        }

        return component.getValidationRule(rule, returnValue);
    };

    if (typeof value === 'string') {
        const maxChars = __mforms_getRuleValue('max-chars') ?? field.getAttribute('data-rule-max-chars');
        const minChars = __mforms_getRuleValue('min-chars') ?? field.getAttribute('data-rule-min-chars');
        const maxWords = __mforms_getRuleValue('max-words') ?? field.getAttribute('data-rule-max-words');
        const minWords = __mforms_getRuleValue('min-words') ?? field.getAttribute('data-rule-min-words');
        
        if (maxChars && value.length > parseInt(maxChars, 10)) {
            wrapper.classList.add('invalid');

            const errorMessage = showError ? getErrorMessage('max-chars', `Maximum ${maxChars} characters allowed.`) : null;
            __mforms_markField(field, false, errorMessage, showError);

            return false;
        }

        if (minChars && value.length < parseInt(minChars, 10)) {
            wrapper.classList.add('invalid');

            const errorMessage = showError ? getErrorMessage('min-chars', `Minimum ${minChars} characters required.`) : null;
            __mforms_markField(field, false, errorMessage, showError);

            return false;
        }

        if (maxWords && value.split(/\s+/).length > parseInt(maxWords, 10)) {
            wrapper.classList.add('invalid');

            const errorMessage = showError ? getErrorMessage('max-words', `Maximum ${maxWords} words allowed.`) : null;
            __mforms_markField(field, false, errorMessage, showError);

            return false;
        }

        if (minWords && value.split(/\s+/).length < parseInt(minWords, 10)) {
            wrapper.classList.add('invalid');

            const errorMessage = showError ? getErrorMessage('min-words', `Minimum ${minWords} words required.`) : null;
            __mforms_markField(field, false, errorMessage, showError);

            return false;
        }

        wrapper.classList.remove('invalid');
        __mforms_markField(field, true, null, showError);

        return true;
    }

    if (Array.isArray(value)) {
        const maxItems = __mforms_getRuleValue('max-items') ?? field.getAttribute('data-rule-max-items');
        const minItems = __mforms_getRuleValue('min-items') ?? field.getAttribute('data-rule-min-items');

        if (maxItems && maxItems !== '-1' && value.length > parseInt(maxItems, 10)) {
            wrapper.classList.add('invalid');

            const errorMessage = showError ? getErrorMessage('max-items', `Maximum ${maxItems} items allowed.`) : null;
            __mforms_markField(field, false, errorMessage, showError);

            return false;
        }

        if (minItems && minItems !== '-1' && value.length < parseInt(minItems, 10)) {
            wrapper.classList.add('invalid');

            const errorMessage = showError ? getErrorMessage('min-items', `Minimum ${minItems} items required.`) : null;
            __mforms_markField(field, false, errorMessage, showError);

            return false;
        }

        wrapper.classList.remove('invalid');
        __mforms_markField(field, true, null, showError);

        return true;
    }

    if (typeof value === 'number' || !isNaN(parseFloat(value))) {
        const maxValue = __mforms_getRuleValue('max') ?? field.getAttribute('data-rule-max');
        const minValue = __mforms_getRuleValue('min') ?? field.getAttribute('data-rule-min');

        if (maxValue && value > parseFloat(maxValue)) {
            wrapper.classList.add('invalid');

            const errorMessage = showError ? getErrorMessage('max', `Maximum value is ${maxValue}.`) : null;
            __mforms_markField(field, false, errorMessage, showError);

            return false;
        }

        if (minValue && value < parseFloat(minValue)) {
            wrapper.classList.add('invalid');

            const errorMessage = showError ? getErrorMessage('min', `Minimum value is ${minValue}.`) : null;
            __mforms_markField(field, false, errorMessage, showError);

            return false;
        }

        wrapper.classList.remove('invalid');
        __mforms_markField(field, true, null, showError);

        return true;
    }

    wrapper.classList.remove('invalid');

    __mforms_markField(field, true, null, showError);
    return true;
};

/**
 * Marks a field as valid or invalid and optionally sets an error message.
 *
 * @param {*} fieldIdentifier - The identifier of the field to mark.
 * @param {boolean} isValid - Whether the field is valid.
 * @param {string|null} errorMessage - The error message to display if the field is invalid.
 * @param {boolean} showError - Whether to show the error message for invalid fields.
 * 
 * @returns {void}
 */
function __mforms_markField(fieldIdentifier, isValid, errorMessage = null, showError = false) {
    const field = __mforms_resolveField(fieldIdentifier);
    const wrapper = field?.closest('.meros-field');
    const wrapperMessageContainer = wrapper?.querySelector('.meros-field-validation-messages');

    if (!field || !wrapper) return;

    if (isValid) {
        wrapper.classList.remove('invalid');
        wrapperMessageContainer && (wrapperMessageContainer.textContent = '');
    } else {
        wrapper.classList.add('invalid');

        if (wrapperMessageContainer && showError) {
            wrapperMessageContainer.textContent = errorMessage || 'Invalid value.';
        } else if (wrapperMessageContainer) {
            wrapperMessageContainer.textContent = '';
        }
    }
}

/**
 * Gets contextual information about a field, such as its containing group and repeater, based on the DOM structure.
 * 
 * @param {*} el - The DOM element representing the field.
 * @param {*} context - An optional context object to merge with the field's context.
 * @returns {Object} - The context object containing group and repeater information.
 */
function __mforms_getContext(el) {
    let formContext = {};
    let groupContext = {};
    let repeaterContext = {};

    // Get Form Context
    const formEl = el.closest('form');

    if (formEl) {
        formContext = {
            id: formEl.id || null,
            el: formEl,
        };
    }
    
    // Get Repeater Context
    const repeaterEl = el.closest('.meros-repeater');

    if (repeaterEl && el !== repeaterEl) {
        const templateRowEl = el.closest('.meros-repeater-template-row');
        let rowEl = null;

        if (templateRowEl) {
            rowEl = templateRowEl;
        } else {
            rowEl = el.closest('.meros-repeater-row');
        }

        const rowIndex = rowEl ? Number.parseInt(rowEl.dataset.repeaterRowIndex || '-1', 10) : null;

        repeaterContext = {
            id: repeaterEl.id,
            name: repeaterEl.dataset.repeaterName || null,
            el: repeaterEl,
            row: rowIndex,
            isInTemplateRow: !!templateRowEl,
        };
    }

    // Get Group Context
    const groupEl = el.closest('.meros-field-group, .canvas-field-group');

    if (groupEl) {
        groupContext = {
            id: groupEl.id || groupEl.dataset.groupId || null,
            el: groupEl,
            title: groupEl.dataset.groupTitle || null,
        };
    }

    return {
        inForm: Object.keys(formContext).length > 0,
        inGroup: Object.keys(groupContext).length > 0,
        inRepeater: Object.keys(repeaterContext).length > 0,
        form: formContext,
        group: groupContext,
        repeater: repeaterContext,
    };
}

window.addEventListener('livewire:init', () => {
    window.mforms = {
        getFieldComponent: typeof __mforms_getFieldComponent !== 'undefined' ? __mforms_getFieldComponent : undefined,
        getField: typeof __mforms_getField !== 'undefined' ? __mforms_getField : undefined,
        getFieldValue: typeof __mforms_getFieldValue !== 'undefined' ? __mforms_getFieldValue : undefined,
        setFieldValue: typeof __mforms_setFieldValue !== 'undefined' ? __mforms_setFieldValue : undefined,
        validateFieldValue: typeof __mforms_validateFieldValue !== 'undefined' ? __mforms_validateFieldValue : undefined,
        markField: typeof __mforms_markField !== 'undefined' ? __mforms_markField : undefined,
        getContext: typeof __mforms_getContext !== 'undefined' ? __mforms_getContext : undefined,
    };

    window.dispatchEvent(new CustomEvent('meros:forms-ready'));
});
