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
        }
    }
};

export default mformsChoice;