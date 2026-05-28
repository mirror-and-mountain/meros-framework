import '../../../../forms/repeaters.js';
import './repeaters.scss';

function escapeRegex(value) {
	return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function updateRowFieldIndexes(rowEl, index, repeaterFieldName) {
	const repeaterIndexPattern = repeaterFieldName
		? new RegExp('^(' + escapeRegex(repeaterFieldName) + '\\[)\\d+(\\])')
		: null;

	rowEl.querySelectorAll('[name]').forEach(function (el) {
		const currentName = el.getAttribute('name');

		if (currentName) {
			if (repeaterIndexPattern && repeaterIndexPattern.test(currentName)) {
				el.setAttribute('name', currentName.replace(repeaterIndexPattern, '$1' + index + '$2'));
			} else {
				// Fallback for unexpected field name formats.
				el.setAttribute('name', currentName.replace(/\[\d+\]/, '[' + index + ']'));
			}
		}

		if (el.id) {
			el.id = el.id.replace(/_\d+$/, '_' + index);
		}
	});
}

function reindexRows(tbody, repeaterFieldName) {
	const rows = tbody.querySelectorAll('tr:not(.meros-drop-indicator)');

	rows.forEach(function (rowEl, index) {
		rowEl.setAttribute('data-row-index', String(index));
		updateRowFieldIndexes(rowEl, index, repeaterFieldName);
	});
}

function lockColumnWidths(table) {
	const headers = table.querySelectorAll('thead th');

	headers.forEach(function (header) {
		const width = header.getBoundingClientRect().width;
		header.style.width = width + 'px';
		header.style.minWidth = width + 'px';
		header.style.maxWidth = width + 'px';
	});

	table.classList.add('meros-is-dragging');
}

function unlockColumnWidths(table) {
	const headers = table.querySelectorAll('thead th');

	headers.forEach(function (header) {
		header.style.width = '';
		header.style.minWidth = '';
		header.style.maxWidth = '';
	});

	table.classList.remove('meros-is-dragging');
}

function getDragAfterElement(container, y) {
	const draggableElements = [...container.querySelectorAll('tr:not(.is-dragging):not(.meros-drop-indicator)')];

	return draggableElements.reduce((closest, child) => {
		const box = child.getBoundingClientRect();
		const offset = y - box.top - box.height / 2;

		if (offset < 0 && offset > closest.offset) {
			return { offset: offset, element: child };
		}

		return closest;
	}, { offset: Number.NEGATIVE_INFINITY }).element;
}

// Initialises add/remove/reorder behavior for each repeater table.
function initRepeater(repeater) {
	const table = repeater.querySelector('.meros-repeater-table');
	const tbody = table ? table.querySelector('tbody') : null;
	const addButton = repeater.querySelector('.meros-add-row');
	const repeaterFieldName = repeater.getAttribute('data-field') || '';

	if (!table || !tbody) {
		return;
	}

	let draggedRow = null;
	const indicator = document.createElement('tr');
	indicator.classList.add('meros-drop-indicator');
	const columnCount = table.querySelectorAll('thead th').length || 1;
	indicator.innerHTML = `<td colspan="${columnCount}"></td>`;

	// Add row by cloning the last one, clearing values, and reindexing all names/ids.
	if (addButton) {
		addButton.addEventListener('click', function () {
			const rows = tbody.querySelectorAll('tr:not(.meros-drop-indicator)');
			const lastRow = rows[rows.length - 1];

			if (!lastRow) {
				return;
			}

			const newRow = lastRow.cloneNode(true);

			newRow.querySelectorAll('input, select, textarea').forEach(function (el) {
				if (el.type === 'checkbox') {
					el.checked = false;

					// Keep hidden checkbox fallback in sync for unchecked submissions.
					let hidden = el.parentNode.querySelector('input[type="hidden"][data-checkbox-fallback]');
					if (!hidden) {
						hidden = document.createElement('input');
						hidden.type = 'hidden';
						hidden.setAttribute('data-checkbox-fallback', 'true');
						hidden.name = el.name;
						hidden.value = '0';
						el.parentNode.insertBefore(hidden, el);
					}
				} else {
					el.value = '';
				}
			});

			tbody.appendChild(newRow);
			reindexRows(tbody, repeaterFieldName);
		});
	}

	// Remove row via delegation, then collapse indexes to avoid sparse arrays server-side.
	tbody.addEventListener('click', function (e) {
		const removeButton = e.target.closest('.meros-remove-row');
		if (!removeButton) {
			return;
		}

		const row = removeButton.closest('tr');
		const rows = tbody.querySelectorAll('tr:not(.meros-drop-indicator)');

		if (row && rows.length > 1) {
			row.remove();
			reindexRows(tbody, repeaterFieldName);
		}
	});

	// Dragging is enabled only when the handle is used.
	tbody.addEventListener('mousedown', function (e) {
		const handle = e.target.closest('.meros-drag-handle');
		if (!handle) {
			return;
		}

		const row = handle.closest('tr');
		if (!row) {
			return;
		}

		row.setAttribute('draggable', 'true');
	});

	tbody.addEventListener('dragstart', function (e) {
		const row = e.target.closest('tr');
		if (!row) {
			return;
		}

		draggedRow = row;
		row.classList.add('is-dragging');
		lockColumnWidths(table);

		e.dataTransfer.setData('text/plain', 'dragging');
		e.dataTransfer.effectAllowed = 'move';
	});

	tbody.addEventListener('dragend', function (e) {
		const row = e.target.closest('tr');
		if (!row) {
			return;
		}

		row.classList.remove('is-dragging');
		row.setAttribute('draggable', 'false');

		if (indicator.parentNode && draggedRow) {
			tbody.insertBefore(draggedRow, indicator);
			indicator.remove();
		}

		reindexRows(tbody, repeaterFieldName);
		unlockColumnWidths(table);
		draggedRow = null;
	});

	tbody.addEventListener('dragover', function (e) {
		e.preventDefault();
		if (!draggedRow) {
			return;
		}

		const afterElement = getDragAfterElement(tbody, e.clientY);

		if (!indicator.parentNode) {
			tbody.appendChild(indicator);
		}

		if (afterElement === null) {
			tbody.appendChild(indicator);
		} else {
			tbody.insertBefore(indicator, afterElement);
		}
	});

	tbody.addEventListener('dragleave', function (e) {
		if (!tbody.contains(e.relatedTarget) && indicator.parentNode) {
			indicator.remove();
		}
	});

	// Normalise names/ids before submit.
	const form = repeater.closest('form');
	if (form) {
		form.addEventListener('submit', function () {
			reindexRows(tbody, repeaterFieldName);
		});
	}
}

document.addEventListener('DOMContentLoaded', function () {
	document.querySelectorAll('.meros-repeater-field').forEach(function (repeater) {
		initRepeater(repeater);
	});
});
