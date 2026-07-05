/**
 * Alpine.js component for managing form data.
 */
document.addEventListener('alpine:init', () => {
    Alpine.data('merosFormData', () => ({
        fields: {},
        onFieldChange: null,

        init() {
            this.onFieldChange = ({ detail }) => {
                const { id, value, context } = detail;

                if (!id || value === undefined) return;

                if (context.repeater && context.repeater.id) {
                    const repeater = mforms.getField(context.repeater.id);
                    if (repeater) {
                        this.fields[context.repeater.id] = repeater.getValue();
                    }
                    console.log(this.fields);
                    return;
                }

                this.fields[id] = value;

                console.log(this.fields);
            }

            document.addEventListener('mforms:field-updated', this.onFieldChange);
        }
    }));
});