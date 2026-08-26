const mformsInput = () => {
    return {
        init() {
            
        },

        onChange(event) {
            const value = event?.target?.value || this.$el.value;
        },
    };
};

export default mformsInput;