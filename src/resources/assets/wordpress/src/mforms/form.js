import MerosFormProcessor from './form-processor.js';
import MerosFieldsProcessor from './fields-processor.js';
import { alignFormRows } from './row-alignment.js';

/**
 * Initialise and manage the form instance, including its fields, pagination, and submission handling.
 * 
 * @returns {Object} The form instance with its methods and properties.
 */
const mform = () => {
    let pageObserver = null;
    let stopRowAlignment = null;
    let validationFeedback = null;
    const clearValidationFeedback = () => {
        if (!validationFeedback) return;
        const { target, message, describedBy } = validationFeedback;
        if (describedBy === null) target.removeAttribute('aria-describedby');
        else target.setAttribute('aria-describedby', describedBy);
        message.remove();
        validationFeedback = null;
    };

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

            // Validate visible controls ourselves so retained hidden values cannot block submission.
            const form = this.resolveForm();
            if (form) form.noValidate = true;
            this.$el.addEventListener('change', clearValidationFeedback);
            this.$el.addEventListener('input', clearValidationFeedback);

            // Nested Alpine components are initialized after this component's init hook.
            this.$nextTick(() => {
                this.fieldsProcessor = new MerosFieldsProcessor(this.$el);
                this.initPagination();
                stopRowAlignment = alignFormRows(this.$el);
            });

            if (this.onSubmit && typeof this.onSubmit === 'string') {
                this.$el.removeAttribute('data-onsubmit');
            }
        },

        destroy() {
            pageObserver?.disconnect();
            stopRowAlignment?.();
            this.$el.removeEventListener('change', clearValidationFeedback);
            this.$el.removeEventListener('input', clearValidationFeedback);
            clearValidationFeedback();
        },

        initPagination() {
            if (this.$el.dataset.paginated !== '1') return;
            this.pages = Array.from(this.$el.querySelectorAll(':scope > .mforms-body > [data-form-page]'));
            if (!this.pages.length) return;

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
            clearValidationFeedback();
            if (!scope) return null;
            const controls = scope.querySelectorAll('input, select, textarea, .meros-choice-field, .mforms-repeater-field');
            for (const control of controls) {
                if (control.matches(':disabled') || control.closest('.meros-repeater-table-row--template') ||
                    this.isConditionallyHidden(control)) continue;

                const message = this.getFieldValidationMessage(control, scope);
                if (message) return { control, message };
                if (control.willValidate && !control.checkValidity()) return { control };
            }
            return null;
        },

        getFieldValidationMessage(control, scope) {
            let count;
            let unit = 'options';
            const required = control.hasAttribute('required');
            if (control.matches('.meros-choice-field')) {
                const inputs = control.querySelectorAll('input:is([type="checkbox"], [type="radio"]):not(:disabled)');
                count = Array.from(inputs).filter(input => input.checked).length;
            } else if (control.matches('select[multiple]')) {
                count = Array.from(control.selectedOptions).filter(option => option.value !== '' &&
                    !option.disabled && !option.closest('optgroup[disabled]')).length;
            } else if (control.matches('.mforms-repeater-field')) {
                unit = 'rows';
                count = Array.from(control.querySelectorAll(
                    '.meros-repeater-table-row:not(.meros-repeater-table-row--template, .meros-repeater-table-row--empty)'
                )).filter(row => row.closest('.mforms-repeater-field') === control).length;
            } else if (control.matches('[data-builder-bound="max"]') && control.value !== '') {
                const min = scope.querySelector('[data-builder-bound="min"]');
                return min && min.value !== '' && control.valueAsNumber < min.valueAsNumber
                    ? 'The maximum must be greater than or equal to the minimum.' : '';
            } else {
                return '';
            }

            const minimum = Math.max(required ? 1 : 0, Number(control.dataset.minCount ?? 0));
            const legacyMax = unit === 'rows' && Number(control.dataset.maxRows) > 0 ? control.dataset.maxRows : null;
            const maximum = Number(control.dataset.maxCount ?? legacyMax ?? Infinity);
            const verb = unit === 'rows' ? 'Add' : 'Select';
            if (count < minimum) return `${verb} at least ${minimum} ${minimum === 1 ? unit.slice(0, -1) : unit}.`;
            if (count > maximum) return `Use no more than ${maximum} ${maximum === 1 ? unit.slice(0, -1) : unit}.`;
            return '';
        },

        reportInvalidControl({ control, message }) {
            const page = control.closest('[data-form-page]');
            if (page) this.showPage(page);
            if (!message && !control.tomselect) {
                control.reportValidity();
                return;
            }
            const fieldset = control.matches('fieldset');
            const focusable = control.matches('.mforms-repeater-field')
                ? '.meros-repeater-table-button--add:not(:disabled), .meros-repeater-table-button--remove:not(:disabled)'
                : 'input:is([type="checkbox"], [type="radio"]):not(:disabled)';
            const target = control.tomselect?.control_input ||
                (fieldset ? control.querySelector(focusable) : control) || control;
            if (target === control && fieldset) target.setAttribute('tabindex', '-1');
            const feedback = document.createElement('div');
            feedback.id = `${this.id || 'mforms'}-validation-message`;
            feedback.className = 'mforms-field-validation';
            feedback.setAttribute('role', 'alert');
            feedback.textContent = message || control.validationMessage;
            if (fieldset) control.append(feedback);
            else (control.tomselect?.wrapper || control).after(feedback);
            const describedBy = target.getAttribute('aria-describedby');
            target.setAttribute('aria-describedby', [describedBy, feedback.id].filter(Boolean).join(' '));
            validationFeedback = { target, message: feedback, describedBy };
            target.focus();
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

            const invalid = this.findInvalidControl(form);
            if (invalid) {
                this.reportInvalidControl(invalid);
                return this.completeSubmission(
                    {success: false, invalid: true, error: invalidError},
                    returnOutcome
                );
            }

            const formData = this.processFormData(new FormData(form));
            const hiddenFields = [];
            for (const field of Object.values(this.fieldsProcessor?.fields ?? {})) {
                const { control } = field;
                if (!control.closest('[data-meros-hidden-field]') || control.matches(':disabled')) continue;
                const name = field.name.replace(/\[\]$/, '');
                if (!Object.prototype.hasOwnProperty.call(formData, name)) {
                    const controls = control.querySelectorAll('input, select, textarea');
                    if (controls.length && Array.from(controls).every(input => input.matches(':disabled'))) continue;
                    formData[name] = this.fieldsProcessor.getFieldValue(field);
                }
                hiddenFields.push(name);
            }

            if (this.onSubmit && typeof this.onSubmit === 'string') {
                this.$dispatch('mforms::' + this.onSubmit, { 
                    form: this,
                    formId: this.id,
                    formName: this.name,
                    formData,
                });

                if (returnOutcome) {
                    return {success: true, error: null, message: this.successMessage};
                }
                return;
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
            postData.append('hidden_fields', JSON.stringify(hiddenFields));

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

        showSuccess(message = this.successMessage) {
            this.completeSubmission({success: true, message}, false);
        },

        showError(message) {
            this.completeSubmission({success: false, error: message}, false);
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