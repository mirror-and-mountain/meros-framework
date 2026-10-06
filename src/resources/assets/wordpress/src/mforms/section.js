import MerosFieldsProcessor from './fields-processor.js';

const mformSection = () => {
    return {
        id: null,
        name: null,
        ajaxUrl: null,
        ajaxNonce: null,
        fieldsProcessor: null,

        init() {
            this.id = this.$el.id || null;
            this.name = this.$el.dataset.name || null;

            this.$nextTick(() => {
                this.fieldsProcessor = new MerosFieldsProcessor(this.$el);
            });
        }
    };
};

export default mformSection;