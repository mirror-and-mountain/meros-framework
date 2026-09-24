const mformSection = () => {
    return {
        id: null,
        name: null,
        ajaxUrl: null,
        ajaxNonce: null,

        init() {
            this.id = this.$el.id || null;
            this.name = this.$el.dataset.name || null;
            this.ajaxUrl = this.$el.dataset.ajaxUrl || null;
            this.ajaxNonce = this.$el.dataset.ajaxNonce || null;

            this.$el.removeAttribute('data-ajax-url');
            // this.$el.removeAttribute('data-ajax-nonce');

            const hasConditions = this.ajaxUrl !== null && this.ajaxNonce !== null;

            if (this.name && hasConditions) {
                this.initInfluencerFields();
            }
        },

        initInfluencerFields() {
            const fields = Array.from(this.$el.querySelectorAll('[data-influencer="group"]'));
            if (fields.length === 0) return;

            fields.forEach(field => {
                field.addEventListener('change', (event) => {
                    const field = event.target;
                    if (!field) return;

                    const name  = this.getFieldName(field);
                    const value = this.getFieldValue(field);
                    
                    const postData = new FormData();
                    postData.append('action', 'meros_handle_field_conditions_' + this.name);
                    postData.append('nonce', this.ajaxNonce);
                    postData.append('influencer', name);
                    postData.append('value', value);

                    fetch(this.ajaxUrl, {
                        method: 'POST',
                        body: postData,
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.data) {
                            const showFields = data.data.showFields || null;
                            const hideFields = data.data.hideFields || null;

                            if (showFields && typeof showFields === 'object') {
                                Object.values(showFields).forEach(fieldName => {
                                    const field = this.getFieldByName(fieldName);
                                    if (!field) return;

                                    field.removeAttribute('data-meros-hidden-field');
                                });
                            }

                            if (hideFields && typeof hideFields === 'object') {
                                Object.values(hideFields).forEach(fieldName => {
                                    const field = this.getFieldByName(fieldName);
                                    if (!field) return;

                                    field.setAttribute('data-meros-hidden-field', "");
                                    const component = this.getFieldComponent(field);

                                    if (component && typeof component.setValue === 'function') {
                                        component.setValue(null);
                                    } else {
                                        field.value = '';
                                    }

                                    if (field.hasAttribute('required')) {
                                        field.removeAttribute('required');
                                    }

                                    if (field.hasAttribute('aria-required')) {
                                        field.removeAttribute('aria-required');
                                    }
                                });
                            }
                        }

                        else if (data.data && data.data.message) {
                            console.error('An error occurred: ' + data.data.message);
                        }

                        else {
                            console.error('An unknown error occurred');
                        }
                    })
                    .catch((error) => {
                        console.log('An unknown error occured: ' + error);
                    })
                });
            });
        },

        getFieldValue(field) {
            const component = this.getFieldComponent(field);

            if (component && typeof component.getValue === 'function') {
                return component.getValue();
            }

            return field.value || null;
        },

        getFieldName(field) {
            return field.dataset.name || field.name || null;
        },

        getFieldByName(name) {
            return this.$el.querySelector(`[data-name="${name}"]`) || this.$el.querySelector(`[name="${name}"]`);
        },

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
    };
};

export default mformSection;