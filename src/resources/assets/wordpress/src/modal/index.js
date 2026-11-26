import './style.scss';

export default class MerosModal {
    static stashedContent = new WeakMap();

    modal = null;
    title = null;
    content = null
    extraContent = null;
    confirmButton = null;
    cancelButton = null;
    closeButton = null;
    confirmCallback = null;
    cancelCallback = null;
    hideOnConfirm = true;
    returnFocus = null;
    background = [];
    handleConfirm = () => this.__confirm();
    handleCancel = () => {
        if (!this.cancelButton.disabled) this.hide();
    };
    handleKeydown = event => {
        if (event.key === 'Escape' && !event.defaultPrevented && !this.cancelButton.disabled) {
            event.preventDefault();
            event.stopPropagation();
            this.hide();
        } else if (event.key === 'Tab') {
            const controls = this.__focusableElements();
            const first = controls[0];
            const last = controls[controls.length - 1];
            if (!first) {
                event.preventDefault();
                this.title.focus();
            } else if (event.shiftKey && (!controls.includes(document.activeElement) || document.activeElement === first)) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && (!controls.includes(document.activeElement) || document.activeElement === last)) {
                event.preventDefault();
                first.focus();
            }

            window.MerosModal = MerosModal;
        }
    };

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
        this.closeButton = this.modal.querySelector('.meros-modal-close-button');

        this.setTitle(title);
        this.setContent(content);
        this.setConfirmButtonText(confirmText);
        this.setCancelButtonText(cancelText);
        this.hideOnConfirm = hideOnConfirm;

        this.confirmButton.addEventListener('click', this.handleConfirm);
        this.cancelButton.addEventListener('click', this.handleCancel);
        this.closeButton.addEventListener('click', this.handleCancel);
        this.modal.addEventListener('keydown', this.handleKeydown);
    }

    destroy() {
        if (!this.modal) return;

        this.confirmButton.removeEventListener('click', this.handleConfirm);
        this.cancelButton.removeEventListener('click', this.handleCancel);
        this.closeButton.removeEventListener('click', this.handleCancel);
        this.modal.removeEventListener('keydown', this.handleKeydown);

        this.modal.remove();
        this.background.forEach(([element, inert]) => { element.inert = inert; });
        this.background = [];
        if (this.returnFocus?.isConnected) this.returnFocus.focus();
        this.modal = null;
        this.title = null;
        this.content = null;
        this.extraContent = null;
        this.confirmButton = null;
        this.cancelButton = null;
        this.closeButton = null;
    }

    // =========================================================================
    // Show/Hide
    // =========================================================================

    show() {
        if (!this.__isReady()) return;
        if (!this.modal.classList.contains('meros-modal-hidden')) return;
        this.returnFocus = document.activeElement;
        this.modal.classList.remove('meros-modal-hidden');
        this.title.focus();
        this.background = Array.from(document.body.children)
            .filter(element => element !== this.modal)
            .map(element => [element, element.inert]);
        this.background.forEach(([element]) => { element.inert = true; });
    }

    focusContent() {
        if (!this.__isReady()) return;
        const control = this.__focusableElements().find(element => this.content.contains(element));
        (control || this.title).focus();
    }

    __focusableElements() {
        return Array.from(this.modal.querySelectorAll('button, input:not([type="hidden"]), select, textarea, a[href], [tabindex], [contenteditable="true"]'))
            .filter(element => element.tabIndex >= 0 && !element.matches(':disabled') &&
                !element.closest('[inert], [hidden]') && element.getClientRects().length > 0);
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

    stash(key, revision) {
        if (!this.__isReady()) return;
        MerosModal.stashedContent.set(key, { revision, html: this.content.innerHTML });
    }

    static getStashedContent(key, revision) {
        const entry = MerosModal.stashedContent.get(key);
        return entry?.revision === revision ? entry.html : null;
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
        this.closeButton.disabled = false;
    }

    enableButtons() {
        this.enableConfirmButton();
        this.enableCancelButton();
    }

    disableCancelButton() {
        if (!this.__isReady()) return;
        this.cancelButton.disabled = true;
        this.closeButton.disabled = true;
    }

    // =========================================================================
    // Operations/Internal
    // =========================================================================

    __confirm() {
        if (!this.__isReady() || this.confirmButton.disabled) return;

        this.disableCancelButton();
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
            this.closeButton,
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
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-modal', 'true');
        modal.setAttribute('aria-labelledby', 'meros-modal-title');

        const modalHeader = document.createElement('div');
        modalHeader.classList.add('meros-modal-header');

        const modalTitle = document.createElement('h2');
        modalTitle.classList.add('meros-modal-title');
        modalTitle.id = 'meros-modal-title';
        modalTitle.tabIndex = -1;

        const modalCloseButton = document.createElement('button');
        modalCloseButton.type = 'button';
        modalCloseButton.classList.add('meros-modal-close-button');
        modalCloseButton.setAttribute('aria-label', 'Close dialog');
        modalCloseButton.textContent = '\u00d7';

        const modalBody = document.createElement('div');
        modalBody.classList.add('meros-modal-body');

        const modalContent = document.createElement('div');
        modalContent.classList.add('meros-modal-content');

        const modalExtraContent = document.createElement('div');
        modalExtraContent.classList.add('meros-modal-extra-content');
        modalExtraContent.setAttribute('role', 'alert');

        const modalButtons = document.createElement('div');
        modalButtons.classList.add('meros-modal-buttons');

        const modalConfirmButton = document.createElement('button');
        modalConfirmButton.type = 'button';
        modalConfirmButton.classList.add('meros-modal-button', 'meros-modal-confirm-button', 'mforms-button');

        const modalCancelButton = document.createElement('button');
        modalCancelButton.type = 'button';
        modalCancelButton.classList.add('meros-modal-button', 'meros-modal-cancel-button', 'mforms-button');
        modalCancelButton.textContent = 'Cancel';

        modalHeader.append(modalTitle, modalCloseButton);
        modalBody.append(modalContent, modalExtraContent);
        modal.append(modalHeader, modalBody);
        modalButtons.appendChild(modalConfirmButton);
        modalButtons.appendChild(modalCancelButton);
        modal.appendChild(modalButtons);
        overlay.appendChild(modal);

        document.body.appendChild(overlay);
        return overlay;
    }
}

window.MerosModal = MerosModal;