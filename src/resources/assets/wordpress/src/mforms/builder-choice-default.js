let hintSequence = 0;

export function createChoiceDefaultEditor(form, type, settings, locked = false) {
    if (!['select', 'radio', 'checkboxes'].includes(type)) return null;

    const optionsRepeater = form.querySelector('.meros-repeater-field[data-name="options"]');
    if (!optionsRepeater) throw new Error('The choice options editor is unavailable.');

    const wrapper = document.createElement('label');
    wrapper.className = 'mforms-builder-choice-default';
    const title = document.createElement('span');
    title.textContent = 'Default value';
    const select = document.createElement('select');
    select.disabled = locked;
    const hint = document.createElement('small');
    hint.id = `mforms-choice-default-hint-${++hintSequence}`;
    select.setAttribute('aria-describedby', hint.id);
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = '__builder_default_value';
    wrapper.append(title, select, hint);
    form.append(wrapper, input);

    const stored = settings.__builder_default_value !== undefined
        ? JSON.parse(settings.__builder_default_value)
        : settings.defaultValue;
    let value = Array.isArray(stored) ? stored.map(String) : stored == null || stored === '' ? [] : [String(stored)];

    const update = () => {
        const component = window.Alpine?.$data(optionsRepeater);
        if (!component || typeof component.getValue !== 'function') {
            throw new Error('The choice options editor is not initialized.');
        }
        const multiple = type === 'checkboxes' ||
            (type === 'select' && Boolean(form.querySelector('[name="allowsMultiple"]')?.checked));
        const options = component.getValue().filter(option => option.value != null && String(option.value) !== '');
        const allowed = new Set(options.map(option => String(option.value)));
        value = value.filter(item => allowed.has(item));
        if (!multiple) value = value.slice(0, 1);
        select.multiple = multiple;
        select.replaceChildren();
        if (!multiple) {
            const empty = document.createElement('option');
            empty.value = '';
            empty.textContent = 'No default';
            select.add(empty);
        }
        options.forEach(option => {
            const optionValue = String(option.value);
            const item = document.createElement('option');
            item.value = optionValue;
            item.textContent = String(option.label || optionValue);
            item.selected = value.includes(optionValue);
            select.add(item);
        });
        if (!multiple && value.length === 0) select.value = '';
        hint.textContent = multiple ? 'Hold Ctrl (or Command) to select multiple values.' : '';
        input.value = JSON.stringify(multiple ? value : value[0] ?? null);
    };

    select.addEventListener('change', () => {
        value = Array.from(select.selectedOptions, option => option.value).filter(item => item !== '');
        input.value = JSON.stringify(select.multiple ? value : value[0] ?? null);
    });
    form.addEventListener('input', event => {
        if (optionsRepeater.contains(event.target)) update();
    });
    form.addEventListener('change', event => {
        if (optionsRepeater.contains(event.target) || event.target.name === 'allowsMultiple') update();
    });
    update();
    return wrapper;
}
