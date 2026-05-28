/**
 * Gets the cells from a repeater row that should be used for configuration.
 * 
 * @param {HTMLTableRowElement} row 
 * @returns {HTMLTableCellElement[]}
 */
function getRepeaterConfigureCells(row) {
	return Array.from(row.querySelectorAll('td')).filter(function (cell) {
		return !(
			cell.classList.contains('meros-col-handle') ||
			cell.classList.contains('meros-repeater-actions') ||
			cell.classList.contains('meros-repeater-move-cell') ||
			cell.classList.contains('meros-repeater-actions-cell')
		);
	});
}

/**
 * Gets the label to use for a repeater configure field based on the corresponding column header. 
 * If a header cannot be found, defaults to 'Field'.
 * 
 * @param {HTMLTableElement} table 
 * @param {HTMLTableCellElement} cell 
 * @returns {string}
 */
function getRepeaterConfigureLabel(table, cell) {
	if (typeof cell.cellIndex !== 'number') {
		return 'Field';
	}

	const header = table.querySelector(`thead th:nth-child(${cell.cellIndex + 1})`);
	const label = header ? header.textContent.trim() : '';

	return label || 'Field';
}

/**
 * Focuses the first interactive control inside the supplied container.
 *
 * @param {ParentNode} container
 * @returns {void}
 */
function focusFirstField(container) {
	const firstField = container.querySelector('input, select, textarea, button');
	if (firstField instanceof HTMLElement) {
		firstField.focus();
	}
}

/**
 * Prevents the page from scrolling while the repeater configuration dialog is open.
 *
 * @returns {() => void}
 */
function lockPageScroll() {
	const scrollY = window.scrollY || window.pageYOffset || 0;
	const html = document.documentElement;
	const body = document.body;
	const previousHtmlOverflow = html.style.overflow;
	const previousBodyOverflow = body.style.overflow;
	const previousBodyPosition = body.style.position;
	const previousBodyTop = body.style.top;
	const previousBodyLeft = body.style.left;
	const previousBodyRight = body.style.right;
	const previousBodyWidth = body.style.width;

	html.style.overflow = 'hidden';
	body.style.overflow = 'hidden';
	body.style.position = 'fixed';
	body.style.top = `-${scrollY}px`;
	body.style.left = '0';
	body.style.right = '0';
	body.style.width = '100%';

	return function () {
		html.style.overflow = previousHtmlOverflow;
		body.style.overflow = previousBodyOverflow;
		body.style.position = previousBodyPosition;
		body.style.top = previousBodyTop;
		body.style.left = previousBodyLeft;
		body.style.right = previousBodyRight;
		body.style.width = previousBodyWidth;
		window.scrollTo(0, scrollY);
	};
}

/**
 * Builds a repeater modal shell and injects the provided HTML into the default
 * dialog body while keeping the shared header/footer chrome.
 *
 * @param {string} bodyHtml
 * @param {(context: { dialog: HTMLDialogElement, shell: HTMLDivElement, body: HTMLDivElement }) => (boolean | void | Promise<boolean | void>) | null} onUpdate
 * @returns {{ dialog: HTMLDialogElement, shell: HTMLDivElement, body: HTMLDivElement }}
 */
export function buildRepeaterDialogFromHtml(bodyHtml = '', onUpdate = null) {
	const dialog = document.createElement('dialog');
	dialog.className = 'meros-repeater-config-dialog';

	const shell = document.createElement('div');
	shell.className = 'meros-repeater-config-dialog__shell';

	const header = document.createElement('div');
	header.className = 'meros-repeater-config-dialog__header';

	const title = document.createElement('h2');
	title.className = 'meros-repeater-config-dialog__title';
	title.textContent = 'Configure';

	const dismissButton = document.createElement('button');
	dismissButton.type = 'button';
	dismissButton.className = 'meros-repeater-config-dialog__dismiss';
	dismissButton.setAttribute('aria-label', 'Close dialog');
	dismissButton.textContent = 'x';
	dismissButton.addEventListener('click', function () {
		dialog.close();
	});

	const body = document.createElement('div');
	body.className = 'meros-repeater-config-dialog__body';
	body.innerHTML = typeof bodyHtml === 'string' ? bodyHtml : '';

	const footer = document.createElement('div');
	footer.className = 'meros-repeater-config-dialog__footer';

	const updateButton = document.createElement('button');
	updateButton.type = 'button';
	updateButton.className = 'button meros-repeater-config-dialog__close';
	updateButton.textContent = 'Update';
	updateButton.addEventListener('click', async function () {
		let shouldClose = true;

		if (typeof onUpdate === 'function') {
			const result = await onUpdate({ dialog, shell, body });
			shouldClose = result !== false;
		}

		if (shouldClose) {
			dialog.close();
		}
	});

	header.appendChild(title);
	header.appendChild(dismissButton);
	shell.appendChild(header);
	shell.appendChild(body);
	footer.appendChild(updateButton);
	shell.appendChild(footer);

	dialog.addEventListener('click', function (event) {
		const bounds = dialog.getBoundingClientRect();
		const clickedBackdrop =
			event.clientX < bounds.left ||
			event.clientX > bounds.right ||
			event.clientY < bounds.top ||
			event.clientY > bounds.bottom;

		if (clickedBackdrop) {
			dialog.close();
		}
	});

	dialog.appendChild(shell);

	return { dialog, shell, body };
}

/**
 * Builds and opens a repeater modal from a raw HTML string.
 * Handles body scroll locking and cleanup when the dialog closes.
 *
 * @param {string} shellHtml
 * @param {(context: { dialog: HTMLDialogElement, shell: HTMLDivElement, body: HTMLDivElement }) => (boolean | void | Promise<boolean | void>) | null} onUpdate
 * @returns {HTMLDialogElement}
 */
export function openRepeaterDialogFromHtml(shellHtml = '', onUpdate = null) {
	const existingDialog = document.querySelector('dialog.meros-repeater-config-dialog[open]');

	if (existingDialog instanceof HTMLDialogElement) {
		existingDialog.close();
	}

	const { dialog, body } = buildRepeaterDialogFromHtml(shellHtml, onUpdate);
	const unlockPageScroll = lockPageScroll();

	const cleanup = function () {
		unlockPageScroll();
		dialog.removeEventListener('close', cleanup);
		dialog.remove();
	};

	dialog.addEventListener('close', cleanup);

	document.body.appendChild(dialog);

	try {
		dialog.showModal();
	} catch (error) {
		dialog.setAttribute('open', 'open');
	}

	focusFirstField(body);

	return dialog;
}

/**
 * Builds the default repeater configuration dialog and temporarily moves the
 * row's field contents into it until the dialog is closed.
 *
 * @param {Element} triggerElement
 * @returns {{ dialog: HTMLDialogElement, body: HTMLDivElement } | null}
 */
function buildRepeaterConfigureDialog(triggerElement) {
	const row = triggerElement.closest('tr');
	const table = row ? row.closest('table') : null;

	if (!row || !table) {
		return null;
	}

	const cells = getRepeaterConfigureCells(row);
	if (cells.length === 0) {
		return null;
	}

	const defaultUpdateHandler = function () {
		// Row controls remain bound to the original inputs while in the modal.
		return true;
	};

	const { dialog, body } = buildRepeaterDialogFromHtml('', defaultUpdateHandler);

	const transfers = cells.map(function (cell) {
		const placeholder = document.createComment('meros-repeater-config-placeholder');
		const fieldWrapper = document.createElement('div');
		fieldWrapper.className = 'meros-repeater-config-dialog__field-input';

		const nodes = Array.from(cell.childNodes);
		cell.appendChild(placeholder);
		nodes.forEach(function (node) {
			fieldWrapper.appendChild(node);
		});

		const field = document.createElement('section');
		field.className = 'meros-repeater-config-dialog__field';

		const label = document.createElement('h3');
		label.className = 'meros-repeater-config-dialog__field-label';
		label.textContent = getRepeaterConfigureLabel(table, cell);

		field.appendChild(label);
		field.appendChild(fieldWrapper);
		body.appendChild(field);

		return { cell, placeholder, fieldWrapper };
	});

	let restored = false;
	const restore = function () {
		if (restored) {
			return;
		}

		restored = true;

		transfers.forEach(function (transfer) {
			while (transfer.fieldWrapper.firstChild) {
				transfer.cell.insertBefore(transfer.fieldWrapper.firstChild, transfer.placeholder);
			}

			if (transfer.placeholder.parentNode) {
				transfer.placeholder.remove();
			}
		});

		dialog.remove();
	};

	dialog.addEventListener('close', restore, { once: true });
	return { dialog, body };
}

/**
 * Default global callback used by repeater Configure actions.
 *
 * @param {{ triggerElement?: Element, rowValue?: Record<string, unknown> } | Element} payload
 * @returns {void}
 */
window.merosDefaultRepeaterRowConfig = function (payload) {
	const triggerElement = payload?.triggerElement ?? payload;

	if (!(triggerElement instanceof Element)) {
		return;
	}

	if (typeof HTMLDialogElement === 'undefined') {
		const row = triggerElement.closest('tr');
		if (row instanceof HTMLElement) {
			focusFirstField(row);
		}
		return;
	}

	const parts = buildRepeaterConfigureDialog(triggerElement);
	if (!parts) {
		return;
	}

	const unlockPageScroll = lockPageScroll();
	const restoreScroll = function () {
		unlockPageScroll();
		parts.dialog.removeEventListener('close', restoreScroll);
	};

	parts.dialog.addEventListener('close', restoreScroll);

	document.body.appendChild(parts.dialog);
	parts.dialog.showModal();
	focusFirstField(parts.body);
};