import { merosInputField, merosPasswordField } from './input.js';
import { merosChoiceField } from './choice.js';
import { merosRichTextField } from './richtext.js';
import { merosRepeaterField } from './repeater.js';
import { merosTomSelectField } from './tomselect.js';

export const createMerosField = (id, rules = {}) => ({
    mforms: window.mforms || null,

    id: id,
    type: null,
    element: null,
    value: null,
    previousValue: null,

    rules: rules,

    context: null,
    inForm: false,
    inGroup: false,
    inRepeater: false,
    inRepeaterTemplateRow: false,
    inRepeaterForm: false,

    init() {
        if (this.id) {
            if (this.id.endsWith('-template')) {
                if (this.$el.closest('.meros-repeater-template-row') === null) {
                    this.id = this.$el.getAttribute('id');
                }
            }

            // Prefer resolving inside this Alpine root first so duplicate IDs
            // (for example row fields + custom dialog fields) bind correctly.
            if (this.$el instanceof HTMLElement) {
                if (this.$el.id === this.id) {
                    this.element = this.$el;
                } else {
                    this.element = this.$el.querySelector(`[id="${this.id}"]`);
                }
            }

            if (!this.element) {
                this.element = document.getElementById(this.id);
            }
        }

        if (this.element) {
            this.type = this.element.dataset.fieldType || (this.element.tagName === 'INPUT' ? this.element.type : null);
            this.value = this.__getValue();
            this.previousValue = this.value;

            this.__setContext();
        }
    },

    destroy() {},

    dispatchUpdate(extra = {}, filter = []) {
        if (this.value === this.previousValue)  return;

        const payload = {
            type: this.hasOwnProperty('type') ? this.type : this.element?.dataset?.fieldType || null,
            id: this.id,
            name: this.element ? this.element.name : this.hasOwnProperty('name') ? this.name : null,
            el: this.element,
            value: this.value,
            prevValue: this.previousValue,
            context: this.context,
            field: this,
            ...extra,
        };

        if (filter.length > 0) {
            for (const key of filter) {
                delete payload[key];
            }
        }

        this.$dispatch('mforms:field-updated', payload);
    },

    getValidationRule(rule, returnValue = 'value') {    
        if (this?.rules === undefined) return null;
        
        const rules = this.__getParsedRules();
        return rules[rule] && rules[rule][returnValue] ? rules[rule][returnValue] : null;
    },

    getErrorMessage(rule) {
        const rules = this.__getParsedRules();
        return rules[rule] && rules[rule].message ? rules[rule].message : null;
    },

    applyHints() {
        if (!this.element) return;
        const fieldWrapper = this.element.closest('.meros-field');
        if (!fieldWrapper) return;

        // Only target hints that belong to this wrapper itself.
        // This avoids repeater-level updates accidentally mutating
        // nested sub-field hints when rows are added/removed.
        const hintsContainer = Array.from(fieldWrapper.children)
            .find((child) => child.classList?.contains('meros-field-hints')) || null;

        const charCountHint = hintsContainer?.querySelector('.char-count-hint') || null;
        const wordCountHint = hintsContainer?.querySelector('.word-count-hint') || null;

        if (charCountHint || wordCountHint) {
            const rules = this.__getParsedRules();
        
            const type     = charCountHint ? 'max-chars' : 'max-words';
            const rawValue = this.__getValue();
            const value    = (rawValue && Object.prototype.toString.call(rawValue) === '[object Object]' && typeof rawValue.htmlValue === 'string')
                ? rawValue.htmlValue
                : rawValue;

            const max = rules && rules[type] ? parseInt(rules[type]?.value, 10) : null;
            let count = 0;

            if (type === 'max-chars') {
                count = value ? value.length : 0;
            } else if (type === 'max-words') {
                count = value ? value.trim().split(/\s+/).length : 0;
            }

            if (type === 'max-chars') {
                charCountHint.textContent = `${count}/${max || '∞'} characters`;
            } else if (type === 'max-words') {
                wordCountHint.textContent = `${count}/${max || '∞'} words`;
            }
        }

        const itemCountHint = hintsContainer?.querySelector('.item-count-hint') || null;

        if (itemCountHint) {
            const rules = this.__getParsedRules();

            const max = rules && rules['max-items'] ? parseInt(rules['max-items']?.value, 10) : null;
            const rawValue = this.getValue();
            const value = (rawValue && Object.prototype.toString.call(rawValue) === '[object Object]' && Array.isArray(rawValue.deltaValue))
                ? rawValue.deltaValue
                : rawValue;

            let count = 0;

            if (Array.isArray(value)) {
                count = value.length;
            }

            itemCountHint.textContent = `${count}/${max || '∞'} items`;
        }
    },

    __getParsedRules() {
        try {
            return JSON.parse(this.rules || '{}');
        } catch (e) {
            return typeof this.rules === 'object' ? this.rules : {};
        }
    },

    __getValue() {
        if (this.hasOwnProperty('getValue') && typeof this.getValue === 'function') {
            return this.getValue();
        }

        if (this.element) {
            return mforms.getFieldValue(this.element);
        }

        return null;
    },

    __setContext() {
        if (!this.element) return;

        // Set context props
        this.context    = mforms.getContext(this.element);
        this.inForm     = this.context?.inForm || false;
        this.inGroup    = this.context?.inGroup || false;
        this.inRepeater = this.context?.inRepeater || false;

        this.inRepeaterTemplateRow = this.context?.repeater?.isInTemplateRow || false;
        this.inRepeaterForm        = this.inRepeater && this.context?.form?.el?.classList.contains('meros-repeater-row-dialog-form') || false;
    }
});

document.addEventListener('alpine:init', () => {
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

            this.hasHints = this.$el.querySelector('.char-count-hint, .word-count-hint, .item-count-hint') !== null;

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

            // Not sure if we need this right now...

            const isValid = mforms.validateFieldValue(this.control);
            this.isValid = typeof isValid === 'boolean' ? isValid : true;
            this.$el.classList.toggle('invalid', !this.isValid);
        },

        getControlCharCount() {
            if (!this.control || !this.hasHints) return 0;

            const value = mforms.getFieldValue(this.control);
            return value ? value.length : 0;
        },

        getControlWordCount() {
            if (!this.control || !this.hasHints) return 0;

            const value = mforms.getFieldValue(this.control);
            return value ? value.trim().split(/\s+/).length : 0;
        },

        getControlItemCount() {
            if (!this.control || !this.hasHints) return 0;

            const value = mforms.getFieldValue(this.control);
            if (Array.isArray(value)) {
                return value.length;
            }
            return 0;
        }
    }));

    Alpine.data('merosInputField', merosInputField);
    Alpine.data('merosPasswordField', merosPasswordField);
    Alpine.data('merosChoiceField', merosChoiceField);
    Alpine.data('merosRichTextField', merosRichTextField);
    Alpine.data('merosRepeaterField', merosRepeaterField);
    Alpine.data('merosTomSelectField', merosTomSelectField);
});