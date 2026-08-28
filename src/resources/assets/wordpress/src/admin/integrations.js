
export function __meros_integrations_switch_environment(event) {
    event.preventDefault();

    const selector = event.currentTarget;

    if (!selector || !selector.hasAttribute('data-meros-integration-env-switch')) {
        return;
    }

    if (!selector.dataset.intName || !selector.dataset.nonce || !selector.dataset.action) return;

    const integrationName = selector.dataset.intName;
    const action          = selector.dataset.action + '_' + integrationName;
    const nonce           = selector.dataset.nonce;
    const env             = selector.value;

    if (!env) return;

    const data = new FormData();
    data.append('action', action);
    data.append('nonce', nonce);
    data.append('env', env);

    fetch(ajaxurl, {
        method: 'POST',
        body: data,
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        }

        else if (data.data && data.data.message) {
            const message = data.data.message;
            console.error('Could not switch current integration environment: ', message);
        }

        else {
            console.error('Could not switch current integration environment.');
        }
    })
    .catch((error) => {
        console.error('Could not switch current integration environment: ', error);
    });
}

export function __meros_integrations_oauth_connect(event) {
    event.preventDefault();

    const button = event.target;
    if (!button || button.getAttribute('id') !== 'meros-integration-connect' || !button.dataset.intName || !button.dataset.nonce) return;

    const integrationName = button.dataset.intName;
    const action = 'meros_integration_oauth_start_' + integrationName;
    const nonce  = button.dataset.nonce;

    const data = new FormData();
    data.append('action', action);
    data.append('nonce', nonce);

    fetch(ajaxurl, {
        method: 'POST',
        body: data,
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.data && data.data.authorisation_url) {
            const url = data.data.authorisation_url;
            window.location.replace(url);
        }

        else if (data.data && data.data.message) {
            const message = data.data.message;
            console.error('Could not switch current integration environment: ', message);
        }

        else {
            console.error('Could not switch current integration environment.');
        }
    })
    .catch((error) => {
        console.error('Could not switch current integration environment: ', error);
    });
}