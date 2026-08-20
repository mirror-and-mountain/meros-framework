import './style.scss';

const mformsInput = () => {
    return {
        init() {
            console.log('mformsInput init', this.$el);
        },

        onChange(event) {
            const value = event?.target?.value || this.$el.value;
            console.log('mformsInput onChange', value);
        },
    };
}

document.addEventListener('alpine:init', () => {
    Alpine.data('mformsInput', mformsInput);
});