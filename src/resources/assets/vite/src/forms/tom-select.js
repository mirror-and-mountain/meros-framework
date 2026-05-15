import {
	initTomSelects,
	observeTomSelectWrapperLoss,
	scheduleTomSelectRebind,
} from './tom-select/lifecycle.js';
import { registerTomSelectBridgeAPI } from './tom-select/sync-bridge.js';

let tomSelectRuntimeInitialized = false;

// -----------------------------------------------------------------------------
// Runtime bootstrap
// Called from index.js once on page boot.
// -----------------------------------------------------------------------------
export function initTomSelectRuntime() {
	if (tomSelectRuntimeInitialized) {
		return;
	}

	tomSelectRuntimeInitialized = true;

	try {
		initTomSelects();
		observeTomSelectWrapperLoss();
	} catch (err) {
		console.error('Error initializing TomSelect system:', err);
	}

	document.addEventListener('livewire:updated', () => {
		scheduleTomSelectRebind();
	});

	document.addEventListener('livewire:init', () => {
		if (typeof window.Livewire === 'undefined' || typeof window.Livewire.hook !== 'function') {
			return;
		}

		window.Livewire.hook('morph.updated', () => {
			scheduleTomSelectRebind();
		});
	});

	registerTomSelectBridgeAPI();
}

