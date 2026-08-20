import { createMerosField } from './field-data.js';

export const merosChoiceField = (id, rules = {}) => {
    const fieldContract = createMerosField(id, rules);

    return {
        ...fieldContract,

        onChange(event) {
            this.value = this.__getValue();
            mforms.validateFieldValue(this.element);

            this.dispatchUpdate();
        },

        dispatchUpdate(extra = {}) {
            const firstInput = this.element
                ? this.element.querySelector('input[type="checkbox"], input[type="radio"]')
                : null;

            fieldContract.dispatchUpdate.call(this, {
                name: firstInput ? firstInput.name : null,
                ...extra,
            });
        }
    }
};