import TomSelect from 'tom-select';

const mformsSelect = () => {
    return {
        name: null,
        multiple: false,
        searchable: false,
        ts: null,
        tsDataItem: null,
        tsDataItemValue: null,
        ajaxUrl: null,
        ajaxNonce: null,
        ajaxAction: null,
        isRepeaterField: false,

        init() {
            if (this.$el.tagName !== 'SELECT') return;

            this.name = this.$el.name || null;
            this.multiple = this.$el.hasAttribute('multiple');
            this.searchable = this.$el.hasAttribute('ts-searchable');
            this.ajaxUrl = this.$el.dataset.ajaxUrl || null;
            this.ajaxNonce = this.$el.dataset.ajaxNonce || null;
            this.ajaxAction = this.$el.dataset.ajaxAction || null;

            this.isRepeaterField = this.$el.hasAttribute('data-repeater-field-name');

            if (!this.isRepeaterField) {
                this.$el.removeAttribute('data-ajax-url');
                this.$el.removeAttribute('data-ajax-nonce');
                this.$el.removeAttribute('data-ajax-action');
            }

            const repeaterTemplateField = 
                this.$el.hasAttribute('data-repeater-field-name') && 
                this.$el.closest('.meros-repeater-table-row--template') !== null;

            if ((this.multiple || this.searchable) && !repeaterTemplateField) {
                this.initTomSelect(this.$el);
            }
        },

        getValue() {
            if (!this.ts) {
                const select = this.resolveElement();
                if (!select) return null;

                if (select.multiple) {
                    const values = Array.from(select.selectedOptions, option => option.value);
                    return values.length > 0 ? values : null;
                }

                return select.value || null;
            }

            return this.ts.getValue();
        },

        setValue(value) {
            if (this.ts) {
                this.ts.setValue(value ?? '', true);
                return;
            }

            const select = this.resolveElement();
            if (!select) return;

            if (select.multiple) {
                const values = new Set((Array.isArray(value) ? value : [value]).map(item => String(item ?? '')));
                Array.from(select.options).forEach(option => {
                    option.selected = values.has(option.value);
                });
            } else {
                select.value = value ?? '';
            }
        },

        setOptions(options) {
            const select = this.resolveElement();
            if (!select) return;

            const currentValue = this.getValue();
            const normalizedOptions = Array.isArray(options) ? options : [];
            const values = new Set(normalizedOptions.map(option => String(option.value)));
            const nextValue = Array.isArray(currentValue)
                ? currentValue.filter(value => values.has(String(value)))
                : values.has(String(currentValue ?? '')) ? currentValue : null;

            if (this.ts) {
                this.ts.clear(true);
                this.ts.clearOptions();
                this.ts.addOptions(normalizedOptions.map(option => ({
                    value: String(option.value),
                    text: String(option.label ?? option.value),
                    disabled: Boolean(option.disabled)
                })));
                this.setValue(nextValue);
                this.ts.refreshOptions(false);
                return;
            }

            select.replaceChildren(...normalizedOptions.map(option => {
                const element = new Option(String(option.label ?? option.value), String(option.value));
                element.disabled = Boolean(option.disabled);
                return element;
            }));
            this.setValue(nextValue);
        },

        destroy() {
            if (this.ts) {
                this.ts.destroy();
            }
        },

        initTomSelect(el) {
            if (this.ts) {
                this.destroy();
            }

            const plugins = this.multiple ? {
                remove_button: {
                    title: 'Remove'
                }
            } : {};

            const sortField = [{ field: '$order' }, { field: '$score' }];
            const maxItems = this.multiple ? null : 1;

            const lookup = !this.isRepeaterField && this.ajaxUrl && this.ajaxNonce && this.ajaxAction 
                ? true
                : this.isRepeaterField && el.hasAttribute('data-ajax-action');

            const load = lookup
                ? (query, callback) => {
                    if (!this.name || !this.ajaxUrl || !this.ajaxNonce || !this.ajaxAction) return;

                    if (query.length < 3) return;

                    const action = this.ajaxAction || el.dataset.ajaxAction || null;
                    const nonce  = this.ajaxNonce || el.dataset.ajaxNonce || null;

                    if (!action || !nonce) return;

                    const params = new URLSearchParams({
                        action: action,
                        nonce: nonce,
                        search: query,
                    });

                    const formId = el.dataset.formId;
                    if (formId) {
                        params.set('form_id', formId);
                    }
                    
                    const url = this.ajaxUrl + '?' + params.toString();

                    fetch(url)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success && data.data && data.data.results) {
                                const results = Array.isArray(data.data.results)
                                    ? data.data.results
                                    : Object.entries(data.data.results).map(([value, text]) => ({
                                        value,
                                        text,
                                    }));

                                callback(results);
                            } 

                            else if (data.data && data.data.message) {
                                console.error('An error occurred when performing the lookup: ', data.data.message);
                                callback();
                            }

                            else {
                                console.error('An error occurred when performing the lookup');
                                callback();
                            }
                        })
                        .catch((error) => {
                            console.error('An error occurred when performing the lookup: ', error);
                            callback();
                        });
                }
                : null

            this.ts = new TomSelect(el, {
                plugins: plugins,
                sortField: sortField,
                maxItems: maxItems,
                valueField: 'value',
                labelField: 'text',
                searchField: ['text'],
                load: load,
                onChange: (value) => {
                    el.tomselect.blur();
                },
                onFocus: () => {
                    if (this.multiple) return;

                    const wrapper = this.$el.parentElement;
                    if (wrapper && wrapper.classList.contains('meros-field-wrapper')) {
                        const input = wrapper.querySelector('.ts-control input');
                        const dataItem = wrapper.querySelector('.ts-control .item');

                        if (input && dataItem) {
                            this.tsDataItem = dataItem;
                            this.tsDataItemValue = dataItem.innerHTML;
                            dataItem.innerHTML = '';

                            setTimeout(() => {
                                input.setAttribute('placeholder', this.tsDataItemValue);
                            }, 10);
                        }
                    }
                },
                onBlur: () => {
                    if (this.tsDataItem) {
                        this.tsDataItem.innerHTML = this.tsDataItemValue;
                        this.tsDataItem = null;
                        this.tsDataItemValue = null;
                    }
                }
            });
        },

        resolveElement() {
            if (this.$el.tagName === 'SELECT') {
                return this.$el;
            }

            const select = this.$el.closest('.meros-field-wrapper')?.querySelector('select');
            return select || null;
        }
    };
};

export default mformsSelect;