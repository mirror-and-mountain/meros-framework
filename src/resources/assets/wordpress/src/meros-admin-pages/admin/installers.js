
/**
 * Handles AJAX calls for installer action buttons in the admin interface.
 * 
 * @param {Event} e 
 * @returns {void}
 */
export default function handleProviderInstaller(e) {
    e.preventDefault();

    const button = e.currentTarget;
    const isInstallerButton   = button.classList.contains('meros-provider-installer-button');
    const isUpdateButton      = button.classList.contains('meros-provider-update-button');
    const isRollbackButton    = button.classList.contains('meros-provider-rollback-button');
    const isUninstallerButton = button.classList.contains('meros-provider-uninstaller-button');
    
    // Return if not valid button type
    if (!isInstallerButton && !isUpdateButton && !isRollbackButton && !isUninstallerButton) {
        return;
    }

    const provider = button.dataset.provider;
    const providerType = button.dataset.providerType;

    // Return if missing necessary data attributes
    if (!provider || !providerType) {
        return;
    }

    // Determine action based on button type
    const subAction = isInstallerButton 
        ? 'install' 
        : (isUpdateButton ? 'update' : (isRollbackButton ? 'rollback' : 'uninstall'));

    const plan = getActionPlan(button, subAction);
    showInstallerModal({
        button,
        provider,
        providerType,
        providerLabel: button.dataset.providerLabel || provider,
        subAction,
        plan,
    });
}

function getActionPlan(button, subAction) {
    /**
     * Reads the precomputed table plan for the selected action from button data attributes.
     * Falls back to an empty list when payload is missing or invalid.
     */
    const key = `installerPlan${subAction.charAt(0).toUpperCase()}${subAction.slice(1)}`;
    const raw = button.dataset[key] || '[]';

    try {
        const plan = JSON.parse(raw);
        return Array.isArray(plan) ? plan : [];
    } catch {
        return [];
    }
}

function showInstallerModal({ button, provider, providerType, providerLabel, subAction, plan }) {
    /**
     * Populates and opens the confirmation modal for installer operations.
     * If modal markup is unavailable, execution continues without modal interruption.
     */
    const modal = document.getElementById('meros-installer-modal');

    if (!modal) {
        executeInstallerAction({ button, provider, providerType, subAction });
        return;
    }

    const title = document.getElementById('meros-installer-modal-title');
    const description = document.getElementById('meros-installer-modal-description');
    const planList = document.getElementById('meros-installer-modal-plan-list');
    const cancelButton = document.getElementById('meros-installer-modal-cancel');
    const confirmButton = document.getElementById('meros-installer-modal-confirm');
    const closeElements = modal.querySelectorAll('[data-modal-close="1"]');

    if (!title || !description || !planList || !cancelButton || !confirmButton) {
        executeInstallerAction({ button, provider, providerType, subAction });
        return;
    }

    const actionLabel = subAction.charAt(0).toUpperCase() + subAction.slice(1);

    title.textContent = `${actionLabel} ${providerLabel} Tables`;
    description.textContent = `You are about to ${subAction} tables for ${providerLabel}. Back up your site and database before proceeding. This action cannot be undone.`;
    planList.innerHTML = '';

    if (!Array.isArray(plan) || plan.length === 0) {
        const li = document.createElement('li');
        li.className = 'meros-installer-plan-row';
        li.textContent = 'No table changes detected for this action.';
        planList.appendChild(li);
    } else {
        plan.forEach(item => {
            const li = document.createElement('li');
            li.className = 'meros-installer-plan-row';

            const badge = document.createElement('span');
            badge.className = `meros-installer-plan-badge meros-installer-plan-badge-${item.change || 'update'}`;
            badge.textContent = (item.change || 'update').toUpperCase();

            const text = document.createElement('span');
            text.className = 'meros-installer-plan-table';
            text.textContent = item.table || 'unknown_table';

            li.appendChild(badge);
            li.appendChild(text);
            planList.appendChild(li);
        });
    }

    const teardown = () => {
        modal.hidden = true;
        cancelButton.removeEventListener('click', onCancel);
        confirmButton.removeEventListener('click', onConfirm);
        document.removeEventListener('keydown', onKeydown);
        closeElements.forEach(element => element.removeEventListener('click', onCancel));
    };

    const onCancel = () => teardown();
    const onConfirm = () => {
        teardown();
        executeInstallerAction({ button, provider, providerType, subAction });
    };
    const onKeydown = event => {
        if (event.key === 'Escape') {
            teardown();
        }
    };

    cancelButton.addEventListener('click', onCancel);
    confirmButton.addEventListener('click', onConfirm);
    closeElements.forEach(element => element.addEventListener('click', onCancel));
    document.addEventListener('keydown', onKeydown);
    modal.hidden = false;
}

function executeInstallerAction({ button, provider, providerType, subAction }) {
    /**
     * Submits the installer action to WP AJAX and reloads the page with status query params.
     */
    button.classList.add('meros-working');

    const data = new FormData();
    data.append('action', 'meros_provider_install_operation');
    data.append('provider', provider);
    data.append('providerType', providerType);
    data.append('subAction', subAction);
    data.append('nonce', button.dataset.nonce);

    fetch(ajaxurl, {
        method: 'POST',
        credentials: 'same-origin',
        body: data
    })
    .then(r => r.json())
    .then(res => {
        if (!res.success) {
            alert(res.data?.message || 'Something went wrong');
            button.classList.remove('meros-working');
            return;
        }

        const operation = subAction === 'install'
            ? 'installed'
            : (subAction === 'update' ? 'updated' : (subAction === 'rollback' ? 'rolled-back' : 'uninstalled'));

        setTimeout(() => {
            const url = new URL(window.location.href);
            url.searchParams.set('provider', provider);
            url.searchParams.set('providerType', providerType);
            url.searchParams.set('operation', operation);
            window.location.href = url.toString();
        }, 400);
    })
    .catch(() => {
        alert('Something went wrong');
        button.classList.remove('meros-working');
    });
}