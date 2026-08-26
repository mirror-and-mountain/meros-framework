
/**
 * Shows the Meros modal with the specified title and content, creating the modal if
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
export function __meros_modal_show(title, content, callback = null, confirmButtonText = 'Confirm', closeModal = true) {
    let modal = document.getElementById('meros-modal-overlay');

    if (!modal) {
        __meros_modal_make();
        modal = document.getElementById('meros-modal-overlay');
        if (!modal) {
            console.error('Failed to create Meros modal.');
            return;
        }
    }

    const modalTitle = modal.querySelector('.meros-modal-title');
    const modalContent = modal.querySelector('.meros-modal-content');

    if (modalTitle) {
        modalTitle.textContent = title;
    }

    if (modalContent) {
        modalContent.innerHTML = content;
    }

    const modalConfirmButton = modal.querySelector('.meros-modal-confirm-button');
    const modalCancelButton = modal.querySelector('.meros-modal-cancel-button');

    if (modalConfirmButton) {
        modalConfirmButton.textContent = confirmButtonText;

        modalConfirmButton.onclick = function() {
            modalConfirmButton.disabled = true; // Disable the confirm button to prevent multiple clicks
            modalConfirmButton.classList.add('meros-working'); // Add a class to indicate the button is in a working state
            
            if (modalCancelButton) {
                modalCancelButton.disabled = true; // Disable the cancel button to prevent multiple clicks
            }

            if (typeof callback === 'function') {
                callback(modal);
            }

            if (closeModal) {
                __meros_modal_hide();
            }
        };
    }

    if (modalCancelButton) {
        modalCancelButton.onclick = function() {
            __meros_modal_hide();
        };
    }

    modal.classList.remove('meros-modal-hidden');
}

/**
 * Appends extra content to the Meros modal. This function can be used to add additional information or elements to the modal after it has been displayed.
 * 
 * @param {string} content - The HTML content to be added to the extra content section of the modal.
 * @param {string|null} color - Optional color for the extra content. If provided, it will be applied to the text color of the extra content.
 * @param {string|null} marginTop - Optional. If provided, the margin will be applied to the top of the content.
 * @returns {void}
 */
export function __meros_modal_setExtraContent(content, color = null, marginTop = null) {
    const modal = document.getElementById('meros-modal-overlay');
    if (!modal) return;

    const extraContent = modal.querySelector('.meros-modal-extra-content');

    if (extraContent) {
        extraContent.innerHTML = content;

        if (color) {
            extraContent.style.color = color;
        }

        if (marginTop) {
            extraContent.style.marginTop = marginTop;
        }
    }
}

/**
 * Hides the confirm button in the Meros modal. 
 * This function can be used to remove the confirm button from the 
 * modal when it is not needed, such as when displaying informational messages or errors.
 * 
 * @returns {void}
 */
export function __meros_modal_hideConfirmButton() {
    const modal = document.getElementById('meros-modal-overlay');
    if (!modal) return;

    const modalConfirmButton = modal.querySelector('.meros-modal-confirm-button');

    if (modalConfirmButton) {
        modalConfirmButton.style.display = 'none';
    }
}

/**
 * Shows the confirm button in the Meros modal if it is currently hidden. 
 * This function can be used to make the confirm button visible again after it has been hidden.
 * 
 * @returns {void}
 */
export function __meros_modal_showConfirmButton() {
    const modal = document.getElementById('meros-modal-overlay');
    if (!modal) return;

    const modalConfirmButton = modal.querySelector('.meros-modal-confirm-button');

    if (modalConfirmButton) {
        modalConfirmButton.style.display = 'block';
    }
}

export function __meros_modal_enableConfirmButton() {
    const modal = document.getElementById('meros-modal-overlay');
    if (!modal) return;

    const modalConfirmButton = modal.querySelector('.meros-modal-confirm-button');

    if (modalConfirmButton) {
        modalConfirmButton.disabled = false;
        modalConfirmButton.classList.remove('meros-working');
    }
}

/**
 * Changes the text of the cancel button in the Meros modal. 
 * If the cancel button is currently disabled, it will be re-enabled.
 * 
 * @param {string} text - The text to set for the cancel button.
 * @returns {void}
 */
export function __meros_modal_setCancelButtonText(text) {
    const modal = document.getElementById('meros-modal-overlay');
    if (!modal) return;

    const modalCancelButton = modal.querySelector('.meros-modal-cancel-button');

    if (modalCancelButton) {
        modalCancelButton.textContent = text;
    }

    if (modalCancelButton.disabled) {
        modalCancelButton.disabled = false; // Re-enable the cancel button if it was disabled
    }
}

export function __meros_modal_setCloseButtonCallback(callback) {
    const modal = document.getElementById('meros-modal-overlay');
    if (!modal) return;

    if (typeof callback !== 'function') return;

    const modalCancelButton = modal.querySelector('.meros-modal-cancel-button');

    if (modalCancelButton) {
        modalCancelButton.onclick = function() {
            callback();
            __meros_modal_hide(callback);
        };
    }
}

export function __meros_modal_enableCloseButton() {
    const modal = document.getElementById('meros-modal-overlay');
    if (!modal) return;

    const modalCancelButton = modal.querySelector('.meros-modal-cancel-button');

    if (modalCancelButton) {
        modalCancelButton.disabled = false;
    }
}

export function __meros_modal_enableButtons() {
    __meros_modal_enableConfirmButton();
    __meros_modal_enableCloseButton();
}

/**
 * Hides the Meros modal by adding the 'meros-modal-hidden' class to the overlay
 * and resetting its content and button states.
 * 
 * @param {Function|null} callback - Optional callback function to be executed after the modal is hidden.
 * 
 * @returns {void}
 */
export function __meros_modal_hide(callback = null) {
    const modal = document.getElementById('meros-modal-overlay');

    if (!modal) {
        console.error('Meros modal not found.');
        return;
    }

    // Reset button states
    const modalConfirmButton = modal.querySelector('.meros-modal-confirm-button');
    const modalCancelButton = modal.querySelector('.meros-modal-cancel-button');

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
    const modalTitle   = modal.querySelector('.meros-modal-title');
    const modalContent = modal.querySelector('.meros-modal-content');
    const extraContent = modal.querySelector('.meros-modal-extra-content');
    
    if (modalTitle) {
        modalTitle.textContent = ''; // Clear the title
    }
    if (modalContent) {
        modalContent.innerHTML = ''; // Clear the content
    }

    if (extraContent) {
        extraContent.innerHTML = ''; // Clear any extra content
        extraContent.style.color = ''; // Reset the color of the extra content
        extraContent.style.marginTop = ''; // Reset the content's top margin
    }

    modal.classList.add('meros-modal-hidden');

    if (typeof callback === 'function') {
        callback();
    }
}

/**
 * Creates the Meros modal and appends it to the document body.
 * This function is called when the modal is first needed and does not exist in the DOM.
 * It sets up the structure of the modal, including title, content area, and action buttons.
 * 
 * @returns {void}
 */
function __meros_modal_make() {
    const overlay = document.createElement('div');
    overlay.id = 'meros-modal-overlay';
    overlay.classList.add('meros-modal-overlay', 'meros-modal-hidden');

    const modal = document.createElement('div');
    modal.id = 'meros-modal';
    modal.classList.add('meros-modal');

    const modalTitle = document.createElement('h2');
    modalTitle.classList.add('meros-modal-title');

    const modalContent = document.createElement('div');
    modalContent.classList.add('meros-modal-content');

    const modalExtraContent = document.createElement('div');
    modalExtraContent.classList.add('meros-modal-extra-content');

    const modalButtons = document.createElement('div');
    modalButtons.classList.add('meros-modal-buttons');

    const modalConfirmButton = document.createElement('button');
    modalConfirmButton.classList.add('meros-modal-button', 'meros-modal-confirm-button', 'button', 'button-primary');

    const modalCancelButton = document.createElement('button');
    modalCancelButton.classList.add('meros-modal-button', 'meros-modal-cancel-button', 'button', 'button-primary');
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