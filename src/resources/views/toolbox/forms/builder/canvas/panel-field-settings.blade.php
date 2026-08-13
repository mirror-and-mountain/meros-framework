<div
    x-data="panelfieldsettings($wire.setActiveField, $wire.updateActiveFieldProperty, $wire.getActiveField, $wire.clearActiveField)"
    :style="`width: ${(open && activeFieldId !== null) ? sidebarWidth : 0}px`"
    :class="isResizing ? 'transition-none' : ((open && activeFieldId !== null) ? 'duration-300 ease-out' : 'duration-250 ease-in')"
    class="relative shrink-0 h-full overflow-hidden bg-slate-50 transition-[width] motion-reduce:transition-none motion-reduce:duration-0"
>
    <aside
        x-show="open && activeFieldId !== null"
        x-cloak
        x-transition:enter="transform-gpu transition ease-out duration-300 motion-reduce:transition-none motion-reduce:duration-0"
        x-transition:enter-start="translate-x-full opacity-0"
        x-transition:enter-end="translate-x-0 opacity-100"
        x-transition:leave="transform-gpu transition ease-in duration-250 motion-reduce:transition-none motion-reduce:duration-0"
        x-transition:leave-start="translate-x-0 opacity-100"
        x-transition:leave-end="translate-x-full opacity-0"
        id="field-settings-panel"
        aria-label="Field settings panel"
        aria-description="Panel for editing the settings of the selected field"
        :aria-expanded="open && activeFieldId !== null"
        style="width: 100%;"
        class="relative h-full border-l border-slate-300 bg-slate-50 p-4 pb-25 overflow-x-hidden overflow-y-auto overscroll-contain"
    >
        <div
            class="absolute top-0 left-0 h-full w-1.5 cursor-col-resize bg-slate-300/60 hover:bg-blue-300 transition-colors duration-150 motion-reduce:transition-none"
            @mousedown.prevent="startResize($event)"
            title="Drag to resize panel"
        ></div>

        <div class="flex items-start justify-between gap-4 mb-5">
            <div>
                <h2 class="text-lg font-bold text-slate-900">Field Settings</h2>
            </div>
            @if($screen !== 'canvas-rules-editor' && $screen !== 'canvas-options-editor')
                <button 
                    type="button" 
                    class="text-sm text-slate-600 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-600 focus-visible:ring-offset-1 rounded-sm transition-colors duration-150 motion-reduce:transition-none cursor-pointer"
                    title="Close settings panel"
                    aria-label="Close settings panel"
                    aria-description="Closes the settings panel and deselects the active field"
                    @click="$dispatch('mforms:close-field-settings')"
                >
                    Close
                </button>
            @endif
        </div>

        <div 
            x-show="activeFieldId !== null && initialised"
            x-cloak
            class="space-y-4 text-slate-800"
            wire:loading.class="opacity-80 pointer-events-none"
            wire:key="field-settings-content-{{ $activeField !== null ? $activeField->getId() : 'none' }}"
        >
            {{-- Field Label --}}
            <div class="nice-form-group">
                <label for="field-label" class="form-label">Label</label>
                <small class="whitespace-normal">The field's label</small>
                <input
                    id="field-label"
                    type="text"
                    required
                    x-model="activeFieldProps.label"
                    @change="updateActiveFieldProperty('label', $event.target.value);"
                />
            </div>

            {{-- Field Name --}}
            <div class="nice-form-group">
                <label for="field-name" class="form-label">Name</label>
                <small class="whitespace-normal">The field's name</small>
                <input
                    id="field-name"
                    type="text"
                    required
                    x-model="activeFieldProps.name"
                    @change="updateActiveFieldProperty('name', $event.target.value);"
                />
            </div>

            {{-- Field Placeholder --}}
            <div class="nice-form-group" x-show="supportsProperty('placeholder')" x-cloak>
                <label for="field-placeholder" class="form-label">Placeholder</label>
                <small class="whitespace-normal">The field's placeholder text</small>
                <input
                    id="field-placeholder"
                    type="text"
                    :value="activeFieldProps?.attributes?.placeholder ?? ''"
                    @change="updateActiveFieldProperty('attributes.placeholder', $event.target.value);"
                />
            </div>

            {{-- Default Value --}}
            <div
                class="nice-form-group"
                x-show="supportsProperty('dynamicDefault') && !(activeFieldProps?.useDynamicOptions ?? false)"
                x-cloak
            >
                <label for="field-use-dynamic-default" class="form-label">Use Dynamic Default</label>
                <small class="whitespace-normal">Whether to use a dynamic default value for the field</small>
                <input
                    id="field-use-dynamic-default"
                    type="checkbox"
                    class="switch"
                    :checked="activeFieldProps?.useDynamicDefault ?? false"
                    @change="
                        const nextUseDynamicDefault = $event.target.checked;
                        updateActiveFieldProperty('useDynamicDefault', nextUseDynamicDefault);

                        if (nextUseDynamicDefault && supportsProperty('dynamicOptions')) {
                            updateActiveFieldProperty('useDynamicOptions', false);
                            activeFieldProps.useDynamicOptions = false;
                        }
                    "
                />
            </div>

            {{-- @php
                $enforceDynamicQueryDefaults = $this->activeField !== null
                    && method_exists($this->activeField, 'usesDynamicOptions')
                    && $this->activeField->usesDynamicOptions();
            @endphp --}}

            @if ($enforceDynamicQueryDefaults)
                <div class="nice-form-group">
                    <small class="whitespace-normal">
                        Default values for this field are selected from the configured dynamic options source.
                    </small>
                </div>
            @endif

            @if ($this->activeField !== null && $this->activeField->useDynamicDefault === true && !$enforceDynamicQueryDefaults)
                <div
                    x-data="{
                        control: null,

                        init() {
                            this.control = this.$el.querySelector('#field-dynamic-default');
                            
                            if (this.control) {
                                this.control.addEventListener('change', (event) => {
                                
                                    $wire.updateActiveFieldProperty('dynamicDefault', event.target.tomselect.getValue());
                                });
                            }
                        }
                    }"
                    x-cloak 
                    wire:key="dynamic-default-control-{{ $this->activeField?->getId() ?? 'none' }}-{{ $this->fieldSettingsVersion }}"
                >
                    {!! $this->getDynamicDefaultControl()?->render() !!}
                </div>
            @else
                <div
                    x-show="activeFieldProps?.type !== 'repeater'"
                    x-cloak
                    wire:key="default-value-control-{{ $this->activeField?->getId() ?? 'none' }}-{{ $this->fieldSettingsVersion }}"
                >
                    @if ($this->activeField !== null)
                        {!! $this->activeField->renderDefaultValueControl() !!}
                    @endif
                </div>
            @endif

            {{-- Dynamic Field Options --}}
            @if($this->activeField !== null && $this->activeField->supports('dynamicOptions'))
                <div class="nice-form-group" x-cloak>
                    <label for="field-use-dynamic-options" class="form-label">Use Dynamic Options</label>
                    <small class="whitespace-normal">Load option choices from a live query instead of a fixed list.</small>
                    <input
                        id="field-use-dynamic-options"
                        type="checkbox"
                        class="switch"
                        :checked="activeFieldProps?.useDynamicOptions ?? false"
                        @change="updateActiveFieldProperty('useDynamicOptions', $event.target.checked);"
                    />
                </div>

                <div
                    class="nice-form-group"
                    x-show="supportsProperty('dynamicOptions') && (activeFieldProps?.useDynamicOptions ?? false)"
                    x-cloak
                >
                    <label for="field-dynamic-options-source" class="form-label">Dynamic Options Source</label>
                    <small class="whitespace-normal">The source of dynamic options for this field</small>
                    <select
                        id="field-dynamic-options-source"
                        :value="activeFieldProps?.dynamicOptionsSource ?? ''"
                        @change="updateActiveFieldProperty('source', $event.target.value);"
                    >
                        <option value="">Select a source</option>
                        @foreach ($this->getDynamicChoiceSources() as $source => $label)
                            <option value="{{ $source }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                @if( $this->activeField !== null && $this->activeField->usesDynamicOptions() === true && $this->activeField->getDynamicOptionsSource() !== '' )
                    <div>
                        {!! $this->getDynamicChoiceSourceConfigFields() !!}
                    </div>
                @endif
            @endif

            {{-- Field Options --}}
            <button
                x-show="supportsProperty('options') && !(activeFieldProps?.useDynamicOptions ?? false)"
                type="button"
                class="cursor-pointer w-full py-2 px-4 my-2 bg-blue-600 text-white rounded hover:bg-blue-700 active:bg-blue-800 font-medium text-sm transition-colors"
                wire:click="openOptionsEditor()"
            >
                Edit Field Options
            </button>

            {{-- Field Icon --}}
            <div class="nice-form-group" x-show="supportsProperty('icon')" x-cloak>
                <label for="show-field-icon" class="form-label">Show Field Icon</label>
                <small class="whitespace-normal">Whether to show the field's icon</small>
                <input
                    id="show-field-icon"
                    type="checkbox"
                    class="switch"
                    :checked="activeFieldProps?.showsIcon ?? false"
                    @change="updateActiveFieldProperty('showIcon', $event.target.checked);"
                />
            </div>

            {{-- Field Icon Position --}}
            <div class="nice-form-group" x-show="supportsProperty('icon') && activeFieldProps?.showsIcon" x-cloak>
                <label for="field-icon-right" class="form-label">Field Icon Position</label>
                <small class="whitespace-normal">Whether to show the field's icon on the right</small>
                <input
                    id="field-icon-right"
                    type="checkbox"
                    class="switch"
                    :checked="activeFieldProps?.iconPosition === 'right'"
                    @change="updateActiveFieldProperty('iconPosition', $event.target.checked ? 'right' : 'left');"
                />
            </div>

            {{-- Field Help Text --}}
            <div class="nice-form-group" x-show="supportsProperty('helpText')" x-cloak>
                <label for="field-help-text" class="form-label">Help Text</label>
                <small class="whitespace-normal">The field's help text</small>
                <textarea
                    id="field-help-text"
                    :value="activeFieldProps?.helpText ?? ''"
                    @change="updateActiveFieldProperty('helpText', $event.target.value);"
                ></textarea>
            </div>

            {{-- States --}}
            <fieldset class="nice-form-group" x-show="supportsProperty('required') || supportsProperty('disabled')" x-cloak>
                <legend class="form-label">Default States</legend>
                <small class="whitespace-normal">The field's default states</small>

                {{-- Required --}}
                <div class="nice-form-group" x-show="supportsProperty('required')" x-cloak>
                    <input
                        id="field-required"
                        class="switch"
                        type="checkbox"
                        :checked="activeFieldProps?.attributes?.required ?? false"
                        :disabled="activeFieldProps?.mustBeRequired ?? false"
                        @change="updateActiveFieldProperty('attributes.required', $event.target.checked);"
                    />
                    <label for="field-required" class="form-label">Required</label>
                </div>

                {{-- Disabled --}}
                <div class="nice-form-group" x-show="supportsProperty('disabled')" x-cloak>
                    <input
                        id="field-disabled"
                        class="switch"
                        type="checkbox"
                        :checked="activeFieldProps?.attributes?.disabled ?? false"
                        @change="updateActiveFieldProperty('attributes.disabled', $event.target.checked);"
                    />
                    <label for="field-disabled" class="form-label">Disabled</label>
                </div>
            </fieldset>

            {{-- Field Rule Controls --}}
            <button
                x-show="supportsProperty('rules')"
                type="button"
                class="cursor-pointer w-full py-2 px-4 my-2 bg-blue-600 text-white rounded hover:bg-blue-700 active:bg-blue-800 font-medium text-sm transition-colors"
                wire:click="openRulesEditor()"
            >
                Edit Field Rules
            </button>
        </div>
    </aside>
</div>