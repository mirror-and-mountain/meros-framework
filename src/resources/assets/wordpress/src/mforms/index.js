import mformsSelect from './fields/select.js';
import mformsRepeater from './fields/repeater.js';
import mformsChoice from './fields/choice.js';
import MerosFormProcessor from './form-processor.js';

import './style.scss';

const mform = () => {
    return {
        id: null,
        name: null,
        ajaxUrl: null,
        ajaxNonce: null,
        onSubmit: null,
        invalidText: null,

        // =========================================================================
        // Initialisation
        // =========================================================================

        init() {
            this.id = this.$el.id || null;
            this.name = this.$el.dataset.name || null;
            this.ajaxUrl = this.$el.dataset.ajaxUrl || null;
            this.ajaxNonce = this.$el.dataset.ajaxNonce || null;
            this.onSubmit = this.$el.dataset.onsubmit || null;
            this.invalidText = this.$el.dataset.invalidText || null;

            this.$el.removeAttribute('data-ajax-url');
            this.$el.removeAttribute('data-ajax-nonce');
            this.$el.removeAttribute('data-invalid-text');

            if (this.onSubmit && typeof this.onSubmit === 'string') {
                this.$el.removeAttribute('data-onsubmit');
            }
        },

        // =========================================================================
        // Operations
        // =========================================================================

        submitForm() {
            const genericError = 'An error occured while submitting the form.'
            const invalidError = this.invalidText || "The form is invalid. Please check the information you've entered."

            if (!this.name) {
                return {success: false, error: genericError};
            }

            const form = this.$el.tagName === 'FORM' ? this.$el : this.$el.closest('form');
            if (!form) {
                return {success: false, error: genericError};
            }

            const valid = form.checkValidity();

            if (valid === false) {
                form.reportValidity();
                return {success: false, invalid: true, error: invalidError};
            }

            const formData = this.processFormData(new FormData(form));

            if (this.onSubmit && typeof this.onSubmit === 'string') {
                this.$dispatch('mforms::' + this.onSubmit, { 
                    formId: this.id,
                    formName: this.name,
                    formData,
                });

                return {success: true, error: null};
            }

            if (!this.ajaxUrl || !this.ajaxNonce) {
                return {success: false, error: genericError};
            }

            const postData = new FormData();
            postData.append('action', 'meros_handle_form_submission_' + this.name);
            postData.append('nonce', this.ajaxNonce);

            postData.append('form_data', JSON.stringify(formData));

            fetch(this.ajaxUrl, {
                method: 'POST',
                body: postData,
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    return {success: true, error: null};
                }

                else if (data.data && data.data.message) {
                    return {success: false, error: data.data.message};
                }

                else {
                    return {success: false, error: genericError};
                }
            })
            .catch((error) => {
                return {success: false, error: error};
            });

            return {success: true, error: null};
        },

        // =========================================================================
        // Helpers
        // =========================================================================

        processFormData(formData) {
            const data = Object.fromEntries(formData.entries());
            const form = this.resolveForm();
            if (!form) return data;

            const fieldProcessor = new MerosFormProcessor(form);
            const processedData = fieldProcessor.process(data);

            return processedData;
        },

        resolveForm() {
            const form = this.$el.tagName === 'FORM' ? this.$el : this.$el.closest('form');
            return form || null;
        }
    }
}

document.addEventListener('alpine:init', () => {
    Alpine.data('mform', mform);
    Alpine.data('mformsSelect', mformsSelect);
    Alpine.data('mformsChoice', mformsChoice);
    Alpine.data('mformsRepeater', mformsRepeater);
});