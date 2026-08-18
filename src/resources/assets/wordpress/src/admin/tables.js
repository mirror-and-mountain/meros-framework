import { 
    merosShowAdminModal, 
    merosAdminModalHideConfirmButton,
    merosAdminModalSetCancelButtonText,
    merosAdminModalSetCloseButtonCallback,
    merosAdminModalSetExtraContent
} from './modal.js';

export function merosHandleMultiTableOperation(event) {
    event.preventDefault();

    const button = event.currentTarget;

    // Validate button and its dataset attributes
    if (!button || !button.classList.contains('meros-tables-action-button')) return;
    if (!button.dataset || !button.dataset.provider || !button.dataset.action || !button.dataset.nonce || !button.dataset.tables) return;

    const provider  = button.dataset.provider;
    const operation = button.dataset.action;
    const nonce     = button.dataset.nonce;
    
    let tables;
    try {
        tables = JSON.parse(button.dataset.tables);
        tables = Array.isArray(tables) ? tables : Object.values(tables || {});
        tables = tables.map((table) => {
            return typeof table === 'string' ? JSON.parse(table) : table;
        });
    } catch (error) {
        return;
    }

    if (!tables || !tables.length) return;

    let modalContent = `<p>Are you sure you want to <strong>${operation}</strong> all the following tables?</p>`;

    modalContent += '<ul>';
    tables.forEach((table) => {
        modalContent += `<li><strong>${table.name}</strong></li>`;
    });
    modalContent += '</ul>';
    modalContent += `<p>This action cannot be undone. We strongly recommend backing up your database before carrying out any table operations.</p>`;

    const modalTitle = operation.charAt(0).toUpperCase() + operation.slice(1).replace('_', ' ') + ' tables';
    const confirmButtonText = operation.charAt(0).toUpperCase() + operation.slice(1).replace('_', ' ');

    merosShowAdminModal(
        modalTitle,
        modalContent,
        () => merosExecuteTableOperation(button, provider, operation, nonce),
        confirmButtonText,
        false
    );
}

/**
 * Handles table operations (install, uninstall, update, rollback) triggered by the user.
 * 
 * @param {Event} event 
 * @returns {void}
 */
export function merosHandleSingleTableOperation(event) {
    event.preventDefault();

    const button = event.currentTarget;

    // Validate button and its dataset attributes
    if (!button || !button.classList.contains('meros-table-card-action-button')) return;
    if (!button.dataset || !button.dataset.provider || !button.dataset.action || !button.dataset.table || !button.dataset.nonce) return;

    const provider  = button.dataset.provider;
    const operation = button.dataset.action;
    const table     = button.dataset.table;
    const nonce     = button.dataset.nonce;
    const tableCard = button.closest('.meros-table-card');

    const updatesList = operation === 'update' ? merosGetUpdatesList('update', tableCard) : null;
    const rollbackContent = operation === 'rollback' ? merosGetUpdatesList('rollback', tableCard) : null;

    let modalContent = `<p>Are you sure you want to <strong>${button.dataset.action}</strong> the table <strong>${button.dataset.table}</strong>?</p>`;
    
    if (updatesList) {
        modalContent += `<p>The following updates will be applied:</p>${updatesList}`;
    }

    if (rollbackContent) {
        modalContent += `<p>The following rollback will be applied:</p>${rollbackContent}`;
    }

    modalContent += `<p>This action cannot be undone. We strongly recommend backing up your database before carrying out any table operations.</p>`;

    merosShowAdminModal(
        operation.charAt(0).toUpperCase() + operation.slice(1) + ' Table',
        modalContent,
        () => merosExecuteTableOperation(button, provider, operation, nonce, table),
        operation.charAt(0).toUpperCase() + operation.slice(1),
        false
    );
}

/**
 * Retrieves the list of updates or rollback information for a given table card based on the specified operation.
 * 
 * @param {string} operation 
 * @param {HTMLElement} tableCard 
 * @returns {string|null}
 */
function merosGetUpdatesList(operation, tableCard) {
    let updatesList = null;
    let rollbackContent = null;
    
    if (operation === 'update' && 
        tableCard && 
        tableCard.querySelector('.meros-table-card-updates')
    ) {
        const updatesData = tableCard.querySelector('.meros-table-card-updates').dataset.updates;
        if (updatesData) {
            try {
                let updates = JSON.parse(updatesData);
                let updateItems = Array.isArray(updates)
                    ? updates
                    : Object.values(updates || {});

                // The data may be double-encoded JSON, so parse each item if needed
                updateItems = updateItems.map((item) => {
                    return typeof item === 'string' ? JSON.parse(item) : item;
                });

                if (updateItems.length) {
                    updatesList = '<ol>';
                    updateItems.forEach((update) => {
                        updatesList += `<li><p><strong>${update.label}</strong>: <br>${update.description}</p></li>`;
                    });
                    updatesList += '</ol>';
                }

                return updatesList;
            } catch (error) {
                return null; // Leave updatesList as null if parsing fails
            }
        }
    }

    else if (operation === 'rollback' && 
        tableCard && 
        tableCard.querySelector('.meros-table-card-rollback')
    ) {
        const rollbackData = tableCard.querySelector('.meros-table-card-rollback').dataset.rollback;
        if (rollbackData) {
            try {
                let rollback = JSON.parse(rollbackData);
                if (typeof rollback === 'string') {
                    rollback = JSON.parse(rollback);
                }
                if (rollback) {
                    rollbackContent = `<p><strong>${rollback.label}</strong>: <br>${rollback.description}</p>`;
                }

            return rollbackContent;
            } catch (error) {
                return null; // Leave rollbackContent as null if parsing fails
            }
        }
    }

    return null;
}

/**
 * Executes the specified table operation by sending an AJAX request to the server.
 * 
 * @param {HTMLButtonElement} button
 * @param {string} provider
 * @param {string} operation 
 * @param {string} nonce
 * @param {string|null} table 
 */
function merosExecuteTableOperation(button, provider, operation, nonce, table = null) {
    const data = new FormData();
    
    data.append('action', 'meros_handle_table_action_' + provider);
    data.append('operation', operation);
    data.append('nonce', nonce);

    if (!operation.endsWith('_all')) {
        data.append('table', table);
    } else {
        data.append('multi', 'true');
    }

    button.disabled = true; // Disable the button to prevent multiple clicks

    fetch(ajaxurl, {
        method: 'POST',
        body: data,
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            button.disabled = false; // Re-enable the button after the operation completes
            configureModalResponse('Table operation completed successfully.', 'green', true);
        } 
        
        else if (data.data && data.data.message) {
            configureModalResponse('An error occured during the table operation: ' + data.data.message, 'red');
        }

        else {
            configureModalResponse('An unknown error occurred during the table operation.', 'red');
        }
    })
    .catch((error) => {
        configureModalResponse('An error occurred during the table operation: ' + error.message, 'red');
    });
}

/**
 * Displays a message in the admin modal and configures the modal buttons
 * following a table operation.
 * 
 * @param {string} message 
 * @param {string} color
 */
function configureModalResponse(message, color, reload = false) {
    merosAdminModalSetExtraContent(message, color);
    merosAdminModalHideConfirmButton();
    merosAdminModalSetCancelButtonText('Close');
    
    if (reload) {
        merosAdminModalSetCloseButtonCallback(() => {
            location.reload();
        });
    }
}