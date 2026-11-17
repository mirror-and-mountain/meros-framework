import MerosModal from '../classes/modal.js';

const operators = ['=', '!=', 'LIKE', 'CONTAINS', 'NOT CONTAINS', 'EMPTY', 'NOT EMPTY', '<', '>', '=>', '=<'];
const equality = ['=', '!='];
const emptiness = ['EMPTY', 'NOT EMPTY'];
const ordering = ['<', '>', '=>', '=<'];
const textMatching = ['LIKE', 'CONTAINS', 'NOT CONTAINS'];
const operatorLabels = {
    '=': 'Equals', '!=': 'Does not equal', LIKE: 'Matches text',
    CONTAINS: 'Contains', 'NOT CONTAINS': 'Does not contain',
    EMPTY: 'Is empty', 'NOT EMPTY': 'Is not empty',
    '<': 'Less than', '>': 'Greater than', '=>': 'Greater than or equal to', '=<': 'Less than or equal to',
};
let hintSequence = 0;

function operatorsForType(type) {
    let available;
    if (['date', 'time', 'number'].includes(type)) {
        available = [...equality, ...emptiness, ...ordering];
    } else if (['text', 'textarea', 'text_area', 'text-area', 'richtext', 'rich_text', 'rich-text', 'email', 'url', 'tel', 'password', 'hidden'].includes(type)) {
        available = [...equality, ...textMatching, ...emptiness];
    } else if (['checkbox', 'user-signed-in'].includes(type)) {
        available = equality;
    } else if (['select', 'radio', 'checkboxes', 'lookup', 'posts-lookup', 'posts_lookup', 'users-lookup', 'users_lookup'].includes(type)) {
        available = [...equality, 'CONTAINS', 'NOT CONTAINS', ...emptiness];
    } else if (type === 'repeater') {
        available = emptiness;
    } else {
        available = type ? operators : equality;
    }
    return available;
}

function element(tag, attributes = {}, text = '') {
    const node = document.createElement(tag);
    Object.entries(attributes).forEach(([key, value]) => {
        if (key === 'className') node.className = value;
        else node.setAttribute(key, value);
    });
    if (text) node.textContent = text;
    return node;
}

function labeledControl(label, control) {
    const wrapper = element('label');
    wrapper.append(element('span', {}, label), control);
    return wrapper;
}

function select(options, selected) {
    const control = element('select');
    options.forEach(([value, label]) => {
        const option = element('option', { value }, label);
        control.append(option);
    });
    control.value = selected;
    return control;
}

function topBuilder(target) {
    let root = target.closest('.meros-form-builder');
    while (root?.parentElement?.closest('.meros-form-builder')) {
        root = root.parentElement.closest('.meros-form-builder');
    }
    return root;
}

function fieldInput(card, suffix) {
    return Array.from(card.querySelectorAll(`[name$="[${suffix}]"]`)).find(input =>
        input.closest('[data-form-field-component]') === card &&
        !input.closest('.meros-repeater-table-row--template')
    );
}

export function getConditionSources(target) {
    const root = topBuilder(target);
    if (!root) throw new Error('The form builder could not be found.');

    const cards = Array.from(root.querySelectorAll('[data-form-field-component]'));
    const sources = [];
    cards.forEach(card => {
        const name = fieldInput(card, 'field_name');
        if (!name || !name.value) return;
        if (!(target.compareDocumentPosition(card) & target.ownerDocument.defaultView.Node.DOCUMENT_POSITION_PRECEDING)) return;
        const label = fieldInput(card, 'field_label')?.value || name.value;
        const type = fieldInput(card, 'field_type')?.value || '';
        sources.push([name.value, `${label} (${name.value})`, type, card]);
    });
    return sources;
}

function optionRecords(options) {
    if (!options || typeof options !== 'object') return [];
    return Object.entries(options).map(([key, option]) => {
        if (option && typeof option === 'object' && !Array.isArray(option)) {
            return {
                value: String(option.value ?? key),
                label: String(option.label ?? option.text ?? option.value ?? key),
                disabled: Boolean(option.disabled),
            };
        }
        return Array.isArray(options)
            ? { value: String(option), label: String(option), disabled: false }
            : { value: key, label: String(option ?? key), disabled: false };
    });
}

function choiceSettings(source) {
    const raw = fieldInput(source[3], '__form_data')?.value;
    const settings = raw ? JSON.parse(raw) : {};
    const conditions = settings.__builder_conditions
        ? JSON.parse(settings.__builder_conditions)
        : settings.conditions;
    const options = optionRecords(settings.options);
    Object.values(conditions?.set_options?.rules || {}).forEach(rule => {
        options.push(...optionRecords(rule.dest_value));
    });
    const uniqueOptions = new Map(options.map(option => [option.value, option]));
    const multiple = source[2] === 'checkboxes' || (source[2] === 'select' &&
        [true, 1, '1', 'true'].includes(settings.allowsMultiple ?? settings.multiple));
    return { options: Array.from(uniqueOptions.values()), multiple };
}

export function createConditionEditor(target, conditions, { options = false, locked = false } = {}) {
    if (!conditions || typeof conditions !== 'object') {
        throw new Error('Invalid saved conditions.');
    }
    const original = Array.isArray(conditions) && conditions.length === 0 ? {} : conditions;
    if (Array.isArray(original)) throw new Error('Invalid saved conditions.');
    const sources = getConditionSources(target);
    const editor = element('div', { className: 'mforms-builder-conditions' });
    const controls = [];
    const types = options ? [['show', 'Show'], ['hide', 'Hide'], ['set_options', 'Set options']] : [['show', 'Show'], ['hide', 'Hide']];
    editor.append(element('h3', {}, 'Conditions'));
    if (!sources.length) {
        editor.append(element('p', {}, 'No earlier fields are available. Show/hide rules can still use user sign-in status.'));
    }

    types.forEach(([type, title]) => {
        const ruleSources = type === 'set_options' ? sources : [
            ['__user_signed_in', 'User signed in', 'user-signed-in'],
            ...sources,
        ];
        const config = original[type] || {};
        const rules = config.rules ? Object.values(config.rules) : [];
        const section = element('fieldset', { className: 'mforms-builder-condition-set' });
        if (locked) section.disabled = true;
        section.append(element('legend', {}, title));
        if (type === 'set_options') {
            section.append(element('p', {}, 'Matching rules contribute their options. With All, every rule must match.'));
        }
        const logic = select([['AND', 'All rules match'], ['OR', 'Any rule matches']], config.logic === 'OR' ? 'OR' : 'AND');
        section.append(labeledControl('Match', logic));
        const list = element('div', { className: 'mforms-builder-condition-rules' });
        section.append(list);

        const addRule = (rule = {}) => {
            const row = element('div', { className: 'mforms-builder-condition-rule' });
            const fieldOptions = [['', 'Select a source'], ...ruleSources];
            if (rule.field && !ruleSources.some(([name]) => name === rule.field)) {
                fieldOptions.push([rule.field, `${rule.field} (unavailable)`]);
            }
            const field = select(fieldOptions, rule.field || '');
            if (rule.field && !ruleSources.some(([name]) => name === rule.field)) {
                field.selectedOptions[0].disabled = true;
            }
            field.required = true;
            const operator = select([], '');
            const sourceValue = Object.prototype.hasOwnProperty.call(rule, 'source_value') ? rule.source_value : rule.value ?? '';
            let currentValue = sourceValue;
            let valueChanged = false;
            let previousField = field.value;
            const valuesByField = new Map([[previousField, { value: currentValue, changed: false }]]);
            let valueControl;
            const valueLabel = element('label');
            valueLabel.append(element('span', {}, 'Value'));
            const valueHint = element('small', { id: `mforms-condition-value-hint-${++hintSequence}` });
            const updateValueControl = () => {
                const source = ruleSources.find(([name]) => name === field.value);
                const sourceType = source?.[2];
                const selectedValues = (Array.isArray(currentValue) ? currentValue : [currentValue])
                    .filter(item => item !== null && item !== undefined)
                    .map(String);
                let control;
                let hint = '';
                if (['select', 'radio', 'checkboxes'].includes(sourceType)) {
                    const { options, multiple } = choiceSettings(source);
                    control = element('select');
                    control.multiple = multiple;
                    control.required = true;
                    if (!multiple) control.append(element('option', { value: '' }, 'Select a value'));
                    options.forEach(option => {
                        const item = element('option', { value: option.value }, option.label);
                        item.disabled = option.disabled;
                        control.append(item);
                    });
                    selectedValues.filter(item => item !== '' && !options.some(option => option.value === item)).forEach(item => {
                        control.append(element('option', { value: item }, `${item} (not in configured options)`));
                    });
                    Array.from(control.options).forEach(option => {
                        option.selected = selectedValues.includes(option.value);
                    });
                    hint = options.length === 0
                        ? 'No options are configured for this field.'
                        : multiple ? 'Hold Ctrl (or Command) to select multiple values.' : '';
                } else if (['checkbox', 'user-signed-in'].includes(sourceType)) {
                    const labels = sourceType === 'user-signed-in' ? ['Signed in', 'Signed out'] : ['Checked', 'Unchecked'];
                    control = select([['', 'Select a value'], ['true', labels[0]], ['false', labels[1]]], String(currentValue ?? ''));
                    control.required = true;
                } else {
                    const inputType = ['date', 'time', 'number'].includes(sourceType) ? sourceType : 'text';
                    control = element('input', { type: inputType });
                    if (inputType === 'number') control.step = 'any';
                    const display = typeof currentValue === 'object' ? JSON.stringify(currentValue) : String(currentValue ?? '');
                    control.value = display;
                    if (display && control.value !== display) {
                        hint = `Saved value: ${display}`;
                    }
                }
                control.disabled = emptiness.includes(operator.value);
                control.setAttribute('aria-describedby', valueHint.id);
                const updateValue = () => {
                    currentValue = control.multiple
                        ? Array.from(control.selectedOptions, option => option.value)
                        : control.value;
                    valueChanged = true;
                    if (!control.multiple) valueHint.textContent = '';
                };
                control.addEventListener('input', updateValue);
                control.addEventListener('change', updateValue);
                if (valueControl) valueControl.replaceWith(control);
                else valueLabel.append(control);
                valueControl = control;
                valueHint.textContent = hint;
                valueLabel.append(valueHint);
            };
            const updateOperators = (selected, preserveUnsupported = false) => {
                const source = ruleSources.find(([name]) => name === field.value);
                let available = operatorsForType(source?.[2] || '');
                const invalidRepeaterOperator = source?.[2] === 'repeater' && !available.includes(selected);
                if (preserveUnsupported && selected && !available.includes(selected)) {
                    available = [...available, selected];
                }
                operator.replaceChildren(...available.map(item => element('option', { value: item }, operatorLabels[item] || item)));
                operator.value = available.includes(selected) ? selected : available[0];
                if (preserveUnsupported && invalidRepeaterOperator) {
                    operator.selectedOptions[0].disabled = true;
                }
            };
            updateOperators(rule.operator || '=', Boolean(rule.field));
            updateValueControl();
            field.addEventListener('change', () => {
                valuesByField.set(previousField, { value: currentValue, changed: valueChanged });
                const saved = valuesByField.get(field.value) || { value: '', changed: true };
                currentValue = saved.value;
                valueChanged = saved.changed;
                previousField = field.value;
                updateOperators(operator.value);
                updateValueControl();
            });
            operator.addEventListener('change', () => {
                valueControl.disabled = emptiness.includes(operator.value);
            });
            const comparison = element('div', { className: 'mforms-builder-condition-comparison' });
            comparison.append(labeledControl('Source', field), labeledControl('Operator', operator), valueLabel);
            row.append(comparison);
            const optionList = element('div', { className: 'mforms-builder-condition-options' });
            const optionControls = [];
            if (type === 'set_options') {
                const addOption = (option = { value: '', label: '', disabled: false }) => {
                    const item = element('div', { className: 'mforms-builder-condition-option' });
                    const optionValue = element('input', { type: 'text' });
                    const optionLabel = element('input', { type: 'text' });
                    const disabled = element('input', { type: 'checkbox' });
                    optionValue.value = option.value;
                    optionLabel.value = option.label;
                    disabled.checked = option.disabled;
                    optionValue.required = true;
                    optionLabel.required = true;
                    const remove = element('button', { type: 'button', className: 'button' }, 'Remove option');
                    remove.addEventListener('click', () => {
                        const next = item.nextElementSibling || item.previousElementSibling;
                        item.remove();
                        (next?.querySelector('input') || add).focus();
                    });
                    item.append(labeledControl('Option value', optionValue), labeledControl('Option label', optionLabel), labeledControl('Disabled', disabled), remove);
                    optionList.append(item);
                    optionControls.push({ item, optionValue, optionLabel, disabled });
                    return optionValue;
                };
                optionRecords(rule.dest_value).forEach(addOption);
                const add = element('button', { type: 'button', className: 'button' }, 'Add option');
                add.addEventListener('click', () => addOption().focus());
                const optionEditor = element('div', { className: 'mforms-builder-condition-option-editor' });
                optionEditor.append(element('strong', {}, 'Options when this rule matches'), optionList, add);
                row.append(optionEditor);
            }
            const remove = element('button', { type: 'button', className: 'button' }, 'Remove rule');
            remove.addEventListener('click', () => {
                const next = row.nextElementSibling || row.previousElementSibling;
                row.remove();
                (next?.querySelector('select') || add).focus();
            });
            row.append(remove);
            list.append(row);
            return { row, rule, field, operator, getSourceValue: () => valueChanged ? currentValue : sourceValue, optionControls };
        };
        const rows = rules.map(addRule);
        const add = element('button', { type: 'button', className: 'button' }, 'Add rule');
        add.addEventListener('click', () => {
            const rule = addRule();
            rows.push(rule);
            rule.field.focus();
        });
        section.append(add);
        editor.append(section);
        controls.push({ type, config, logic, rows });
    });

    return {
        element: editor,
        getValue() {
            if (locked) return original;
            const result = { ...original };
            controls.forEach(({ type, config, logic, rows }) => {
                const active = rows.filter(({ row }) => row.isConnected);
                if (!active.length) {
                    delete result[type];
                    return;
                }
                result[type] = {
                    ...config,
                    logic: logic.value,
                    rules: active.map(({ rule, field, operator, getSourceValue, optionControls }) => {
                        const next = { ...rule, field: field.value, operator: operator.value, source_value: getSourceValue() };
                        if (type === 'set_options') {
                            next.dest_value = optionControls.filter(({ item }) => item.isConnected).map(({ optionValue, optionLabel, disabled }) => ({
                                value: optionValue.value, label: optionLabel.value, disabled: disabled.checked,
                            }));
                        }
                        return next;
                    }),
                };
            });
            return result;
        },
        validate() {
            if (!locked && controls.some(({ rows }) => rows.some(({ row, field, operator }) =>
                row.isConnected && (field.selectedOptions[0]?.disabled || operator.selectedOptions[0]?.disabled)
            ))) {
                window.alert('Choose an available field and operator for each condition rule.');
                return false;
            }
            const invalid = editor.querySelector(':invalid');
            if (invalid) {
                invalid.reportValidity();
                return false;
            }
            if (!locked && controls.some(({ type, rows }) => type === 'set_options' && rows.some(({ row, optionControls }) =>
                row.isConnected && !optionControls.some(({ item }) => item.isConnected)
            ))) {
                window.alert('Add at least one option to each set-options rule.');
                return false;
            }
            return true;
        },
    };
}

export function configureConditions(target) {
    const input = target.querySelector('[data-builder-conditions]');
    if (!input) throw new Error('The condition storage field is missing.');
    const editor = createConditionEditor(target, JSON.parse(input.value));
    const modal = new MerosModal('Configure conditions', '', 'Save', 'Cancel', false);
    modal.content.append(editor.element);
    modal.onConfirm(() => {
        if (!editor.validate()) {
            modal.enableButtons();
            return;
        }
        input.value = JSON.stringify(editor.getValue());
        input.dispatchEvent(new Event('input', { bubbles: true }));
        modal.hide();
    });
    modal.show();
}
