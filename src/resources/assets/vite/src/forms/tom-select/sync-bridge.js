import {
    cacheSelectValue,
    destroyTomSelectForElement,
    initTomSelectForElement,
    initTomSelects,
    initTomSelectsAfterLivewireUpdate,
    syncTomSelectValue,
} from './lifecycle.js';

let bridgeRegistered = false;

// -----------------------------------------------------------------------------
// Livewire emitted updates
// Used by backend-emitted browser events for targeted select sync.
// -----------------------------------------------------------------------------
function applyTomSelectFieldSync(detail) {
    if (!detail || !detail.locationKey) {
        return;
    }

    const container = document.querySelector(`[data-field-location="${detail.locationKey}"]`);

    if (!container) {
        return;
    }

    const select = container.querySelector('select');

    if (!select) {
        return;
    }

    const options = Array.isArray(detail.options) ? detail.options : [];
    const normalizedValue = Array.isArray(detail.value)
        ? detail.value.map((item) => String(item))
        : (detail.value === null || detail.value === undefined ? null : String(detail.value));

    if (select.tomselect) {
        destroyTomSelectForElement(select);
    }

    select.setAttribute('data-advanced', detail.advanced ? 'true' : 'false');
    select.setAttribute('data-allow-add', detail.allowAdd ? 'true' : 'false');

    if (detail.disabled) {
        select.setAttribute('disabled', 'disabled');
    } else {
        select.removeAttribute('disabled');
    }

    if (detail.required) {
        select.setAttribute('required', 'required');
    } else {
        select.removeAttribute('required');
    }

    const selectedValues = Array.isArray(normalizedValue)
        ? normalizedValue
        : (normalizedValue ? [normalizedValue] : []);

    select.innerHTML = '';

    options.forEach((opt) => {
        const optionValue = String(opt.value ?? '');
        const optionLabel = String(opt.label ?? optionValue);
        const option = document.createElement('option');
        option.value = optionValue;
        option.textContent = optionLabel;
        option.selected = selectedValues.includes(optionValue);
        select.appendChild(option);
    });

    const shouldUseTomSelect = Boolean(detail.multiple) || Boolean(detail.advanced);

    if (shouldUseTomSelect) {
        initTomSelectForElement(select);
    }
}

function updateRepeaterSelectField(select, detail, options) {
    if (!select) {
        return;
    }

    const currentValue = select.multiple
        ? Array.from(select.querySelectorAll('option:checked')).map((opt) => opt.value)
        : select.value;

    if (select.tomselect) {
        destroyTomSelectForElement(select);
    }

    select.setAttribute('data-advanced', detail.advanced ? 'true' : 'false');
    select.setAttribute('data-allow-add', detail.allowAdd ? 'true' : 'false');

    if (detail.disabled) {
        select.setAttribute('disabled', 'disabled');
    } else {
        select.removeAttribute('disabled');
    }

    if (detail.required) {
        select.setAttribute('required', 'required');
    } else {
        select.removeAttribute('required');
    }

    select.innerHTML = '';

    options.forEach((opt) => {
        const optionValue = String(opt.value ?? '');
        const optionLabel = String(opt.label ?? optionValue);
        const option = document.createElement('option');
        option.value = optionValue;
        option.textContent = optionLabel;

        if (Array.isArray(currentValue)) {
            option.selected = currentValue.includes(optionValue);
        } else if (currentValue) {
            option.selected = String(currentValue) === optionValue;
        }

        select.appendChild(option);
    });

    if (detail.multiple || detail.advanced) {
        initTomSelectForElement(select);
    }
}

function applyTomSelectRepeaterColumnSync(detail) {
    if (!detail || !detail.columnName || detail.columnIndex === undefined) {
        return;
    }

    const options = Array.isArray(detail.options) ? detail.options : [];
    const columnName = String(detail.columnName);

    const repeaterTables = document.querySelectorAll('.meros-repeater-table');
    repeaterTables.forEach((table) => {
        const rows = table.querySelectorAll('tbody tr');

        rows.forEach((row) => {
            const cells = row.querySelectorAll('td.meros-repeater-data-cell');

            if (cells[detail.columnIndex]) {
                const select = cells[detail.columnIndex].querySelector('select');

                if (select) {
                    updateRepeaterSelectField(select, detail, options);
                }
            }
        });
    });

    const allSelects = document.querySelectorAll('select');
    allSelects.forEach((select) => {
        const selectName = select.getAttribute('name') || '';

        if (selectName.includes(columnName) && !select.closest('.meros-repeater-table')) {
            updateRepeaterSelectField(select, detail, options);
        }
    });
}

function updateRepeaterSelectValue(select, value) {
    if (!select) {
        return;
    }

    const isMultiple = select.hasAttribute('multiple');

    if (isMultiple) {
        Array.from(select.options).forEach((opt) => {
            opt.selected = Array.isArray(value) && value.includes(opt.value);
        });
    } else {
        select.value = value || '';
    }

    if (select.tomselect && typeof select.tomselect.setValue === 'function') {
        if (isMultiple) {
            select.tomselect.setValue(Array.isArray(value) ? value : [], true);
        } else {
            select.tomselect.setValue(value || '', true);
        }

        cacheSelectValue(select, value);
    }
}

function applyTomSelectRepeaterRowValueSync(detail) {
    if (!detail || !detail.fieldName) {
        return;
    }

    const fieldName = String(detail.fieldName);
    const value = detail.value;
    const columnIndex = detail.columnIndex;

    const repeaterTables = document.querySelectorAll('.meros-repeater-table');

    repeaterTables.forEach((table) => {
        const rows = table.querySelectorAll('tbody tr');

        rows.forEach((row) => {
            const cells = row.querySelectorAll('td.meros-repeater-data-cell');

            if (cells[columnIndex]) {
                const select = cells[columnIndex].querySelector('select');

                if (select && select.getAttribute('name')?.includes(fieldName)) {
                    updateRepeaterSelectValue(select, value);
                }
            }
        });
    });

    const allSelects = document.querySelectorAll('select');
    allSelects.forEach((select) => {
        const selectName = select.getAttribute('name') || '';

        if (selectName.includes(fieldName) && !select.closest('.meros-repeater-table')) {
            updateRepeaterSelectValue(select, value);
        }
    });
}

// -----------------------------------------------------------------------------
// Global bridge API
// Used by inline handlers in Blade and browser-side debugging.
// -----------------------------------------------------------------------------
function beforeRemoveField(rowIndex, fieldIndex) {
    const fieldContainer = document.querySelector(`[data-field-location="${rowIndex}-${fieldIndex}"]`);
    if (fieldContainer) {
        const selects = fieldContainer.querySelectorAll('select');
        selects.forEach(destroyTomSelectForElement);
    }
}

function beforeRemoveGroupField(groupRowIndex, rowIndex, fieldIndex) {
    const fieldContainer = document.querySelector(`[data-field-location="group-${groupRowIndex}-${rowIndex}-${fieldIndex}"]`);
    if (fieldContainer) {
        const selects = fieldContainer.querySelectorAll('select');
        selects.forEach(destroyTomSelectForElement);
    }
}

function refreshTomSelectForActiveField() {
    try {
        const settingsPanel = document.querySelector('[role="dialog"]') || document.querySelector('.settings-panel');
        if (!settingsPanel) {
            console.warn('Could not find active settings panel');
            return;
        }

        const allSelects = document.querySelectorAll('select[data-advanced="true"], select[multiple]');

        if (allSelects.length === 0) {
            console.warn('No TomSelect-enabled selects found in canvas');
            return;
        }

        allSelects.forEach((select) => {
            try {
                if (select.tomselect) {
                    destroyTomSelectForElement(select);
                }

                initTomSelectForElement(select);
            } catch (err) {
                console.warn('Error refreshing select:', err);
            }
        });

        console.log(`Refreshed ${allSelects.length} TomSelect instances`);
    } catch (err) {
        console.error('Error in refreshTomSelectForActiveField:', err);
    }
}

function exposeTomSelectGlobals() {
    window.beforeRemoveField = beforeRemoveField;
    window.beforeRemoveGroupField = beforeRemoveGroupField;
    window.refreshTomSelectForActiveField = refreshTomSelectForActiveField;

    window.TomSelectUtils = {
        init: initTomSelectForElement,
        destroy: destroyTomSelectForElement,
        initAll: initTomSelects,
        sync: initTomSelectsAfterLivewireUpdate,
        syncValue: syncTomSelectValue,
        refresh: window.refreshTomSelectForActiveField,
    };
}

// -----------------------------------------------------------------------------
// Event bridge registration
// Called once from the TomSelect runtime bootstrap.
// -----------------------------------------------------------------------------
export function registerTomSelectBridgeAPI() {
    if (bridgeRegistered) {
        return;
    }

    bridgeRegistered = true;

    window.addEventListener('tomselect-field-sync', (event) => {
        applyTomSelectFieldSync(event.detail || {});
    });

    window.addEventListener('tomselect-repeater-column-sync', (event) => {
        applyTomSelectRepeaterColumnSync(event.detail || {});
    });

    window.addEventListener('tomselect-repeater-row-value-sync', (event) => {
        applyTomSelectRepeaterRowValueSync(event.detail || {});
    });

    exposeTomSelectGlobals();
}
