import MerosFormProcessor from './form-processor.js';
import MerosFieldsProcessor from './fields-processor.js';

const mform = () => {
    let pageObserver = null;

    return {
        id: null,
        name: null,
        ajaxAction: null,
        ajaxUrl: null,
        ajaxNonce: null,
        conditionsAjaxNonce: null,
        onSubmit: null,
        submitButton: null,
        invalidText: null,
        successMessage: 'Form Submitted Successfully.',
        showSuccessOnNewPage: false,
        fieldsProcessor: null,
        feedbackMessage: '',
        feedbackType: '',
        isSubmitting: false,
        pages: [],
        availablePages: [],
        activePage: null,
        pageNumber: 0,
        pageCount: 0,
        pageTitle: '',
        userSignedIn: false,
        conditionValues: {},

        get showingConfirmation() {
            return this.showSuccessOnNewPage && this.feedbackType === 'success';
        },

        get hasPreviousPage() {
            return this.pageNumber > 1;
        },

        get hasNextPage() {
            return this.pageNumber < this.pageCount;
        },

        get pageStatus() {
            return `Step ${this.pageNumber} of ${this.pageCount}: ${this.pageTitle}`;
        },

        // =========================================================================
        // Initialisation
        // =========================================================================

        init() {
            this.id = this.$el.id || null;
            this.name = this.$el.dataset.name || null;
            this.conditionValues = this.$el.hasAttribute('data-condition-values')
                ? JSON.parse(this.$el.dataset.conditionValues)
                : { __user_signed_in: String(this.$el.hasAttribute('data-user-signed-in')) };
            this.userSignedIn = this.conditionValues.__user_signed_in === 'true';

            this.ajaxAction = 'meros_submit_form';
            this.ajaxUrl = this.$el.dataset.ajaxUrl || null;
            this.ajaxNonce = this.$el.dataset.ajaxNonce || null;

            this.onSubmit = this.$el.dataset.onsubmit || null;
            this.submitButton = this.$el.querySelector('[type="submit"]') || null;
            this.invalidText = this.$el.dataset.invalidText || null;
            this.successMessage = this.$el.dataset.successMessage ?? this.successMessage;
            this.showSuccessOnNewPage = this.$el.hasAttribute('data-success-on-new-page');

            this.$el.removeAttribute('data-ajax-url');
            this.$el.removeAttribute('data-ajax-nonce');
            this.$el.removeAttribute('data-invalid-text');
            this.$el.removeAttribute('data-success-message');
            this.$el.removeAttribute('data-condition-values');
            this.$el.removeAttribute('data-user-signed-in');

            // Nested Alpine components are initialized after this component's init hook.
            this.$nextTick(() => {
                this.fieldsProcessor = new MerosFieldsProcessor(this.$el);
                this.initPagination();
            });

            if (this.onSubmit && typeof this.onSubmit === 'string') {
                this.$el.removeAttribute('data-onsubmit');
            }
        },

        destroy() {
            pageObserver?.disconnect();
        },

        initPagination() {
            if (this.$el.dataset.paginated !== '1') return;
            this.pages = Array.from(this.$el.querySelectorAll(':scope > .mforms-body > [data-form-page]'));
            if (!this.pages.length) return;

            // Inactive pages retain their values and constraints; validation reveals the relevant page.
            this.$el.noValidate = true;
            this.refreshPages();
            pageObserver = new MutationObserver(() => this.refreshPages());
            pageObserver.observe(this.$el, {
                subtree: true,
                attributes: true,
                attributeFilter: ['data-meros-hidden-field', 'style'],
            });
        },

        isConditionallyHidden(control) {
            if (control.closest('[data-meros-hidden-field]')) return true;
            let element = control.closest('.mforms-field-wrapper') || control;
            while (element && element !== this.$el) {
                if (!element.hasAttribute('data-form-page')) {
                    const style = getComputedStyle(element);
                    if (element.hidden || style.display === 'none' || style.visibility === 'hidden') return true;
                }
                element = element.parentElement;
            }
            return false;
        },

        refreshPages() {
            if (this.showingConfirmation) return;
            const previous = this.activePage;
            const previousIndex = this.pages.indexOf(previous);
            this.availablePages = this.pages.filter(page =>
                Array.from(page.querySelectorAll('.mforms-field-wrapper')).some(wrapper => {
                    const control = wrapper.matches('[data-field-type]')
                        ? wrapper
                        : wrapper.querySelector('[data-field-type]');
                    return control && control.dataset.fieldType !== 'hidden' && !this.isConditionallyHidden(control);
                })
            );
            const next = this.availablePages.includes(previous) ? previous :
                this.availablePages.find(page => this.pages.indexOf(page) >= previousIndex) ||
                this.availablePages[this.availablePages.length - 1] || null;
            this.showPage(next, previous !== null && previous !== next);
        },

        showPage(page, focus = false) {
            this.activePage = page;
            this.pageCount = this.availablePages.length;
            this.pageNumber = page ? this.availablePages.indexOf(page) + 1 : 0;
            this.pageTitle = page?.dataset.pageTitle || '';
            this.pages.forEach(candidate => { candidate.hidden = candidate !== page; });
            if (focus && page) {
                page.focus({ preventScroll: true });
                page.scrollIntoView({ block: 'start' });
            }
        },

        findInvalidControl(scope) {
            return Array.from(scope.querySelectorAll('input, select, textarea'))
                .find(control => control.willValidate && !this.isConditionallyHidden(control) && !control.checkValidity());
        },

        reportInvalidControl(control) {
            const page = control.closest('[data-form-page]');
            if (page) this.showPage(page);
            control.reportValidity();
        },

        nextPage() {
            if (this.isSubmitting) return;
            this.refreshPages();
            if (!this.hasNextPage) return;
            const invalid = this.findInvalidControl(this.activePage);
            if (invalid) {
                this.reportInvalidControl(invalid);
                return;
            }
            this.showPage(this.availablePages[this.pageNumber], true);
        },

        previousPage() {
            if (this.isSubmitting) return;
            this.refreshPages();
            if (this.hasPreviousPage) this.showPage(this.availablePages[this.pageNumber - 2], true);
        },

        // =========================================================================
        // Operations
        // =========================================================================

        submitForm(returnOutcome = false) {
            if (this.isSubmitting || this.showingConfirmation) return;

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

            const invalid = this.pages.length ? this.findInvalidControl(form) : null;
            const valid = this.pages.length ? !invalid : form.checkValidity();

            if (valid === false) {
                if (invalid) this.reportInvalidControl(invalid);
                else form.reportValidity();
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
                    {success: true, error: null, message: this.successMessage},
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
            postData.append('id', this.id);
            postData.append('form_data', JSON.stringify(formData));

            this.isSubmitting = true;

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
                        message: data.data.message ?? this.successMessage
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
            })
            .finally(async () => {
                this.isSubmitting = false;
                // Remove inert before a successful reset focuses the first page.
                await this.$nextTick(() => {});
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
                ? outcome.message ?? this.successMessage
                : outcome.error || 'An error occured while submitting the form.';
            if (outcome.success) this.resetForm(!this.showSuccessOnNewPage);
            this.feedbackType = outcome.success ? 'success' : 'error';
            if (this.showingConfirmation) {
                this.$nextTick(() => this.$el.querySelector('.mforms-confirmation')?.focus());
            }
        },

        startAnotherResponse() {
            if (!this.showingConfirmation) return;
            this.feedbackMessage = '';
            this.feedbackType = '';
            this.$nextTick(() => {
                if (this.pages.length) this.refreshPages();
                (this.activePage ?? this.$el).focus();
            });
        },

        resetForm(focus = true) {
            const form = this.resolveForm();
            if (!form) return;

            HTMLFormElement.prototype.reset.call(form);
            this.fieldsProcessor?.reset();
            if (this.pages.length) {
                this.refreshPages();
                this.showPage(this.availablePages[0] || null, focus);
            }
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

export default mform;