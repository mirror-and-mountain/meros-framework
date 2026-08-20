
/**
 * Handles AJAX calls for installer action buttons in the admin interface.
 * 
 * @param {Event} e 
 * @returns {void}
 */
export default function handleProviderInstaller(e) {
    e.preventDefault();

    const button = e.currentTarget;
    const isInstallerButton   = button.classList.contains('meros-provider-install-button');
    const isUpdateButton      = button.classList.contains('meros-provider-update-button');
    const isRollbackButton    = button.classList.contains('meros-provider-rollback-button');
    const isUninstallerButton = button.classList.contains('meros-provider-uninstall-button');
    
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

    executeInstallerAction({ button, provider, providerType, subAction });
}

function executeInstallerAction({ button, provider, providerType, subAction }) {
    /**
     * Submits the installer action to WP AJAX and reloads the page with status query params.
     */
    button.classList.add('meros-working');

    const confirmMsg = `Are you sure you want to ${subAction} the ${providerType} "${provider}"? This action cannot be undone. We highly recommend backing up your site before proceeding.`;

    if (confirm(confirmMsg) === false) {
        button.classList.remove('meros-working');
        return;
    }

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
            url.searchParams.set('operation', operation);
            window.location.href = url.toString();
        }, 400);
    })
    .catch(() => {
        alert('Something went wrong');
        button.classList.remove('meros-working');
    });
}