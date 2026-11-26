const MerosModal = window.MerosModal;

export async function configureRepeaterDefaults(card) {
    const editor = card.querySelector('.mforms-builder-repeater-editor');
    const input = editor?.querySelector('[data-builder-repeater-defaults]');
    const columnsElement = editor?.querySelector(':scope > div > .meros-repeater-field');
    const columnsComponent = columnsElement && window.Alpine?.$data(columnsElement);
    if (!input || !columnsComponent || typeof columnsComponent.getValue !== 'function') {
        throw new Error('The repeater column editor is unavailable.');
    }

    const columns = columnsComponent.getValue();
    if (columns.length === 0) {
        window.alert('Add at least one repeater column before setting default values.');
        return;
    }

    let root = card.closest('.meros-form-builder');
    while (root?.parentElement?.closest('.meros-form-builder')) {
        root = root.parentElement.closest('.meros-form-builder');
    }
    const { repeaterDefaultsUrl: url, repeaterDefaultsAction: action, repeaterDefaultsNonce: nonce, postId } = root?.dataset || {};
    if (!url || !action || !nonce || !postId) {
        throw new Error('The repeater default editor request is not configured.');
    }

    const modal = new MerosModal('Set repeater default value', 'Loading...', 'Save', 'Cancel', false);
    modal.show();
    modal.disableConfirmButton();

    const request = new FormData();
    request.append('action', action);
    request.append('nonce', nonce);
    request.append('post_id', postId);
    request.append('columns', JSON.stringify(columns));
    request.append('defaults', input.value);

    try {
        const response = await fetch(url, { method: 'POST', body: request });
        const result = await response.json();
        if (!response.ok || !result.success || !result.data?.html) {
            throw new Error(result.data?.message || 'Unable to load the repeater default editor.');
        }
        if (!modal.modal) return;

        modal.setContent(result.data.html);
        const preview = modal.content.querySelector('.meros-repeater-field');
        if (!preview || !window.Alpine || typeof window.Alpine.initTree !== 'function') {
            throw new Error('The repeater default editor could not be initialized.');
        }
        Object.assign(modal.content.dataset, {
            builderRepeaterPreview: '1',
            repeaterDefaultsUrl: url,
            repeaterDefaultsAction: action,
            repeaterDefaultsNonce: nonce,
            postId,
            columns: JSON.stringify(columns),
        });
        window.Alpine.initTree(preview);
        modal.onConfirm(() => {
            const component = window.Alpine.$data(preview);
            if (!component || typeof component.getValue !== 'function') {
                modal.enableButtons();
                modal.setExtraContent('The repeater default editor is unavailable.', 'red', '1rem');
                return;
            }
            input.value = JSON.stringify(component.getValue());
            input.dispatchEvent(new Event('input', { bubbles: true }));
            modal.hide();
        });
        modal.enableConfirmButton();
        modal.focusContent();
    } catch (error) {
        if (!modal.modal) return;
        modal.content.textContent = error instanceof Error ? error.message : 'Unable to load the repeater default editor.';
        modal.content.setAttribute('role', 'alert');
        modal.hideConfirmButton();
    }
}
