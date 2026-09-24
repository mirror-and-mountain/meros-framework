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

        init() {
            if (this.$el.tagName !== 'SELECT') return;

            this.name = this.$el.name || null;
            this.multiple = this.$el.hasAttribute('multiple');
            this.searchable = this.$el.hasAttribute('ts-searchable');
            this.ajaxUrl = this.$el.dataset.ajaxUrl || null;
            this.ajaxNonce = this.$el.dataset.ajaxNonce || null;
            this.ajaxAction = this.$el.dataset.ajaxAction || null;

            this.$el.removeAttribute('data-ajax-url');
            this.$el.removeAttribute('data-ajax-nonce');
            this.$el.removeAttribute('data-ajax-action');

            if (this.searchable && (!this.ajaxUrl || !this.ajaxNonce || !this.ajaxAction)) {
                console.error('Lookup select is missing AJAX configuration.', {
                    name: this.name,
                    ajaxUrl: this.ajaxUrl,
                    ajaxNonce: this.ajaxNonce,
                    ajaxAction: this.ajaxAction,
                });
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
                return this.resolveElement()?.value || null;
            }

            return this.ts.getValue();
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

            const load = this.ajaxUrl && this.ajaxNonce && this.ajaxAction
                ? (query, callback) => {
                    if (!this.name || !this.ajaxUrl || !this.ajaxNonce || !this.ajaxAction) return;

                    if (query.length < 3) return;

                    const params = new URLSearchParams({
                        action: this.ajaxAction,
                        nonce: this.ajaxNonce,
                        search: query,
                    });
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