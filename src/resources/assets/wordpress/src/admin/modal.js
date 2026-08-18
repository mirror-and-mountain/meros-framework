
/**
 * Shows the Meros admin modal with the specified title and content, creating the modal if
 * it does not already exist in the DOM.
 * 
 * A callback may be passed as a third parameter which will be executed when the confirm button is clicked. 
 * If no callback is provided, the modal will simply close when the confirm button is clicked.
 * 
 * To keep the modal open after the confirm button is clicked, pass `false` as the fourth parameter. By default, the modal will close after the confirm button is clicked.
 * You should only set this to `false` if you are handling the modal closing in your callback function.
 * 
 * @param {string} title 
 * @param {string} content
 * @param {Function|null} callback - Optional callback function to be executed when the confirm button is clicked.
 * @returns {void}
 */
export function merosShowAdminModal(title, content, callback = null, confirmButtonText = 'Confirm', closeModal = true) {
    let modal = document.getElementById('meros-admin-modal-overlay');

    if (!modal) {
        merosMakeAdminModal();
        modal = document.getElementById('meros-admin-modal-overlay');
        if (!modal) {
            console.error('Failed to create Meros admin modal.');
            return;
        }
    }

    const modalTitle = modal.querySelector('.meros-admin-modal-title');
    const modalContent = modal.querySelector('.meros-admin-modal-content');

    if (modalTitle) {
        modalTitle.textContent = title;
    }

    if (modalContent) {
        modalContent.innerHTML = content;
    }

    const modalConfirmButton = modal.querySelector('.meros-admin-modal-confirm-button');
    const modalCancelButton = modal.querySelector('.meros-admin-modal-cancel-button');

    if (modalConfirmButton) {
        modalConfirmButton.textContent = confirmButtonText;

        modalConfirmButton.onclick = function() {
            modalConfirmButton.disabled = true; // Disable the confirm button to prevent multiple clicks
            modalConfirmButton.classList.add('meros-working'); // Add a class to indicate the button is in a working state
            
            if (modalCancelButton) {
                modalCancelButton.disabled = true; // Disable the cancel button to prevent multiple clicks
            }

            if (typeof callback === 'function') {
                callback();
            }

            if (closeModal) {
                merosHideAdminModal();
            }
        };
    }

    if (modalCancelButton) {
        modalCancelButton.onclick = function() {
            merosHideAdminModal();
        };
    }

    modal.classList.remove('meros-admin-modal-hidden');
}

/**
 * Appends extra content to the Meros admin modal. This function can be used to add additional information or elements to the modal after it has been displayed.
 * 
 * @param {string} content - The HTML content to be added to the extra content section of the modal.
 * @param {string|null} color - Optional color for the extra content. If provided, it will be applied to the text color of the extra content.
 * @returns {void}
 */
export function merosAdminModalSetExtraContent(content, color = null) {
    const modal = document.getElementById('meros-admin-modal-overlay');
    if (!modal) return;

    const extraContent = modal.querySelector('.meros-admin-modal-extra-content');

    if (extraContent) {
        extraContent.innerHTML = content;

        if (color) {
            extraContent.style.color = color;
        }
    }
}

/**
 * Hides the confirm button in the Meros admin modal. 
 * This function can be used to remove the confirm button from the 
 * modal when it is not needed, such as when displaying informational messages or errors.
 * 
 * @returns {void}
 */
export function merosAdminModalHideConfirmButton() {
    const modal = document.getElementById('meros-admin-modal-overlay');
    if (!modal) return;

    const modalConfirmButton = modal.querySelector('.meros-admin-modal-confirm-button');

    if (modalConfirmButton) {
        modalConfirmButton.style.display = 'none';
    }
}

/**
 * Shows the confirm button in the Meros admin modal if it is currently hidden. 
 * This function can be used to make the confirm button visible again after it has been hidden.
 * 
 * @returns {void}
 */
export function merosAdminModalShowConfirmButton() {
    const modal = document.getElementById('meros-admin-modal-overlay');
    if (!modal) return;

    const modalConfirmButton = modal.querySelector('.meros-admin-modal-confirm-button');

    if (modalConfirmButton) {
        modalConfirmButton.style.display = 'block';
    }
}

/**
 * Changes the text of the cancel button in the Meros admin modal. 
 * If the cancel button is currently disabled, it will be re-enabled.
 * 
 * @param {string} text - The text to set for the cancel button.
 * @returns {void}
 */
export function merosAdminModalSetCancelButtonText(text) {
    const modal = document.getElementById('meros-admin-modal-overlay');
    if (!modal) return;

    const modalCancelButton = modal.querySelector('.meros-admin-modal-cancel-button');

    if (modalCancelButton) {
        modalCancelButton.textContent = text;
    }

    if (modalCancelButton.disabled) {
        modalCancelButton.disabled = false; // Re-enable the cancel button if it was disabled
    }
}

export function merosAdminModalSetCloseButtonCallback(callback) {
    const modal = document.getElementById('meros-admin-modal-overlay');
    if (!modal) return;

    if (typeof callback !== 'function') return;

    const modalCancelButton = modal.querySelector('.meros-admin-modal-cancel-button');

    if (modalCancelButton) {
        modalCancelButton.onclick = function() {
            callback();
            merosHideAdminModal(callback);
        };
    }
}

/**
 * Hides the Meros admin modal by adding the 'meros-admin-modal-hidden' class to the overlay
 * and resetting its content and button states.
 * 
 * @param {Function|null} callback - Optional callback function to be executed after the modal is hidden.
 * 
 * @returns {void}
 */
export function merosHideAdminModal(callback = null) {
    const modal = document.getElementById('meros-admin-modal-overlay');

    if (!modal) {
        console.error('Meros admin modal not found.');
        return;
    }

    // Reset button states
    const modalConfirmButton = modal.querySelector('.meros-admin-modal-confirm-button');
    const modalCancelButton = modal.querySelector('.meros-admin-modal-cancel-button');

    if (modalConfirmButton) {
        modalConfirmButton.disabled = false;
        modalConfirmButton.classList.remove('meros-working');
        modalConfirmButton.textContent = 'Confirm'; // Reset to default text
        modalConfirmButton.onclick = null; // Remove any existing click handlers
        modalConfirmButton.style.display = 'block'; // Ensure the confirm button is visible
    }

    if (modalCancelButton) {
        modalCancelButton.disabled = false;
        modalCancelButton.onclick = null; // Remove any existing click handlers
        modalCancelButton.textContent = 'Cancel'; // Reset to default text
    }

    // Clear modal content
    const modalTitle   = modal.querySelector('.meros-admin-modal-title');
    const modalContent = modal.querySelector('.meros-admin-modal-content');
    const extraContent = modal.querySelector('.meros-admin-modal-extra-content');
    
    if (modalTitle) {
        modalTitle.textContent = ''; // Clear the title
    }
    if (modalContent) {
        modalContent.innerHTML = ''; // Clear the content
    }

    if (extraContent) {
        extraContent.innerHTML = ''; // Clear any extra content
        extraContent.style.color = ''; // Reset the color of the extra content
    }

    modal.classList.add('meros-admin-modal-hidden');

    if (typeof callback === 'function') {
        callback();
    }
}

/**
 * Creates the Meros admin modal and appends it to the document body.
 * This function is called when the modal is first needed and does not exist in the DOM.
 * It sets up the structure of the modal, including title, content area, and action buttons.
 * 
 * @returns {void}
 */
function merosMakeAdminModal() {
    const overlay = document.createElement('div');
    overlay.id = 'meros-admin-modal-overlay';
    overlay.classList.add('meros-admin-modal-overlay', 'meros-admin-modal-hidden');

    const modal = document.createElement('div');
    modal.id = 'meros-admin-modal';
    modal.classList.add('meros-admin-modal');

    const modalTitle = document.createElement('h2');
    modalTitle.classList.add('meros-admin-modal-title');

    const modalContent = document.createElement('div');
    modalContent.classList.add('meros-admin-modal-content');

    const modalExtraContent = document.createElement('div');
    modalExtraContent.classList.add('meros-admin-modal-extra-content');

    const modalButtons = document.createElement('div');
    modalButtons.classList.add('meros-admin-modal-buttons');

    const modalConfirmButton = document.createElement('button');
    modalConfirmButton.classList.add('meros-admin-modal-button', 'meros-admin-modal-confirm-button', 'button', 'button-primary');

    const modalCancelButton = document.createElement('button');
    modalCancelButton.classList.add('meros-admin-modal-button', 'meros-admin-modal-cancel-button', 'button', 'button-primary');
    modalCancelButton.textContent = 'Cancel';

    modal.appendChild(modalTitle);
    modal.appendChild(modalContent);
    modal.appendChild(modalExtraContent);
    modalButtons.appendChild(modalConfirmButton);
    modalButtons.appendChild(modalCancelButton);
    modal.appendChild(modalButtons);
    overlay.appendChild(modal);

    document.body.appendChild(overlay);
}