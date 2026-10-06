export default class MerosFieldsProcessor {
    parent = null;
    parentType = null;
    fieldWrappers = null;
    fields = {};

    /**
     * Collects and configures fields within a form, group, or repeater.
     *
     * @param {HTMLElement} parentEl Container whose field wrappers should be processed.
     */
    constructor(parentEl) {
        const isFormParent = parentEl.classList.contains('mforms-form');
        const isGroupParent = parentEl.classList.contains('mforms-field-group');
        const isRepeaterParent = parentEl.classList.contains('mforms-repeater-field');

        if (!isFormParent && !isGroupParent && !isRepeaterParent) return;

        if (isFormParent) {
            this.parentType = 'form';
        } else if (isGroupParent) {
            this.parentType = 'group';
        } else if (isRepeaterParent) {
            this.parentType = 'repeater';
        }

        this.parent = parentEl;
        this.fieldWrappers = this.getParentFieldWrappers();
        this.configureFieldObjects();
    }

    /**
     * Finds field wrappers contained by the configured parent.
     *
     * @returns {NodeListOf<HTMLElement>|undefined} Matching wrappers, or undefined when no parent is set.
     */
    getParentFieldWrappers() {
        if (!this.parent) return;

        return Array.from(this.parent.querySelectorAll('.mforms-field-wrapper')).filter(wrapper => {
            const repeater = wrapper.closest('.mforms-repeater-field');

            if (this.parentType === 'repeater') {
                const row = wrapper.closest('.meros-repeater-table-row');

                return repeater === this.parent &&
                    row !== null &&
                    !row.classList.contains('meros-repeater-table-row--template');
            }

            return repeater === null;
        });
    }

    /**
     * Registers each field, links condition influencers, and applies initial visibility.
     *
     * @returns {void}
     */
    configureFieldObjects() {
        if (typeof this.fieldWrappers !== 'object') return;
        if (this.parentType === 'group' && this.parent.closest('.mforms-form')) return;

        this.fieldWrappers.forEach((wrapperElement) => {
            this.configureFieldObject(wrapperElement);
        });

        Object.values(this.fields).forEach(field => {
            if (this.fieldHasConditions(field.name)) {
                this.initFieldConditions(field);
            }
        });

        const actionFields = this.getFieldsWithActions();
        actionFields.forEach(field => {
            this.initFieldActions(field);
        });

        Object.values(this.fields).forEach(field => {
            if (this.fieldHasConditions(field.name)) {
                this.applyFieldConditions(field.name);
            }
        });
    }

    /**
     * Registers and initializes fields added to a repeater row after its initial setup.
     *
     * @param {HTMLElement} row Repeater row containing the new fields.
     * @returns {void}
     */
    configureRepeaterRow(row) {
        if (this.parentType !== 'repeater' || !row) return;

        row.querySelectorAll('.mforms-field-wrapper').forEach(wrapper => {
            this.configureFieldObject(wrapper);
        });

        const rowFields = Object.values(this.fields).filter(field => field.row === row);
        rowFields.forEach(field => {
            if (this.fieldHasConditions(field.name)) {
                this.initFieldConditions(field);
            }
        });

        rowFields.filter(field => field.actions.length > 0).forEach(field => {
            this.initFieldActions(field);
        });

        rowFields.forEach(field => {
            if (this.fieldHasConditions(field.name)) {
                this.applyFieldConditions(field.name);
            }
        });
    }

    /**
     * Resolves a wrapper's field control and stores its metadata and conditions.
     *
     * @param {HTMLElement} wrapperElement Field wrapper to configure.
     * @returns {void}
     */
    configureFieldObject(wrapperElement) {
        const control = wrapperElement.matches('[data-field-type]')
            ? wrapperElement
            : wrapperElement.classList.contains('mforms-repeater-field')
            ? wrapperElement
            : wrapperElement.querySelector('[data-field-type]');

        if (!control) {
            return;
        }

        const fieldBaseName = wrapperElement.dataset.fieldOriginName;
        const fieldName     = control.dataset.name || control.dataset.fieldName || control.name;
        if (!fieldName || !fieldBaseName) return;

        const component = this.getFieldComponent(control);
        const group = control.closest('.mforms-field-group');
        const repeater = control.closest('.mforms-repeater-field');

        const conditions = wrapperElement.dataset.fieldConditions
            ? JSON.parse(wrapperElement.dataset.fieldConditions)
            : null;

        this.fields[fieldName] = {
            name: fieldName,
            baseName: fieldBaseName,
            id: control.id,
            baseId: control.id.replace(/-row-\d+/, '').replace(/-template$/, ''),
            wrapper: wrapperElement,
            group: group,
            repeater: repeater,
            row: this.parentType === 'repeater'
                ? control.closest('.meros-repeater-table-row')
                : null,
            control: control,
            component: component,
            conditions: conditions,
            initialValue: this.getControlValue(control, component),
            initialOptions: this.getControlOptions(control),
            conditionalValueState: null,
            conditionalValueActive: false,
            conditionalOptionsActive: false,
            actionListenerInitialized: false,
            actions: []
        };

        wrapperElement.removeAttribute('data-field-conditions');
    }

    /**
     * Links fields referenced by a conditional field's rules to that field's actions.
     *
     * @param {object} conditionalField Field whose condition rules are being registered.
     * @returns {void}
     */
    initFieldConditions(conditionalField) {
        Object.entries(conditionalField.conditions).forEach(([type, config]) => {
            if (!this.shouldProcessConditionType(type)) return;
            if (typeof config !== 'object') return;
            if (typeof config.rules !== 'object') return;

            Object.values(config.rules).forEach(rule => {
                if (typeof rule !== 'object') return;
                if (!rule.field) return;
                if (!rule.operator) return;
                if (
                    !Object.prototype.hasOwnProperty.call(rule, 'source_value') &&
                    !Object.prototype.hasOwnProperty.call(rule, 'value')
                ) return;

                const actionField = this.getFieldByConditionName(rule.field, conditionalField);

                if (!actionField) return;

                if (!actionField.actions.includes(conditionalField.name)) {
                    actionField.actions.push(conditionalField.name);
                }
            });
        });
    }

    /**
     * Adds a change listener that reevaluates fields influenced by the given field.
     *
     * @param {object} actionField Field whose value influences other fields.
     * @returns {void}
     */
    initFieldActions(actionField) {
        if (typeof actionField !== 'object') return;
        if (!actionField.control) return;
        if (actionField.actionListenerInitialized) return;

        actionField.actionListenerInitialized = true;
        const control = actionField.control;
        const updateDependentFields = () => {
            actionField.actions.forEach(fieldName => {
                const affectedField = this.fields[fieldName];
                if (!affectedField) return;

                if (
                    this.parentType === 'repeater' &&
                    actionField.row !== affectedField.row
                ) {
                    return;
                }

                this.applyFieldConditions(fieldName);
            });
        };

        control.addEventListener('input', updateDependentFields);
        control.addEventListener('change', updateDependentFields);
    }

    /**
     * Evaluates a field's visibility rules and updates its hidden, value, and required state.
     *
     * @param {string} fieldName Name of the field to update.
     * @returns {void}
     */
    applyFieldConditions(fieldName) {
        const field = this.fields[fieldName];
        if (!field) return;

        this.applyConditionalValue(field);
        this.applyConditionalOptions(field);

        const hasShowRules = this.shouldProcessConditionType('show') &&
            this.conditionTypeHasRules(field.conditions, 'show');
        const shouldShow = !hasShowRules || this.evaluateConditionType(field, 'show');
        const shouldHide = this.shouldProcessConditionType('hide') &&
            this.evaluateConditionType(field, 'hide');
        const isVisible = shouldShow && !shouldHide;

        if (isVisible) {
            field.control.removeAttribute('data-meros-hidden-field');
        } else {
            field.control.setAttribute('data-meros-hidden-field', '');
            this.clearFieldValue(field);
        }

        const shouldRequire = this.evaluateConditionType(field, 'require');
        const shouldMakeOptional = this.evaluateConditionType(field, 'optional');
        const required = !isVisible
            ? false
            : shouldRequire
                ? true
                : shouldMakeOptional
                    ? false
                    : null;
        this.setFieldRequiredState(field, required);

        const shouldDisable = this.evaluateConditionType(field, 'disable');
        const shouldEnable = this.evaluateConditionType(field, 'enable');
        const disabled = shouldDisable
            ? true
            : shouldEnable
                ? false
                : null;
        this.setFieldDisabledState(field, disabled);
    }

    /**
     * Determines whether this processor should evaluate a condition type.
     *
     * Repeater rows intentionally do not process visibility rules.
     *
     * @param {string} type Condition type.
     * @returns {boolean} Whether the condition type is enabled for this parent.
     */
    shouldProcessConditionType(type) {
        return this.parentType !== 'repeater' || !['show', 'hide'].includes(type);
    }

    /**
     * Checks whether a condition type contains at least one rule.
     *
     * @param {object|null} conditions Field condition configuration.
     * @param {string} type Condition type, such as "show" or "hide".
     * @returns {boolean} Whether the condition type has rules.
     */
    conditionTypeHasRules(conditions, type) {
        const rules = conditions?.[type]?.rules;

        return Array.isArray(rules)
            ? rules.length > 0
            : Boolean(rules && typeof rules === 'object' && Object.keys(rules).length > 0);
    }

    /**
     * Evaluates a field's rules of one condition type using current source-field values.
     *
     * @param {object} field Conditional field to evaluate.
     * @param {string} type Condition type, such as "show" or "hide".
     * @returns {boolean} Whether the rules match.
     */
    evaluateConditionType(field, type) {
        const config = field.conditions?.[type];
        if (!config || typeof config !== 'object' || !config.rules || typeof config.rules !== 'object') {
            return false;
        }

        const results = Object.values(config.rules)
            .filter(rule => rule && typeof rule === 'object' && rule.field && rule.operator)
            .map(rule => {
                const sourceField = this.getFieldByConditionName(rule.field, field);

                return sourceField
                    ? this.evaluateRule(
                        this.getFieldValue(sourceField),
                        rule.operator,
                        this.getRuleSourceValue(rule)
                    )
                    : false;
            });

        if (results.length === 0) return false;

        return String(config.logic).toLowerCase() === 'or'
            ? results.some(Boolean)
            : results.every(Boolean);
    }

    /**
     * Compares a field value with a configured condition value.
     *
     * @param {*} actual Current field value.
     * @param {string} operator Comparison operator.
     * @param {*} expected Configured condition value.
     * @returns {boolean} Whether the comparison matches.
     */
    evaluateRule(actual, operator, expected) {
        const normalizedOperator = String(operator).trim().toUpperCase();
        const actualString = actual == null ? '' : String(actual);
        const expectedString = expected == null ? '' : String(expected);
        const actualValues = Array.isArray(actual) ? actual.map(String) : [actualString];

        switch (normalizedOperator) {
            case '=':
            case '==':
            case 'EQUALS':
                return this.valuesEqual(actual, expected);
            case '!=':
            case '<>':
            case 'NOT EQUALS':
            case 'NOT_EQUALS':
                return !this.valuesEqual(actual, expected);
            case 'CONTAINS':
                return Array.isArray(expected)
                    ? expected.every(value => actualValues.includes(String(value)))
                    : Array.isArray(actual)
                        ? actualValues.includes(expectedString)
                        : actualString.includes(expectedString);
            case 'NOT CONTAINS':
            case 'NOT_CONTAINS':
                return !this.evaluateRule(actual, 'CONTAINS', expected);
            case 'LIKE':
                return actualString.includes(expectedString.replace(/%/g, ''));
            case '>':
                return Number(actual) > Number(expected);
            case '<':
                return Number(actual) < Number(expected);
            case '=>':
            case '>=':
                return Number(actual) >= Number(expected);
            case '=<':
            case '<=':
                return Number(actual) <= Number(expected);
            default:
                return false;
        }
    }

    /**
     * Retrieves the comparison value, supporting both current and legacy rule keys.
     *
     * @param {object} rule Condition rule.
     * @returns {*} Value compared against the source field.
     */
    getRuleSourceValue(rule) {
        return Object.prototype.hasOwnProperty.call(rule, 'source_value')
            ? rule.source_value
            : rule.value;
    }

    /**
     * Finds a registered field by its original name or rendered name.
     *
     * @param {string} name Field identifier from a condition rule.
     * @returns {object|null} Matching field, or null when it cannot be resolved.
     */
    getFieldByConditionName(name, relativeField = null) {
        const matches = Object.values(this.fields).filter(
            field => field.baseName === name ||
                field.name === name ||
                field.id === name ||
                field.baseId === name
        );

        if (this.parentType !== 'repeater' || !relativeField) {
            return matches[0] || null;
        }

        return matches.find(field => field.row === relativeField.row) || null;
    }

    /**
     * Compares values while treating scalar option values as strings.
     *
     * @param {*} actual Current value.
     * @param {*} expected Expected value.
     * @returns {boolean} Whether the values match.
     */
    valuesEqual(actual, expected) {
        if (Array.isArray(actual) || Array.isArray(expected)) {
            const actualValues = Array.isArray(actual) ? actual.map(String) : [String(actual ?? '')];
            const expectedValues = Array.isArray(expected) ? expected.map(String) : [String(expected ?? '')];

            return actualValues.length === expectedValues.length &&
                expectedValues.every(value => actualValues.includes(value));
        }

        return String(actual ?? '') === String(expected ?? '');
    }

    /**
     * Reads a field value from its Alpine component or native control.
     *
     * @param {object} field Registered field metadata.
     * @returns {*} Current field value, or null when empty.
     */
    getFieldValue(field) {
        return this.getControlValue(field.control, field.component);
    }

    /**
     * Retrieves a control's selected value, using its Alpine component when available.
     *
     * @param {HTMLElement} control Field control.
     * @param {object|null} component Alpine component attached to the control.
     * @returns {*} Current value or null.
     */
    getControlValue(control, component) {
        if (component && typeof component.getValue === 'function') {
            return component.getValue();
        }

        const checkedInputs = control.querySelectorAll('input[type="checkbox"]:checked');
        if (checkedInputs.length > 0) {
            return Array.from(checkedInputs, input => input.value);
        }

        const checkedRadio = control.querySelector('input[type="radio"]:checked');
        if (checkedRadio) return checkedRadio.value;

        if (control.multiple && control.options) {
            return Array.from(control.selectedOptions, option => option.value);
        }

        return control.value ?? null;
    }

    /**
     * Captures the available options from a select or choice field.
     *
     * @param {HTMLElement} control Field control.
     * @returns {Array<{value: string, label: string, disabled: boolean}>} Initial options.
     */
    getControlOptions(control) {
        if (control.tagName === 'SELECT') {
            return Array.from(control.options, option => ({
                value: option.value,
                label: option.textContent,
                disabled: option.disabled
            }));
        }

        return Array.from(control.querySelectorAll('input[type="radio"], input[type="checkbox"]'), input => ({
            value: input.value,
            label: control.querySelector(`label[for="${CSS.escape(input.id)}"]`)?.textContent ?? input.value,
            disabled: input.disabled
        }));
    }

    /**
     * Clears the value of an Alpine-backed or native field control.
     *
     * @param {object} field Registered field metadata.
     * @returns {void}
     */
    clearFieldValue(field) {
        const component = field.component;
        if (component && typeof component.setValue === 'function') {
            component.setValue(null);
        }

        field.control.querySelectorAll('input, select, textarea').forEach(control => {
            if (control.type === 'checkbox' || control.type === 'radio') {
                control.checked = false;
            } else {
                control.value = '';
            }
        });

        if ('value' in field.control) {
            field.control.value = '';
        }
    }

    /**
     * Applies a configured set-value rule, restoring the pre-condition value when it stops matching.
     *
     * @param {object} field Conditional field.
     * @returns {void}
     */
    applyConditionalValue(field) {
        const matches = this.getMatchingConditionRules(field, 'set_value')
            .filter(rule => Object.prototype.hasOwnProperty.call(rule, 'dest_value'));

        if (matches.length > 0) {
            if (!field.conditionalValueActive) {
                field.conditionalValueState = this.getFieldValue(field);
                field.conditionalValueActive = true;
            }

            const rule = matches[0];
            this.setFieldValue(field, rule.dest_value);
            return;
        }

        if (field.conditionalValueActive) {
            this.setFieldValue(field, field.conditionalValueState);
            field.conditionalValueState = null;
            field.conditionalValueActive = false;
        }
    }

    /**
     * Applies options from matching set-options rules, restoring the original options otherwise.
     *
     * @param {object} field Conditional field.
     * @returns {void}
     */
    applyConditionalOptions(field) {
        const matches = this.getMatchingConditionRules(field, 'set_options')
            .filter(rule => Object.prototype.hasOwnProperty.call(rule, 'dest_value'));
        if (matches.length === 0) {
            if (field.conditionalOptionsActive) {
                this.setFieldOptions(field, field.initialOptions);
                field.conditionalOptionsActive = false;
            }
            return;
        }

        const options = matches.flatMap(rule => this.normalizeOptions(rule.dest_value));
        if (options.length > 0) {
            this.setFieldOptions(field, options);
            field.conditionalOptionsActive = true;
        }
    }

    /**
     * Returns rules for a condition type whose source-field comparisons match.
     *
     * @param {object} field Conditional field.
     * @param {string} type Condition type.
     * @returns {Array<object>} Matching rules.
     */
    getMatchingConditionRules(field, type) {
        const config = field.conditions?.[type];
        if (!config || typeof config !== 'object' || !config.rules || typeof config.rules !== 'object') {
            return [];
        }

        const evaluatedRules = Object.values(config.rules)
            .filter(rule => rule && typeof rule === 'object' && rule.field && rule.operator)
            .map(rule => {
                const sourceField = this.getFieldByConditionName(rule.field, field);

                return {
                    rule,
                    matches: sourceField !== null && this.evaluateRule(
                        this.getFieldValue(sourceField),
                        rule.operator,
                        this.getRuleSourceValue(rule)
                    )
                };
            });

        const matched = String(config.logic).toLowerCase() === 'or'
            ? evaluatedRules.filter(result => result.matches)
            : evaluatedRules.every(result => result.matches)
                ? evaluatedRules
                : [];

        return matched.map(result => result.rule);
    }

    /**
     * Converts option maps or lists into normalized option records.
     *
     * @param {*} options Conditional option data.
     * @returns {Array<{value: string, label: string, disabled: boolean}>} Normalized options.
     */
    normalizeOptions(options) {
        if (!options || typeof options !== 'object') return [];

        if (Array.isArray(options)) {
            return options.map((option, index) => {
                if (option && typeof option === 'object') {
                    return {
                        value: String(option.value ?? index),
                        label: String(option.label ?? option.text ?? option.value ?? index),
                        disabled: Boolean(option.disabled)
                    };
                }

                return {
                    value: String(option),
                    label: String(option),
                    disabled: false
                };
            });
        }

        return Object.entries(options).map(([value, label]) => {
            if (label && typeof label === 'object' && !Array.isArray(label)) {
                return {
                    value: String(label.value ?? value),
                    label: String(label.label ?? label.text ?? label.value ?? value),
                    disabled: Boolean(label.disabled)
                };
            }

            return {
                value: String(value),
                label: String(label ?? value),
                disabled: false
            };
        });
    }

    /**
     * Applies a value to native choice controls and any attached Alpine/Tom Select component.
     *
     * @param {object} field Registered field metadata.
     * @param {*} value Value to apply.
     * @returns {void}
     */
    setFieldValue(field, value) {
        const component = field.component;
        if (component && typeof component.setValue === 'function') {
            component.setValue(value);
        }

        const controls = field.control.querySelectorAll('input, select, textarea');
        if (controls.length > 0 && (controls[0].type === 'checkbox' || controls[0].type === 'radio')) {
            const values = new Set((Array.isArray(value) ? value : [value]).map(item => String(item ?? '')));
            controls.forEach(control => {
                control.checked = values.has(control.value);
            });
        } else if (field.control.multiple && Array.isArray(value)) {
            Array.from(field.control.options).forEach(option => {
                option.selected = value.map(String).includes(option.value);
            });
        } else if ('value' in field.control) {
            field.control.value = value ?? '';
        }

        if (component?.ts && typeof component.ts.setValue === 'function') {
            component.ts.setValue(value ?? '', true);
        }
    }

    /**
     * Replaces a select or radio/checkbox field's options.
     *
     * @param {object} field Registered field metadata.
     * @param {Array<{value: string, label: string, disabled: boolean}>} options Options to apply.
     * @returns {void}
     */
    setFieldOptions(field, options) {
        const component = field.component || this.getFieldComponent(field.control);

        if (component && typeof component.setOptions === 'function') {
            component.setOptions(options);
            field.component = component;
        }
    }

    /**
     * Applies conditional required state, or restores the field's initial state when null.
     *
     * @param {object} field Registered field metadata.
     * @param {boolean|null} required Required state, or null to restore initial state.
     * @returns {void}
     */
    setFieldRequiredState(field, required) {
        field.requiredAttributes ||= new Map();

        this.getFieldControls(field).forEach(control => {
            if (!field.requiredAttributes.has(control)) {
                field.requiredAttributes.set(control, {
                    required: control.hasAttribute('required'),
                    ariaRequired: control.getAttribute('aria-required')
                });
            }

            const initial = field.requiredAttributes.get(control);
            const nextState = required === null ? initial.required : required;
            if (nextState) {
                control.setAttribute('required', '');
                control.setAttribute('aria-required', 'true');
            } else {
                control.removeAttribute('required');
                if (required === null && initial.ariaRequired !== null) {
                    control.setAttribute('aria-required', initial.ariaRequired);
                } else {
                    control.removeAttribute('aria-required');
                }
            }
        });
    }

    /**
     * Applies conditional disabled state, or restores the field's initial state when null.
     *
     * @param {object} field Registered field metadata.
     * @param {boolean|null} disabled Disabled state, or null to restore initial state.
     * @returns {void}
     */
    setFieldDisabledState(field, disabled) {
        field.disabledAttributes ||= new Map();

        this.getFieldControls(field).forEach(control => {
            if (!field.disabledAttributes.has(control)) {
                field.disabledAttributes.set(control, {
                    disabled: control.disabled,
                    ariaDisabled: control.getAttribute('aria-disabled')
                });
            }

            const initial = field.disabledAttributes.get(control);
            const nextState = disabled === null ? initial.disabled : disabled;
            control.disabled = nextState;

            if (nextState) {
                control.setAttribute('aria-disabled', 'true');
            } else if (disabled === null && initial.ariaDisabled !== null) {
                control.setAttribute('aria-disabled', initial.ariaDisabled);
            } else {
                control.removeAttribute('aria-disabled');
            }
        });
    }

    /**
     * Resolves the native inputs whose required and disabled states represent a field.
     *
     * @param {object} field Registered field metadata.
     * @returns {Array<HTMLElement>} Native field controls.
     */
    getFieldControls(field) {
        const control = field.control;

        if (control.matches('input, select, textarea')) {
            return [control];
        }

        return Array.from(control.querySelectorAll('input:not([type="hidden"]), select, textarea'));
    }

    /**
     * Tests whether any underlying control was initially required.
     *
     * @param {object} field Registered field metadata.
     * @returns {boolean} Whether the field was initially required.
     */
    hasOriginalRequired(field) {
        return this.getFieldControls(field).some(control => control.hasAttribute('required'));
    }

    /**
     * Tests whether any underlying control was initially disabled.
     *
     * @param {object} field Registered field metadata.
     * @returns {boolean} Whether the field was initially disabled.
     */
    hasOriginalDisabled(field) {
        return this.getFieldControls(field).some(control => control.disabled);
    }

    /**
     * Checks whether a registered field belongs to a field group.
     *
     * @param {string} fieldName Name of the field to check.
     * @returns {boolean} Whether the field belongs to a group.
     */
    fieldIsInGroup(fieldName) {
        if (!this.fields[fieldName]) return false;

        if (this.fields[fieldName].group !== null) {
            return true;
        }

        return false;
    }

    /**
     * Checks whether a registered field belongs to a repeater.
     *
     * @param {string} fieldName Name of the field to check.
     * @returns {boolean} Whether the field belongs to a repeater.
     */
    fieldIsInRepeater(fieldName) {
        if (!this.fields[fieldName]) return false;

        if (this.fields[fieldName].repeater !== null) {
            return true;
        }

        return false;
    }

    /**
     * Checks whether a registered field has condition rules.
     *
     * @param {string} fieldName Name of the field to check.
     * @returns {boolean} Whether the field has conditions.
     */
    fieldHasConditions(fieldName) {
        if (!this.fields[fieldName]) return false;

        if (this.fields[fieldName].conditions !== false && 
            this.fields[fieldName].conditions !== null
        ) {
            return true;
        } 

        return false;
    }

    /**
     * Checks whether a registered field influences another field.
     *
     * @param {string} fieldName Name of the field to check.
     * @returns {boolean} Whether the field has dependent actions.
     */
    fieldHasActions(fieldName) {
        if (!this.fields[fieldName]) return false;

        if (this.fields[fieldName].actions.length > 0) {
            return true;
        }

        return false;
    }

    /**
     * Returns all registered fields that influence other fields.
     *
     * @returns {Array<object>} Fields with one or more dependent actions.
     */
    getFieldsWithActions() {
        return Object.values(this.fields).filter(
            field => field.actions.length > 0
        );
    }

    /**
     * Retrieves this element's own Alpine component, without falling back to an ancestor.
     *
     * @param {HTMLElement} field Element to inspect.
     * @returns {object|null} Its Alpine component data, or null when it has none.
     */
    getFieldComponent(field) {
        if (!field.hasAttribute('x-data')) {
            return null;
        }

        const dataStack = field._x_dataStack;
        if (!Array.isArray(dataStack)) return null;

        return dataStack[0] ?? null;
    }
}