const mformsChoice = () => {
    return {
        type: null,
        inputs: null,

        init() {
            if (this.$el.tagName !== 'FIELDSET') return;

            this.type   = this.$el.getAttribute('data-field-type');
            this.inputs = Array.from(
                this.$el.querySelectorAll('input[type="radio"], input[type="checkbox"]')
            );
        },

        getValue() {
            if (!this.inputs || this.inputs.length === 0) return null;

            if (this.type === 'checkboxes') {
                const checkedValues = this.inputs
                    .filter(input => input.checked)
                    .map(input => input.value);

                return checkedValues.length > 0 ? checkedValues : null;
            }

            if (this.type === 'radio') {
                const checkedInput = this.inputs.find(input => input.checked);
                return checkedInput ? checkedInput.value : null;
            }

            return null;
        },

        setValue(value) {
            if (!this.inputs) return;

            const values = new Set((Array.isArray(value) ? value : [value]).map(item => String(item ?? '')));
            this.inputs.forEach(input => {
                input.checked = values.has(input.value);
            });
        },

        setOptions(options) {
            if (this.$el.tagName !== 'FIELDSET') return;

            const currentValue = this.getValue();
            const currentInput = this.inputs?.[0];
            const inputType = currentInput?.type || (this.type === 'checkboxes' ? 'checkbox' : 'radio');
            const inputName = currentInput?.name || '';
            const values = new Set((Array.isArray(options) ? options : []).map(option => String(option.value)));
            const nextValue = Array.isArray(currentValue)
                ? currentValue.filter(value => values.has(String(value)))
                : values.has(String(currentValue ?? '')) ? currentValue : null;

            this.inputs?.forEach(input => {
                input.closest('.nice-form-group')?.remove();
            });

            this.inputs = (Array.isArray(options) ? options : []).map((option, index) => {
                const value = String(option.value);
                const inputId = `${this.$el.id}-${value || index}`;
                const wrapper = document.createElement('div');
                wrapper.className = 'nice-form-group';

                const input = document.createElement('input');
                input.id = inputId;
                input.className = 'meros-choice-field-input';
                input.type = inputType;
                input.name = inputName;
                input.value = value;
                input.disabled = Boolean(option.disabled);

                const label = document.createElement('label');
                label.htmlFor = inputId;
                label.textContent = String(option.label ?? value);

                wrapper.append(input, label);
                this.$el.append(wrapper);

                return input;
            });

            this.setValue(nextValue);
        }
    }
};

export default mformsChoice;