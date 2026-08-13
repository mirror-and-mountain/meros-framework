import TomSelect from 'tom-select';
import { createMerosField } from './field-data.js';

export const merosTomSelectField = (id, rules = {}) => {
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

            this.__instantiate();
            this.__setContext();

            window.addEventListener('mforms:field-settings-opened', this.onRefresh);
            window.addEventListener('mforms:field-settings-refreshed', this.onRefresh);
            window.addEventListener('mforms:field-settings-closed', this.onRefresh);
            window.addEventListener('mforms:form-canvas-updated', this.onRefresh);
            window.addEventListener('mforms:form-dom-updated', this.onRefresh);
            window.addEventListener('mforms:external-fields-refresh', this.onRefresh);
            window.addEventListener('meros:forms-ready', this.onRefresh);
        },

        getValue() {
            if (!this.element) return null;

            if (this.element.tomselect) {
                const value = this.element.tomselect.getValue();

                if (this.element.hasAttribute('multiple')) {
                    return this.__normaliseMultiValue(value);
                }

                return value;
            }

            if (this.element.hasAttribute('multiple')) {
                return Array.from(this.element.selectedOptions).map(option => option.value);
            }

            return this.element.value;
        },

        setValue(value) {
            if (!this.element || !this.element.tomselect) return;

            const isMultiple = this.element.hasAttribute('multiple');

            const normalisedValue = isMultiple
                ? this.__normaliseMultiValue(value)
                : (value === null || value === undefined ? '' : String(value));

            this.element.tomselect.setValue(normalisedValue, true);
            this.value = normalisedValue;
        },

        destroy() {
            if (this.element && this.element.tomselect) {
                this.element.tomselect.destroy();
            }

            window.removeEventListener('mforms:field-settings-opened', this.onRefresh);
            window.removeEventListener('mforms:field-settings-refreshed', this.onRefresh);
            window.removeEventListener('mforms:field-settings-closed', this.onRefresh);
            window.removeEventListener('mforms:form-canvas-updated', this.onRefresh);
            window.removeEventListener('mforms:form-dom-updated', this.onRefresh);
            window.removeEventListener('mforms:external-fields-refresh', this.onRefresh);
            window.removeEventListener('meros:forms-ready', this.onRefresh);

            fieldContract.destroy.call(this);
            this.element = null;
            this.onRefresh = null;
        },

        __instantiate() {
            if (!this.element) return;
            if (this.element.closest('.meros-repeater-template-row')) return;

            this.isInstantiating = true;

            let reinstantiating = false;
            if (this.element.tomselect) {
                this.element.tomselect.destroy();
                reinstantiating = true;
            }

            // Setup configurations
            const multiple = this.element.hasAttribute('multiple');
            const allowAdd = this.element.dataset.allowAdd === 'true';

            const plugins = multiple ? {
                remove_button: {
                    title: 'Remove',
                }
            } : {};

            const create = allowAdd ? (input) => {
                return {
                    value: input.toLowerCase().replace(/\s+/g, '-'),
                    text: input,
                };
            } : false;

            const sortField = [{ field: '$order' }, { field: '$score' }];
            const maxItems = multiple ? null : 1;

            new TomSelect(this.element, {
                plugins: plugins,
                create: create,
                sortField: sortField,
                maxItems: maxItems,
                onChange: (value) => {
                    this.element.tomselect.blur();

                    const rules = this.__getParsedRules();

                    if (rules['max-items'] && rules['max-items'].value) {
                        const maxItems = parseInt(rules['max-items'].value, 10);
                        if (multiple && Array.isArray(value) && value.length > maxItems) {
                            this.element.tomselect.setValue(value.slice(0, maxItems), true);
                        }
                    }
                    
                    this.value = multiple ? this.__normaliseMultiValue(value) : value;
                    this.applyHints();
                    this.dispatchUpdate({}, ['prevValue']);
                }
            });

            this.value = this.getValue();
            
            if (!reinstantiating) {
                this.previousValue = this.value;
            }

            this.isInstantiating = false;
        },

        __normaliseMultiValue(value) {
            const values = Array.isArray(value)
                ? value
                : (typeof value === 'string' && value !== '' ? value.split(',') : []);

            const normalised = values
                .map((item) => String(item).trim())
                .filter((item) => item !== '');

            return Array.from(new Set(normalised));
        }
    };
};