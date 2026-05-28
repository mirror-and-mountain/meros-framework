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

	const dialog = document.createElement('dialog');
	dialog.className = 'meros-repeater-config-dialog';

	const shell = document.createElement('div');
	shell.className = 'meros-repeater-config-dialog__shell';

	const header = document.createElement('div');
	header.className = 'meros-repeater-config-dialog__header';

	const title = document.createElement('h2');
	title.className = 'meros-repeater-config-dialog__title';
	title.textContent = 'Configure row';

	const closeButton = document.createElement('button');
	closeButton.type = 'button';
	closeButton.className = 'button';
	closeButton.textContent = 'Update';
	closeButton.addEventListener('click', function () {
		dialog.close();
	});

	header.appendChild(title);
	header.appendChild(closeButton);
	shell.appendChild(header);

	const body = document.createElement('div');
	body.className = 'meros-repeater-config-dialog__body';
	shell.appendChild(body);

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

	return { dialog, body };
}

/**
 * Default global callback used by repeater Configure actions.
 *
 * @param {Element} triggerElement
 * @returns {void}
 */
window.merosDefaultRepeaterRowConfig = function (triggerElement) {
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

	document.body.appendChild(parts.dialog);
	parts.dialog.showModal();
	focusFirstField(parts.body);
};