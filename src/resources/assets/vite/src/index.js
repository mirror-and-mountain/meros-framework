import { registerFormDragStore } from './forms/stores.js';
import { initTomSelectRuntime } from './forms/tom-select.js';
import './style.css';

// Alpine is bundled inside Livewire and initialises after our script loads,
// so hooking into alpine:init is the correct registration point.
document.addEventListener('alpine:init', registerFormDragStore);

// TomSelect bootstrap lives in forms/tom-select.js; index.js is now only wiring.
document.addEventListener('DOMContentLoaded', () => {
    initTomSelectRuntime();
});
