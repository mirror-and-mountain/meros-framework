
/**
 * Handles AJAX calls for installer action buttons in the admin interface.
 * 
 * @param {Event} e 
 * @returns {void}
 */
export default function handleProviderInstaller(e) {
    const button = e.target;
    const isInstallerButton   = button.classList.contains('meros-provider-installer-button');
    const isUpdateButton      = button.classList.contains('meros-provider-update-button');
    const isUninstallerButton = button.classList.contains('meros-provider-uninstaller-button');
    
    // Return if not valid button type
    if (!isInstallerButton && !isUpdateButton && !isUninstallerButton) {
        return;
    }

    const provider = button.dataset.provider;
    const providerType = button.dataset.providerType;

    // Return if missing necessary data attributes
    if (!provider || !providerType) {
        return;
    }

    // Add busy class to button
    button.classList.add('meros-working');

    // Determine action based on button type
    const subAction = isInstallerButton 
        ? 'install' 
        : (isUpdateButton ? 'update' : 'uninstall');

    // Confirm update/uninstall actions
    if (subAction === 'update' || subAction === 'uninstall') {
        if (!confirm(`Are you sure you want to ${subAction} "${provider}"? We strongly recommend backing up your site before proceeding as this action cannot be undone.`)) {
            button.classList.remove('meros-working');
            return;
        }
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

        // Add query params and reload
        const operation = subAction === 'install' 
            ? 'installed' 
            : (subAction === 'update' ? 'updated' : 'uninstalled');

        setTimeout(() => {
            const url = new URL(window.location.href);
            url.searchParams.set('provider', provider);
            url.searchParams.set('operation', operation);
            window.location.href = url.toString();
        }, 1000); // Slight delay before reload
    });
}