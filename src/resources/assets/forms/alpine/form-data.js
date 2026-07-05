/**
 * Alpine.js component for managing form data.
 */
document.addEventListener('alpine:init', () => {
    Alpine.data('merosFormData', (livewire, fields) => ({
        livewire: livewire || null,
        fields: fields || {},
        initialiseFields: null,
        onFieldChange: null,

        init() {
            this.onFieldChange = ({ detail }) => {
                const { id, value, element, context } = detail;

                if (!id || value === undefined) return;
                // Ignore fields being updated in a repeater dialog.
                if (element?.closest('.meros-repeater-config-dialog__body')) return;

                if (!this.fields[id]) return;

                // Handle repeater updates
                if (context?.repeater?.id) {
                    this.__storeRepeaterValue(context.repeater.id);
                } else {
                    // Store the field value
                    this.fields[id].value = value;
                }
            };

            window.addEventListener('mforms:field-updated', this.onFieldChange);
        },

        destroy() {
            if (this.onFieldChange) {
                window.removeEventListener('mforms:field-updated', this.onFieldChange);
            }
        },

        async submitForm() {
            if (!this.livewire || typeof this.livewire.submitForm !== 'function') {
                return;
            }

            this.__dispatchLifecycleEvent('mforms:form-submit-started');
            await this.syncFormState();

            const clientValidationErrors = this.validateClientFields();

            if (clientValidationErrors.length > 0) {
                const firstErroredField = clientValidationErrors[0];
                const firstComponent = window?.mforms?.getField?.(firstErroredField.id) || null;
                const firstElement = this.__resolveValidationElement(firstComponent);

                if (firstElement && typeof firstElement.focus === 'function') {
                    firstElement.focus();
                }

                this.__scrollToFormTop(true);
                await this.__notifyDomUpdated('client-validation-failed');

                this.__dispatchLifecycleEvent('mforms:client-validation-failed', {
                    errors: clientValidationErrors,
                });

                return;
            }

            await this.livewire.submitForm(this.fields);
            await this.__notifyDomUpdated('submit');
            await this.__scrollToTopOnServerError();
            await this.__scrollToTopOnServerSuccess();
        },

        validateClientFields() {
            if (!window.mforms || typeof window.mforms.getField !== 'function') {
                return [];
            }

            const errors = [];

            Object.keys(this.fields || {}).forEach((fieldId) => {
                const component = window.mforms.getField(fieldId);

                if (!component || typeof component.getValue !== 'function') {
                    return;
                }

                const element = this.__resolveValidationElement(component);

                if (!element) {
                    return;
                }

                const fieldType = String(element?.dataset?.fieldType || element?.type || '').toLowerCase();

                if (fieldType !== 'email' && element.type !== 'email') {
                    return;
                }

                const rawValue = component.getValue();
                const value = typeof rawValue === 'string' ? rawValue.trim() : '';

                if (value === '') {
                    this.__setFieldInvalidState(element, false, '');
                    return;
                }

                const isValidEmail = this.__isValidEmail(value, element);

                if (!isValidEmail) {
                    const message = 'Please enter a valid email address.';

                    this.__setFieldInvalidState(element, true, message);
                    errors.push({ id: fieldId, message });
                    return;
                }

                this.__setFieldInvalidState(element, false, '');
            });

            return errors;
        },

        async syncFormState() {
            if (!this.livewire || typeof this.livewire.syncFormState !== 'function') {
                return;
            }

            this.__refreshFieldsFromComponents();

            await this.livewire.syncFormState(this.fields);
        },

        async nextGroupPage() {
            if (!this.livewire || typeof this.livewire.nextGroupPage !== 'function') {
                return;
            }

            this.__dispatchLifecycleEvent('mforms:group-page-changing', { direction: 'next' });
            await this.syncFormState();
            await this.livewire.nextGroupPage();
            await this.__notifyDomUpdated('next-group-page');
        },

        async prevGroupPage() {
            if (!this.livewire || typeof this.livewire.prevGroupPage !== 'function') {
                return;
            }

            this.__dispatchLifecycleEvent('mforms:group-page-changing', { direction: 'prev' });
            await this.syncFormState();
            await this.livewire.prevGroupPage();
            await this.__notifyDomUpdated('prev-group-page');
        },

        async setPagedView() {
            if (!this.livewire || typeof this.livewire.setPagedView !== 'function') {
                return;
            }

            this.__dispatchLifecycleEvent('mforms:view-mode-changing', { mode: 'paged' });
            await this.syncFormState();
            await this.livewire.setPagedView();
            await this.__notifyDomUpdated('set-paged-view');
        },

        async setFullView() {
            if (!this.livewire || typeof this.livewire.setFullView !== 'function') {
                return;
            }

            this.__dispatchLifecycleEvent('mforms:view-mode-changing', { mode: 'full' });
            await this.syncFormState();
            await this.livewire.setFullView();
            await this.__notifyDomUpdated('set-full-view');
        },

        __storeRepeaterValue(repeaterId) {
            const repeater = mforms.getField(repeaterId);

            if (repeater && this.fields[repeaterId]) {
                this.fields[repeaterId].value = repeater.getValue();
            }
        },

        __refreshFieldsFromComponents() {
            if (!window.mforms || typeof window.mforms.getField !== 'function') {
                return;
            }

            Object.keys(this.fields || {}).forEach((fieldId) => {
                const field = this.fields[fieldId];

                if (!field) {
                    return;
                }

                const component = window.mforms.getField(fieldId);

                if (!component || typeof component.getValue !== 'function') {
                    return;
                }

                field.value = component.getValue();
            });
        },

        __dispatchLifecycleEvent(type, detail = {}) {
            window.dispatchEvent(new CustomEvent(type, {
                detail,
                bubbles: false,
                composed: true,
            }));
        },

        __resolveValidationElement(component) {
            if (!component || !(component.element instanceof HTMLElement)) {
                return null;
            }

            if (component.element.matches('input, textarea, select')) {
                return component.element;
            }

            return component.element.querySelector('input[type="email"], [data-field-type="email"]');
        },

        __isValidEmail(value, element = null) {
            if (typeof document !== 'undefined') {
                const probe = document.createElement('input');
                probe.type = 'email';
                probe.value = value;

                if (typeof probe.checkValidity === 'function') {
                    return probe.checkValidity();
                }
            }

            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
        },

        __getFormContainer() {
            if (!this.$el || !(this.$el instanceof HTMLElement)) {
                return null;
            }

            return this.$el.closest('.meros-form-container') || this.$el;
        },

        __hasErrorFlash() {
            const container = this.__getFormContainer();

            if (!container) {
                return false;
            }

            return !!container.querySelector('.meros-form-flash--validation-error, .meros-form-flash--system-error');
        },

        __hasSuccessFlash() {
            const container = this.__getFormContainer();

            if (!container) {
                return false;
            }

            return !!container.querySelector('.meros-form-flash--success');
        },

        async __scrollToTopOnServerError() {
            // Livewire DOM morphing may render the flash on a later frame.
            for (let i = 0; i < 10; i += 1) {
                if (this.__hasErrorFlash()) {
                    this.__scrollToFormTop(true);
                    return;
                }

                await new Promise((resolve) => window.requestAnimationFrame(resolve));
            }
        },

        async __scrollToTopOnServerSuccess() {
            for (let i = 0; i < 10; i += 1) {
                if (this.__hasSuccessFlash()) {
                    this.__scrollToFormTop(true);
                    return;
                }

                await new Promise((resolve) => window.requestAnimationFrame(resolve));
            }
        },

        __scrollToFormTop(smooth = false) {
            const container = this.__getFormContainer();

            if (!container) {
                return;
            }

            container.scrollIntoView({
                behavior: smooth ? 'smooth' : 'auto',
                block: 'start',
                inline: 'nearest',
            });

            const flash = container.querySelector('.meros-form-flash--validation-error, .meros-form-flash--system-error, .meros-form-flash--success');

            if (flash && typeof flash.focus === 'function') {
                if (!flash.hasAttribute('tabindex')) {
                    flash.setAttribute('tabindex', '-1');
                }

                flash.focus({ preventScroll: true });
            }
        },

        __setFieldInvalidState(element, isInvalid, message = '') {
            const wrapper = element?.closest('.meros-field');

            if (!wrapper) {
                return;
            }

            wrapper.classList.toggle('invalid', isInvalid);

            const messageContainer = wrapper.querySelector('.meros-field-validation-messages');

            if (messageContainer) {
                messageContainer.textContent = isInvalid ? message : '';
            }
        },

        async __notifyDomUpdated(reason = '') {
            await new Promise((resolve) => this.$nextTick(resolve));
            await new Promise((resolve) => window.requestAnimationFrame(resolve));

            const detail = { reason };

            this.__dispatchLifecycleEvent('mforms:form-dom-updated', detail);
            this.__dispatchLifecycleEvent('mforms:external-fields-refresh', detail);

            // Backward-compatible refresh signal already used across field components.
            this.__dispatchLifecycleEvent('mforms:form-canvas-updated', detail);
        }
    }));
});