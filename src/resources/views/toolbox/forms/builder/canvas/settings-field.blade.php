<div class="space-y-4">

    {{-- Field Label --}}

    <div class="nice-form-group">
        <label for="field-label" class="form-label">Label</label>
        <small class="whitespace-normal">The field's label</small>
        <input
            id="field-label"
            type="text"
            required
            :value="activeField?.label ?? ''"
            @change="updateSetting('label', $event.target.value)"
        />
    </div>

    {{-- Field Name --}}

    <div class="nice-form-group">
        <label for="field-name" class="form-label">Name</label>
        <small class="whitespace-normal">The field's name attribute (must be unique)</small>
        <input
            id="field-name"
            type="text"
            required
            :value="activeField?.name ?? ''"
            @change="updateSetting('name', $event.target.value)"
        />
    </div>

    {{-- Field Placeholder - Input Types Only --}}

    <div x-show="open && $store.formBuilder.isInputField(activeField.handle)" class="nice-form-group">
        <label for="field-placeholder" class="form-label">Placeholder</label>
        <small class="whitespace-normal">The field's placeholder text</small>
        <input
            id="field-placeholder"
            type="text"
            :value="activeField?.placeholder ?? ''"
            @change="updateSetting('placeholder', $event.target.value)"
        />
    </div>

    {{-- Field Default Value - Input Types --}}

    <div x-show="open && $store.formBuilder.isInputField(activeField.handle)" class="nice-form-group">
        <label for="field-default-value-input" class="form-label">Default value</label>
        <small class="whitespace-normal">The field's default value</small>
        <input
            id="field-default-value-input"
            type="text"
            :value="activeField?.default ?? ''"
            @change="updateSetting('default', $event.target.value)"
        />
    </div>

    {{-- Field Default Value - Textarea --}}

    <div x-show="open && activeField.handle === 'textarea'" class="nice-form-group">
        <label for="field-default-value-textarea" class="form-label">Default value</label>
        <small class="whitespace-normal">The field's default value</small>
        <textarea
            id="field-default-value-textarea"
            :value="activeField?.default ?? 'Test default value'"
            @change="updateSetting('default', $event.target.value)"
        ></textarea>
    </div>

    {{-- Field Default Value - Rich Text --}}
    <div x-show="open && activeField.handle === 'rich_text'" class="nice-form-group meros-rich-textarea-wrapper" wire:ignore>
        <label id="field-default-value-rich-text" class="form-label">Default value</label>
        <small class="whitespace-normal">The field's default value</small>
        <div
            id="field-default-value-rich-text"
            class="meros-rich-textarea meros-rich-text-default-value"
            aria-labelledby="field-default-value-rich-text"
            :data-rt-id="activeField?.id"
        ></div>
    </div>

    {{-- Field Default Value - Single Choice --}}

    <div x-show="open && $store.formBuilder.isSingleChoiceField(activeField.handle)" class="nice-form-group">
        <label for="field-default-value-select" class="form-label">Default value</label>
        <small class="whitespace-normal">The field's default value</small>
        <select
            id="field-default-value-select"
            :value="activeField?.default ?? ''"
            @change="updateSetting('default', $event.target.value)"
        >
            <option value="" disabled>Select default option</option>
            <template x-for="[value, label] in getOptionEntries(activeField?.options)" :key="value">
                <option :value="value" x-text="label" :selected="activeField?.default === value"></option>
            </template>
        </select>
    </div>

    {{-- Field Default Value - Multiple Choice  --}}

    <div x-show="open && $store.formBuilder.isMultipleChoiceField(activeField.handle)" class="nice-form-group" wire:ignore>
        <label for="field-default-value-multiple" class="form-label">Default value</label>
        <small class="whitespace-normal">The field's default value</small>
        <select
            id="field-default-value-multiple"
            class="meros-select-field meros-multi-select-default-value"
            @change="updateSetting('default', $event.target.value)"
            multiple
            data-advanced="true"
            :data-field-id="activeField?.id"
        >
            <template x-for="[value, label] in getOptionEntries(activeField?.options)" :key="value">
                <option :value="value" x-text="label"></option>
            </template>
        </select>
    </div>

    {{-- Field Default Value - Advanced Select  --}}

    <div x-show="open && activeFieldType === 'advanced_select'" class="nice-form-group" wire:ignore>
        <label for="field-default-value-advanced" class="form-label">Default value</label>
        <small class="whitespace-normal">The field's default value</small>
        <select
            id="field-default-value-advanced"
            class="meros-select-field meros-advanced-select-default-value"
            @change="updateSetting('default', $event.target.value)"
            data-advanced="true"
            :data-field-id="activeField?.id"
        >
            <template x-for="[value, label] in getOptionEntries(activeField?.options)" :key="value">
                <option :value="value" x-text="label"></option>
            </template>
        </select>
    </div>

    {{-- Options for Choice Fields --}}

    <div x-show="open && $store.formBuilder.isChoiceField(activeField.handle)" class="nice-form-group">
        <label for="field-options" class="form-label">Options</label>
        <small class="whitespace-normal">The field's available options formatted as value|Label or just Label</small>

        <textarea
            id="field-options"
            rows="5"
            :value="getOptionEntries(activeField?.options).map(([value, label]) => value + '|' + label).join('\n')"
            @change="updateSetting('options', parseOptionsInput($event.target.value))"
        ></textarea>
    </div>

    {{-- Allow add toggle for tomselect fields --}}
    <div x-show="open && activeField?.advanced" class="nice-form-group">
        <label for="field-advanced-allow-add" class="form-label">Allow Custom Values</label>
        <small class="whitespace-normal">Whether to allow users to add custom values to the field</small>
        <input
            id="field-advanced-allow-add"
            class="switch"
            type="checkbox"
            :checked="activeField?.allowAdd ?? false"
            @change="updateSetting('allowAdd', $event.target.checked)"
        />
    </div>

    {{-- Help Text --}}

    <div x-show="open" class="nice-form-group">
        <label for="field-help-text" class="form-label">Help text</label>
        <small class="whitespace-normal">Additional help text to assist the user filling out the field</small>
        <textarea
            id="field-help-text"
            :value="activeField?.helpText ?? ''"
            @change="updateSetting('helpText', $event.target.value)"
        ></textarea>
    </div>

    <div x-show="open && supportsHelpTextPosition()" class="nice-form-group">
        <label for="field-help-text-position" class="form-label">Show help text below field</label>
        <small class="whitespace-normal">Whether to show the help text below the field</small>
        <input
            id="field-help-text-position"
            class="switch"
            type="checkbox"
            :checked="activeField?.helpTextPosition === 'bottom' ?? false"
            @change="updateSetting('helpTextPosition', $event.target.checked ? 'bottom' : 'top')"
        />
    </div>

    {{-- States --}}
    <div x-show="open" class="nice-form-group">
        <label for="field-required" class="form-label">Required</label>
        <small class="whitespace-normal">Whether the field is required by default</small>
        <input
            id="field-required"
            class="switch"
            type="checkbox"
            :checked="activeField?.required ?? false"
            @change="updateDependentControls('required', $event.target.checked)"
        />
    </div>

    {{-- States --}}
    <div x-show="open" class="nice-form-group">
        <label for="field-disabled" class="form-label">Disabled</label>
        <small class="whitespace-normal">Whether the field is disabled by default</small>
        <input
            id="field-disabled"
            class="switch"
            type="checkbox"
            :checked="activeField?.disabled ?? false"
            @change="updateDependentControls('disabled', $event.target.checked)"
        />
    </div>
</div>