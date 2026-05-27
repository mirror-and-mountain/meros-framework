import Quill from 'quill';
import registerRepeaterFieldStore from '../../../../forms/alpine/repeaterFieldStore.js';
import { initTomSelects } from '../../../../forms/tom-select/index.js';
import './style.scss';

// Initialise the alpine repeaterField store
document.addEventListener('alpine:init', registerRepeaterFieldStore);

document.addEventListener('livewire:initialized', () => {
	// Initialise TomSelects on page load
	initTomSelects();

	// Initialise Quill editors on page load
	const richTextEditors = document.querySelectorAll('.meros-rich-textarea');

	richTextEditors.forEach(editor => {
		if (editor._quill) {
			return;
		}

        const quill = new Quill(editor, {
            theme: 'snow',
            modules: {
                toolbar: ['bold', 'italic', 'underline', 'link']
            }
        });

        editor._quill = quill;
	});
});

