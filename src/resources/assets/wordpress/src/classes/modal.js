export default class MerosModal {
    modal = null;
    title = null;
    content = null
    extraContent = null;
    confirmButton = null;
    cancelButton = null;
    confirmCallback = null;
    cancelCallback = null;
    hideOnConfirm = true;

    // =========================================================================
    // Initialisation
    // =========================================================================

    constructor(title, content, confirmText = 'Confirm', cancelText = 'Cancel', hideOnConfirm = true) {
        this.modal = document.getElementById('meros-modal-overlay') || this.__make();
        if (!this.modal) return;

        this.title = this.__getTitleElement();
        this.content = this.__getContentElement();
        this.extraContent = this.__getExtraContentElement();
        this.confirmButton = this.__getConfirmButtonElement();
        this.cancelButton = this.__getCancelButtonElement();

        this.setTitle(title);
        this.setContent(content);
        this.setConfirmButtonText(confirmText);
        this.setCancelButtonText(cancelText);
        this.hideOnConfirm = hideOnConfirm;

        this.confirmButton.addEventListener('click', this.__confirm.bind(this));
        this.cancelButton.addEventListener('click', this.hide.bind(this));
    }

    destroy() {
        if (!this.modal) return;

        this.confirmButton.removeEventListener('click', this.__confirm.bind(this));
        this.cancelButton.removeEventListener('click', this.hide.bind(this));

        this.modal.remove();
        this.modal = null;
        this.title = null;
        this.content = null;
        this.extraContent = null;
        this.confirmButton = null;
        this.cancelButton = null;
    }

    // =========================================================================
    // Show/Hide
    // =========================================================================

    show() {
        if (!this.__isReady()) return;
        this.modal.classList.remove('meros-modal-hidden');
    }

    hide() {
        if (!this.__isReady()) return;
        this.modal.classList.add('meros-modal-hidden');

        if (typeof this.cancelCallback === 'function') {
            this.cancelCallback(this.modal);
        }

        this.destroy();
    }

    // =========================================================================
    // Setters and Callbacks
    // =========================================================================

    setTitle(title) {
        if (!this.__isReady()) return;
        this.title.textContent = title;
    }

    setContent(content) {
        if (!this.__isReady()) return;
        this.content.innerHTML = content;
    }

    setExtraContent(content, color = null, marginTop = null) {
        if (!this.__isReady()) return;

        this.extraContent.innerHTML = content;
        this.extraContent.style.color = color || '';
        this.extraContent.style.marginTop = marginTop || '';
    }

    clearExtraContent() {
        if (!this.__isReady()) return;
        this.extraContent.innerHTML = '';
    }

    setConfirmButtonText(text) {
        if (!this.__isReady()) return;
        this.confirmButton.textContent = text;
    }

    setCancelButtonText(text) {
        if (!this.__isReady()) return;
        this.cancelButton.textContent = text;
    }

    onConfirm(callback) {
        if (!this.__isReady()) return;
        
        if (typeof callback === 'function') {
            this.confirmCallback = callback;
        }
    }

    onCancel(callback) {
        if (!this.__isReady()) return;

        if (typeof callback === 'function') {
            this.cancelCallback = callback;
        }
    }

    // =========================================================================
    // Button Controls
    // =========================================================================

    showConfirmButton() {
        if (!this.__isReady()) return;
        this.confirmButton.style.display = '';
    }

    hideConfirmButton() {
        if (!this.__isReady()) return;
        this.confirmButton.style.display = 'none';
    }

    enableConfirmButton() {
        if (!this.__isReady()) return;
        this.confirmButton.disabled = false;
        this.confirmButton.classList.remove('meros-working');
    }

    disableConfirmButton(working = false) {
        if (!this.__isReady()) return;
        this.confirmButton.disabled = true;

        if (working) {
            this.confirmButton.classList.add('meros-working');
        } else {
            this.confirmButton.classList.remove('meros-working');
        }
    }

    showCancelButton() {
        if (!this.__isReady()) return;
        this.cancelButton.style.display = '';
    }

    hideCancelButton() {
        if (!this.__isReady()) return;
        this.cancelButton.style.display = 'none';
    }

    enableCancelButton() {
        if (!this.__isReady()) return;
        this.cancelButton.disabled = false;
    }

    enableButtons() {
        this.enableConfirmButton();
        this.enableCancelButton();
    }

    disableCancelButton() {
        if (!this.__isReady()) return;
        this.cancelButton.disabled = true;
    }

    // =========================================================================
    // Operations/Internal
    // =========================================================================

    __confirm() {
        if (!this.__isReady()) return;

        this.cancelButton.disabled = true;
        this.confirmButton.disabled = true;
        this.confirmButton.classList.add('meros-working');

        if (typeof this.confirmCallback === 'function') {
            this.confirmCallback(this.modal);
        }

        if (this.hideOnConfirm) {
            this.hide();
        }
    }

    __isReady() {
        const checks = [
            this.modal,
            this.title,
            this.content,
            this.extraContent,
            this.confirmButton,
            this.cancelButton,
        ];

        return checks.every(el => el !== null);
    }

    __getTitleElement() {
        return this.modal.querySelector('.meros-modal-title');
    }

    __getContentElement() {
        return this.modal.querySelector('.meros-modal-content');
    }

    __getExtraContentElement() {
        return this.modal.querySelector('.meros-modal-extra-content');
    }

    __getConfirmButtonElement() {
        return this.modal.querySelector('.meros-modal-confirm-button');
    }

    __getCancelButtonElement() {
        return this.modal.querySelector('.meros-modal-cancel-button');
    }

    __make() {
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
        return overlay;
    }
}