const mformsRichText = () => {
    // Quill relies on object identity and must not be wrapped by Alpine's reactive proxy.
    let quill = null;
    let inputObserver = null;

    return {
        init() {
            if (this.$el.closest('.meros-repeater-table-row--template')) return;

            quill = new Quill(this.$refs.editor, {
                theme: 'snow'
            });
            quill.root.setAttribute('aria-label', this.$refs.editor.dataset.label);
            quill.root.setAttribute('role', 'textbox');
            quill.root.setAttribute('aria-multiline', 'true');
            const description = this.$refs.input.getAttribute('aria-describedby');
            if (description) quill.root.setAttribute('aria-describedby', description);
            const toolbar = this.$el.querySelector('.ql-toolbar');
            if (toolbar) {
                toolbar.setAttribute('aria-label', `${this.$refs.editor.dataset.label} formatting`);
                toolbar.querySelectorAll('.ql-picker').forEach(picker => {
                    const format = Array.from(picker.classList).find(name => name !== 'ql-picker' && name.startsWith('ql-'))?.slice(3) || 'format';
                    const name = {
                        header: 'Heading', font: 'Font', size: 'Text size',
                        color: 'Text color', background: 'Background color', align: 'Text alignment',
                    }[format] || format;
                    picker.querySelector('.ql-picker-label')?.setAttribute('aria-label', name);
                    picker.querySelectorAll('.ql-picker-item').forEach(item => {
                        item.setAttribute('aria-label', item.dataset.label || (item.dataset.value ? `${name}: ${item.dataset.value}` : `Default ${name.toLowerCase()}`));
                    });
                });
                toolbar.addEventListener('keydown', event => {
                    if (event.key === ' ' && event.target.matches('.ql-picker-label, .ql-picker-item')) {
                        event.preventDefault();
                        event.target.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
                    }
                });
            }
            this.setValue(this.$refs.input.value);
            if (/\[success_message\]$/.test(this.$refs.input.name)) {
                attachDynamicSuggestions(this.$el, { quill, includeFields: true });
            }

            quill.on('text-change', () => {
                this.syncInput();
                this.$refs.input.dispatchEvent(new Event('input', { bubbles: true }));
                this.$refs.input.dispatchEvent(new Event('change', { bubbles: true }));
            });

            const syncEnabled = () => {
                const disabled = this.$refs.input.matches(':disabled');
                const readonly = this.$refs.input.hasAttribute('readonly');
                quill.enable(!disabled && !readonly);
                quill.root.setAttribute('aria-readonly', String(readonly));
                quill.root.setAttribute('aria-disabled', String(disabled));
                if (toolbar) toolbar.inert = disabled || readonly;
            };
            syncEnabled();
            inputObserver = new MutationObserver(syncEnabled);
            inputObserver.observe(this.$refs.input, {
                attributes: true,
                attributeFilter: ['disabled', 'readonly']
            });
        },

        getValue() {
            return quill ? quill.root.innerHTML : this.$refs.input.value;
        },

        setValue(value) {
            if (quill) {
                quill.clipboard.dangerouslyPasteHTML(String(value ?? ''), 'silent');
                this.syncInput();
            } else {
                this.$refs.input.value = value ?? '';
            }
        },

        syncInput() {
            this.$refs.input.value = this.getValue();
        },

        destroy() {
            inputObserver?.disconnect();
        }
    }
}

export default mformsRichText;
import { attachDynamicSuggestions } from '../dynamic-suggestions.js';
