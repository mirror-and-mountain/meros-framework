import mformsRepeater from './repeater';
import { attachDynamicSuggestions } from '../dynamic-suggestions.js';

const mformsFlowActionsRepeater = () => {
    const repeater = mformsRepeater();
    const cachedControls = new WeakMap();

    const getInputs = control => control.matches('input, select, textarea')
        ? [control]
        : Array.from(control.querySelectorAll('input[name], select[name], textarea[name]'));

    const getValues = control => getInputs(control).map(input => ({
        value: input.value,
        checked: input.checked,
        selected: input.tagName === 'SELECT'
            ? Array.from(input.options, option => option.selected)
            : null,
    }));

    const setValues = (control, values) => {
        getInputs(control).forEach((input, index) => {
            const value = values[index];
            if (!value) return;

            if (input.tagName === 'SELECT' && value.selected) {
                Array.from(input.options).forEach((option, optionIndex) => {
                    option.selected = value.selected[optionIndex] ?? false;
                });
            } else {
                input.value = value.value;
                if (input.type === 'checkbox' || input.type === 'radio') {
                    input.checked = value.checked;
                }
            }
        });
    };

    return {
        ...repeater,

        dynamicChoices: [],
        selectorChangeHandler: null,

        init() {
            repeater.init.call(this);

            if (!this.container) {
                return;
            }

            this.dynamicChoices = JSON.parse(this.container.dataset.flowDynamicChoices ?? '[]');

            this.selectorChangeHandler = this.selectorOnChange.bind(this);
            this.initializeDynamicControls();
            this.container.addEventListener('change', this.selectorChangeHandler);
        },

        initializeDynamicControls() {
            this.container.querySelectorAll('input[data-dynamic-selector]').forEach(field => {
                const row = field.closest('.meros-repeater-table-row:not(.meros-repeater-table-row--template)');
                if (row && field.closest('.meros-repeater-field') === this.container) {
                    const control = row.querySelector('[data-repeater-field-name="input_value"]');
                    const wrapper = control?.closest('.meros-field-wrapper');
                    const activeMode = field.checked ? 'dynamic' : 'defined';
                    cachedControls.set(row, {
                        activeMode,
                        controls: wrapper ? { [activeMode]: { html: wrapper.outerHTML, values: null } } : {},
                    });
                    if (field.checked && control) {
                        attachDynamicSuggestions(control, { includeFields: true, additionalChoices: this.dynamicChoices });
                    }
                }
            });
        },

        reset() {
            repeater.reset.call(this);
            this.initializeDynamicControls();
        },

        destroy() {
            repeater.destroy.call(this);
            this.container?.removeEventListener('change', this.selectorChangeHandler);
        },

        selectorOnChange(event) {
            const dynamicSelector = event.target;
            if (!dynamicSelector?.matches('input[data-dynamic-selector]') ||
                dynamicSelector.closest('.meros-repeater-field') !== this.container) return;

            const row = dynamicSelector.closest('.meros-repeater-table-row:not(.meros-repeater-table-row--template)');
            if (!row) return;

            const inputFieldTypeControl = row.querySelector('[data-repeater-field-name="input_field_type"]');
            const inputValueField = row.querySelector('[data-repeater-field-name="input_value"]');
            if (!inputFieldTypeControl || !inputValueField) return;

            const wrapper = inputValueField.closest('.meros-field-wrapper');
            if (!wrapper || !wrapper.classList.contains('meros-field-wrapper')) return;

            const fieldType = inputFieldTypeControl.value;
            const useDynamicValue = dynamicSelector.checked;
            const targetMode = useDynamicValue ? 'dynamic' : 'defined';
            const state = cachedControls.get(row) ?? {
                activeMode: useDynamicValue ? 'defined' : 'dynamic',
                controls: {},
            };
            cachedControls.set(row, state);
            if (state.activeMode === targetMode) return;

            const fail = message => {
                console.error(message);
                dynamicSelector.checked = state.activeMode === 'dynamic';
                dynamicSelector.dispatchEvent(new Event('change', { bubbles: true }));
            };

            if (!fieldType) {
                fail('Cannot switch action input: missing field type.');
                return;
            }

            const controls = state.controls;
            let target = controls[targetMode];
            if (!target) {
                const inputName = row.querySelector('[data-repeater-field-name="input_name"]')?.value;
                const fieldHtml = useDynamicValue
                    ? this.container.querySelector('.meros-repeater-table-row--template [data-repeater-field-name="input_value__template"]')
                        ?.closest('.meros-field-wrapper')?.outerHTML
                    : Array.from(this.container.querySelectorAll('template[data-input-control]'))
                        .find(element => element.dataset.inputControl === inputName)?.innerHTML;
                if (!fieldHtml) {
                    fail(`Cannot create action input field: ${useDynamicValue ? 'text' : fieldType}.`);
                    return;
                }
                target = { html: fieldHtml, values: null };
            }

            const template = document.createElement('template');
            template.innerHTML = target.html.trim();

            const newFieldControl = template.content.querySelector('[data-field-type]');
            if (!newFieldControl) {
                fail('Cannot switch action input: generated field has no control.');
                return;
            }

            const oldId = newFieldControl.id;
            const oldName = (newFieldControl.getAttribute('name') ?? newFieldControl.dataset.name)?.replace(/\[\]$/, '');
            const newName = (inputValueField.getAttribute('name') ?? inputValueField.dataset.name)?.replace(/\[\]$/, '');
            const newWrapper = newFieldControl.closest('.meros-field-wrapper');
            if (!newWrapper || !oldName || !newName) {
                fail('Cannot switch action input: generated field is missing its wrapper or name.');
                return;
            }

            this.replaceIdPrefix(newWrapper, oldId, inputValueField.id);
            [newWrapper, ...newWrapper.querySelectorAll('[name], [data-name]')].forEach(element => {
                ['name', 'data-name'].forEach(attribute => {
                    const name = element.getAttribute(attribute);
                    if (oldName && name && (name === oldName || name.startsWith(`${oldName}[`))) {
                        element.setAttribute(attribute, newName + name.slice(oldName.length));
                    }
                });
            });
            newFieldControl.id = inputValueField.id;
            newFieldControl.title = inputValueField.title ?? '';
            newFieldControl.dataset.repeaterRowIndex = inputValueField.dataset.repeaterRowIndex;
            newFieldControl.dataset.repeaterFieldName = inputValueField.dataset.repeaterFieldName;
            newFieldControl.removeAttribute('data-repeater-template-row');
            newWrapper.dataset.fieldOriginName = wrapper.dataset.fieldOriginName ?? 'input_value';
            if (useDynamicValue && inputValueField.hasAttribute('required')) {
                newFieldControl.setAttribute('required', '');
            }

            if (target.values) setValues(newFieldControl, target.values);
            controls[state.activeMode] = {
                html: controls[state.activeMode]?.html ?? wrapper.outerHTML,
                values: getValues(inputValueField),
            };
            controls[targetMode] = target;
            state.activeMode = targetMode;
            cachedControls.set(row, state);

            wrapper.replaceWith(template.content);
            if (useDynamicValue) {
                attachDynamicSuggestions(newFieldControl, { includeFields: true, additionalChoices: this.dynamicChoices });
            }
            this.$nextTick(() => {
                if (row.contains(newWrapper)) {
                    this.fieldsProcessor?.configureFieldObject(newWrapper);
                }
            });
        }
    }
};

export default mformsFlowActionsRepeater;