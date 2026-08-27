import TomSelect from 'tom-select';

const mformsSelect = () => {
    return {
        multiple: false,
        searchable: false,
        ts: null,
        tsDataItem: null,
        tsDataItemValue: null,

        init() {
            if (this.$el.tagName !== 'SELECT') return;

            this.multiple = this.$el.hasAttribute('multiple');
            this.searchable = this.$el.hasAttribute('ts-searchable');

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

            this.ts = new TomSelect(el, {
                plugins: plugins,
                sortField: sortField,
                maxItems: maxItems,
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