import registerFormBuilderStore from '../../forms/alpine/formBuilderStore.js';
import registerRepeaterFieldStore from '../../forms/alpine/repeaterFieldStore.js';
import { initRichTextEditors } from '../../forms/richtext.js';
import { initTomSelects } from '../../forms/tom-select/index.js';
import { updateIgnoredFieldWrapperElements } from '../../forms/utils.js';
import './style.css';

// Initialise the alpine formBuilder store
document.addEventListener('alpine:init', registerFormBuilderStore);
// Initialise the alpine repeaterField store
document.addEventListener('alpine:init', registerRepeaterFieldStore);
// Initialise TomSelects on page load
document.addEventListener('livewire:initialized', initTomSelects);

// Listen for updates to the form builder schema to reinitialise js components as needed
window.addEventListener('meros-form-builder-schema-updated', (event) => {
    const { ignoredFields, richTextPayloads } = event.detail;

    updateIgnoredFieldWrapperElements(ignoredFields);
    initTomSelects();
    initRichTextEditors(richTextPayloads);
});

// Listen for updates to rich text content to reinitialise rich text editors as needed
window.addEventListener('meros-form-builder-rich-text-updated', (event) => {
    const { richTextPayloads } = event.detail;

    initRichTextEditors(richTextPayloads);
});