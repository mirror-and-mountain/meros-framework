

export default function registerFormStore() {
    const store = {
        fieldCallbacks: {},

        // ======================================
        // Field conditions handlers
        // ======================================

        evalFieldConditions(field) {
            return;
        },

        // ======================================
        // Field callback handlers
        // ======================================

        // Normalise callback names to prevent unsafe or invalid callback registrations and lookups.
        normaliseCallbackName(callbackName) {
            if (typeof callbackName !== 'string') {
                return '';
            }

            const trimmedName = callbackName.trim();

            if (trimmedName === '' || trimmedName.length > 200) {
                return '';
            }

            const callbackPathPattern = /^(?:(?:\$store|\$wire)\.)?[A-Za-z_$][A-Za-z0-9_$]*(?:\.[A-Za-z_$][A-Za-z0-9_$]*)*$/;

            if (!callbackPathPattern.test(trimmedName)) {
                return '';
            }

            const pathWithoutStorePrefix = trimmedName
                .replace(/^\$store\./, '')
                .replace(/^\$wire\./, '');

            const blockedSegments = ['__proto__', 'prototype', 'constructor'];
            const segments = pathWithoutStorePrefix.split('.').filter(Boolean);

            if (segments.some(segment => blockedSegments.includes(segment))) {
                return '';
            }

            return trimmedName;
        },

        // Register a single callback with a given name. Returns the normalised callback name if registration is successful, or an empty string if registration fails.
        registerCallback(callbackName, callback) {
            const normalisedName = this.normaliseCallbackName(callbackName);

            if (normalisedName === '' || typeof callback !== 'function') {
                return '';
            }

            this.fieldCallbacks[normalisedName] = callback;
            return normalisedName;
        },

        // Register multiple callbacks at once. Returns an array of successfully registered callback names.
        registerCallbacks(callbacks = {}) {
            if (!callbacks || typeof callbacks !== 'object' || Array.isArray(callbacks)) {
                return [];
            }

            return Object.entries(callbacks).flatMap(([callbackName, callback]) => {
                const registeredName = this.registerCallback(callbackName, callback);
                return registeredName === '' ? [] : [registeredName];
            });
        },

        // Resolves a Livewire owner from callback invocation args.
        resolveWireOwner(args = []) {
            const explicitWireOwner = Array.isArray(args)
                ? args.find(arg => arg && typeof arg === 'object' && typeof arg.$call === 'function')
                : null;

            if (explicitWireOwner) {
                return explicitWireOwner;
            }

            const eventArg = Array.isArray(args)
                ? args.find(arg => arg && typeof arg === 'object' && arg.target instanceof Element)
                : null;

            const sourceElement = eventArg?.target instanceof Element ? eventArg.target : null;
            const wireHost = sourceElement?.closest?.('[wire\\:id]') ?? null;
            const wireId = wireHost?.getAttribute?.('wire:id') ?? '';

            if (wireId !== '' && typeof window?.Livewire?.find === 'function') {
                const owner = window.Livewire.find(wireId);

                if (owner && typeof owner === 'object') {
                    return owner;
                }
            }

            if (window?.$wire && typeof window.$wire === 'object') {
                return window.$wire;
            }

            return null;
        },

        // Resolve a callback by name, supporting lookups of globally registered callbacks, Alpine store methods, Livewire component methods, and previously registered callbacks in the formStore. Returns the resolved function or null if no valid callback is found.
        resolveCallback(callbackName, args = []) {
            const normalisedName = this.normaliseCallbackName(callbackName);

            if (normalisedName === '') {
                return null;
            }

            const registeredCallback = this.fieldCallbacks[normalisedName];

            if (typeof registeredCallback === 'function') {
                return registeredCallback;
            }

            if (normalisedName.startsWith('$wire.')) {
                const wirePath = normalisedName.replace(/^\$wire\./, '');
                const segments = wirePath.split('.').filter(Boolean);
                const wireOwner = this.resolveWireOwner(args);

                if (!wireOwner || segments.length === 0) {
                    return null;
                }

                let owner = wireOwner;
                let value = wireOwner;

                for (const segment of segments) {
                    owner = value;
                    value = value?.[segment];
                }

                if (typeof value === 'function') {
                    return value.bind(owner);
                }

                return null;
            }

            if (normalisedName.startsWith('$store.')) {
                const storePath = normalisedName.replace(/^\$store\./, '');
                const segments = storePath.split('.').filter(Boolean);
                const storeName = segments.shift();

                if (!storeName) {
                    return null;
                }

                let owner = Alpine.store(storeName);
                let value = owner;

                for (const segment of segments) {
                    owner = value;
                    value = value?.[segment];
                }

                if (typeof value === 'function') {
                    return value.bind(owner);
                }

                return null;
            }

            if (typeof window[normalisedName] === 'function') {
                return window[normalisedName].bind(window);
            }

            const segments = normalisedName.split('.').filter(Boolean);
            let owner = window;
            let value = window;

            for (const segment of segments) {
                owner = value;
                value = value?.[segment];
            }

            return typeof value === 'function' ? value.bind(owner) : null;
        },

        // Invoke a callback by name with the provided arguments. Returns true if the callback was successfully resolved and invoked, or false if no valid callback was found.
        invokeCallback(callbackName, ...args) {
            const callback = this.resolveCallback(callbackName, args);

            if (typeof callback !== 'function') {
                return false;
            }

            callback(...args);
            return true;
        },

        // Unregister a previously registered callback by name. Returns true if the callback was successfully unregistered, or false if no valid callback was found to unregister.
        unregisterCallback(callbackName) {
            const normalisedName = this.normaliseCallbackName(callbackName);

            if (normalisedName === '') {
                return false;
            }

            if (!Object.prototype.hasOwnProperty.call(this.fieldCallbacks, normalisedName)) {
                return false;
            }

            delete this.fieldCallbacks[normalisedName];
            return true;
        },

        // Clear all registered callbacks.
        clearCallbacks() {
            this.fieldCallbacks = {};
        },
    };

    // ======================================================
    // Alpine store registration and public API exposure
    // ======================================================
    Alpine.store('formStore', store);

    if (typeof window !== 'undefined') {
        // Public callback registry API.
        // Supported callback contracts:
        // - field onChange: (event)
        // - repeater configure: (params)
        // - repeater add/remove/move: (params)
        const helper = {
            registerCallback: (callbackName, callback) => Alpine.store('formStore')?.registerCallback(callbackName, callback) ?? '',
            registerCallbacks: callbacks => Alpine.store('formStore')?.registerCallbacks(callbacks) ?? [],
            resolveCallback: callbackName => Alpine.store('formStore')?.resolveCallback(callbackName) ?? null,
            invokeCallback: (callbackName, ...args) => Alpine.store('formStore')?.invokeCallback(callbackName, ...args) ?? false,
            unregisterCallback: callbackName => Alpine.store('formStore')?.unregisterCallback(callbackName) ?? false,
            clearCallbacks: () => Alpine.store('formStore')?.clearCallbacks(),
        };

        window.merosForms = helper;
        window.dispatchEvent(new CustomEvent('meros::forms-ready'));
    }
}