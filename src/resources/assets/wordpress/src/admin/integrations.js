import MerosModal from '../classes/modal.js';

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
        if (data.success && data.data) {
            const existingConnection = data.data?.existing_connection ?? false;

            if (existingConnection) {
                const redirectUrl = data.data?.redirect_url ?? false;
                if (redirectUrl) {
                    window.location.replace(redirectUrl);
                } else {
                    window.location.reload();
                }
            }

            const url = data.data?.authorisation_url ?? false;

            if (!url) {
                console.error("Couldn't get authorisation URL for integration: ", integrationName);
                return;
            }

            window.location.replace(url);
        }

        else if (data.data && data.data.message) {
            const message = data.data.message;
            console.error('An error occurred: ', message);
        }

        else {
            console.error('An error occurred while trying to connect.');
        }
    })
    .catch((error) => {
        console.error('An error occurred while trying to connect: ', error);
    });
}

export function __meros_integrations_init_connections_repeater(repeater) {
    const rows = repeater.resolveRows();

    rows.forEach(row => {
        const rowData = repeater.getRowData(row);
        const status = rowData?.connection_status ?? null;
        const removeButton = row.querySelector('button.meros-repeater-table-button--remove');

        if (!removeButton) return;

        if (status === 'revoked') {
            removeButton.innerText = 'Delete';
        }
    });
}

export function __meros_integrations_revoke_connection(row) {
    const modal = new MerosModal(
        'Revoke Connection',
        'Are you sure you want to revoke this connection? This action cannot be undone.',
        'Revoke'
    );

    const removeButton = row.querySelector('button.meros-repeater-table-button--remove');
    const deleteConnection = removeButton && removeButton.innerText === 'Delete' ? true : false;

    if (deleteConnection) {
        modal.setTitle('Delete Connection');
        modal.setContent('Are you sure you want to delete this connection? This action cannot be undone.');
        modal.setConfirmButtonText('Delete');
    }

    modal.onConfirm(() => {
        const connectionId = row.querySelector('input[data-repeater-field-name="connection_id"]').value;
        if (!connectionId) {
            return;
        }

        const integrationId = row.querySelector('input[data-repeater-field-name="connection_integration_id"]').value;
        if (!integrationId) {
            return;
        }
            
        const action = 'meros_integration_revoke_connection' + '_' + integrationId;
        const nonce  = row.querySelector('input[data-repeater-field-name="connection_revoke_nonce"]').value;

        if (!nonce) {
            return;
        }

        const data = new FormData();
        data.append('action', action);
        data.append('connection_id', connectionId);
        data.append('nonce', nonce);

        fetch(ajaxurl, {
            method: 'POST',
            body: data,
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const url = new URL(window.location.href);
                url.searchParams.set('status', deleteConnection ? 'connection_deleted' : 'connection_revoked');
                window.location.href = url.toString();
            } else if (data.data && data.data.message) {
                const message = data.data.message;
                alert('Could not revoke connection: ' + message);
            } else {
                alert('Could not revoke connection.');
            }
        })
        .catch((error) => {
            alert('Could not revoke connection: ' + error.message);
        });
    });

    modal.show();
    return false;
}