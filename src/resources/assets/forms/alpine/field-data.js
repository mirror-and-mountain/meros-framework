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

        setValue(value, options = {}) {
            if (!this.element) return;

            this.previousValue = snapshotValue(this.getValue());

            setFieldValue(this.element, value, true, options);

            this.value = snapshotValue(this.getValue());

            if (!options?.silent) {
                this.__dispatchUpdate();
            }
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
            
                const type  = charCountHint ? 'max-chars' : 'max-words';
                const rawValue = snapshotValue(this.getValue());
                const value = (rawValue && Object.prototype.toString.call(rawValue) === '[object Object]' && typeof rawValue.htmlValue === 'string')
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
                const rawValue = snapshotValue(this.getValue());
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
                return JSON.parse(this.rules || '{}');
            } catch (e) {
                return typeof this.rules === 'object' ? this.rules : {};
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

            this.hasHints = this.$el.querySelector('.char-count-hint, .word-count-hint, .item-count-hint') !== null;

            this.onRefresh = () => {
                // Not sure if we need this right now...

                // this.$nextTick(() => {
                //     this.__syncValidity();
                // });
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
        },

        getControlItemCount() {
            if (!this.control || !this.hasHints) return 0;

            const value = getFieldValue(this.control);
            if (Array.isArray(value)) {
                return value.length;
            }
            return 0;
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
            onDateTimeClick: null,

            init() {
                fieldContract.init.call(this);

                if (this.element) {
                    this.type = this.element.dataset.fieldType || (this.element.tagName === 'INPUT' ? this.element.type : null);
                    this.previousValue = snapshotValue(this.value);

                    const pickerTypes = ['date', 'time', 'month', 'week', 'datetime-local'];
                    const inputType = (this.element.getAttribute('type') || '').toLowerCase();

                    if (pickerTypes.includes(inputType) && typeof this.element.showPicker === 'function') {
                        this.onDateTimeClick = () => {
                            // Ensure picker opens consistently when decorative icons are clicked.
                            try {
                                this.element.showPicker();
                            } catch (e) {
                                // Ignore unsupported or blocked calls and fall back to native behavior.
                            }
                        };

                        this.element.addEventListener('click', this.onDateTimeClick);
                    }
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

            setValue(value, options = {}) {
                if (!this.element) return;
                this.previousValue = snapshotValue(this.getValue());

                if (this.type === 'checkbox') {
                    if (Array.isArray(value)) {
                        this.element.checked = value.length > 0;
                    } else if (typeof value === 'string') {
                        const normalised = value.trim().toLowerCase();
                        this.element.checked = !(
                            normalised === ''
                            || normalised === '0'
                            || normalised === 'false'
                            || normalised === 'off'
                            || normalised === 'no'
                            || normalised === 'null'
                            || normalised === 'undefined'
                        );
                    } else {
                        this.element.checked = !!value;
                    }
                } else {
                    this.element.value = value;
                }

                this.value = snapshotValue(this.getValue());

                if (!options?.silent) {
                    this.__dispatchUpdate();
                }
            },

            destroy() {
                if (this.element && this.onDateTimeClick) {
                    this.element.removeEventListener('click', this.onDateTimeClick);
                }

                fieldContract.destroy.call(this);
                this.type = null;
                this.onDateTimeClick = null;
            },

            onChange() {
                this.previousValue = snapshotValue(this.value);
                validateFieldValue(this.element);

                this.__dispatchUpdate();
            },

            onInput() {
                this.applyHints();
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
     * Alpine component for radio and checkboxes fieldsets.
     */
    Alpine.data('merosChoiceField', (id, rules = {}) => {
        const fieldContract = createMerosField(id, rules);

        return {
            ...fieldContract,

            init() {
                fieldContract.init.call(this);

                if (this.element) {
                    this.value = snapshotValue(this.getValue());
                    this.previousValue = snapshotValue(this.value);
                }
            },

            getValue() {
                if (!this.element) return null;

                const inputs = Array.from(this.element.querySelectorAll('input[type="checkbox"], input[type="radio"]'));

                if (inputs.length === 0) {
                    return null;
                }

                const hasRadio = inputs.some((input) => input.type === 'radio');

                if (hasRadio) {
                    const checkedRadio = inputs.find((input) => input.checked);
                    return checkedRadio ? checkedRadio.value : null;
                }

                return inputs
                    .filter((input) => input.checked)
                    .map((input) => input.value);
            },

            setValue(value, options = {}) {
                if (!this.element) return;

                this.previousValue = snapshotValue(this.getValue());

                const inputs = Array.from(this.element.querySelectorAll('input[type="checkbox"], input[type="radio"]'));

                if (inputs.length === 0) {
                    return;
                }

                const hasRadio = inputs.some((input) => input.type === 'radio');

                if (hasRadio) {
                    const normalisedValue = value === null || value === undefined ? null : String(value);

                    inputs.forEach((input) => {
                        input.checked = normalisedValue !== null && input.value === normalisedValue;
                    });
                } else {
                    const normalisedValues = Array.isArray(value)
                        ? value.map((item) => String(item))
                        : (value === null || value === undefined || value === '' ? [] : [String(value)]);

                    inputs.forEach((input) => {
                        input.checked = normalisedValues.includes(input.value);
                    });
                }

                this.value = snapshotValue(this.getValue());

                if (!options?.silent) {
                    this.__dispatchUpdate();
                }
            },

            onChange() {
                this.previousValue = snapshotValue(this.value);
                validateFieldValue(this.element);

                this.__dispatchUpdate();
            },

            __dispatchUpdate(context = {}) {
                const newValue = snapshotValue(this.getValue());
                const oldValue = snapshotValue(this.previousValue);
                const firstInput = this.element
                    ? this.element.querySelector('input[type="checkbox"], input[type="radio"]')
                    : null;

                this.$dispatch('mforms:field-updated', {
                    formId: this.element ? this.element.closest('form')?.id || null : null,
                    type: this.element ? this.element.dataset.fieldType || null : null,
                    id: this.id,
                    name: firstInput ? firstInput.name : null,
                    element: this.element,
                    oldValue,
                    value: newValue,
                    context: getContext(this.element, context),
                    field: this,
                });

                this.value = snapshotValue(newValue);
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
                this.previousValue = snapshotValue(this.value)
                this.__instantiate();

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

            __normaliseMultiValue(value) {
                const values = Array.isArray(value)
                    ? value
                    : (typeof value === 'string' && value !== '' ? value.split(',') : []);

                const normalised = values
                    .map((item) => String(item).trim())
                    .filter((item) => item !== '');

                return Array.from(new Set(normalised));
            },

            setValue(value, options = {}) {
                if (!this.element) return;

                this.previousValue = snapshotValue(this.getValue());

                const isMultiple = this.element.hasAttribute('multiple');
                const normalisedValue = isMultiple
                    ? this.__normaliseMultiValue(value)
                    : (value === null || value === undefined ? '' : String(value));

                // During dialog hydration this can be called before TomSelect is instantiated.
                // Seed the native select first, then instantiate/apply so hydration is reliable.
                if (!this.element.tomselect) {
                    if (isMultiple) {
                        const selectedValues = Array.isArray(normalisedValue) ? normalisedValue : [];

                        Array.from(this.element.options).forEach((option) => {
                            option.selected = selectedValues.includes(option.value);
                        });
                    } else {
                        this.element.value = normalisedValue;
                    }

                    this.__instantiate();
                }

                if (this.element.tomselect) {
                    // Use silent=true to suppress onChange so we don't double-dispatch.
                    this.element.tomselect.setValue(normalisedValue, true);
                }

                this.value = snapshotValue(this.getValue());

                if (!options?.silent) {
                    this.__dispatchUpdate();
                }
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

            __getDynamicOptionsConfig() {
                if (!this.element || this.element.dataset.dynamicOptionsEnabled !== 'true') {
                    return null;
                }

                let params = {};

                try {
                    const rawConfig = this.element.dataset.dynamicOptionsConfig || '{}';
                    const parsed = JSON.parse(rawConfig);

                    if (parsed && Object.prototype.toString.call(parsed) === '[object Object]') {
                        params = parsed;
                    }
                } catch (error) {
                    params = {};
                }

                if (params.postType === undefined) {
                    params.postType = this.element.dataset.dynamicOptionsPostType || 'post';
                }

                if (params.postStatus === undefined) {
                    params.postStatus = this.element.dataset.dynamicOptionsPostStatus || 'publish';
                }

                if (params.taxonomy === undefined) {
                    params.taxonomy = this.element.dataset.dynamicOptionsTaxonomy || '';
                }

                if (params.terms === undefined) {
                    params.terms = this.element.dataset.dynamicOptionsTerms || '';
                }

                if (params.userRole === undefined) {
                    params.userRole = this.element.dataset.dynamicOptionsUserRole || '';
                }

                const limit = parseInt(
                    (params.limit ?? this.element.dataset.dynamicOptionsLimit ?? '20'),
                    10
                ) || 20;

                params = this.__resolveDynamicOptionsParams(params);
                params.limit = limit;

                return {
                    endpoint: this.element.dataset.dynamicOptionsEndpoint || '',
                    source: this.element.dataset.dynamicOptionsSource || '',
                    limit,
                    params,
                };
            },

            __resolveDynamicOptionsParams(params) {
                if (!params || Object.prototype.toString.call(params) !== '[object Object]') {
                    return params;
                }

                const context = this.__getDynamicOptionsContext();
                const resolve = (value) => {
                    if (Array.isArray(value)) {
                        return value.map(resolve);
                    }

                    if (value && Object.prototype.toString.call(value) === '[object Object]') {
                        const next = {};

                        Object.entries(value).forEach(([key, nestedValue]) => {
                            next[key] = resolve(nestedValue);
                        });

                        return next;
                    }

                    if (typeof value === 'string') {
                        return value.replace(/\{\{\s*([A-Za-z0-9_\.]+)\s*\}\}/g, (match, path) => {
                            const resolved = this.__resolveContextPath(context, String(path || ''));

                            if (resolved === null || resolved === undefined) {
                                return '';
                            }

                            if (Array.isArray(resolved)) {
                                return resolved.join(',');
                            }

                            return String(resolved);
                        });
                    }

                    return value;
                };

                return resolve(params);
            },

            __getDynamicOptionsContext() {
                const context = {
                    row: {},
                    dialog: {},
                };

                const dialogBody = this.element
                    ? this.element.closest('#meros-repeater-config-dialog-custom-body, .meros-repeater-config-dialog__body')
                    : null;

                if (!dialogBody) {
                    return context;
                }

                const inputs = dialogBody.querySelectorAll('[data-field-type]');

                inputs.forEach((input) => {
                    if (!(input instanceof HTMLElement)) {
                        return;
                    }

                    const fieldName = (input.dataset.fieldName || input.name || '').trim();

                    if (!fieldName) {
                        return;
                    }

                    context.dialog[fieldName] = getFieldValue(input);
                });

                return context;
            },

            __resolveContextPath(context, path) {
                if (!path || !context) {
                    return null;
                }

                const segments = path.split('.').filter(Boolean);

                if (segments.length < 2) {
                    return null;
                }

                const root = segments.shift();

                if (!root || !(root in context)) {
                    return null;
                }

                let current = context[root];

                for (const segment of segments) {
                    if (!current || Object.prototype.toString.call(current) !== '[object Object]') {
                        return null;
                    }

                    if (!(segment in current)) {
                        return null;
                    }

                    current = current[segment];
                }

                return current;
            },

            async __requestDynamicOptions(config, { search = '', selected = [] } = {}) {
                if (!config || !config.endpoint || !config.source) {
                    return [];
                }

                const url = new URL(config.endpoint, window.location.origin);
                url.searchParams.set('source', config.source);
                url.searchParams.set('limit', String(config.limit));

                const params = config.params && Object.prototype.toString.call(config.params) === '[object Object]'
                    ? config.params
                    : {};

                Object.entries(params).forEach(([key, value]) => {
                    if (key === 'source' || key === 'limit' || key === 'search' || key === 'selected') {
                        return;
                    }

                    if (value === null || value === undefined || value === '') {
                        return;
                    }

                    if (Array.isArray(value)) {
                        if (value.length > 0) {
                            url.searchParams.set(key, value.join(','));
                        }

                        return;
                    }

                    url.searchParams.set(key, String(value));
                });

                if (search !== '') {
                    url.searchParams.set('search', search);
                }

                if (selected.length > 0) {
                    url.searchParams.set('selected', selected.join(','));
                }

                const response = await fetch(url.toString(), {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                    },
                });

                if (!response.ok) {
                    throw new Error(`Dynamic options request failed with status ${response.status}`);
                }

                const payload = await response.json();

                return Array.isArray(payload?.options) ? payload.options : [];
            },

            async __hydrateSelectedDynamicOptions(config) {
                if (!config || !this.element || !this.element.tomselect) {
                    return;
                }

                const selectedValue = this.getValue();
                const selected = (Array.isArray(selectedValue) ? selectedValue : [selectedValue])
                    .filter((value) => value !== null && value !== undefined && value !== '')
                    .map((value) => String(value));

                if (selected.length === 0) {
                    return;
                }

                try {
                    const options = await this.__requestDynamicOptions(config, { selected });

                    options.forEach((option) => {
                        if (typeof this.element.tomselect.updateOption === 'function' && this.element.tomselect.options[option.value]) {
                            this.element.tomselect.updateOption(option.value, option);
                        } else {
                            this.element.tomselect.addOption(option);
                        }
                    });

                    this.element.tomselect.setValue(
                        this.element.hasAttribute('multiple') ? selected : selected[0],
                        true
                    );
                    this.element.tomselect.refreshOptions(false);
                } catch (error) {
                    // Ignore dynamic option hydration errors.
                }
            },

            __loadDynamicOptions(config, query, callback) {
                this.__requestDynamicOptions(config, { search: query })
                    .then((options) => {
                        if (this.element?.tomselect && typeof this.element.tomselect.clearOptions === 'function') {
                            const selectedItems = Array.isArray(this.element.tomselect.items)
                                ? [...this.element.tomselect.items]
                                : [];

                            const selectedOptions = selectedItems
                                .map((itemValue) => this.element.tomselect.options[itemValue])
                                .filter((option) => option && option.value !== undefined)
                                .map((option) => ({ ...option }));

                            this.element.tomselect.clearOptions();

                            selectedOptions.forEach((option) => {
                                this.element.tomselect.addOption(option);
                            });

                            const incoming = Array.isArray(options) ? options : [];
                            const merged = [...selectedOptions, ...incoming];
                            const uniqueByValue = [];
                            const seen = new Set();

                            merged.forEach((option) => {
                                const value = option && option.value !== undefined ? String(option.value) : '';

                                if (value === '' || seen.has(value)) {
                                    return;
                                }

                                seen.add(value);
                                uniqueByValue.push(option);
                            });

                            callback(uniqueByValue);
                            return;
                        }

                        callback(options);
                    })
                    .catch((error) => {
                        callback([]);
                    });
            },

            __instantiate() {
                if (!this.element) return;
                if (this.element.closest('.meros-repeater-template-row')) return;

                this.isInstantiating = true;

                const isReinstantiation = !!this.element.tomselect;
                
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
                const dynamicOptions = this.__getDynamicOptionsConfig();

                const tomSelectDynamicOptions = dynamicOptions
                    ? {
                        valueField: 'value',
                        labelField: 'text',
                        searchField: ['text'],
                        score: () => () => 1,
                        preload: true,
                        shouldLoad: () => true,
                        load: (query, callback) => this.__loadDynamicOptions(dynamicOptions, query, callback),
                    }
                    : {};

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
                    ...tomSelectDynamicOptions,
                    onChange: (value) => {
                        this.element.tomselect.blur();

                        const rules = this.__getParsedRules();

                        if (rules['max-items'] && rules['max-items'].value) {
                            const maxItems = parseInt(rules['max-items'].value, 10);
                            if (multiple && Array.isArray(value) && value.length > maxItems) {
                                this.element.tomselect.setValue(value.slice(0, maxItems), true);
                            }
                        }

                        this.applyHints();
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

                if (dynamicOptions) {
                    this.__hydrateSelectedDynamicOptions(dynamicOptions);
                }
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
    Alpine.data('merosRichTextField', (id, rules = {}, onChange = null) => {
        const fieldContract = createMerosField(id, rules);

        return {
            ...fieldContract,
            isInstantiating: false,
            onRefresh: null,
            onChange: typeof onChange === 'function' ? onChange : null,
            quill: null,
            quillHost: null,

            init() {
                this.onRefresh = () => {
                    this.$nextTick(() => {
                        const nextElement = this.__resolveElement();

                        if (!nextElement) {
                            return;
                        }

                        const hostChanged = this.element && this.element !== nextElement;

                        if (hostChanged) {
                            this.__teardownQuill();
                        }

                        this.element = nextElement;
                        this.__instantiate();
                    });
                };

                this.element = this.__resolveElement();
                this.__instantiate();
                this.value = snapshotValue(this.getValue());
                this.previousValue = snapshotValue(this.value);

                window.addEventListener('mforms:field-settings-opened', this.onRefresh);
                window.addEventListener('mforms:field-settings-refreshed', this.onRefresh);
                window.addEventListener('mforms:field-settings-closed', this.onRefresh);
                window.addEventListener('mforms:form-canvas-updated', this.onRefresh);
                window.addEventListener('mforms:form-dom-updated', this.onRefresh);
                window.addEventListener('mforms:external-fields-refresh', this.onRefresh);
                window.addEventListener('meros:forms-ready', this.onRefresh);
            },

            getValue() {
                const quill = this.quill || this.element?.__quill || null;

                if (quill?.root) {
                    return quill.root.innerHTML || '';
                }

                return this.element ? (this.element.innerHTML || '') : '';
            },

            setValue(value, options = {}) {
                this.previousValue = snapshotValue(this.value);
                const htmlValue = typeof value === 'string' ? value : '';

                if (!this.__isInstantiated()) {
                    if (this.element && !this.element.closest('.meros-repeater-template-row')) {
                        this.element.innerHTML = htmlValue;
                        this.__syncHiddenInput(htmlValue);
                        this.__instantiate();
                    }

                    if (!this.__isInstantiated()) {
                        this.value = snapshotValue(htmlValue);
                        this.__syncHiddenInput(htmlValue);
                        return;
                    }
                }

                this.__setEditorHtml(this.element.__quill, htmlValue);
                this.__syncHiddenInput(htmlValue);

                if (!options?.silent) {
                    this.__dispatchUpdate();
                }
            },

            destroy() {
                this.__teardownQuill();

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

            __teardownQuill() {
                const quill = this.quill || this.element?.__quill || null;
                const host = this.quillHost || this.element || null;
                const html = quill?.root?.innerHTML
                    ?? host?.querySelector('.ql-editor')?.innerHTML
                    ?? host?.innerHTML
                    ?? '';

                if (quill && this.onChange) {
                    quill.root.removeEventListener('blur', this.onChange);
                }

                if (host && host.__quill === quill) {
                    host.__quill = null;
                }

                if (host) {
                    this.__cleanupQuillDom(host, html);
                }

                this.quill = null;
                this.quillHost = null;
                this.onChange = null;
                this.isInstantiating = false;
            },

            __instantiate() {
                if (!this.element) return;

                if (this.isInstantiating) {
                    return;
                }

                if (this.__isInstantiated()) {
                    return;
                }

                if (this.element.closest('.meros-repeater-template-row')) return;

                this.isInstantiating = true;

                try {
                    if (this.quill && this.quillHost !== this.element) {
                        this.__teardownQuill();
                    }

                    this.__cleanupQuillDom(this.element, this.element.innerHTML || '');

                    const quill = new Quill(this.element, {
                        readOnly: this.element.getAttribute('aria-disabled') === 'true' || this.element.hasAttribute('disabled'),
                        theme: 'snow',
                        modules: {
                            toolbar: ['bold', 'italic', 'underline', 'link']
                        }
                    });

                    this.element.__quill = quill;
                    this.quill = quill;
                    this.quillHost = this.element;

                    if (typeof this.onChange !== 'function') {
                        this.onChange = () => {
                            this.applyHints();
                            this.__dispatchUpdate();
                        };
                    }

                    quill.root.addEventListener('blur', this.onChange);

                    const isEmpty = (v) => v === null || v === undefined || v === '';

                    this.value = snapshotValue(this.getValue());
                    this.previousValue = snapshotValue(this.value);
                    this.__syncHiddenInput(this.value);
                } finally {
                    this.isInstantiating = false;
                }
            },

            __syncHiddenInput(value = null) {
                if (!this.element || !this.id) {
                    return;
                }

                const hiddenInput = this.element
                    .closest('fieldset')
                    ?.querySelector(`[data-rich-text-input-for="${this.id}"]`);

                if (!hiddenInput) {
                    return;
                }

                const html = typeof value === 'string' ? value : (this.getValue() || '');
                hiddenInput.value = html;
            },

            __cleanupQuillDom(host, fallbackHtml = '') {
                if (!host) {
                    return;
                }

                const hasQuillMarkup = host.classList.contains('ql-container')
                    || host.querySelector('.ql-editor') !== null;

                if (!hasQuillMarkup) {
                    return;
                }

                const editorHtml = host.querySelector('.ql-editor')?.innerHTML
                    ?? fallbackHtml
                    ?? '';

                let prev = host.previousElementSibling;

                while (prev && prev.classList.contains('ql-toolbar')) {
                    const toolbar = prev;
                    prev = toolbar.previousElementSibling;
                    toolbar.remove();
                }

                host.classList.remove('ql-container', 'ql-snow', 'ql-bubble', 'ql-disabled');
                host.innerHTML = editorHtml;
            },

            __isInstantiated() {
                return !!(
                    this.element
                    && this.quill
                    && this.quillHost === this.element
                    && this.element.__quill === this.quill
                );
            },

            __resolveElement() {
                if (this.id) {
                    const byId = document.getElementById(this.id);

                    if (byId) {
                        return byId;
                    }
                }

                if (this.$el && this.$el.isConnected) {
                    return this.$el;
                }

                return null;
            },

            __dispatchUpdate(context = {}) {
                const oldValue = snapshotValue(this.value);
                const value = snapshotValue(this.getValue());
                this.__syncHiddenInput(value);

                this.$dispatch('mforms:field-updated', {
                    formId: this.element ? this.element.closest('form')?.id || null : null,
                    type: this.element ? this.element.dataset.fieldType || 'rich_text' : 'rich_text',
                    id: this.id,
                    name: this.element ? this.element.name : null,
                    element: this.element,
                    oldValue,
                    value,
                    context: getContext(this.element, context),
                    field: this,
                });

                this.previousValue = snapshotValue(oldValue);
                this.value = snapshotValue(value);
            }
        };
    });

    /**
     * Alpine component for repeater fields, handling row addition, removal, and reordering.
     */
    Alpine.data('merosRepeaterField', (id, placeholder, fieldCount, rules = {}) => {
        const fieldContract = createMerosField(id, rules);

        return {
            ...fieldContract,

            placeholder: placeholder || 'Nothing to show.',
            showPlaceholder: true,
            canAddRows: true,
            rowCount: 0,
            fieldCount: fieldCount,

            onRefresh: null,
            
            openDelayMs: 180,
            updateDelayMs: 180,
            rowDialogOpen: false,
            isUsingCustomConfigurationDialog: false,
            customConfigurationDialogHtml: '',
            activeCustomConfigurationDialog: null,
            activeCustomDialogs: [],
            isSwitchingCustomDialog: false,

            isOpeningRowDialog: false,
            isUpdatingRowDialog: false,
            pendingDialogRowIndex: null,
            activeDialogRowIndex: null,
            activeDialogRowEl: null,
            activeDialogFieldMounts: [],
            bodyScrollLockClass: 'meros-repeater-dialog-open',
            onFieldUpdated: null,

            init() {
                this.onRefresh = () => {
                    if (this.rowDialogOpen || this.isOpeningRowDialog || this.isUpdatingRowDialog) {
                        return;
                    }

                    this.$nextTick(() => {
                        this.__initialise();
                    });
                };

                this.__initialise();

                window.addEventListener('mforms:form-canvas-updated', this.onRefresh);

                this.onFieldUpdated = (event) => {
                    this.__handleRowFieldUpdate(event);
                    this.__handleDialogFieldUpdate(event);
                };

                window.addEventListener('mforms:field-updated', this.onFieldUpdated);
            },

            destroy() {
                if (this.onRefresh) {
                    window.removeEventListener('mforms:form-canvas-updated', this.onRefresh);
                }

                if (this.onFieldUpdated) {
                    window.removeEventListener('mforms:field-updated', this.onFieldUpdated);
                }

                this.__finalizeRowDialogClose();
                fieldContract.destroy.call(this);
                this.element = null;
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

                this.value = snapshotValue(this.getValue());
                this.previousValue = snapshotValue(this.value);

                this.__clearRowsWhenNoFields();
                this.__setRowCount();
                this.__setCanAddRows();
                this.__togglePlaceholder();
            },

            addRow(configuring = false) {
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

                this.__reindexRowFields();

                if (window.Alpine && typeof window.Alpine.initTree === 'function') {
                    window.Alpine.initTree(newRow);
                }

                this.__setRowCount();
                this.__setCanAddRows();
                this.__togglePlaceholder();

                validateFieldValue(this.element);
                this.applyHints();

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
                const rowData = this.__readRowData(row, rowIndex) || {};
                this.activeCustomDialogs = Array.isArray(customDialogs) ? customDialogs : [];

                const currentConfig = this.__readRowConfiguration(row);

                const matchedDialog = this.__resolveMatchingCustomDialog(this.activeCustomDialogs, rowData, currentConfig, false);

                if (matchedDialog) {
                    this.isUsingCustomConfigurationDialog = true;
                    this.customConfigurationDialogHtml = matchedDialog.html;
                    this.activeCustomConfigurationDialog = matchedDialog;
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

                            this.__applyCustomDialogHydration(dialogBody, row, rowIndex, currentConfig);
                        });
                    }, this.openDelayMs);

                    return;
                }

                this.isUsingCustomConfigurationDialog = false;
                this.customConfigurationDialogHtml = '';
                this.activeCustomConfigurationDialog = null;

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

            __readRowConfiguration(row) {
                if (!row) {
                    return {};
                }

                const configurationField = row.querySelector('.meros-repeater-data-cell[data-field-name="__configuration"] [data-field-type="hidden"]');

                if (!configurationField) {
                    return {};
                }

                try {
                    const raw = configurationField.value || '{}';
                    const parsed = JSON.parse(raw);
                    const safeParsed = parsed && Object.prototype.toString.call(parsed) === '[object Object]'
                        ? { ...parsed }
                        : {};

                    if (Object.prototype.hasOwnProperty.call(safeParsed, '__configuration')) {
                        delete safeParsed.__configuration;
                    }

                    return safeParsed;
                } catch (e) {
                    return {};
                }
            },

            __writeRowConfiguration(row, configuration = {}) {
                if (!row) {
                    return;
                }

                const configurationField = row.querySelector('.meros-repeater-data-cell[data-field-name="__configuration"] [data-field-type="hidden"]');

                if (!configurationField) {
                    return;
                }

                const safeConfiguration = configuration && Object.prototype.toString.call(configuration) === '[object Object]'
                    ? configuration
                    : {};
                const cleanedConfiguration = { ...safeConfiguration };

                if (Object.prototype.hasOwnProperty.call(cleanedConfiguration, '__configuration')) {
                    delete cleanedConfiguration.__configuration;
                }

                const configurationJson = JSON.stringify(cleanedConfiguration);
                const component = getFieldComponent(configurationField);

                // Silent write: avoid emitting a secondary mforms:field-updated event
                // from the hidden __configuration field on every dialog change.
                configurationField.value = configurationJson;

                // Keep component snapshots in sync when present.
                if (component && Object.prototype.toString.call(component) === '[object Object]') {
                    if ('previousValue' in component) {
                        component.previousValue = snapshotValue(configurationJson);
                    }

                    if ('value' in component) {
                        component.value = snapshotValue(configurationJson);
                    }
                }
            },

            __syncConfigurationFieldValue(row, fieldName, value) {
                if (!row || !fieldName || fieldName === '__configuration') {
                    return;
                }

                let nextValue = value;
                const resolvedFieldName = this.__resolveRowFieldName(row, fieldName);

                if (resolvedFieldName) {
                    const rowCell = row.querySelector(`.meros-repeater-data-cell[data-field-name="${resolvedFieldName}"]`);
                    const rowInput = rowCell
                        ? rowCell.querySelector([
                            'input[type="checkbox"][name]',
                            'input[type="radio"][name]',
                            'select[name]',
                            'textarea[name]',
                            'input[name]:not([type="hidden"]):not([type="checkbox"]):not([type="radio"])',
                        ].join(', ')) || this.__resolveDialogControlElement(rowCell.querySelector('[data-field-type]'))
                        : null;

                    if (rowInput) {
                        nextValue = this.__readControlValue(rowInput);
                    }
                }

                const currentConfig = this.__readRowConfiguration(row);
                const targetFieldName = resolvedFieldName || fieldName;
                currentConfig[targetFieldName] = nextValue;
                this.__writeRowConfiguration(row, currentConfig);
            },

            __resolveMatchingCustomDialog(customDialogs, rowData, dialogData = {}, preferDialog = false) {
                if (!Array.isArray(customDialogs) || customDialogs.length === 0) {
                    return null;
                }

                const safeRowData = rowData && Object.prototype.toString.call(rowData) === '[object Object]'
                    ? rowData
                    : {};
                const safeDialogData = dialogData && Object.prototype.toString.call(dialogData) === '[object Object]'
                    ? dialogData
                    : {};
                const context = preferDialog
                    ? { ...safeRowData, ...safeDialogData }
                    : { ...safeDialogData, ...safeRowData };

                for (const entry of customDialogs) {
                    const html = typeof entry?.html === 'string' ? entry.html : '';

                    if (html.trim() === '') {
                        continue;
                    }

                    const when = entry?.when ?? entry?.rule ?? null;

                    if (this.__matchesDialogWhen(context, when)) {
                        return entry;
                    }
                }

                return null;
            },

            __matchesDialogWhen(context, when) {
                if (Array.isArray(when)) {
                    if (when.length < 3) {
                        return true;
                    }

                    return this.__matchesSingleDialogRule(context, when);
                }

                if (!when || Object.prototype.toString.call(when) !== '[object Object]') {
                    return true;
                }

                const mode = String(when.mode || 'and').toLowerCase() === 'or' ? 'or' : 'and';
                const rules = Array.isArray(when.rules) ? when.rules : [];

                if (rules.length === 0) {
                    return true;
                }

                if (mode === 'or') {
                    return rules.some((rule) => this.__matchesSingleDialogRule(context, rule));
                }

                return rules.every((rule) => this.__matchesSingleDialogRule(context, rule));
            },

            __matchesSingleDialogRule(context, rule) {
                if (!Array.isArray(rule) || rule.length < 3) {
                    return false;
                }

                const [field, operator, value] = rule;
                const fieldValue = context ? context[field] : null;

                switch (operator) {
                    case '=':
                        return fieldValue == value;
                    case '!=':
                        return fieldValue != value;
                    case '>':
                        return fieldValue > value;
                    case '<':
                        return fieldValue < value;
                    case '>=':
                        return fieldValue >= value;
                    case '<=':
                        return fieldValue <= value;
                    case 'contains': {
                        const haystack = Array.isArray(fieldValue) ? fieldValue.map((item) => String(item)) : String(fieldValue ?? '');

                        if (Array.isArray(haystack)) {
                            return haystack.includes(String(value));
                        }

                        return haystack.includes(String(value));
                    }
                    default:
                        return false;
                }
            },

            __resolveFieldName(element) {
                if (!element || !(element instanceof HTMLElement)) {
                    return '';
                }

                const resolveFromElement = (candidate) => {
                    if (!candidate || !(candidate instanceof HTMLElement)) {
                        return '';
                    }

                    const baseFieldName = (candidate.dataset?.baseFieldName || '').trim();

                    if (baseFieldName !== '') {
                        return baseFieldName;
                    }

                    const datasetFieldName = (candidate.dataset?.fieldName || '').trim();

                    if (datasetFieldName !== '') {
                        return datasetFieldName;
                    }

                    const elementName = typeof candidate.name === 'string' ? candidate.name.trim() : '';

                    if (elementName === '') {
                        return '';
                    }

                    const match = elementName.match(/\[([^\[\]]+)\](?:\[\])?$/);
                    if (match && match[1]) {
                        return match[1];
                    }

                    return elementName;
                };

                const ownName = resolveFromElement(element);

                if (ownName !== '') {
                    return ownName;
                }

                const component = getFieldComponent(element);
                const componentElement = component && component.element instanceof HTMLElement
                    ? component.element
                    : null;
                const componentName = resolveFromElement(componentElement);

                if (componentName !== '') {
                    return componentName;
                }

                const descendant = element.querySelector('[data-base-field-name], [data-field-name], [name]');
                const descendantName = resolveFromElement(descendant);

                if (descendantName !== '') {
                    return descendantName;
                }

                return '';
            },

            __resolveFieldNameFromInputName(inputName) {
                const name = typeof inputName === 'string' ? inputName.trim() : '';

                if (name === '') {
                    return '';
                }

                const match = name.match(/\[([^\[\]]+)\](?:\[\])?$/);

                if (match && match[1]) {
                    return match[1];
                }

                return name;
            },

            __isMeaningfulDialogValue(value) {
                return (
                    value !== null
                    && value !== undefined
                    && !(typeof value === 'string' && value.trim() === '')
                );
            },

            __resolveDialogControlElement(element) {
                if (!element || !(element instanceof HTMLElement)) {
                    return null;
                }

                if (element.matches('select, textarea, input:not([type="hidden"])')) {
                    return element;
                }

                if (element.hasAttribute('data-field-type')) {
                    const descendant = element.querySelector('select[name], textarea[name], input[name]:not([type="hidden"])');

                    if (descendant) {
                        return descendant;
                    }

                    return element;
                }

                return element.querySelector('[data-field-type], select, textarea, input:not([type="hidden"])');
            },

            __getDialogControls(dialogBody) {
                if (!dialogBody) {
                    return [];
                }

                const controls = Array.from(dialogBody.querySelectorAll([
                    '[data-field-type]',
                    'select[name]',
                    'textarea[name]',
                    'input[type="checkbox"][name]',
                    'input[type="radio"][name]',
                    'input[name]:not([type="hidden"]):not([type="checkbox"]):not([type="radio"])',
                ].join(', '))).filter((el) => el instanceof HTMLElement);

                const resolvedControls = controls
                    .map((el) => this.__resolveDialogControlElement(el))
                    .filter((el) => el instanceof HTMLElement && el.matches('select, textarea, input:not([type="hidden"]), [data-field-type]'));

                return Array.from(new Set(resolvedControls));
            },

            __setControlValueSilently(control, value) {
                if (!control || !(control instanceof HTMLElement)) {
                    return;
                }

                if (control.tagName === 'SELECT') {
                    control.value = value === null || value === undefined ? '' : String(value);
                    return;
                }

                if (control.tagName === 'INPUT') {
                    if (control.type === 'checkbox') {
                        control.checked = this.__coerceCheckboxValue(value);
                        return;
                    }

                    if (control.type === 'radio') {
                        const radioValue = value === null || value === undefined ? '' : String(value);
                        const root = control.closest('[x-data]') || control.parentElement || document;
                        const radios = root.querySelectorAll(`input[type="radio"][name="${control.name}"]`);

                        radios.forEach((radio) => {
                            radio.checked = radio.value === radioValue;
                        });

                        return;
                    }

                    control.value = value === null || value === undefined ? '' : value;
                    return;
                }

                setFieldValue(control, value, true, { silent: true });
            },

            __readControlValue(control) {
                if (!control || !(control instanceof HTMLElement)) {
                    return null;
                }

                if (control.tagName === 'SELECT') {
                    if (control.multiple) {
                        return Array.from(control.selectedOptions).map((option) => option.value);
                    }

                    return control.value;
                }

                if (control.tagName === 'TEXTAREA') {
                    return control.value;
                }

                if (control.tagName === 'INPUT') {
                    if (control.type === 'checkbox') {
                        return control.checked;
                    }

                    if (control.type === 'radio') {
                        const root = control.closest('.meros-repeater-data-cell, #meros-repeater-config-dialog-custom-body, .meros-repeater-config-dialog') || document;
                        const checkedRadio = root.querySelector(`input[type="radio"][name="${control.name}"]:checked`)
                            || document.querySelector(`input[type="radio"][name="${control.name}"]:checked`);
                        return checkedRadio ? checkedRadio.value : null;
                    }

                    return control.value;
                }

                if (control.hasAttribute('data-field-type')) {
                    const descendant = this.__resolveDialogControlElement(control);

                    if (descendant && descendant !== control) {
                        return this.__readControlValue(descendant);
                    }
                }

                return control.value ?? null;
            },

            __coerceCheckboxValue(value) {
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
            },

            __readRowFieldValue(row, rowIndex, fieldName, sourceElement = null) {
                if (!row || !fieldName) {
                    return {
                        found: false,
                        value: undefined,
                    };
                }

                const resolvedFieldName = this.__resolveRowFieldName(row, fieldName, sourceElement);

                if (!resolvedFieldName) {
                    return {
                        found: false,
                        value: undefined,
                    };
                }

                const cell = row.querySelector(`.meros-repeater-data-cell[data-field-name="${resolvedFieldName}"]`);

                if (!cell) {
                    return {
                        found: false,
                        value: undefined,
                    };
                }

                return {
                    found: true,
                    value: this.__readCellData(row, cell, rowIndex, resolvedFieldName),
                };
            },

            __normaliseFieldKey(value) {
                return String(value || '')
                    .trim()
                    .toLowerCase()
                    .replace(/\[\]/g, '')
                    .replace(/[^a-z0-9]/g, '');
            },

            __extractFieldNameCandidates(fieldName, sourceElement = null) {
                const candidates = new Set();
                const add = (value) => {
                    const next = String(value || '').trim();

                    if (next !== '') {
                        candidates.add(next);
                    }
                };

                add(fieldName);

                if (sourceElement && sourceElement instanceof HTMLElement) {
                    add(sourceElement.dataset?.baseFieldName);
                    add(sourceElement.dataset?.fieldName);
                    add(sourceElement.getAttribute('name'));
                    add(sourceElement.id);
                }

                Array.from(candidates).forEach((candidate) => {
                    add(candidate.replace(/\[\]$/, ''));

                    const bracketMatches = candidate.match(/\[([^\[\]]+)\](?:\[\])?$/);
                    if (bracketMatches && bracketMatches[1]) {
                        add(bracketMatches[1]);
                    }

                    const dotParts = candidate.split('.').filter(Boolean);
                    if (dotParts.length > 1) {
                        add(dotParts[dotParts.length - 1]);
                    }

                    const underscoreParts = candidate.split('_').filter(Boolean);
                    if (underscoreParts.length > 1) {
                        add(underscoreParts[underscoreParts.length - 1]);
                    }
                });

                return Array.from(candidates);
            },

            __resolveRowFieldName(row, fieldName, sourceElement = null) {
                if (!row) {
                    return '';
                }

                const rowFieldNames = Array.from(
                    row.querySelectorAll('.meros-repeater-data-cell[data-field-name]')
                )
                    .map((cell) => String(cell.dataset.fieldName || '').trim())
                    .filter((name) => name !== '');

                if (rowFieldNames.length === 0) {
                    return '';
                }

                const candidates = this.__extractFieldNameCandidates(fieldName, sourceElement);

                for (const candidate of candidates) {
                    if (rowFieldNames.includes(candidate)) {
                        return candidate;
                    }
                }

                const normalisedMap = rowFieldNames.reduce((map, name) => {
                    const key = this.__normaliseFieldKey(name);

                    if (!map[key]) {
                        map[key] = [];
                    }

                    map[key].push(name);
                    return map;
                }, {});

                for (const candidate of candidates) {
                    const normalisedCandidate = this.__normaliseFieldKey(candidate);
                    const matches = normalisedMap[normalisedCandidate] || [];

                    if (matches.length === 1) {
                        return matches[0];
                    }
                }

                return '';
            },

            __applyCustomDialogHydration(dialogBody, row, rowIndex, config = {}, preferred = {}) {
                const safeConfig = config && Object.prototype.toString.call(config) === '[object Object]'
                    ? config
                    : {};
                const safePreferred = preferred && Object.prototype.toString.call(preferred) === '[object Object]'
                    ? preferred
                    : {};
                const nextConfig = { ...safeConfig };

                const fields = this.__getDialogControls(dialogBody);

                fields.forEach((field) => {
                    const control = this.__resolveDialogControlElement(field);
                    const name = this.__resolveFieldName(control || field);

                    if (!name) {
                        return;
                    }

                    if (Object.prototype.hasOwnProperty.call(safePreferred, name)) {
                        const value = safePreferred[name];
                        this.__setControlValueSilently(control || field, value);
                        nextConfig[name] = value;
                        return;
                    }

                    if (Object.prototype.hasOwnProperty.call(nextConfig, name)) {
                        this.__setControlValueSilently(control || field, nextConfig[name]);
                        return;
                    }

                    const rowValueResult = this.__readRowFieldValue(row, rowIndex, name, control || field);

                    if (rowValueResult.found && this.__isMeaningfulDialogValue(rowValueResult.value)) {
                        this.__setControlValueSilently(control || field, rowValueResult.value);
                        nextConfig[name] = rowValueResult.value;
                        return;
                    }

                    if (Object.prototype.hasOwnProperty.call(safeConfig, name)) {
                        this.__setControlValueSilently(control || field, safeConfig[name]);
                    }
                });

                this.__writeRowConfiguration(row, nextConfig);
            },

            __writeRowFieldValue(row, rowIndex, fieldName, value, sourceElement = null) {
                if (!row || !fieldName || fieldName === '__configuration') {
                    return;
                }

                const resolvedFieldName = this.__resolveRowFieldName(row, fieldName, sourceElement);

                if (!resolvedFieldName) {
                    return;
                }

                const cell = row.querySelector(`.meros-repeater-data-cell[data-field-name="${resolvedFieldName}"]`);

                if (!cell) {
                    return;
                }

                const input = cell.querySelector('[data-field-type]');

                if (!input) {
                    return;
                }

                // Keep row data in sync with dialog edits without emitting another
                // field-updated event that can re-enter dialog switching logic.
                if (input.tagName === 'SELECT') {
                    input.value = value === null || value === undefined ? '' : String(value);
                    return;
                }

                if (input.tagName === 'INPUT') {
                    if (input.type === 'checkbox') {
                        input.checked = this.__coerceCheckboxValue(value);
                        return;
                    }

                    if (input.type === 'radio') {
                        const radioValue = value === null || value === undefined ? '' : String(value);
                        const radios = cell.querySelectorAll('input[type="radio"]');

                        radios.forEach((radio) => {
                            radio.checked = radio.value === radioValue;
                        });

                        return;
                    }

                    input.value = value === null || value === undefined ? '' : value;
                    return;
                }

                // Fallback for complex controls that need component-aware assignment.
                setFieldValue(input, value, true, { silent: true });
            },

            __normaliseDialogRuntime(dialogEntry) {
                const runtime = dialogEntry && Object.prototype.toString.call(dialogEntry.runtime) === '[object Object]'
                    ? dialogEntry.runtime
                    : {};

                const renderMode = String(runtime.renderMode || 'static').toLowerCase() === 'dynamic'
                    ? 'dynamic'
                    : 'static';

                const rerenderOn = runtime && Object.prototype.toString.call(runtime.rerenderOn) === '[object Object]'
                    ? runtime.rerenderOn
                    : {};

                const normaliseNames = (value) => {
                    if (!Array.isArray(value)) {
                        return [];
                    }

                    return Array.from(new Set(value
                        .map((name) => String(name).trim())
                        .filter((name) => name !== '')));
                };

                return {
                    renderMode,
                    rerenderOn: {
                        row: normaliseNames(rerenderOn.row),
                        dialog: normaliseNames(rerenderOn.dialog),
                        debounceMs: Math.max(0, Number.parseInt(String(rerenderOn.debounceMs ?? 120), 10) || 120),
                    },
                };
            },

            __collectCustomDialogValues() {
                const dialogBody = document.getElementById('meros-repeater-config-dialog-custom-body');

                if (!dialogBody) {
                    return {};
                }

                const values = {};
                const inputs = this.__getDialogControls(dialogBody);

                inputs.forEach((input) => {
                    const control = this.__resolveDialogControlElement(input);
                    const fieldName = this.__resolveFieldName(control || input);

                    if (!fieldName) {
                        return;
                    }

                    values[fieldName] = this.__readControlValue(control || input);
                });

                return values;
            },

            __applyPreferredDialogValues(preferredValues = {}) {
                const dialogBody = document.getElementById('meros-repeater-config-dialog-custom-body');

                if (!dialogBody) {
                    return;
                }

                const safePreferred = preferredValues && Object.prototype.toString.call(preferredValues) === '[object Object]'
                    ? preferredValues
                    : {};
                const controls = this.__getDialogControls(dialogBody);

                controls.forEach((controlEl) => {
                    const control = this.__resolveDialogControlElement(controlEl);
                    const fieldName = this.__resolveFieldName(control || controlEl);

                    if (!fieldName || !Object.prototype.hasOwnProperty.call(safePreferred, fieldName)) {
                        return;
                    }

                    this.__setControlValueSilently(control || controlEl, safePreferred[fieldName]);
                });
            },

            __switchActiveDialogIfNeeded(preferredValues = {}) {
                if (!this.rowDialogOpen || !this.activeDialogRowEl || this.activeDialogRowIndex === null || this.activeDialogRowIndex === undefined) {
                    return;
                }

                const safePreferred = preferredValues && Object.prototype.toString.call(preferredValues) === '[object Object]'
                    ? preferredValues
                    : {};
                const rowData = this.__readRowData(this.activeDialogRowEl, this.activeDialogRowIndex) || {};
                const nextDialog = this.__resolveMatchingCustomDialog(this.activeCustomDialogs, rowData, safePreferred, true);

                if (!nextDialog) {
                    if (!this.isUsingCustomConfigurationDialog) {
                        return;
                    }

                    this.isSwitchingCustomDialog = true;
                    this.__saveCustomConfigurationDialogValues();
                    this.isUsingCustomConfigurationDialog = false;
                    this.customConfigurationDialogHtml = '';
                    this.activeCustomConfigurationDialog = null;

                    this.$nextTick(() => {
                        this.__mountRowDialogFields();
                        this.isSwitchingCustomDialog = false;
                    });

                    return;
                }

                const nextHtml = typeof nextDialog.html === 'string' ? nextDialog.html : '';

                if (nextHtml.trim() === '') {
                    return;
                }

                const isAlreadySameCustomDialog = this.isUsingCustomConfigurationDialog
                    && this.activeCustomConfigurationDialog === nextDialog
                    && this.customConfigurationDialogHtml === nextHtml;

                if (isAlreadySameCustomDialog) {
                    return;
                }

                this.isSwitchingCustomDialog = true;

                if (!this.isUsingCustomConfigurationDialog) {
                    this.__restoreRowDialogFields();
                    this.isUsingCustomConfigurationDialog = true;
                }

                this.customConfigurationDialogHtml = nextHtml;
                this.activeCustomConfigurationDialog = nextDialog;

                this.$nextTick(() => {
                    const dialogBody = document.getElementById('meros-repeater-config-dialog-custom-body');

                    if (dialogBody && window.Alpine && typeof window.Alpine.initTree === 'function') {
                        window.Alpine.initTree(dialogBody);
                    }

                    this.__applyCustomDialogHydration(
                        dialogBody,
                        this.activeDialogRowEl,
                        this.activeDialogRowIndex,
                        this.__readRowConfiguration(this.activeDialogRowEl),
                        safePreferred
                    );

                    this.isSwitchingCustomDialog = false;
                });
            },

            __handleRowFieldUpdate(event) {
                const updatedElement = event?.detail?.element;

                if (!updatedElement || !(updatedElement instanceof HTMLElement)) {
                    return;
                }

                const rowEl = updatedElement.closest('tr.meros-repeater-row');

                if (!rowEl || !this.element || !this.element.contains(rowEl)) {
                    return;
                }

                const detailName = typeof event?.detail?.name === 'string'
                    ? event.detail.name
                    : '';
                const updatedFieldName = this.__resolveFieldNameFromInputName(detailName)
                    || this.__resolveFieldName(updatedElement);

                if (!updatedFieldName || updatedFieldName === '__configuration') {
                    return;
                }

                const detailValue = event?.detail?.value;
                const updatedValue = detailValue !== undefined
                    ? detailValue
                    : this.__readControlValue(updatedElement);

                this.__syncConfigurationFieldValue(rowEl, updatedFieldName, updatedValue);
            },

            __handleDialogFieldUpdate(event) {
                if (
                    !this.rowDialogOpen
                    || !this.activeDialogRowEl
                    || this.isOpeningRowDialog
                    || this.isUpdatingRowDialog
                    || this.isSwitchingCustomDialog
                ) {
                    return;
                }

                const updatedElement = event?.detail?.element;

                if (!updatedElement || !(updatedElement instanceof HTMLElement)) {
                    return;
                }

                const rowEl = updatedElement.closest('tr.meros-repeater-row');
                const isRowField = rowEl && rowEl === this.activeDialogRowEl;
                const dialogBody = document.getElementById('meros-repeater-config-dialog-custom-body');
                const isDialogField = dialogBody ? dialogBody.contains(updatedElement) : false;
                const defaultDialogBody = this.$refs?.rowConfigDialogBody || null;
                const isDefaultDialogField = defaultDialogBody ? defaultDialogBody.contains(updatedElement) : false;

                if (!isRowField && !isDialogField && !isDefaultDialogField) {
                    return;
                }

                const detailName = typeof event?.detail?.name === 'string'
                    ? event.detail.name
                    : '';
                const updatedFieldName = this.__resolveFieldNameFromInputName(detailName)
                    || this.__resolveFieldName(updatedElement);

                if (updatedFieldName === '') {
                    return;
                }

                // Internal hidden state payload updates should never trigger dynamic rerenders.
                if (updatedFieldName === '__configuration') {
                    return;
                }

                let shouldRerenderFromRow = false;
                let shouldRerenderFromDialog = false;
                const detailValue = event?.detail?.value;
                const updatedValue = detailValue !== undefined
                    ? detailValue
                    : this.__readControlValue(updatedElement);

                if (this.isUsingCustomConfigurationDialog) {
                    const runtime = this.__normaliseDialogRuntime(this.activeCustomConfigurationDialog);

                    if (runtime.renderMode === 'dynamic') {
                        shouldRerenderFromRow = isRowField && runtime.rerenderOn.row.includes(updatedFieldName);
                        shouldRerenderFromDialog = isDialogField && runtime.rerenderOn.dialog.includes(updatedFieldName);
                    }
                } else {
                    const dynamicDialogs = Array.isArray(this.activeCustomDialogs)
                        ? this.activeCustomDialogs.filter((entry) => this.__normaliseDialogRuntime(entry).renderMode === 'dynamic')
                        : [];

                    shouldRerenderFromRow = isRowField && dynamicDialogs.some((entry) => {
                        const runtime = this.__normaliseDialogRuntime(entry);
                        return runtime.rerenderOn.row.includes(updatedFieldName);
                    });

                    shouldRerenderFromDialog = (isDialogField || isDefaultDialogField) && dynamicDialogs.some((entry) => {
                        const runtime = this.__normaliseDialogRuntime(entry);
                        return runtime.rerenderOn.dialog.includes(updatedFieldName);
                    });
                }

                if (!shouldRerenderFromRow && !shouldRerenderFromDialog) {
                    if (isDialogField || isDefaultDialogField || isRowField) {
                        this.__syncConfigurationFieldValue(this.activeDialogRowEl, updatedFieldName, updatedValue);
                    }

                    if (isDialogField) {
                        const preferredValues = this.__collectCustomDialogValues();
                        preferredValues[updatedFieldName] = updatedValue;

                        window.requestAnimationFrame(() => {
                            this.__applyPreferredDialogValues(preferredValues);
                        });
                    }

                    return;
                }

                if (isDialogField) {
                    this.__writeRowFieldValue(this.activeDialogRowEl, this.activeDialogRowIndex, updatedFieldName, updatedValue, updatedElement);
                }

                if (isDialogField || isDefaultDialogField || isRowField) {
                    this.__syncConfigurationFieldValue(this.activeDialogRowEl, updatedFieldName, updatedValue);
                }

                const preferredValues = this.isUsingCustomConfigurationDialog
                    ? this.__collectCustomDialogValues()
                    : {};

                if (isDialogField || isDefaultDialogField) {
                    // Preserve the most recent changed value even if the collector
                    // still sees stale DOM during the same change tick.
                    preferredValues[updatedFieldName] = updatedValue;
                }

                this.__switchActiveDialogIfNeeded(preferredValues);
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

            removeRow(event = null, rowIndex = null) {
                if (event === null && rowIndex === null) return;

                let row = null;
                if (event) {
                    const trigger = event.currentTarget || event.target;
                    row = trigger ? trigger.closest('tr.meros-repeater-row') : null;
                } 
                
                else if (rowIndex !== null) {
                    row = this.element.querySelector(`.meros-repeater-row[data-repeater-row-index="${rowIndex}"]`);
                }

                if (!row) return;

                const index = rowIndex !== null 
                    ? rowIndex 
                    : Number.parseInt(row.dataset.repeaterRowIndex || '-1', 10);

                if (this.activeDialogRowEl === row) {
                    this.closeRowDialog();
                }

                row.remove();

                this.__reindexRowFields();
                this.__setRowCount();
                this.__setCanAddRows();
                this.__togglePlaceholder();

                try {
                    validateFieldValue(this.element);
                    this.applyHints();
                } finally {
                    this.__dispatchUpdate({
                        action: 'remove',
                        oldIndex: index,
                    });
                }
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
                let rowCount = 0;
                
                if (this.element) {
                    rowCount = this.element.querySelectorAll('.meros-repeater-row:not(.meros-repeater-template-row)')?.length || 0;
                } else {
                    rowCount = this.rowCount || 0;
                }

                if (rowCount > 0) {
                    this.showPlaceholder = false;
                } else {
                    this.showPlaceholder = true;
                }
            },

            __setRowCount() {
                if (!this.element) {
                    this.rowCount = 0;
                    return;
                }

                this.rowCount = this.element.querySelectorAll('.meros-repeater-row:not(.meros-repeater-template-row)')?.length || 0;
            },

            __clearRowsWhenNoFields() {
                if (!this.element || this.fieldCount > 0) {
                    return;
                }

                const rows = this.element.querySelectorAll('.meros-repeater-row:not(.meros-repeater-template-row)');

                rows.forEach((row) => {
                    row.remove();
                });
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
                const component = getFieldComponent(input);

                if (
                    component
                    && component !== this
                    && !(component.element && component.element === this.element)
                    && typeof component.getValue === 'function'
                ) {
                    const componentValue = component.getValue();

                    if (componentValue !== undefined) {
                        return componentValue;
                    }
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

                const detail = {
                    formId: this.element ? this.element.closest('form')?.id || null : null,
                    type: 'repeater',
                    id: this.id,
                    name: this.element ? this.element.dataset.repeaterName : null,
                    element: this.element,
                    oldValue,
                    value,
                    context: getContext(this.element, context),
                    field: this,
                };

                const dispatchTarget = this.element || this.$el || null;
                const updateEvent = new CustomEvent('mforms:field-updated', {
                    detail,
                    bubbles: true,
                    composed: true,
                });

                if (dispatchTarget) {
                    dispatchTarget.dispatchEvent(updateEvent);

                    // If the source node is detached (e.g. during row removal),
                    // bubbling cannot reach global listeners on window.
                    if (!dispatchTarget.isConnected) {
                        window.dispatchEvent(new CustomEvent('mforms:field-updated', {
                            detail,
                            bubbles: false,
                            composed: true,
                        }));
                    }
                } else {
                    window.dispatchEvent(new CustomEvent('mforms:field-updated', {
                        detail,
                        bubbles: false,
                        composed: true,
                    }));
                }

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
                this.activeCustomConfigurationDialog = null;
                this.activeCustomDialogs = [];
                this.isSwitchingCustomDialog = false;
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
                const rows = this.element.querySelectorAll('.meros-repeater-row:not(.meros-repeater-template-row)');

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

            __mountRowDialogFields() {
                if (!this.activeDialogRowEl || !this.$refs?.rowConfigDialogBody) {
                    return;
                }

                const dialogBody = this.$refs.rowConfigDialogBody;
                const placeholders = dialogBody.querySelectorAll('.meros-repeater-config-dialog__field-input[data-field-name]');

                // If a prior mount did not fully restore (for example after a refresh),
                // restore first so we never strand fields outside their source row cells.
                if (this.activeDialogFieldMounts.length > 0) {
                    this.__restoreRowDialogFields();
                }

                this.activeDialogFieldMounts = [];

                placeholders.forEach((placeholder) => {
                    const fieldName = placeholder.dataset.fieldName;
                    const sourceCell = this.activeDialogRowEl.querySelector(`.meros-repeater-data-cell[data-field-name="${fieldName}"]`);

                    placeholder.innerHTML = '';

                    if (!sourceCell) {
                        return;
                    }

                    const sourceControl = sourceCell.querySelector('[data-field-type]');
                    const sourceField = sourceControl?.closest('.meros-field')
                        || sourceCell.querySelector(':scope > .meros-field')
                        || sourceControl;

                    if (!sourceField) {
                        return;
                    }

                    const anchor = document.createElement('div');
                    anchor.hidden = true;
                    anchor.dataset.repeaterDialogAnchor = fieldName;

                    const sourceParent = sourceField.parentNode;

                    if (!sourceParent || !sourceParent.contains(sourceField)) {
                        return;
                    }

                    sourceParent.insertBefore(anchor, sourceField);
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
                    } else {
                        const fieldName = placeholder?.dataset?.fieldName;
                        const sourceCell = fieldName && this.activeDialogRowEl
                            ? this.activeDialogRowEl.querySelector(`.meros-repeater-data-cell[data-field-name="${fieldName}"]`)
                            : null;

                        if (sourceCell && !sourceCell.contains(fieldEl)) {
                            sourceCell.appendChild(fieldEl);
                        }
                    }

                    if (placeholder && placeholder.contains(fieldEl)) {
                        placeholder.removeChild(fieldEl);
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

                const configuration = this.__readRowConfiguration(this.activeDialogRowEl);
                const inputs = this.__getDialogControls(dialogBody);

                inputs.forEach((input) => {
                    const control = this.__resolveDialogControlElement(input);
                    const fieldName = this.__resolveFieldName(control || input);

                    if (!fieldName || fieldName === '__configuration') return;

                    const value = this.__readControlValue(control || input);
                    configuration[fieldName] = value;

                    this.__writeRowFieldValue(this.activeDialogRowEl, this.activeDialogRowIndex, fieldName, value, control || input);
                });
                this.__writeRowConfiguration(this.activeDialogRowEl, configuration);
            }
        };
    });
});