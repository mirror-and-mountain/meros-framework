
/**
 * Processes form fields and their values, returning a normalised payload object.
 * 
 * @class
 * @param {HTMLFormElement} form - The form element to process. May also be a parent element containing fields.
 */
export default class MerosFormProcessor {
    // The form, or parent element containing the fields to process.
    form = null;
    // The processed payload object.
    payload = {};
    // A map of resolved repeater field values, keyed by repeater name.
    resolvedRepeaterFields = new Map();

    constructor(form) {
        this.form = form;
    }

    /**
     * Processes the given form data, returning a normalised payload object.
     * 
     * @param {object} data 
     * @returns 
     */
    process(data) {
        this.payload = { ...data };

        Object.keys(this.payload).forEach((key) => {
            let normalisedKey = key;

            // Remove repeater template row data
            if (normalisedKey.includes('__template')) {
                delete this.payload[normalisedKey];
                return;
            }

            this.processField(key);

            if (this.looksLikeMultiChoiceField(normalisedKey)) {
                normalisedKey = normalisedKey.replace(/\[\]$/, '');
                this.payload[normalisedKey] = this.payload[key];
                delete this.payload[key];
            }
        });

        return this.payload;
    }

    /**
     * Resolves a field using the given name, and processes its value into the payload object.
     * 
     * @param {string} fieldName 
     * @returns 
     */
    processField(fieldName) {
        const field = this.resolveField(fieldName);
        if (!field) return null;

        if (this.looksLikeRepeaterSubField(fieldName)) {
            return this.processRepeaterField(fieldName, field);
        }

        const fieldValue = this.getFieldValue(field);
        this.payload[fieldName] = fieldValue;

        return fieldValue;
    }

    /**
     * Processes a repeater field using the given sub-field name and sub-field element, returning the resolved repeater value.
     * 
     * @param {string} subFieldName 
     * @param {HTMLElement} subField 
     * @returns 
     */
    processRepeaterField(subFieldName, subField) {
        const resolve = () => {
            const repeater = subField.closest('.meros-repeater-field');
            if (!repeater) return null;

            const repeaterName = repeater.getAttribute('data-name');

            if (!this.resolvedRepeaterFields.has(repeaterName)) {
                const repeaterComponent = this.getFieldComponent(repeater);

                if (repeaterComponent && typeof repeaterComponent.getValue === 'function') {
                    const repeaterValue = repeaterComponent.getValue();
                    this.resolvedRepeaterFields.set(repeaterName, repeaterValue);
                    this.payload[repeaterName] = repeaterValue;

                    return repeaterValue;
                }
            } 

            if (this.resolvedRepeaterFields.has(repeaterName)) {
                return this.resolvedRepeaterFields.get(repeaterName);
            }
        };

        const repeaterValue = resolve();
        delete this.payload[subFieldName];

        return repeaterValue;
    }

    /**
     * Retrieves the value of a given field element, using its associated Alpine component if available.
     * 
     * @param {HTMLElement} field 
     * @returns 
     */
    getFieldValue(field) {
        const component = this.getFieldComponent(field, false);

        if (component && typeof component.getValue === 'function') {
            return component.getValue();
        }

        return field.value || null;
    }

    /**
     * Resolves a field element using the given field name.
     * 
     * @param {string} fieldName 
     * @returns 
     */
    resolveField(fieldName) {
        let field = null;

        const getFieldByAttribute = (attribute, name) => {
            return this.form.querySelector(`[${attribute}="${name}"]`);
        }
        
        if (this.looksLikeMultiChoiceField(fieldName)) {
            const baseName = fieldName.replace(/\[\]$/, '');
            field = getFieldByAttribute('data-name', baseName);
        } 
        
        if (field === null) {
            field = getFieldByAttribute('name', fieldName);
        }

        return field;
    }

    /**
     * Retrieves the Alpine component associated with a given field element.
     * Will traverse up the DOM tree to find the nearest parent with an Alpine component.
     * 
     * If allowRepeater is false, it will skip any repeater fields to prevent returning a 
     * repeater component instead of the actual field component.
     * 
     * @param {HTMLElement} field 
     * @param {boolean} allowRepeater 
     * @returns 
     */
    getFieldComponent(field, allowRepeater = true) {
        let current = field;

        while (current) {
            if (
                current.hasAttribute('x-data') &&
                (allowRepeater || !current.classList.contains('meros-repeater-field'))
            ) {
                return Alpine.$data(current) || null;
            }

            current = current.parentElement;
        }

        return null;
    }

    /**
     * Determines if a field name looks like a repeater sub-field.
     * 
     * @param {string} fieldName 
     * @returns 
     */
    looksLikeRepeaterSubField(fieldName) {
        return /^[^\[\]]+\[\d+\]\[[^\[\]]+\]$/.test(fieldName);
    }

    /**
     * Determines if a field name looks like a multi-choice field.
     * 
     * @param {string} fieldName 
     * @returns 
     */
    looksLikeMultiChoiceField(fieldName) {
        return /^[^\[\]]+\[\]$/.test(fieldName);
    }
}