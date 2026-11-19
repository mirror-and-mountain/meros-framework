/**
 * Provides dynamic suggestions for form fields, including token parsing and suggestion menu handling.
 */

/**
 * Finds the token at the current cursor position within the text.
 *
 * @param {string} text The text to search within.
 * @param {number} cursor The current cursor position.
 * @returns {Object|null} The token information or null if none found.
 */
function tokenAtCursor(text, cursor) {
    const before = text.slice(0, cursor);
    const complete = before.match(/\{\{([^{}]*)\}\}$/);
    if (complete) return { start: cursor - complete[0].length, end: cursor, query: complete[1] };

    const partial = before.match(/\{\{([^{}]*)$/);
    if (!partial) return null;
    const after = text.slice(cursor);
    const closing = after.match(/^[^{}]*\}\}/);
    return {
        start: cursor - partial[0].length,
        end: closing ? cursor + closing[0].length : cursor,
        query: partial[1],
    };
}

/**
 * Retrieves the list of available dynamic value choices for the form.
 * 
 * @param {boolean} includeFields Whether to include form fields in the choices.
 * @returns {Array} The list of available choices, including dynamic values and optionally form fields.
 */
function choices(includeFields) {
    const builder = document.querySelector('.meros-form-builder[data-dynamic-values]');
    if (!builder) return [];
    const definitions = JSON.parse(builder.dataset.dynamicValues);
    const options = definitions.map(({ name, type }) => ({ name, type, label: name.replaceAll('_', ' ') }));
    if (includeFields) {
        builder.querySelectorAll('[data-form-field-component]').forEach(card => {
            if (card.closest('.meros-repeater-table-row--template')) return;
            const input = suffix => Array.from(card.querySelectorAll(`[name$="[${suffix}]"]`))
                .find(element => element.closest('[data-form-field-component]') === card);
            const name = input('field_name')?.value;
            const type = input('field_type')?.value;
            if (!name || name.startsWith('new_field_') || name === '__FIELD_NAME__' || type === 'password') return;
            if (!options.some(option => option.name === name)) {
                options.push({ name, label: `Field: ${input('field_label')?.value || name}` });
            }
        });
    }
    return options;
}

/**
 * Attaches dynamic suggestions to a form control, optionally integrating with a Quill editor instance.
 *
 * @param {HTMLElement} control The form control to attach suggestions to.
 * @param {Object} options Configuration options.
 * @param {Quill|null} options.quill The Quill editor instance, if applicable.
 * @param {boolean} options.includeFields Whether to include form fields in the suggestions.
 */
export function attachDynamicSuggestions(control, { quill = null, includeFields = false } = {}) {
    const host = quill ? control : control.parentElement;
    if (!host || host.dataset.dynamicSuggestionsAttached) return;
    host.dataset.dynamicSuggestionsAttached = '1';
    const editor = quill ? quill.root : control;
    const menu = document.createElement('div');
    menu.className = 'mforms-dynamic-suggestions';
    const closeButton = document.createElement('button');
    closeButton.type = 'button';
    closeButton.className = 'mforms-dynamic-suggestions-close';
    closeButton.textContent = 'Close';
    closeButton.setAttribute('aria-label', 'Close dynamic value suggestions');
    const list = document.createElement('div');
    list.setAttribute('role', 'listbox');
    list.setAttribute('aria-label', 'Dynamic value suggestions');
    menu.append(closeButton, list);
    menu.hidden = true;
    host.classList.add('mforms-dynamic-suggestions-host');
    host.append(menu);
    let active = 0;
    let match = null;
    let options = [];
    let inserting = false;
    let acceptedToken = null;
    let dismissedText = null;
    const types = control.hasAttribute('data-dynamic-value-types')
        ? JSON.parse(control.dataset.dynamicValueTypes) : null;
    const availableChoices = () => choices(includeFields).filter(option => !types || types.includes(option.type));
    const validate = () => {
        if (!types) return;
        const token = control.value.match(/^\{\{\s*([^{}]+?)\s*\}\}$/);
        const name = token?.[1].trim();
        const definition = choices(false).find(option => option.name === name);
        const error = !name ? 'Enter a complete dynamic value, for example {{user_id}}.'
            : !definition ? 'Choose a registered dynamic value.'
                : !types.includes(definition.type) ? `This field requires a dynamic value of type: ${types.join(', ')}.`
                    : '';
        control.setCustomValidity(error);
    };
    const hide = () => {
        menu.hidden = true;
        editor.removeAttribute('aria-activedescendant');
        editor.setAttribute('aria-expanded', 'false');
    };
    const dismiss = () => {
        dismissedText = quill ? quill.getText() : control.value;
        hide();
    };
    closeButton.addEventListener('mousedown', event => event.preventDefault());
    closeButton.addEventListener('click', event => {
        event.stopPropagation();
        dismiss();
        editor.focus();
    });
    menu.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            event.preventDefault();
            event.stopPropagation();
            dismiss();
            editor.focus();
        }
    });
    const insert = option => {
        if (!match) return;
        const value = `{{${option.name}}}`;
        acceptedToken = { start: match.start, end: match.start + value.length, value };
        if (quill) {
            inserting = true;
            quill.deleteText(match.start, match.end - match.start, 'user');
            quill.insertText(match.start, value, 'user');
            quill.setSelection(match.start + value.length, 0, 'silent');
            editor.focus();
            inserting = false;
        } else {
            control.setRangeText(value, match.start, match.end, 'end');
            control.dispatchEvent(new Event('input', { bubbles: true }));
            control.dispatchEvent(new Event('change', { bubbles: true }));
            control.focus();
        }
        hide();
    };
    const highlight = () => {
        Array.from(list.children).forEach((item, index) => {
            item.setAttribute('aria-selected', String(index === active));
        });
        const selected = list.children[active];
        if (selected) {
            editor.setAttribute('aria-activedescendant', selected.id);
            selected.scrollIntoView?.({ block: 'nearest' });
        }
    };
    const update = () => {
        if (inserting) return;
        if (!editor.isConnected || editor.matches('[readonly], :disabled, [contenteditable="false"]')) return hide();
        const selection = quill ? quill.getSelection() : null;
        const cursor = quill ? selection?.index : control.selectionStart;
        if (cursor === null || cursor === undefined || (selection && selection.length)) return hide();
        const text = quill ? quill.getText() : control.value;
        if (text === dismissedText) return hide();
        dismissedText = null;
        match = tokenAtCursor(text, cursor);
        if (match && acceptedToken && match.start === acceptedToken.start && match.end === acceptedToken.end &&
            text.slice(match.start, match.end) === acceptedToken.value) return hide();
        acceptedToken = null;
        if (!match) return hide();
        const query = match.query.trim().toLowerCase();
        options = availableChoices().filter(option =>
            option.name.toLowerCase().includes(query) || option.label.toLowerCase().includes(query));
        list.replaceChildren();
        options.forEach((option, index) => {
            const item = document.createElement('div');
            item.id = `${menu.id}-option-${index}`;
            item.setAttribute('role', 'option');
            item.textContent = `${option.label}  {{${option.name}}}`;
            item.addEventListener('mousedown', event => event.preventDefault());
            item.addEventListener('click', () => insert(option));
            list.append(item);
        });
        if (!options.length) return hide();
        active = 0;
        menu.hidden = false;
        editor.setAttribute('aria-expanded', 'true');
        highlight();
    };
    menu.id = `mforms-dynamic-suggestions-${++suggestionSequence}`;
    list.id = `${menu.id}-listbox`;
    if (!quill) editor.setAttribute('role', 'combobox');
    editor.setAttribute('aria-autocomplete', 'list');
    editor.setAttribute('aria-controls', list.id);
    editor.setAttribute('aria-expanded', 'false');
    editor.addEventListener('keydown', event => {
        if (menu.hidden) return;
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            active = (active + (event.key === 'ArrowDown' ? 1 : -1) + options.length) % options.length;
            highlight();
        } else if (event.key === 'Enter' || event.key === 'Tab') {
            event.preventDefault();
            insert(options[active]);
        } else if (event.key === 'Escape') {
            event.preventDefault();
            event.stopPropagation();
            dismiss();
        }
    }, true);
    editor.addEventListener('blur', event => {
        if (!menu.contains(event.relatedTarget)) hide();
    });
    if (quill) {
        quill.on('text-change', () => {
            if (!inserting) queueMicrotask(update);
        });
        quill.on('selection-change', update);
    } else {
        control.addEventListener('input', () => {
            validate();
            update();
        });
        control.addEventListener('change', validate);
        control.addEventListener('click', update);
        validate();
    }
    return validate;
}
let suggestionSequence = 0;

