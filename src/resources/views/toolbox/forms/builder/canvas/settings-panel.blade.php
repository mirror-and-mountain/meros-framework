<aside
    x-data="{
        open: false,
        panelWidth: 384,
        isResizing: false,
        resizeStartX: 0,
        resizeStartWidth: 384,
        activeField: null,
        activeFieldType: null,
        requiredControl: null,
        disabledControl: null,

        {{-- Initialise the settings panel --}}
        initialise(activeField) {
            if (activeField && activeField.id !== (this.activeField?.id ?? null)) {
                this.open = true;
                this.activeField = $store.formBuilder.activeField;
                this.activeFieldType = activeField?.handle ?? null;


                if ($store.formBuilder.hasTomSelectDefaultValue(activeField.handle)) {
                    this.initTomSelectDefaultValueControl();
                }

                if (activeField?.handle === 'rich_text') {
                    this.hydrateQuillContent();
                }

                this.requiredControl = document.getElementById('field-required');
                this.disabledControl = document.getElementById('field-disabled');
            }

            else if (!activeField) {
                this.open = false;
                this.activeField = null;
            }
        },

        {{-- Initialise the TomSelect default value control if selected --}}
        initTomSelectDefaultValueControl() {
            this.$nextTick(() => {
                const options = { ...this.activeField.options };
                
                const isMultipleChoice = $store.formBuilder.isMultipleChoiceField(this.activeFieldType);

                let defaultValue = this.activeField.default;

                if (isMultipleChoice) {
                    defaultValue = Array.isArray(defaultValue) ? defaultValue : (defaultValue ? [defaultValue] : []);
                } else {
                    defaultValue = defaultValue ? defaultValue : '';
                }

                let defaultValueSelect;

                if (isMultipleChoice) {
                    defaultValueSelect = document.querySelector('.meros-multi-select-default-value');
                } else {
                    defaultValueSelect = document.querySelector('.meros-advanced-select-default-value');
                }
                
                if (defaultValueSelect?.tomselect) {
                    defaultValueSelect.tomselect.clear(true);
                    defaultValueSelect.tomselect.clearOptions();
                    defaultValueSelect.tomselect.addOptions(Object.entries(options).map(([optionValue, optionLabel]) => ({
                        value: optionValue,
                        text: optionLabel,
                    })));
                    
                    defaultValueSelect.tomselect.setValue(defaultValue);
                }
            });
        },

        {{-- Update the required and disabled controls --}}
        updateDependentControls(control, value) {
            if (control === 'required' && this.requiredControl) {
                if (this.disabledControl?.checked) {
                    this.disabledControl.checked = false;
                    this.updateSetting('disabled', false);
                }
                this.updateSetting('required', value);
            }

            if (control === 'disabled' && this.disabledControl) {
                if (this.requiredControl?.checked) {
                    this.requiredControl.checked = false;
                    this.updateSetting('required', false);
                }
                this.updateSetting('disabled', value);
            }
        },

        {{-- Update a setting for the active field --}}
        updateSetting(setting, value) {
            if (!this.activeField) {
                return;
            }

            if (this.activeField?.advanced || this.activeFieldType === 'checkboxes') {
                if (setting === 'options') {
                    this.updateSelectOptions(value);
                }

                if (setting === 'default') {
                    value = this.getDefaultSelectValueControl()?.tomselect.getValue() ?? value;
                    
                    if (this.activeField?.advanced) {
                        this.updateSelectDefaultValue(value);
                    }

                    if ($store.formBuilder.isMultipleChoiceField(this.activeFieldType)) {
                        const selectedValues = this.getDefaultSelectValueControl()?.tomselect?.getValue();

                        if (Array.isArray(selectedValues)) {
                            value = [...selectedValues];
                        } else if (selectedValues === '' || selectedValues === null || typeof selectedValues === 'undefined') {
                            value = [];
                        } else {
                            value = [selectedValues];
                        }
                    }
                }
            }

            if (this.activeField?.advanced && setting === 'allowAdd') {
                value = Boolean(value);
                const fieldEl = document.getElementById(this.activeField.id);

                if (fieldEl) {
                    fieldEl.dataset.allowAdd = value ? 'true' : 'false';

                    window.dispatchEvent(new CustomEvent('meros-remake-tom-select', {
                        detail: {
                            fieldId: this.activeField.id,
                        }
                    }));
                }
            }

            $store.formBuilder.updateActiveFieldProperty(setting, value);
        },

        {{-- Update the options for a select field (advanced selects) --}}
        updateSelectOptions(options) {
            const id = this.activeField.id;
            const el = document.getElementById(id);
            const defaultValueSelect = this.getDefaultSelectValueControl();

            const updateOptions = (element, options, reset = false) => {
                if (element && element.tomselect) {
                    const ts = element.tomselect;
                    ts.clear();
                    ts.clearOptions();
                    ts.addOptions(Object.entries(options).map(([optionValue, optionLabel]) => ({
                        value: optionValue,
                        text: optionLabel,
                    })));
                }
            };
            
            if (el && el.tomselect) {
                updateOptions(el, options);

                const isInRepeater = el.dataset?.isRepeaterField === 'true';

                if (isInRepeater) {
                    const baseName = el.name.replace(/\[\]$/, '');
                    const repeaterInstances = document.querySelectorAll(`[data-base-field-name='${baseName}'], [data-base-field-name='${baseName}[]']`);

                    repeaterInstances.forEach(instance => {
                        if (instance !== el && instance.tomselect) {
                            updateOptions(instance, options, true);
                        }
                    });
                }
            }

            if (defaultValueSelect && defaultValueSelect.tomselect) {
                updateOptions(defaultValueSelect, options, true);
            }
        },

        {{-- Update the default value for a select field (advanced selects) --}}
        updateSelectDefaultValue(value) {
            const id = this.activeField.id;
            const el = document.getElementById(id);

            if (el && el?.tomselect) {
                el.tomselect.setValue(value, true);
            }
        },

        {{-- Get the default select value control --}}
        getDefaultSelectValueControl() {
            const isMultipleChoice = $store.formBuilder.isMultipleChoiceField(this.activeFieldType);

            return isMultipleChoice 
                ? document.querySelector('.meros-multi-select-default-value') 
                : document.querySelector('.meros-advanced-select-default-value');
        },

        {{-- Get the option entries for a field --}}
        getOptionEntries(options) {
            if (Array.isArray(options)) {
                return options
                    .map(option => {
                        const label = (option?.label ?? option?.value ?? '').toString().trim();
                        const value = (option?.value ?? $store.formBuilder.slugify(label) ?? label).toString().trim();

                        if (!value && !label) {
                            return null;
                        }

                        return [value, label || value];
                    })
                    .filter(Boolean);
            }

            return Object.entries(options ?? {})
                .map(([value, label]) => [
                    (value ?? '').toString().trim(),
                    (label ?? value ?? '').toString().trim(),
                ])
                .filter(([value, label]) => Boolean(value || label));
        },

        {{-- Parse the options input for a field --}}
        parseOptionsInput(rawValue) {
            return rawValue.split('\n').reduce((options, line) => {
                const trimmed = line.trim();

                if (!trimmed) {
                    return options;
                }

                const [rawOptionValue, rawOptionLabel] = trimmed.split('|').map(part => part.trim());
                const hasExplicitLabel = trimmed.includes('|');
                const label = hasExplicitLabel ? (rawOptionLabel || rawOptionValue) : rawOptionValue;
                const explicitValue = hasExplicitLabel ? rawOptionValue : '';
                const value = explicitValue || $store.formBuilder.slugify(label) || label;

                if (!value && !label) {
                    return options;
                }

                options[value] = label || value;

                return options;
            }, {});
        },

        {{-- Hydrate the content of a Quill editor --}}
        hydrateQuillContent() {
            const defaultValueEditor = document.querySelector('.meros-rich-text-default-value');

            if (!defaultValueEditor) {
                return;
            }

            const currentDefaultValue = $store.formBuilder.activeField?.default;
            if (typeof currentDefaultValue === 'string') {
                defaultValueEditor._quill.setContents(JSON.parse(currentDefaultValue));
                return;
            }
        },

        {{-- Start resizing the settings panel --}}
        startResize(event) {
            this.isResizing = true;
            this.resizeStartX = event.clientX;
            this.resizeStartWidth = this.panelWidth;

            const onMouseMove = (moveEvent) => {
                if (!this.isResizing) {
                    return;
                }

                const nextWidth = this.resizeStartWidth - (moveEvent.clientX - this.resizeStartX);
                this.panelWidth = Math.max(320, Math.min(760, nextWidth));
            };

            const onMouseUp = () => {
                this.isResizing = false;
                window.removeEventListener('mousemove', onMouseMove);
                window.removeEventListener('mouseup', onMouseUp);
            };

            window.addEventListener('mousemove', onMouseMove);
            window.addEventListener('mouseup', onMouseUp);
        },

        {{-- Check if the active field supports help text position --}}
        supportsHelpTextPosition() {
            return this.activeFieldType !== 'radio' && 
                   this.activeFieldType !== 'checkboxes' &&
                   this.activeFieldType !== 'checkbox' &&
                   this.activeField?.handle !== 'repeater';
        }
    }"
    x-init="$watch('$store.formBuilder.activeField', value => initialise(value))"
    x-show="open"
    x-cloak
    x-transition:enter="transition ease-out duration-250"
    x-transition:enter-start="transform translate-x-8 opacity-0"
    x-transition:enter-end="transform translate-x-0 opacity-100"
    id="field-settings-panel"
    aria-label="Field settings panel"
    aria-description="Panel for editing the settings of the selected field"
    aria-expanded="open"
    class="relative shrink-0 h-full border-l border-gray-300 bg-white p-4 pb-25 overflow-x-hidden overflow-y-auto overscroll-contain"
    :style="`width: ${panelWidth}px`"
>
    <div
        class="absolute top-0 left-0 h-full w-1.5 cursor-col-resize hover:bg-blue-200 transition-colors"
        @mousedown.prevent="startResize($event)"
        title="Drag to resize panel"
    ></div>

    <div class="flex items-start justify-between gap-4 mb-5">
        <div>
            <h2 class="text-lg font-bold text-gray-900">{{ $title ?? 'Settings' }}</h2>
            @if(!empty($subtitle ?? null))
                <p class="text-sm text-gray-500 mt-1">{{ $subtitle }}</p>
            @endif
        </div>
        <button 
            type="button" 
            class="text-sm text-gray-500 hover:text-gray-800 cursor-pointer"
            title="Close settings panel"
            aria-label="Close settings panel"
            aria-description="Closes the settings panel and deselects the active field"
            @click="$store.formBuilder.activeField = null"
        >Close
        </button>
    </div>

    <div x-show="open && activeField && activeFieldType !== 'group'">
        @include('meros::toolbox.forms.builder.canvas.settings-field')
    </div>

    <div x-show="open && activeField && activeFieldType === 'group'">
        @include('meros::toolbox.forms.builder.canvas.settings-group')
    </div>
</aside>
