import TomSelect from 'tom-select';

const tomSelectValueCache = new Map();

let tomSelectRebindScheduled = false;
let tomSelectRebindInProgress = false;

// -----------------------------------------------------------------------------
// Cache key helpers
// Used by internal value cache to survive Livewire-driven select re-renders.
// -----------------------------------------------------------------------------
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

export function cacheSelectValue(select, value) {
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

// -----------------------------------------------------------------------------
// Core TomSelect lifecycle
// Used by initial page boot + Livewire rebinding + emitted sync events.
// -----------------------------------------------------------------------------
export function initTomSelects() {
    const selects = document.querySelectorAll('select[data-advanced="true"], select[multiple]');
    selects.forEach((select) => initTomSelectForElement(select));
}

function hashSelectOptions(select) {
    return Array.from(select.options).map((opt) => `${opt.value}|${opt.text}`).join('||');
}

export function destroyTomSelectForElement(select) {
    try {
        if (!select || !select.tomselect) return;

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
        if (select) {
            select.tomselect = null;
            select.classList.remove('tomselected', 'ts-hidden-accessible');
            select.removeAttribute('tabindex');
        }
    }
}

export function syncTomSelectValue(select) {
    try {
        if (!select || !select.tomselect) return;

        const isMultiple = select.hasAttribute('multiple');

        if (isMultiple) {
            const selectedValues = Array.from(select.querySelectorAll('option:checked')).map((opt) => opt.value);
            if (select.tomselect.setValue) {
                select.tomselect.setValue(selectedValues, true);
            }
        } else {
            const selectedValue = select.value;
            if (selectedValue && select.tomselect.setValue) {
                select.tomselect.setValue(selectedValue, true);
            }
        }
    } catch (err) {
        console.warn('Error syncing TomSelect value:', err);
    }
}

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

export function initTomSelectForElement(select) {
    try {
        if (!select || !(select instanceof HTMLSelectElement)) return;
        if (!select.parentElement) return;

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

            destroyTomSelectForElement(select);
        }

        if (select.getAttribute('data-advanced') !== 'true' && !select.hasAttribute('multiple')) return;

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
                },
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
                    if (!isMultiple) {
                        select.tomselect.blur();
                    }
                }
            },
            onDropdownOpen: isInsideRepeater ? function(dropdown) {
                const wrapper = select.tomselect?.wrapper;
                if (!wrapper || !dropdown) return;

                const rect = wrapper.getBoundingClientRect();
                const scrollX = window.scrollX || window.pageXOffset;
                const scrollY = window.scrollY || window.pageYOffset;

                dropdown.style.position = 'absolute';
                dropdown.style.zIndex = '999999';
                dropdown.style.width = `${rect.width}px`;
                dropdown.style.top = `${rect.bottom + scrollY}px`;
                dropdown.style.left = `${rect.left + scrollX}px`;

                document.body.appendChild(dropdown);
                select._tomSelectDropdown = dropdown;
            } : undefined,
            onDropdownClose: isInsideRepeater ? function(dropdown) {
                const wrapper = select.tomselect?.wrapper;
                if (wrapper && dropdown && dropdown.parentElement === document.body) {
                    wrapper.appendChild(dropdown);
                }
                select._tomSelectDropdown = null;
            } : undefined,
        });

        if (select.tomselect) {
            select.dataset.tomselectOptionsHash = hashSelectOptions(select);
            select.dataset.tomselectAllowAdd = allowAdd ? 'true' : 'false';
            select.dataset.tomselectInitialized = 'true';

            syncTomSelectValue(select);
            cacheSelectValue(select, select.tomselect.getValue());
        }
    } catch (err) {
        console.warn('Error initializing TomSelect:', err);
        if (select && select.tomselect) {
            try {
                select.tomselect.destroy();
            } catch (e) {
                // Ignore cleanup errors
            }
        }
    }
}

// -----------------------------------------------------------------------------
// Livewire rebind pipeline
// Used to rebuild TomSelect instances after Livewire morphs.
// -----------------------------------------------------------------------------
export function initTomSelectsAfterLivewireUpdate() {
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

                select.classList.remove('tomselected', 'ts-hidden-accessible');
                select.removeAttribute('tabindex');

                if (select.nextElementSibling && select.nextElementSibling.classList.contains('ts-wrapper')) {
                    select.nextElementSibling.remove();
                }

                return;
            }

            let htmlValue;
            if (select.hasAttribute('multiple')) {
                htmlValue = Array.from(select.querySelectorAll('option:checked')).map((opt) => opt.value);
            } else {
                const explicitlySelected = select.querySelector('option[selected]');
                htmlValue = explicitlySelected ? explicitlySelected.value : null;
            }

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

    requestAnimationFrame(() => {
        rebindAllManagedSelects();
    });

    setTimeout(() => {
        rebindAllManagedSelects();
    }, 0);
}

export function scheduleTomSelectRebind() {
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
            setTimeout(() => {
                tomSelectRebindInProgress = false;
            }, 20);
        }
    });
}

export function observeTomSelectWrapperLoss() {
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
