import Quill from 'quill';
import { createMerosField } from './field-data.js';

export const merosRichTextField = (id, rules = {}) => {
    const fieldContract = createMerosField(id, rules);

    return {
        ...fieldContract,
        isInstantiating: false,
        onRefresh: null,
        onChange: null,
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

            this.value = this.getValue();
            this.previousValue = this.value;

            this.__setContext();
        
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

        setValue(value) {
            const htmlValue = typeof value === 'string' ? value : '';

            if (!this.__isInstantiated()) {
                if (this.element && !this.element.closest('.meros-repeater-template-row')) {
                    this.element.innerHTML = htmlValue;
                    this.value = htmlValue;
                    this.__syncHiddenInput(htmlValue);
                    this.__instantiate();
                }

                if (!this.__isInstantiated()) {
                    this.value = htmlValue;
                    this.__syncHiddenInput(htmlValue);
                    return;
                }
            }

            this.__setEditorHtml(this.element.__quill, htmlValue);
            this.__syncHiddenInput(htmlValue);
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
            this.onChange = null;
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
                        this.value = this.getValue();
                        this.__syncHiddenInput(this.value);
                        this.applyHints();
                        this.dispatchUpdate();
                    };
                }

                quill.root.addEventListener('blur', this.onChange);

                const isEmpty = (v) => v === null || v === undefined || v === '';

                this.value = this.getValue();
                this.previousValue = this.value;
                this.__syncHiddenInput(this.value);
            } finally {
                this.isInstantiating = false;
            }
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
        }
    };
};