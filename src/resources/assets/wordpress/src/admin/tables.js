import MerosModal from '../classes/modal.js';

export function __meros_tables_multi_operation(event) {
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

    // Open modal
    __meros_tables_show_multi_operation_modal(tables, operation, button, provider, nonce);
}

/**
 * Opens a modal for a multi-table operation.
 * 
 * @param {*} tables 
 * @param {*} operation 
 * @param {*} button 
 * @param {*} provider 
 * @param {*} nonce 
 */
function __meros_tables_show_multi_operation_modal(tables, operation, button, provider, nonce) {
    let modalContent = `<p>Are you sure you want to <strong>${operation}</strong> all the following tables?</p>`;

    modalContent += '<ul>';
    tables.forEach((table) => {
        modalContent += `<li><strong>${table.name}</strong></li>`;
    });
    modalContent += '</ul>';
    modalContent += `<p>This action cannot be undone. We strongly recommend backing up your database before carrying out any table operations.</p>`;

    const modalTitle = operation.charAt(0).toUpperCase() + operation.slice(1).replace('_', ' ') + ' tables';
    const confirmButtonText = operation.charAt(0).toUpperCase() + operation.slice(1).replace('_', ' ');

    const modal = new MerosModal(
        modalTitle, 
        modalContent, 
        confirmButtonText, 
        'Cancel', 
        false
    );

    modal.onConfirm(() => {
        __meros_tables_execute_operation(button, provider, operation, nonce, modal);
    });

    modal.show();
}

/**
 * Handles table operations (install, uninstall, update, rollback) triggered by the user.
 * 
 * @param {Event} event 
 * @returns {void}
 */
export function __meros_tables_single_operation(event) {
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

    const updatesList = operation === 'update' ? __meros_tables_get_updates_list('update', tableCard) : null;
    const rollbackContent = operation === 'rollback' ? __meros_tables_get_updates_list('rollback', tableCard) : null;

    let modalContent = `<p>Are you sure you want to <strong>${button.dataset.action}</strong> the table <strong>${button.dataset.table}</strong>?</p>`;
    
    if (updatesList) {
        modalContent += `<p>The following updates will be applied:</p>${updatesList}`;
    }

    if (rollbackContent) {
        modalContent += `<p>The following rollback will be applied:</p>${rollbackContent}`;
    }

    modalContent += `<p>This action cannot be undone. We strongly recommend backing up your database before carrying out any table operations.</p>`;

    // Open modal
    __meros_tables_show_single_operation_modal(table, modalContent, operation, button, provider, nonce);
}

/**
 * Opens a modal for a single-table operation
 * 
 * @param {*} table 
 * @param {*} content 
 * @param {*} operation 
 * @param {*} button 
 * @param {*} provider 
 * @param {*} nonce 
 */
function __meros_tables_show_single_operation_modal(table, content, operation, button, provider, nonce) {
    const modal = new MerosModal(
        operation.charAt(0).toUpperCase() + operation.slice(1) + ' Table',
        content,
        operation.charAt(0).toUpperCase() + operation.slice(1),
        'Cancel',
        false  
    );

    modal.onConfirm(() => {
        __meros_tables_execute_operation(button, provider, operation, nonce, modal, table);
    });

    modal.show();
}

/**
 * Retrieves the list of updates or rollback information for a given table card based on the specified operation.
 * 
 * @param {string} operation 
 * @param {HTMLElement} tableCard 
 * @returns {string|null}
 */
function __meros_tables_get_updates_list(operation, tableCard) {
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
 * @param {MerosModal} modal
 * @param {string|null} table 
 */
function __meros_tables_execute_operation(button, provider, operation, nonce, modal, table = null) {
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
            __meros_tables_configure_modal_response(modal, 'Table operation completed successfully.', 'green', true);
        } 
        
        else if (data.data && data.data.message) {
            __meros_tables_configure_modal_response(modal, 'An error occured during the table operation: ' + data.data.message, 'red');
        }

        else {
            __meros_tables_configure_modal_response(modal, 'An unknown error occurred during the table operation.', 'red');
        }
    })
    .catch((error) => {
        __meros_tables_configure_modal_response(modal, 'An error occurred during the table operation: ' + error.message, 'red');
    });
}

/**
 * Displays a message in the admin modal and configures the modal buttons
 * following a table operation.
 * 
 * @param {MerosModal} modal
 * @param {string} message 
 * @param {string} color
 * @param {boolean} reload
 */
function __meros_tables_configure_modal_response(modal, message, color, reload = false) {
    modal.setExtraContent(message, color);
    modal.hideConfirmButton();
    modal.setCancelButtonText('Close');
    modal.enableCancelButton();
    
    if (reload) {
        modal.onCancel(() => {
            location.reload();
        });
    }
}