@if($activeFieldSettingsModel)
    @php
        $field = $activeFieldSettingsModel['field'];
        $payload = $activeFieldSettingsModel['payload'] ?? [];
        $location = $activeFieldSettingsModel['location'] ?? [];
        $fieldSettingsPanelKey = sprintf(
            'field-settings-panel-%s-%s-%s-%s',
            (string) ($payload['id'] ?? 'unknown'),
            (string) ($location['groupRowIndex'] ?? 'root'),
            (string) ($location['rowIndex'] ?? 'x'),
            (string) ($location['fieldIndex'] ?? 'y')
        );
        $optionsTextareaKey = sprintf(
            'field-options-%s-%s-%s-%s',
            (string) ($payload['id'] ?? 'unknown'),
            (string) ($location['groupRowIndex'] ?? 'root'),
            (string) ($location['rowIndex'] ?? 'x'),
            (string) ($location['fieldIndex'] ?? 'y')
        );
    @endphp

    @component('meros::toolbox.form-builder.settings-panel', [
        'title' => 'Field Settings',
        'subtitle' => $field->getLabel() ?: ucwords(str_replace(['-', '_'], ' ', (string) ($payload['handle'] ?? 'Field'))),
        'closeAction' => 'closeFieldSettings',
    ])
        <div wire:key="{{ $fieldSettingsPanelKey }}" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Label</label>
                <input
                    type="text"
                    value="{{ $payload['label'] ?? '' }}"
                    class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                    @input.debounce.300ms="$wire.updateActiveFieldSetting('label', $event.target.value)"
                >
            </div>

            <div class="grid grid-cols-1 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                    <input
                        type="text"
                        value="{{ $payload['name'] ?? ($payload['handle'] ?? '') }}"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                        @input.debounce.300ms="$wire.updateActiveFieldSetting('name', $event.target.value)"
                    >
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">ID</label>
                    <input
                        type="text"
                        value="{{ $payload['id'] ?? '' }}"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                        @input.debounce.300ms="$wire.updateActiveFieldSetting('id', $event.target.value)"
                    >
                </div>
            </div>

            @if($activeFieldSettingsModel['supportsPlaceholder'] ?? false)
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Placeholder</label>
                    <input
                        type="text"
                        value="{{ $payload['placeholder'] ?? '' }}"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                        @input.debounce.300ms="$wire.updateActiveFieldSetting('placeholder', $event.target.value)"
                    >
                </div>
            @endif

            @if($activeFieldSettingsModel['supportsRows'] ?? false)
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Rows</label>
                    <input
                        type="number"
                        min="1"
                        value="{{ (int) ($payload['rows'] ?? 3) }}"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                        @input.debounce.300ms="$wire.updateActiveFieldSetting('rows', $event.target.value)"
                    >
                </div>
            @endif

            @if($activeFieldSettingsModel['supportsOptions'] ?? false)
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Options</label>
                    <p class="mb-2 text-xs text-gray-500">One per line. Use value|Label or just Label.</p>
                    <textarea
                        wire:key="{{ $optionsTextareaKey }}"
                        rows="6"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                        @input.debounce.300ms="$wire.updateActiveFieldSetting('optionsText', $event.target.value)"
                    >{{ $activeFieldSettingsModel['optionsText'] ?? '' }}</textarea>
                </div>
            @endif

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Help Text</label>
                <textarea
                    rows="3"
                    class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                    @input.debounce.300ms="$wire.updateActiveFieldSetting('helpText', $event.target.value)"
                >{{ $payload['helpText'] ?? '' }}</textarea>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Help Text Position</label>
                <select
                    class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                    @change="$wire.updateActiveFieldSetting('helpTextPosition', $event.target.value)"
                >
                    <option value="bottom" @selected(($payload['helpTextPosition'] ?? 'bottom') === 'bottom')>Bottom</option>
                    <option value="top" @selected(($payload['helpTextPosition'] ?? 'bottom') === 'top')>Top</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Width</label>
                <select
                    class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                    @change="$wire.updateActiveFieldSetting('width', $event.target.value)"
                >
                    <option value="full" @selected(($payload['width'] ?? 'full') === 'full')>Full</option>
                    <option value="half" @selected(($payload['width'] ?? 'full') === 'half')>Half</option>
                    <option value="third" @selected(($payload['width'] ?? 'full') === 'third')>Third</option>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <label class="flex items-center gap-2 rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-700">
                    <input
                        type="checkbox"
                        class="h-4 w-4"
                        @checked((bool) ($payload['required'] ?? false))
                        @change="$wire.updateActiveFieldSetting('required', $event.target.checked)"
                    >
                    Required
                </label>

                <label class="flex items-center gap-2 rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-700">
                    <input
                        type="checkbox"
                        class="h-4 w-4"
                        @checked((bool) ($payload['disabled'] ?? false))
                        @change="$wire.updateActiveFieldSetting('disabled', $event.target.checked)"
                    >
                    Disabled
                </label>
            </div>

            @php
                $fieldHandle = $payload['handle'] ?? null;
                $isMultiSelect = $fieldHandle === 'multi_select';
                $isSelect = $fieldHandle === 'select';
                $isChoiceField = in_array($fieldHandle, ['select', 'multi_select', 'radio', 'checkboxes'], true);
                $supportsAdvanced = $isSelect || $isMultiSelect;
                $isAdvancedEnabled = (bool) ($payload['advanced'] ?? ($isMultiSelect ? true : false));
            @endphp

            @if($supportsAdvanced)
                @if($isSelect)
                    <div>
                        <label class="flex items-center gap-2 rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-700 cursor-pointer">
                            <input
                                type="checkbox"
                                class="h-4 w-4"
                                @checked($isAdvancedEnabled)
                                @change="$wire.updateActiveFieldSetting('advanced', $event.target.checked)"
                            >
                            <span>Use Advanced Select (TomSelect)</span>
                        </label>
                    </div>
                @endif

                @if($isAdvancedEnabled)
                    <div>
                        <label class="flex items-center gap-2 rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-700 cursor-pointer">
                            <input
                                type="checkbox"
                                class="h-4 w-4"
                                @checked((bool) ($payload['allowAdd'] ?? false))
                                @change="$wire.updateActiveFieldSetting('allowAdd', $event.target.checked)"
                            >
                            <span>Allow users to add new options</span>
                        </label>
                    </div>

                    <div class="pt-2">
                        <button
                            type="button"
                            @click="window.refreshTomSelectForActiveField()"
                            class="w-full rounded-md bg-blue-50 px-3 py-2 text-sm font-medium text-blue-700 border border-blue-200 hover:bg-blue-100 transition-colors"
                        >
                            Refresh Select Display
                        </button>
                    </div>
                @endif
            @endif
        </div>
    @endcomponent
@endif
