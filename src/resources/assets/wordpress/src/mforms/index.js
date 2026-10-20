import mformSection from './section.js';
import mformsSelect from './fields/select.js';
import mformsRepeater from './fields/repeater.js';
import mformsChoice from './fields/choice.js';
import MerosFormProcessor from './form-processor.js';
import MerosFieldsProcessor from './fields-processor.js';

import './style.scss';

const mform = () => {
    return {
        id: null,
        name: null,
        ajaxAction: null,
        ajaxUrl: null,
        ajaxNonce: null,
        conditionsAjaxNonce: null,
        onSubmit: null,
        invalidText: null,
        fieldsProcessor: null,
        feedbackMessage: '',
        feedbackType: '',

        // =========================================================================
        // Initialisation
        // =========================================================================

        init() {
            this.id = this.$el.id || null;
            this.name = this.$el.dataset.name || null;

            this.ajaxAction = 'meros_submit_form';
            this.ajaxUrl = this.$el.dataset.ajaxUrl || null;
            this.ajaxNonce = this.$el.dataset.ajaxNonce || null;

            this.onSubmit = this.$el.dataset.onsubmit || null;
            this.invalidText = this.$el.dataset.invalidText || null;

            this.$el.removeAttribute('data-ajax-url');
            this.$el.removeAttribute('data-ajax-nonce');
            this.$el.removeAttribute('data-invalid-text');

            // Nested Alpine components are initialized after this component's init hook.
            this.$nextTick(() => {
                this.fieldsProcessor = new MerosFieldsProcessor(this.$el);
            });

            if (this.onSubmit && typeof this.onSubmit === 'string') {
                this.$el.removeAttribute('data-onsubmit');
            }
        },

        // =========================================================================
        // Operations
        // =========================================================================

        submitForm(returnOutcome = false) {
            const genericError = 'An error occured while submitting the form.'
            const invalidError = this.invalidText || "The form is invalid. Please check the information you've entered."

            this.feedbackMessage = '';
            this.feedbackType = '';

            if (!this.name) {
                return this.completeSubmission({success: false, error: genericError}, returnOutcome);
            }

            const form = this.$el.tagName === 'FORM' ? this.$el : this.$el.closest('form');
            if (!form) {
                return this.completeSubmission({success: false, error: genericError}, returnOutcome);
            }

            const valid = form.checkValidity();

            if (valid === false) {
                form.reportValidity();
                return this.completeSubmission(
                    {success: false, invalid: true, error: invalidError},
                    returnOutcome
                );
            }

            const formData = this.processFormData(new FormData(form));

            if (this.onSubmit && typeof this.onSubmit === 'string') {
                this.$dispatch('mforms::' + this.onSubmit, { 
                    formId: this.id,
                    formName: this.name,
                    formData,
                });

                return this.completeSubmission(
                    {success: true, error: null, message: 'Form submitted successfully.'},
                    returnOutcome
                );
            }

            if (!this.ajaxUrl || !this.ajaxNonce || !this.ajaxAction) {
                return this.completeSubmission({success: false, error: genericError}, returnOutcome);
            }

            const postData = new FormData();

            postData.append('action', this.ajaxAction);
            postData.append('nonce', this.ajaxNonce);
            postData.append('form_name', this.name);
            postData.append('form_id', this.id);
            postData.append('form_data', JSON.stringify(formData));

            const request = fetch(this.ajaxUrl, {
                method: 'POST',
                body: postData,
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(genericError);
                }

                return response.json();
            })
            .then(data => {
                if (data.success && data.data) {
                    return {
                        success: true,
                        error: null,
                        message: data.data.message || 'Form submitted successfully.'
                    };
                }

                return {
                    success: false,
                    error: data.data?.message || genericError
                };
            })
            .catch((error) => {
                return {
                    success: false,
                    error: error instanceof Error ? error.message : genericError
                };
            });

            if (returnOutcome) {
                return request;
            }

            return request.then(outcome => this.completeSubmission(outcome, false));
        },

        completeSubmission(outcome, returnOutcome) {
            if (returnOutcome) {
                return outcome;
            }

            this.feedbackMessage = outcome.success
                ? outcome.message || 'Form submitted successfully.'
                : outcome.error || 'An error occured while submitting the form.';
            this.feedbackType = outcome.success ? 'success' : 'error';
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
    Alpine.data('mformSection', mformSection);
    Alpine.data('mformsSelect', mformsSelect);
    Alpine.data('mformsChoice', mformsChoice);
    Alpine.data('mformsRepeater', mformsRepeater);
});