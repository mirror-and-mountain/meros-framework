import { 
    __meros_tables_single_operation, 
    __meros_tables_multi_operation 
} from './tables.js';

import {
    __meros_integrations_switch_environment,
    __meros_integrations_oauth_connect,
    __meros_integrations_init_connections_repeater,
    __meros_integrations_revoke_connection
} from './integrations.js';

import './style.scss';

document.addEventListener('DOMContentLoaded', () => {
    // Setup event listeners for table action buttons
    const singleTableActionButtons = document.querySelectorAll('.meros-table-card-action-button');
    singleTableActionButtons.forEach(button => {
        button.addEventListener('click', __meros_tables_single_operation);
    });

    const multiTableActionButtons = document.querySelectorAll('.meros-tables-action-button');
    multiTableActionButtons.forEach(button => {
        button.addEventListener('click', __meros_tables_multi_operation);
    });

    // Setup listeners for integrations
    const integrationEnvironmentSelectors = document.querySelectorAll('select[data-meros-integration-env-switch]');
    integrationEnvironmentSelectors.forEach(selector => {
        selector.addEventListener('change', __meros_integrations_switch_environment);
    });

    const oauthConnectButton = document.getElementById('meros-integration-connect');
    if (oauthConnectButton) {
        oauthConnectButton.addEventListener('click', __meros_integrations_oauth_connect);
    }

    // Expose the init connections repeater function to the global scope
    window.__meros_integrations_init_connections_repeater = __meros_integrations_init_connections_repeater;

    // Expose the revoke connection function to the global scope
    window.__meros_integrations_revoke_connection = __meros_integrations_revoke_connection;
});