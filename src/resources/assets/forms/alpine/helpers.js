/**
 * Resolves a field identifier (string or HTMLElement) to the corresponding field element in the DOM.
 * 
 * @param {string|HTMLElement} fieldIdentifier 
 * @returns {HTMLElement|null}
 */
function resolveField(fieldIdentifier) {
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

function coerceCheckboxLike(value) {
    if (Array.isArray(value)) {
        return value.length > 0;
    }

    if (typeof value === 'boolean') {
        return value;
    }

    if (typeof value === 'number') {
        return value !== 0;
    }

    if (typeof value === 'string') {
        const normalised = value.trim().toLowerCase();

        if (
            normalised === ''
            || normalised === '0'
            || normalised === 'false'
            || normalised === 'off'
            || normalised === 'no'
            || normalised === 'null'
            || normalised === 'undefined'
        ) {
            return false;
        }

        return true;
    }

    return !!value;
}

// Prevent recursive component.setValue -> setFieldValue -> component.setValue loops
// when components delegate their setters back to this helper.
const activeSetFieldOps = new WeakSet();

/**
 * Retrieves the Alpine component instance associated with a given field ID
 * if available.
 * 
 * @param {string|HTMLElement} fieldIdentifier - The ID of the field, the field name, or the field element itself.
 * @returns {object|null}
 */
export function  getFieldComponent(fieldIdentifier) {
    const field = resolveField(fieldIdentifier);

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
export function getField(fieldIdentifier) {
    const field = resolveField(fieldIdentifier);

    const component = getFieldComponent(field);
    
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
export function getFieldValue(fieldIdentifier) {
    const field = resolveField(fieldIdentifier);

    if (!field) return null;
    const component = getFieldComponent(field);

    if (component && typeof component.getValue === 'function') {
        const componentValue = component.getValue();
        const hasElementProperty = Object.prototype.hasOwnProperty.call(component, 'element');
        const isUninitialized = hasElementProperty && !component.element;

        if (!isUninitialized) {
            return componentValue;
        }
    }

    if (field.tagName === 'INPUT' || field.tagName === 'TEXTAREA') {
        if (field.type === 'checkbox') {
            return field.checked ? true : false;
        }

        if (field.type === 'radio') {
            const checkedRadio = document.querySelector(`[name="${field.name}"]:checked`);
            return checkedRadio ? checkedRadio.value : null;
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
export function setFieldValue(fieldIdentifier, value, validate = true, options = {}) {
    const field = resolveField(fieldIdentifier);

    if (!field) return;
    const component = getFieldComponent(field);

    if (!activeSetFieldOps.has(field) && component && typeof component.setValue === 'function') {
        const hasElementProperty = Object.prototype.hasOwnProperty.call(component, 'element');
        const isUninitialized = hasElementProperty && !component.element;

        if (!isUninitialized) {
            activeSetFieldOps.add(field);

            try {
                component.setValue(value, options);
            } finally {
                activeSetFieldOps.delete(field);
            }

            if (validate) {
                validateFieldValue(field, value);
            }

            return;
        }
    }

    if (field.tagName === 'INPUT') {
        if (field.type === 'checkbox') {
            field.checked = coerceCheckboxLike(value);
        }

        else if (field.type === 'radio') {
            const radioToCheck = document.querySelector(`[name="${field.name}"][value="${value}"]`);
            
            if (radioToCheck) {
                radioToCheck.checked = true;
            }
        }

        else {
            field.value = value;
        }

        if (validate) {
            validateFieldValue(field, value);
        }

        return;
    }

    if (field.tagName === 'SELECT') {
        if (field.multiple && Array.isArray(value)) {
            Array.from(field.options).forEach(option => {
                option.selected = value.includes(option.value);
            });
        }

        else {
            field.value = value;
        }

        if (validate) {
            validateFieldValue(field, value);
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
            validateFieldValue(field, value);
        }

        return;
    }

    if (validate) {
        validateFieldValue(field, value);
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
export function validateFieldValue(fieldIdentifier, value = null, showError = false) {
    const field = resolveField(fieldIdentifier);

    const isDefaultValueControl = field?.getAttribute('data-default-value-control') === 'true';
    if (isDefaultValueControl) {
        return true;
    }

    const wrapper = field?.closest('.meros-field');
    const wrapperMessageContainer = wrapper?.querySelector('.meros-field-validation-messages');

    if (!field || !wrapper) return;
    const component = getFieldComponent(field);

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
                markField(field, false, errorMessage);
            } 
            
            else if (valid) {
                markField(field, true);
            }

            return valid;
        }
    }

    value = value !== null ? value : snapshotValue(getFieldValue(field));
    const getRuleValue = (rule, returnValue = 'value') => {
        if (!component || typeof component.getValidationRule !== 'function') {
            return null;
        }

        return component.getValidationRule(rule, returnValue);
    };

    if (typeof value === 'string') {
        const maxChars = getRuleValue('max-chars') ?? field.getAttribute('data-rule-max-chars');
        const minChars = getRuleValue('min-chars') ?? field.getAttribute('data-rule-min-chars');
        const maxWords = getRuleValue('max-words') ?? field.getAttribute('data-rule-max-words');
        const minWords = getRuleValue('min-words') ?? field.getAttribute('data-rule-min-words');
        
        if (maxChars && value.length > parseInt(maxChars, 10)) {
            wrapper.classList.add('invalid');

            const errorMessage = showError ? getErrorMessage('max-chars', `Maximum ${maxChars} characters allowed.`) : null;
            markField(field, false, errorMessage, showError);

            return false;
        }

        if (minChars && value.length < parseInt(minChars, 10)) {
            wrapper.classList.add('invalid');

            const errorMessage = showError ? getErrorMessage('min-chars', `Minimum ${minChars} characters required.`) : null;
            markField(field, false, errorMessage, showError);

            return false;
        }

        if (maxWords && value.split(/\s+/).length > parseInt(maxWords, 10)) {
            wrapper.classList.add('invalid');

            const errorMessage = showError ? getErrorMessage('max-words', `Maximum ${maxWords} words allowed.`) : null;
            markField(field, false, errorMessage, showError);

            return false;
        }

        if (minWords && value.split(/\s+/).length < parseInt(minWords, 10)) {
            wrapper.classList.add('invalid');

            const errorMessage = showError ? getErrorMessage('min-words', `Minimum ${minWords} words required.`) : null;
            markField(field, false, errorMessage, showError);

            return false;
        }

        wrapper.classList.remove('invalid');
        markField(field, true, null, showError);

        return true;
    }

    if (Array.isArray(value)) {
        const maxItems = getRuleValue('max-items') ?? field.getAttribute('data-rule-max-items');
        const minItems = getRuleValue('min-items') ?? field.getAttribute('data-rule-min-items');

        if (maxItems && maxItems !== '-1' && value.length > parseInt(maxItems, 10)) {
            wrapper.classList.add('invalid');

            const errorMessage = showError ? getErrorMessage('max-items', `Maximum ${maxItems} items allowed.`) : null;
            markField(field, false, errorMessage, showError);

            return false;
        }

        if (minItems && minItems !== '-1' && value.length < parseInt(minItems, 10)) {
            wrapper.classList.add('invalid');

            const errorMessage = showError ? getErrorMessage('min-items', `Minimum ${minItems} items required.`) : null;
            markField(field, false, errorMessage, showError);

            return false;
        }

        wrapper.classList.remove('invalid');
        markField(field, true, null, showError);

        return true;
    }

    if (typeof value === 'number' || !isNaN(parseFloat(value))) {
        const maxValue = getRuleValue('max') ?? field.getAttribute('data-rule-max');
        const minValue = getRuleValue('min') ?? field.getAttribute('data-rule-min');

        if (maxValue && value > parseFloat(maxValue)) {
            wrapper.classList.add('invalid');

            const errorMessage = showError ? getErrorMessage('max', `Maximum value is ${maxValue}.`) : null;
            markField(field, false, errorMessage, showError);

            return false;
        }

        if (minValue && value < parseFloat(minValue)) {
            wrapper.classList.add('invalid');

            const errorMessage = showError ? getErrorMessage('min', `Minimum value is ${minValue}.`) : null;
            markField(field, false, errorMessage, showError);

            return false;
        }

        wrapper.classList.remove('invalid');
        markField(field, true, null, showError);

        return true;
    }

    wrapper.classList.remove('invalid');

    markField(field, true, null, showError);
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
export function markField(fieldIdentifier, isValid, errorMessage = null, showError = false) {
    const field = resolveField(fieldIdentifier);
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
 * Creates a deep copy of the given value, preserving the structure of arrays and objects.
 *
 * @param {*} value - The value to snapshot.
 * @returns {*} - The snapshot of the value.
 */
export function snapshotValue(value) {
    if (Array.isArray(value)) {
        return value.map(snapshotValue);
    }

    if (!value || Object.prototype.toString.call(value) !== '[object Object]') {
        return value;
    }

    const snapshot = {};

    for (const key of Object.keys(value)) {
        snapshot[key] = snapshotValue(value[key]);
    }

    return snapshot;
};

/**
 * Gets contextual information about a field, such as its containing group and repeater, based on the DOM structure.
 * 
 * @param {*} el - The DOM element representing the field.
 * @param {*} context - An optional context object to merge with the field's context.
 * @returns {Object} - The context object containing group and repeater information.
 */
export function getContext(el, context = {}) {
    let groupContext = null;
    let repeaterContext = null;
    
    const repeaterEl = el.closest('.meros-repeater');

    if (repeaterEl) {
        const rowEl = el.closest('.meros-repeater-row');
        const rowIndex = rowEl ? Number.parseInt(rowEl.dataset.repeaterRowIndex || '-1', 10) : null;

        repeaterContext = {
            id: repeaterEl.id,
            name: repeaterEl.dataset.repeaterName || null,
            row: rowIndex,
        };
    }

    const groupEl = el.closest('.meros-field-group, .canvas-field-group');

    if (groupEl) {
        groupContext = {
            id: groupEl.id || groupEl.dataset.groupId || null,
            title: groupEl.dataset.groupTitle || null,
        };
    }

    return {
        ...context,
        group: groupContext,
        repeater: repeaterContext,
    };
}

window.addEventListener('load', () => {
    window.mforms = {
        getFieldComponent,
        getField,
        getFieldValue,
        setFieldValue,
        validateFieldValue,
    }

    window.dispatchEvent(new CustomEvent('meros:forms-ready'));
});
