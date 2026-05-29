function getRepeaterFieldStore() {
    if (typeof Alpine === 'undefined' || typeof Alpine.store !== 'function') {
        return null;
    }

    try {
        return Alpine.store('repeaterField');
    } catch (error) {
        return null;
    }
}

export function buildRepeaterDialogFromHtml(bodyHtml = '', onUpdate = null) {
    const repeaterFieldStore = getRepeaterFieldStore();

    if (typeof repeaterFieldStore?.buildRepeaterDialogFromHtml !== 'function') {
        return null;
    }

    return repeaterFieldStore.buildRepeaterDialogFromHtml(bodyHtml, onUpdate);
}

export function openRepeaterDialogFromHtml(shellHtml = '', onUpdate = null) {
    const repeaterFieldStore = getRepeaterFieldStore();

    if (typeof repeaterFieldStore?.openRepeaterDialogFromHtml !== 'function') {
        return null;
    }

    return repeaterFieldStore.openRepeaterDialogFromHtml(shellHtml, onUpdate);
}

if (typeof window !== 'undefined' && typeof window.merosDefaultRepeaterRowConfig !== 'function') {
    window.merosDefaultRepeaterRowConfig = payload => {
        const repeaterFieldStore = getRepeaterFieldStore();

        if (typeof repeaterFieldStore?.defaultConfigureRowModal === 'function') {
            repeaterFieldStore.defaultConfigureRowModal(payload);
        }
    };
}
