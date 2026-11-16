const settings = {
    __properties_locked_by_template: 'allow_edit_field_properties',
    __move_locked_by_template: 'allow_move_components',
    __remove_locked_by_template: 'allow_remove_components',
};

const matchesName = (input, name) =>
    input.name === name || input.name.replaceAll("'", '').endsWith(`[${name}]`);

export function positionTemplateFieldLocks(form) {
    const controls = Array.from(form.querySelectorAll('input[type="checkbox"][name]:not(:disabled)'))
        .filter(input => Object.keys(settings).some(flag => matchesName(input, flag)))
        .map(input => input.closest('.mforms-field-wrapper'));
    if (!controls.length) return;

    const group = document.createElement('div');
    group.className = 'mforms-builder-modal-locks';
    group.setAttribute('role', 'group');
    group.setAttribute('aria-label', 'Template field locks');
    const rows = new Set(controls.map(control => control.closest('.mforms-row')));
    group.append(...controls);
    form.append(group);
    rows.forEach(row => {
        if (row && !row.children.length) row.remove();
    });
}

export function updateTemplateLockControls(container, builder) {
    const root = builder.closest('[data-template-settings]');
    if (!root) return;

    const values = JSON.parse(root.dataset.templateSettings);
    Object.entries(settings).forEach(([flag, setting]) => {
        const globalControl = Array.from(document.querySelectorAll('input[type="checkbox"][name]'))
            .find(input => matchesName(input, setting));
        const allowed = globalControl ? globalControl.checked : ![false, 0, '0', 'false'].includes(values[setting]);
        container.querySelectorAll('input[type="checkbox"][name]').forEach(input => {
            if (!matchesName(input, flag)) return;
            const wrapper = input.closest('.mforms-field-wrapper') || input.closest('label');
            if (!wrapper) return;
            if (!allowed) {
                wrapper._templateLockDisplay ??= wrapper.style.display;
                wrapper.style.display = 'none';
            } else if (wrapper._templateLockDisplay !== undefined) {
                wrapper.style.display = wrapper._templateLockDisplay;
                delete wrapper._templateLockDisplay;
            }
        });
    });
}

export function watchTemplateLockControls(root) {
    const update = () => updateTemplateLockControls(root, root);
    const onChange = event => {
        if (event.target.matches('input[type="checkbox"][name]') &&
            Object.values(settings).some(setting => matchesName(event.target, setting))) {
            update();
        }
    };
    document.addEventListener('change', onChange);
    update();
    return () => document.removeEventListener('change', onChange);
}
