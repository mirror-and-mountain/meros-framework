import TomSelect from 'tom-select';
import './style.css';

const tomSelectValueCache = new Map();

function normalizeKeyPart(value) {
    return String(value ?? '').replace(/\s+/g, ' ').trim();
}

function getSelectCacheKey(select) {
    if (!select || !(select instanceof HTMLSelectElement)) {
        return null;
    }

    const container = select.closest('[data-field-location]');
    const containerKey = normalizeKeyPart(container?.getAttribute('data-field-location'));

    const row = select.closest('tr');
    const rowKey = normalizeKeyPart(row?.getAttribute('wire:key'));

    const cell = select.closest('td');
    let cellIndex = '';

    if (cell && cell.parentElement) {
        cellIndex = String(Array.from(cell.parentElement.children).indexOf(cell));
    }

    const nameKey = normalizeKeyPart(select.name);
    const idKey = normalizeKeyPart(select.id);

    if (containerKey || rowKey || cellIndex) {
        return [
            'ctx',
            containerKey || 'no-container',
            rowKey || 'no-row',
            `cell:${cellIndex || 'x'}`,
            `name:${nameKey || 'x'}`,
            `id:${idKey || 'x'}`,
        ].join('|');
    }

    if (select.id) {
        return `id:${select.id}`;
    }

    if (select.name) {
        return `name:${select.name}`;
    }

    return null;
}

function isEmptySelectValue(value) {
    if (Array.isArray(value)) {
        return value.length === 0;
    }

    return value === null || value === undefined || value === '';
}

function cacheSelectValue(select, value) {
    const cacheKey = getSelectCacheKey(select);

    if (!cacheKey || isEmptySelectValue(value)) {
        return;
    }

    tomSelectValueCache.set(cacheKey, value);
}

function getCachedSelectValue(select) {
    const cacheKey = getSelectCacheKey(select);

    if (!cacheKey) {
        return null;
    }

    return tomSelectValueCache.get(cacheKey) ?? null;
}

/**
 * Register an Alpine store that tracks the currently dragged field's type and label.
 * The store is used by the form builder sidebar (@dragstart) and canvas drop zones (@drop).
 */
function registerFormDragStore() {
    Alpine.store('formDrag', {
        isDragging: false,
        isCanvasDrag: false,
        itemKind: null,
        itemHandle: null,
        itemLabel: null,
        fieldType: null,
        fieldLabel: null,
        sourceRowIndex: null,
        sourceFieldIndex: null,
        sourceGroupRowIndex: null,
        sourceGroupInnerRowIndex: null,

        // Dragging a new sidebar item (field/group).
        startDrag(kind, handle, label) {
            this.isDragging = true;
            this.isCanvasDrag = false;
            this.itemKind = kind;
            this.itemHandle = handle;
            this.itemLabel = label;
            this.fieldType = kind === 'field' ? handle : null;
            this.fieldLabel = label;
            this.sourceRowIndex = null;
            this.sourceFieldIndex = null;
            this.sourceGroupRowIndex = null;
            this.sourceGroupInnerRowIndex = null;
        },

        // Dragging an existing field within the canvas to reorder it.
        startCanvasDrag(rowIndex, fieldIndex) {
            this.isDragging = true;
            this.isCanvasDrag = true;
            this.itemKind = 'field';
            this.itemHandle = null;
            this.itemLabel = null;
            this.fieldType = null;
            this.fieldLabel = null;
            this.sourceRowIndex = rowIndex;
            this.sourceFieldIndex = fieldIndex;
            this.sourceGroupRowIndex = null;
            this.sourceGroupInnerRowIndex = null;
        },

        // Dragging an existing field within a group row to reorder it.
        startGroupCanvasDrag(groupRowIndex, rowIndex, fieldIndex) {
            this.isDragging = true;
            this.isCanvasDrag = true;
            this.itemKind = 'field';
            this.itemHandle = null;
            this.itemLabel = null;
            this.fieldType = null;
            this.fieldLabel = null;
            this.sourceRowIndex = null;
            this.sourceFieldIndex = fieldIndex;
            this.sourceGroupRowIndex = groupRowIndex;
            this.sourceGroupInnerRowIndex = rowIndex;
        },

        showInsertMarker(zoneElement) {
            const marker = zoneElement?.firstElementChild;

            if (!marker) {
                return;
            }

            marker.style.opacity = '1';
            marker.style.height = '88%';
            marker.style.boxShadow = '0 0 0 1px rgba(59,130,246,0.28), 0 8px 20px rgba(59,130,246,0.30)';
        },

        hideInsertMarker(zoneElement) {
            const marker = zoneElement?.firstElementChild;

            if (!marker) {
                return;
            }

            marker.style.opacity = '0';
            marker.style.height = '';
            marker.style.boxShadow = '';
        },

        showRowGap(gapElement) {
            if (!gapElement) {
                return;
            }

            gapElement.style.height = '2rem';
            gapElement.classList.add('bg-blue-200');
        },

        hideRowGap(gapElement) {
            if (!gapElement) {
                return;
            }

            gapElement.style.height = '';
            gapElement.classList.remove('bg-blue-200');
        },

        endDrag() {
            this.isDragging = false;
            this.isCanvasDrag = false;
            this.itemKind = null;
            this.itemHandle = null;
            this.itemLabel = null;
            this.fieldType = null;
            this.fieldLabel = null;
            this.sourceRowIndex = null;
            this.sourceFieldIndex = null;
            this.sourceGroupRowIndex = null;
            this.sourceGroupInnerRowIndex = null;
        },
    });
}

function initTomSelects() {
    const selects = document.querySelectorAll('select[data-advanced="true"], select[multiple]');
    selects.forEach((select) => initTomSelectForElement(select));
}

/**
 * Initialize TomSelect for a single select element if it needs advanced mode.
 */
function initTomSelectForElement(select) {
    try {
        if (!select || !(select instanceof HTMLSelectElement)) return;

        if (!select.parentElement) return;

        // If an instance exists, ensure it is healthy before deciding to skip init.
        if (select.tomselect) {
            const wrapper = select.tomselect.wrapper || select.nextElementSibling;
            const hasHealthyWrapper = !!(
                wrapper
                && wrapper instanceof HTMLElement
                && wrapper.classList.contains('ts-wrapper')
                && wrapper.parentElement
            );

            if (hasHealthyWrapper) {
                return;
            }

            // Stale instance reference (or detached wrapper): force cleanup and rebuild.
            destroyTomSelectForElement(select);
        }
        
        // Only initialize if advanced or multiple
        if (select.getAttribute('data-advanced') !== 'true' && !select.hasAttribute('multiple')) return;

        // Remove any stale TomSelect wrappers/classes before creating a new instance.
        if (select.nextElementSibling && select.nextElementSibling.classList.contains('ts-wrapper')) {
            select.nextElementSibling.remove();
        }
        select.classList.remove('tomselected', 'ts-hidden-accessible');
        select.removeAttribute('tabindex');

        const isMultiple = select.hasAttribute('multiple');
        const allowAdd = select.getAttribute('data-allow-add') === 'true';

        const isInsideRepeater = !!select.closest('.meros-repeater-table');

        new TomSelect(select, {
            plugins: isMultiple ? {
                remove_button: {
                    title: 'Remove',
                }
            } : {},
            create: allowAdd ? (input) => {
                return {
                    value: input.toLowerCase().replace(/\s+/g, '-'),
                    text: input,
                };
            } : false,
            sortField: [{ field: '$order' }, { field: '$score' }],
            maxItems: isMultiple ? null : 1,
            onChange: () => {
                if (select.tomselect) {
                    cacheSelectValue(select, select.tomselect.getValue());
                    // Only blur on single-select — blurring a multi-select closes
                    // the dropdown after every item pick, preventing further selection.
                    if (!isMultiple) {
                        select.tomselect.blur();
                    }
                }
            },
            onDropdownOpen: isInsideRepeater ? function(dropdown) {
                // Teleport dropdown to body to escape overflow/stacking context constraints
                const wrapper = select.tomselect?.wrapper;
                if (!wrapper || !dropdown) return;

                const rect = wrapper.getBoundingClientRect();
                const scrollX = window.scrollX || window.pageXOffset;
                const scrollY = window.scrollY || window.pageYOffset;

                dropdown.style.position = 'absolute';
                dropdown.style.zIndex = '999999';
                dropdown.style.width = rect.width + 'px';
                dropdown.style.top = (rect.bottom + scrollY) + 'px';
                dropdown.style.left = (rect.left + scrollX) + 'px';

                document.body.appendChild(dropdown);

                // Store cleanup reference on the select element
                select._tomSelectDropdown = dropdown;
            } : undefined,
            onDropdownClose: isInsideRepeater ? function(dropdown) {
                // Return dropdown to wrapper so TomSelect can manage it
                const wrapper = select.tomselect?.wrapper;
                if (wrapper && dropdown && dropdown.parentElement === document.body) {
                    wrapper.appendChild(dropdown);
                }
                select._tomSelectDropdown = null;
            } : undefined,
        });
        
        // Store metadata as data attributes for persistence through Livewire re-renders
        if (select.tomselect) {
            select.dataset.tomselectOptionsHash = hashSelectOptions(select);
            select.dataset.tomselectAllowAdd = allowAdd ? 'true' : 'false';
            select.dataset.tomselectInitialized = 'true';
            
            // Sync the current values with TomSelect
            syncTomSelectValue(select);
            cacheSelectValue(select, select.tomselect.getValue());
        }
    } catch (err) {
        console.warn('Error initializing TomSelect:', err);
        // Clean up any partial state
        if (select && select.tomselect) {
            try {
                select.tomselect.destroy();
            } catch (e) {
                // Ignore cleanup errors
            }
        }
    }
}

/**
 * Create a hash of the select element's options to detect when they change.
 */
function hashSelectOptions(select) {
    const options = Array.from(select.options).map(opt => `${opt.value}|${opt.text}`).join('||');
    return options;
}

/**
 * Destroy TomSelect instance for a single element.
 */
function destroyTomSelectForElement(select) {
    try {
        if (!select || !select.tomselect) return;
        
        // Clear metadata
        select.dataset.tomselectOptionsHash = '';
        select.dataset.tomselectAllowAdd = '';
        select.dataset.tomselectInitialized = '';
        
        select.tomselect.destroy();
        select.classList.remove('tomselected', 'ts-hidden-accessible');
        select.removeAttribute('tabindex');

        if (select.nextElementSibling && select.nextElementSibling.classList.contains('ts-wrapper')) {
            select.nextElementSibling.remove();
        }
    } catch (err) {
        console.warn('Error destroying TomSelect:', err);
        // Force-clear the reference if destroy fails
        if (select) {
            select.tomselect = null;
            select.classList.remove('tomselected', 'ts-hidden-accessible');
            select.removeAttribute('tabindex');
        }
    }
}

/**
 * Sync a TomSelect instance's value with the underlying select element.
 * Useful when the underlying select options/values have changed.
 */
function syncTomSelectValue(select) {
    try {
        if (!select || !select.tomselect) return;
        
        const isMultiple = select.hasAttribute('multiple');
        
        if (isMultiple) {
            // For multi-select, get all currently selected option values
            const selectedValues = Array.from(select.querySelectorAll('option:checked'))
                .map(opt => opt.value);
            
            // Set TomSelect's value to match
            if (select.tomselect.setValue) {
                select.tomselect.setValue(selectedValues, true);
            }
        } else {
            // For single select, just use the select's value
            const selectedValue = select.value;
            if (selectedValue && select.tomselect.setValue) {
                select.tomselect.setValue(selectedValue, true);
            }
        }
    } catch (err) {
        console.warn('Error syncing TomSelect value:', err);
    }
}

/**
 * Read current value from a select, preferring active TomSelect instance state.
 */
function getCurrentSelectValue(select) {
    if (!select || !(select instanceof HTMLSelectElement)) {
        return null;
    }

    if (select.tomselect && typeof select.tomselect.getValue === 'function') {
        return select.tomselect.getValue();
    }

    if (select.hasAttribute('multiple')) {
        return Array.from(select.querySelectorAll('option:checked')).map((opt) => opt.value);
    }

    return select.value || null;
}

/**
 * Restore value to a select after TomSelect re-initialization.
 */
function restoreSelectValue(select, value) {
    if (!select || !(select instanceof HTMLSelectElement)) {
        return;
    }

    if (!select.tomselect || typeof select.tomselect.setValue !== 'function') {
        return;
    }

    if (Array.isArray(value)) {
        select.tomselect.setValue(value.map((item) => String(item)), true);
        cacheSelectValue(select, value);
        return;
    }

    if (value !== null && value !== undefined && value !== '') {
        select.tomselect.setValue(String(value), true);
        cacheSelectValue(select, String(value));
    }
}

/**
 * Initialize TomSelect instances after Livewire DOM updates.
 * This is intentionally simple and one-way to avoid observer feedback loops.
 */
function initTomSelectsAfterLivewireUpdate() {
    const rebindAllManagedSelects = () => {
        const allSelects = document.querySelectorAll('select');

        allSelects.forEach((select) => {
            if (!(select instanceof HTMLSelectElement)) {
                return;
            }

            const shouldUseTomSelect = select.getAttribute('data-advanced') === 'true' || select.hasAttribute('multiple');

            if (!shouldUseTomSelect) {
                if (select.tomselect) {
                    destroyTomSelectForElement(select);
                }

                // Ensure non-advanced selects are visible even after prior advanced mode.
                select.classList.remove('tomselected', 'ts-hidden-accessible');
                select.removeAttribute('tabindex');

                if (select.nextElementSibling && select.nextElementSibling.classList.contains('ts-wrapper')) {
                    select.nextElementSibling.remove();
                }

                return;
            }

            // Read value from HTML, not from old TomSelect instance (which may have stale data).
            // For single-select, select.value always returns something (defaults to first option)
            // even when nothing was explicitly selected, so check for a [selected] attribute instead.
            let htmlValue;
            if (select.hasAttribute('multiple')) {
                htmlValue = Array.from(select.querySelectorAll('option:checked')).map((opt) => opt.value);
            } else {
                const explicitlySelected = select.querySelector('option[selected]');
                htmlValue = explicitlySelected ? explicitlySelected.value : null;
            }

            // Only overwrite the cache when the HTML carries an explicit value.
            if (!isEmptySelectValue(htmlValue)) {
                cacheSelectValue(select, htmlValue);
            }
            const cachedValue = getCachedSelectValue(select);
            const valueToRestore = isEmptySelectValue(htmlValue) ? cachedValue : htmlValue;

            if (select.tomselect) {
                destroyTomSelectForElement(select);
            }

            initTomSelectForElement(select);
            restoreSelectValue(select, valueToRestore);
        });
    };

    // Run after DOM settles to avoid racing Livewire morph updates.
    requestAnimationFrame(() => {
        rebindAllManagedSelects();
    });

    setTimeout(() => {
        rebindAllManagedSelects();
    }, 0);
}

let tomSelectRebindScheduled = false;
let tomSelectRebindInProgress = false;

function scheduleTomSelectRebind() {
    if (tomSelectRebindScheduled || tomSelectRebindInProgress) {
        return;
    }

    tomSelectRebindScheduled = true;

    requestAnimationFrame(() => {
        tomSelectRebindScheduled = false;
        tomSelectRebindInProgress = true;

        try {
            initTomSelectsAfterLivewireUpdate();
        } finally {
            // Delay reset so internal setTimeout/RAF passes can settle first.
            setTimeout(() => {
                tomSelectRebindInProgress = false;
            }, 20);
        }
    });
}

function observeTomSelectWrapperLoss() {
    const root = document.body || document.documentElement;

    if (!root) {
        return;
    }

    const observer = new MutationObserver((mutations) => {
        if (tomSelectRebindInProgress) {
            return;
        }

        let shouldRebind = false;

        for (const mutation of mutations) {
            if (mutation.type !== 'childList') {
                continue;
            }

            for (const removedNode of mutation.removedNodes) {
                if (!(removedNode instanceof HTMLElement)) {
                    continue;
                }

                if (
                    removedNode.classList.contains('ts-wrapper')
                    || removedNode.querySelector('.ts-wrapper')
                ) {
                    shouldRebind = true;
                    break;
                }
            }

            if (shouldRebind) {
                break;
            }
        }

        if (shouldRebind) {
            scheduleTomSelectRebind();
        }
    });

    observer.observe(root, {
        childList: true,
        subtree: true,
    });
}

// Alpine is bundled inside Livewire and initialises after our script loads,
// so hooking into `alpine:init` is the correct registration point.
document.addEventListener('alpine:init', registerFormDragStore);
document.addEventListener('DOMContentLoaded', () => {
    try {
        initTomSelects();
        observeTomSelectWrapperLoss();
    } catch (err) {
        console.error('Error initializing TomSelect system:', err);
    }
});

/**
 * After Livewire updates the DOM (fields added/moved/removed), sync TomSelect state.
 * This handles the case where new select fields are added, advanced toggle is changed, or fields are moved.
 */
document.addEventListener('livewire:updated', () => {
    scheduleTomSelectRebind();
});

document.addEventListener('livewire:init', () => {
    if (typeof window.Livewire === 'undefined' || typeof window.Livewire.hook !== 'function') {
        return;
    }

    window.Livewire.hook('morph.updated', () => {
        scheduleTomSelectRebind();
    });
});

/**
 * Global function to handle field removal.
 * Called from Livewire removeField methods to clean up TomSelect before field is removed.
 */
window.beforeRemoveField = function(rowIndex, fieldIndex) {
    // Find and destroy any TomSelect instances in this field
    const fieldContainer = document.querySelector(`[data-field-location="${rowIndex}-${fieldIndex}"]`);
    if (fieldContainer) {
        const selects = fieldContainer.querySelectorAll('select');
        selects.forEach(destroyTomSelectForElement);
    }
};

window.beforeRemoveGroupField = function(groupRowIndex, rowIndex, fieldIndex) {
    // Find and destroy any TomSelect instances in this field
    const fieldContainer = document.querySelector(`[data-field-location="group-${groupRowIndex}-${rowIndex}-${fieldIndex}"]`);
    if (fieldContainer) {
        const selects = fieldContainer.querySelectorAll('select');
        selects.forEach(destroyTomSelectForElement);
    }
};

/**
 * Apply a targeted select field update emitted from Livewire.
 * This is used for wire:ignore-managed TomSelect fields.
 */
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

    // Keep select element attributes in sync with settings panel changes.
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

    // Rebuild native options so the underlying form state stays accurate.
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

window.addEventListener('tomselect-field-sync', (event) => {
    applyTomSelectFieldSync(event.detail || {});
});

/**
 * Apply TomSelect sync for all instances of a repeater column across all rows.
 * This finds and updates select fields in all three rendering contexts:
 * 1. Canvas repeater rows
 * 2. Repeater settings panel preview rows
 * 3. Edit Row panel
 */
function applyTomSelectRepeaterColumnSync(detail) {
    if (!detail || !detail.columnName || detail.columnIndex === undefined) {
        return;
    }

    const options = Array.isArray(detail.options) ? detail.options : [];
    const columnName = String(detail.columnName);

    // 1. Find select fields in repeater tables (canvas + settings preview)
    const repeaterTables = document.querySelectorAll('.meros-repeater-table');
    repeaterTables.forEach((table) => {
        const rows = table.querySelectorAll('tbody tr');

        rows.forEach((row) => {
            const cells = row.querySelectorAll('td.meros-repeater-data-cell');

            if (cells[detail.columnIndex]) {
                const cellContainer = cells[detail.columnIndex];
                const select = cellContainer.querySelector('select');

                if (select) {
                    updateRepeaterSelectField(select, detail, options, columnName);
                }
            }
        });
    });

    // 2. Find standalone select fields with the column name (Edit Row panel + other contexts)
    // Look for all selects whose name includes the column name
    const allSelects = document.querySelectorAll('select');
    allSelects.forEach((select) => {
        const selectName = select.getAttribute('name') || '';
        
        // Check if this select is for the column we're updating
        if (selectName.includes(columnName) && !select.closest('.meros-repeater-table')) {
            // Make sure it's not already handled above
            updateRepeaterSelectField(select, detail, options, columnName);
        }
    });
}

/**
 * Helper function to update a single select field's options and state.
 */
function updateRepeaterSelectField(select, detail, options, columnName) {
    if (!select) {
        return;
    }

    // Cache the current value before destroying
    const currentValue = select.multiple
        ? Array.from(select.querySelectorAll('option:checked')).map((opt) => opt.value)
        : select.value;

    // Destroy existing TomSelect instance
    if (select.tomselect) {
        destroyTomSelectForElement(select);
    }

    // Update attributes
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

    // Rebuild options
    select.innerHTML = '';

    options.forEach((opt) => {
        const optionValue = String(opt.value ?? '');
        const optionLabel = String(opt.label ?? optionValue);
        const option = document.createElement('option');
        option.value = optionValue;
        option.textContent = optionLabel;

        // Restore previous selection if value was in old options
        if (Array.isArray(currentValue)) {
            option.selected = currentValue.includes(optionValue);
        } else if (currentValue) {
            option.selected = String(currentValue) === optionValue;
        }

        select.appendChild(option);
    });

    // Reinitialize TomSelect if needed
    if (detail.multiple || detail.advanced) {
        initTomSelectForElement(select);
    }
}

window.addEventListener('tomselect-repeater-column-sync', (event) => {
    applyTomSelectRepeaterColumnSync(event.detail || {});
});

/**
 * Apply value sync for all instances of a repeater row field across all rendering contexts.
 * Updates TomSelect values when a row value changes in any location.
 */
function applyTomSelectRepeaterRowValueSync(detail) {
    if (!detail || !detail.fieldName) {
        return;
    }

    const fieldName = String(detail.fieldName);
    const value = detail.value;
    const columnIndex = detail.columnIndex;

    // Find all repeater tables on the page
    const repeaterTables = document.querySelectorAll('.meros-repeater-table');

    repeaterTables.forEach((table) => {
        const rows = table.querySelectorAll('tbody tr');

        // Column index should map to cell position (skip first cell with drag handle)
        rows.forEach((row) => {
            const cells = row.querySelectorAll('td.meros-repeater-data-cell');

            // Column index corresponds to cell position
            if (cells[columnIndex]) {
                const select = cells[columnIndex].querySelector('select');

                if (select && select.getAttribute('name')?.includes(fieldName)) {
                    updateRepeaterSelectValue(select, value);
                }
            }
        });
    });

    // Also find standalone select fields in Edit Row panel
    const allSelects = document.querySelectorAll('select');
    allSelects.forEach((select) => {
        const selectName = select.getAttribute('name') || '';
        
        if (selectName.includes(fieldName) && !select.closest('.meros-repeater-table')) {
            updateRepeaterSelectValue(select, value);
        }
    });
}

/**
 * Helper function to update a select field's value without changing options.
 */
function updateRepeaterSelectValue(select, value) {
    if (!select) {
        return;
    }

    // Update the underlying HTML select options
    const isMultiple = select.hasAttribute('multiple');
    
    if (isMultiple) {
        // For multi-select, clear all selections then select specified values
        Array.from(select.options).forEach((opt) => {
            opt.selected = Array.isArray(value) && value.includes(opt.value);
        });
    } else {
        // For single select, set the value
        select.value = value || '';
    }

    // If TomSelect instance exists, update it with the new value
    if (select.tomselect && typeof select.tomselect.setValue === 'function') {
        if (isMultiple) {
            select.tomselect.setValue(Array.isArray(value) ? value : [], true);
        } else {
            select.tomselect.setValue(value || '', true);
        }

        // Update cache with new value
        cacheSelectValue(select, value);
    }
}

window.addEventListener('tomselect-repeater-row-value-sync', (event) => {
    applyTomSelectRepeaterRowValueSync(event.detail || {});
});

/**
 * Manually refresh TomSelect for all select fields in the active field settings.
 * Called when user clicks the "Refresh Select Display" button.
 */
window.refreshTomSelectForActiveField = function() {
    try {
        // Find the active field container - look for the settings panel
        const settingsPanel = document.querySelector('[role="dialog"]') || document.querySelector('.settings-panel');
        if (!settingsPanel) {
            console.warn('Could not find active settings panel');
            return;
        }
        
        // Find all select elements in the canvas that have TomSelect enabled
        const allSelects = document.querySelectorAll('select[data-advanced="true"], select[multiple]');
        
        if (allSelects.length === 0) {
            console.warn('No TomSelect-enabled selects found in canvas');
            return;
        }
        
        // Reinitialize all TomSelect instances
        allSelects.forEach(select => {
            try {
                // Destroy the old instance if it exists
                if (select.tomselect) {
                    destroyTomSelectForElement(select);
                }
                
                // Reinitialize with fresh state
                initTomSelectForElement(select);
            } catch (err) {
                console.warn('Error refreshing select:', err);
            }
        });
        
        console.log(`Refreshed ${allSelects.length} TomSelect instances`);
    } catch (err) {
        console.error('Error in refreshTomSelectForActiveField:', err);
    }
};

/**
 * Expose TomSelect utilities globally for inline use in templates if needed.
 */
window.TomSelectUtils = {
    init: initTomSelectForElement,
    destroy: destroyTomSelectForElement,
    initAll: initTomSelects,
    sync: initTomSelectsAfterLivewireUpdate,
    syncValue: syncTomSelectValue,
    refresh: window.refreshTomSelectForActiveField,
};