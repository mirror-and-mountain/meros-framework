import { registerFormDragStore } from './forms/stores.js';
import { initTomSelects } from '../../forms/tom-select/index.js';
import { bindRepeaterCellChangeDelegation, fixRadioValuesinRepeaterRows } from '../../../assets/forms/repeaters/index.js';
import '../../wordpress/src/fields/site/forms.scss';
import './style.css';

// Alpine is bundled inside Livewire and initialises after our script loads,
// so hooking into alpine:init is the correct registration point.
document.addEventListener('alpine:init', registerFormDragStore);

document.addEventListener('DOMContentLoaded', () => {
	initTomSelects();
	bindRepeaterCellChangeDelegation();
});

window.addEventListener('load', () => {
	Livewire.hook('morphed', () => {
		initTomSelects();
        requestAnimationFrame(() => {
            const rows = document.querySelectorAll('.meros-repeater-table[data-livewire-sync-enabled="true"] tr.meros-repeater-row');
            fixRadioValuesinRepeaterRows(rows);
        });
	});
});
