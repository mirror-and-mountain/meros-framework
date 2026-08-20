
/**
 * Handles AJAX calls for OAuth start buttons in the admin interface.
 * 
 * @param {Event} e 
 * @returns {void}
 */
export function handleOauthStart(e) {
    e.preventDefault();

    const button = e.currentTarget;
    const isOauthButton = button.dataset.merosAction === 'oauth-start';

    if (!isOauthButton) {
        return;
    }

    const integration = button.dataset.integration;
    const returnUrl = button.dataset.returnUrl;
    const nonce = button.dataset.nonce;

    if (!integration || !returnUrl || !nonce) {
        return;
    }

    executeOauthStart({ button, integration, returnUrl, nonce });
}

/**
 * Handles the change of integration environments in the admin interface.
 * 
 * @param {Event} e 
 * @returns {void}
 */
export function handleIntegrationEnvironmentChange(e) {
    e.preventDefault();

    const selector = e.currentTarget;
    const value = selector.value;

    if (!value) {
        return;
    }

    const data = new FormData();
    data.append('action', 'meros_integration_environment_change');
    data.append('integration', selector.dataset.integration);
    data.append('environment', value);
    data.append('nonce', selector.dataset.nonce);

    fetch(ajaxurl, {
        method: 'POST',
        credentials: 'same-origin',
        body: data
    })
    .then(r => r.json())
    .then(res => {
        if (!res.success) {
            alert('An error occurred while changing the integration environment: ' + (res.data?.message || 'Unknown error'));
            return;
        }

        // Reload the page to reflect the environment change
        window.location.reload();
    })
    .catch(() => {
        alert('An error occurred while changing the integration environment: Unknown error.');
    });
}

function executeOauthStart({ button, integration, returnUrl, nonce }) {
    button.classList.add('meros-working');

    const data = new FormData();
    data.append('action', 'meros_integration_oauth_start');
    data.append('integration', integration);
    data.append('return_url', returnUrl);
    data.append('nonce', nonce);

    const errorPrefix = 'An error occurred while starting the OAuth flow: ';

    fetch(ajaxurl, {
        method: 'POST',
        credentials: 'same-origin',
        body: data
    })
    .then(r => r.json())
    .then(res => {
        if (!res.success) {
            alert(errorPrefix + (res.data?.message || 'Unknown error'));
            button.classList.remove('meros-working');
            return;
        }

        if (res.data?.auth_url) {
            window.location.href = res.data.auth_url;
        } else {
            alert(errorPrefix + 'No authorizationURL provided by the server.');
            button.classList.remove('meros-working');
        }
    })
    .catch(() => {
        alert(errorPrefix + 'Unknown error.');
        button.classList.remove('meros-working');
    });
}