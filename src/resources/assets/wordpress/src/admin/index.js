import { 
    __meros_tables_single_operation, 
    __meros_tables_multi_operation 
} from './tables.js';

import {
    __meros_integrations_switch_environment,
    __meros_integrations_oauth_connect
} from './integrations.js';

import './style.scss';

document.addEventListener('DOMContentLoaded', () => {
    // Attach event listeners to all action buttons/selectors
    const singleTableActionButtons = document.querySelectorAll('.meros-table-card-action-button');
    singleTableActionButtons.forEach(button => {
        button.addEventListener('click', __meros_tables_single_operation);
    });

    const multiTableActionButtons = document.querySelectorAll('.meros-tables-action-button');
    multiTableActionButtons.forEach(button => {
        button.addEventListener('click', __meros_tables_multi_operation);
    });

    const integrationEnvironmentSelectors = document.querySelectorAll('select[data-meros-integration-env-switch]');
    integrationEnvironmentSelectors.forEach(selector => {
        selector.addEventListener('change', __meros_integrations_switch_environment);
    });

    const oauthConnectButton = document.getElementById('meros-integration-connect');
    if (oauthConnectButton) {
        oauthConnectButton.addEventListener('click', __meros_integrations_oauth_connect);
    }
});