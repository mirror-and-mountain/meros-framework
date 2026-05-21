import {
    initTomSelects,
    observeTomSelectWrapperLoss,
    scheduleTomSelectRebind,
} from './lifecycle.js';
import { registerTomSelectBridgeAPI } from './sync-bridge.js';

let tomSelectRuntimeInitialized = false;

// -----------------------------------------------------------------------------
// Runtime bootstrap
// Called from entrypoints once on page boot.
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
