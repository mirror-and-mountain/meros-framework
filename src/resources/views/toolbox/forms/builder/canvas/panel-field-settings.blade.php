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
                <h2 class="text-lg font-bold text-slate-900">{{ 'Field Settings' }}</h2>
            </div>
            <button 
                type="button" 
                class="text-sm text-slate-600 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-600 focus-visible:ring-offset-1 rounded-sm transition-colors duration-150 motion-reduce:transition-none cursor-pointer"
                title="Close settings panel"
                aria-label="Close settings panel"
                aria-description="Closes the settings panel and deselects the active field"
                @click="$dispatch('mforms:close-field-settings')"
            >Close
            </button>
        </div>

        <div 
            x-show="activeFieldId !== null && initialised"
            x-cloak
            class="space-y-4 text-slate-800"
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
            <div x-show="activeFieldProps?.type !== 'repeater'" x-cloak>
                @if ($this->activeField !== null)
                    {!! $this->activeField->renderDefaultValueControl() !!}
                @endif
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

            {{-- Required --}}
            <div class="nice-form-group" x-show="supportsProperty('required')" x-cloak>
                <label for="field-required" class="form-label">Required</label>
                <small class="whitespace-normal">Whether the field is required or not</small>
                <input
                    id="field-required"
                    class="switch"
                    type="checkbox"
                    :checked="activeFieldProps?.attributes?.required ?? false"
                    :disabled="activeFieldProps?.mustBeRequired ?? false"
                    @change="updateActiveFieldProperty('attributes.required', $event.target.checked);"
                />
            </div>

            {{-- Disabled --}}
            <div class="nice-form-group" x-show="supportsProperty('disabled')" x-cloak>
                <label for="field-disabled" class="form-label">Disabled</label>
                <small class="whitespace-normal">Whether the field is disabled or not</small>
                <input
                    id="field-disabled"
                    class="switch"
                    type="checkbox"
                    :checked="activeFieldProps?.attributes?.disabled ?? false"
                    @change="updateActiveFieldProperty('attributes.disabled', $event.target.checked);"
                />
            </div>

            {{-- Field Rule Controls --}}

            <div class="nice-form-group" x-cloak>
                <label for="field-show-rules" class="form-label">Show Field Rules</label>
                <input
                    id="field-show-rules"
                    class="switch"
                    type="checkbox"
                    :checked="showRules"
                    :disabled="activeFieldProps?.hasRules"
                    @change="showRules = $event.target.checked;"
                />
            </div>
            
            <div x-show="showRules" x-cloak>
                @if ($this->activeField !== null)
                    {!! $this->activeField->getRuleControlsHtml() !!}
                @endif
            </div>
        </div>
    </aside>
</div>