<div
    class="meros-form-builder mforms-form"
    :class="{ fullscreen: isFullscreen }"
    @keydown.escape.window="!$event.defaultPrevented && isFullscreen && toggleFullscreen($el)"
    x-data="mformsBuilder"
    x-init="initialize($el)"
    data-repeater-defaults-url="{{ admin_url('admin-ajax.php') }}"
    data-repeater-defaults-action="{{ $repeaterDefaultsAction }}"
    data-repeater-defaults-nonce="{{ $repeaterDefaultsNonce }}"
    data-post-id="{{ $postId }}"
    data-template-settings="{{ json_encode($templateLockSettings, JSON_THROW_ON_ERROR) }}"
    data-condition-sources="{{ json_encode((object) $conditionSources, JSON_THROW_ON_ERROR) }}"
    data-dynamic-values="{{ json_encode($dynamicValues, JSON_THROW_ON_ERROR) }}"
    data-field-data-types="{{ json_encode((object) $fieldDataTypes, JSON_THROW_ON_ERROR) }}"
>
    <div class="mforms-body">
        @if($templateRows !== null)
            <input type="hidden" name="form_components[__builder]" value="1">
        @endif
        {{-- Render the template rows inertly so reset can restore their original controls. --}}
        @foreach($templateRows === null ? [$rows] : [$rows, $templateRows] as $setIndex => $builderRows)
        @if($setIndex === 1)
            <template x-ref="resetTemplate">
        @endif
        @foreach($builderRows as $index => $row)
            @php
                $childGroup = $row['childGroup'] ?? null;
                $isGroup = is_array($childGroup) && $childGroup !== [];
                $groupTitle = is_array($childGroup) ? ($childGroup['title'] ?? 'Group') : 'Group';
                $groupTitle = is_string($groupTitle) && $groupTitle !== '' ? $groupTitle : 'Group';
                $groupName = is_array($childGroup) ? ($childGroup['name'] ?? 'group_' . $index) : 'group_' . $index;
                $groupDescription = is_array($childGroup) ? ($childGroup['description'] ?? '') : '';
                $groupId = is_array($childGroup) ? ($childGroup['id'] ?? 'mforms-section-' . $index) : 'mforms-section-' . $index;
                $groupLocks = $isGroup ? $this->groupLocks($childGroup) : [];
                $groupPropertiesLocked = !$isFormTemplate && ($groupLocks['__properties_locked_by_template'] ?? false);
                $groupMoveLocked = !$isFormTemplate && (($groupLocks['__move_locked_by_template'] ?? false) || ($isGroup && $this->groupContainsLockedField($childGroup, '__move_locked_by_template')));
                $groupRemoveLocked = !$isFormTemplate && (($groupLocks['__remove_locked_by_template'] ?? false) || ($isGroup && $this->groupContainsLockedField($childGroup, '__remove_locked_by_template')));
                $templateGroup = $isGroup ? $this->templateGroup($childGroup) : null;
                if ($groupPropertiesLocked && $templateGroup) {
                    $groupTitle = $templateGroup['title'] ?? 'Group';
                    $groupDescription = $templateGroup['description'] ?? '';
                }
            @endphp
            <div
                class="mforms-row"
                id="mforms-row-{{ $index }}"
                data-row-index="{{ $index }}"
                data-row-type="{{ $isGroup ? 'section' : 'fields' }}"
            >
                @if ($isGroup)
                    <div class="mforms-group-component mforms-builder-group-card" data-form-group-component id="{{ $groupId }}" data-move-locked="{{ $groupMoveLocked ? '1' : '0' }}" data-remove-locked="{{ $groupRemoveLocked ? '1' : '0' }}">
                        <div
                            class="mforms-group-builder-header"
                            aria-label="Group component. Drag to reorder."
                        >
                            @unless($groupMoveLocked)
                            @include('meros::forms.builder-move-control', ['moveGroup' => true])
                            @endunless
                            <label>
                                <span>Group title</span>
                                <input
                                    type="text"
                                    name="form_components[{{ $index }}]['childGroup']['title']"
                                    value="{{ $groupTitle }}"
                                    @readonly($groupPropertiesLocked)
                                >
                            </label>
                            <div class="mforms-group-header-actions">
                                <div class="mforms-builder-lock-controls">
                                    @foreach($groupLocks as $flag => $locked)
                                        <input type="hidden" name="form_components[{{ $index }}]['childGroup'][{{ $flag }}]" value="{{ $isFormTemplate ? '0' : ($locked ? '1' : '0') }}">
                                        @if($isFormTemplate)
                                            <label><input type="checkbox" name="form_components[{{ $index }}]['childGroup'][{{ $flag }}]" value="1" @checked($locked)> {{ ['__properties_locked_by_template' => 'Lock Group Properties', '__move_locked_by_template' => 'Prevent Moving', '__remove_locked_by_template' => 'Prevent Removal'][$flag] }}</label>
                                        @endif
                                    @endforeach
                                </div>
                                <button type="button" class="button" @disabled($groupPropertiesLocked) @click.stop.prevent="configureConditions($el.closest('[data-form-group-component]'))">Configure conditions</button>
                                @unless($groupRemoveLocked)
                                <button
                                    type="button"
                                    class="mforms-builder-remove"
                                    aria-label="Remove group"
                                    @click.stop.prevent="removeGroup($el.closest('[data-form-group-component]'))"
                                >Remove</button>
                                @endunless
                            </div>
                            <input
                                type="hidden"
                                name="form_components[{{ $index }}]['childGroup']['name']"
                                value="{{ $groupName }}"
                            >
                            <input
                                type="hidden"
                                name="form_components[{{ $index }}]['childGroup']['id']"
                                value="{{ $groupId }}"
                            >
                            <input type="hidden" data-builder-conditions name="form_components[{{ $index }}]['childGroup']['__builder_conditions']" value="{{ json_encode(($groupPropertiesLocked ? ($templateGroup['conditions'] ?? []) : ($childGroup['conditions'] ?? [])), JSON_THROW_ON_ERROR) }}">
                        </div>
                        <div class="mforms-group-description-control">
                            {!! $this->renderDescriptionControl("form_components[{$index}]['childGroup']['description']", $groupId . '-description', 'Group description', $groupDescription, $groupPropertiesLocked) !!}
                        </div>

                        <div
                            id="{{ $groupId }}-builder"
                            class="meros-form-builder mforms-form meros-group-form-builder"
                            data-rows-name-prefix="form_components[{{ $index }}]['childGroup']['rows']"
                            x-data="{}"
                            x-init="initialize($el)"
                        >
                            <div class="mforms-body">
                                @foreach($childGroup['rows'] ?? [] as $groupRowIndex => $groupRow)
                                    <div
                                        class="mforms-row"
                                        id="mforms-row-{{ $groupRowIndex }}"
                                        data-row-index="{{ $groupRowIndex }}"
                                        data-row-type="fields"
                                    >
                                        @foreach($groupRow['fields'] ?? [] as $fieldIndex => $field)
                                            @php
                                                $groupFieldName = $field['name'] ?? '';
                                                $fieldLocks = $this->fieldLocks($field);
                                                $fieldMoveLocked = !$isFormTemplate && $fieldLocks['__move_locked_by_template'];
                                                $fieldRemoveLocked = !$isFormTemplate && $fieldLocks['__remove_locked_by_template'];
                                            @endphp
                                            @if ($groupFieldName !== '')
                                                <div
                                                    id="mforms-group-field-component-{{ $index }}-{{ $groupRowIndex }}-{{ $fieldIndex }}"
                                                    data-form-field-component
                                                    data-move-locked="{{ $fieldMoveLocked ? '1' : '0' }}"
                                                    data-remove-locked="{{ $fieldRemoveLocked ? '1' : '0' }}"
                                                    @if(($field['type'] ?? '') === 'repeater') data-repeater-component @endif
                                                    data-row-index="{{ $groupRowIndex }}"
                                                    data-field-position="{{ $fieldIndex }}"
                                                    @dragend="endDrag($el.closest('.meros-form-builder'))"
                                                    class="mforms-builder-field-card"
                                                >
                                                    @unless($fieldMoveLocked)
                                                    @include('meros::forms.builder-move-control', ['moveGroup' => false])
                                                    @endunless
                                                    @unless($fieldRemoveLocked)
                                                    <button
                                                        type="button"
                                                        class="mforms-builder-remove"
                                                        aria-label="Remove field"
                                                        title="Remove field"
                                                        @click.stop.prevent="removeField($el.closest('[data-form-field-component]'))"
                                                    >×</button>
                                                    @endunless
                                                    <div class="mforms-builder-field-content">
                                                        {!! $this->getGroupFieldComponent($index, $groupRowIndex, $fieldIndex, $field) !!}
                                                    </div>
                                                </div>
                                            @endif
                                        @endforeach
                                    </div>
                                @endforeach

                                @if (empty($childGroup['rows'] ?? []))
                                    <div class="mforms-row" data-row-index="0" data-row-type="fields"></div>
                                @endif

                                <template x-ref="newFieldTemplate">
                                    <div class="mforms-row" data-row-type="fields">
                                        <div
                                            id="mforms-group-field-component-template-0"
                                            data-form-field-component
                                            class="mforms-builder-field-card"
                                        >
                                            @include('meros::forms.builder-move-control', ['moveGroup' => false])
                                            <button
                                                type="button"
                                                class="mforms-builder-remove"
                                                aria-label="Remove field"
                                                title="Remove field"
                                                @click.stop.prevent="removeField($el.closest('[data-form-field-component]'))"
                                            >×</button>
                                            <div class="mforms-builder-field-content">
                                                {!! $this->getNewFieldComponent("form_components[{$index}]['childGroup']['rows'][0]['fields'][0]") !!}
                                            </div>
                                        </div>
                                    </div>
                                </template>

                                <template x-ref="newRepeaterTemplate">
                                    @include('meros::forms.builder-repeater-template', ['componentName' => "form_components[{$index}]['childGroup']['rows'][0]['fields'][0]"])
                                </template>

                                <div class="mforms-builder-actions">
                                    <button
                                        type="button"
                                        class="mforms-builder-add-field"
                                        @click="addField($el.closest('.meros-form-builder'))"
                                    >
                                        <span aria-hidden="true">+</span>
                                        Add field
                                    </button>
                                    @if($templateSettings['allow_add_components'] ?? true)
                                        <button type="button" class="mforms-builder-add-field" @click="addField($el.closest('.meros-form-builder'), 'repeater')">
                                            <span aria-hidden="true">+</span> Add repeater field
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @else
                    @foreach($row['fields'] ?? [] as $fieldIndex => $field)
                        @php
                            $name = $field['name'] ?? '';
                            $fieldLocks = $this->fieldLocks($field);
                            $fieldMoveLocked = !$isFormTemplate && $fieldLocks['__move_locked_by_template'];
                            $fieldRemoveLocked = !$isFormTemplate && $fieldLocks['__remove_locked_by_template'];
                        @endphp
                        @if ($name !== '')
                            <div
                                id="mforms-field-component-{{ $index }}-{{ $fieldIndex }}"
                                data-form-field-component
                                data-move-locked="{{ $fieldMoveLocked ? '1' : '0' }}"
                                data-remove-locked="{{ $fieldRemoveLocked ? '1' : '0' }}"
                                @if(($field['type'] ?? '') === 'repeater') data-repeater-component @endif
                                data-row-index="{{ $index }}"
                                data-field-position="{{ $fieldIndex }}"
                                @dragend="endDrag($el.closest('.meros-form-builder'))"
                                class="mforms-builder-field-card"
                            >
                                @unless($fieldMoveLocked)
                                @include('meros::forms.builder-move-control', ['moveGroup' => false])
                                @endunless
                                @unless($fieldRemoveLocked)
                                <button
                                    type="button"
                                    class="mforms-builder-remove"
                                    aria-label="Remove field"
                                    title="Remove field"
                                    @click.stop.prevent="removeField($el.closest('[data-form-field-component]'))"
                                >×</button>
                                @endunless
                                <div class="mforms-builder-field-content" @if(!$isFormTemplate && $fieldLocks['__properties_locked_by_template']) inert aria-disabled="true" @endif>
                                    {!! $this->renderFieldComponent($field, "form_components[{$index}]['fields'][{$fieldIndex}]") !!}
                                </div>
                            </div>
                        @endif
                    @endforeach
                @endif
            </div>
        @endforeach
        @if($setIndex === 1)
            </template>
        @endif
        @endforeach

        <template x-ref="newFieldTemplate">
            <div class="mforms-row" data-row-type="fields">
                <div
                    id="mforms-field-component-template-0"
                    data-form-field-component
                    data-row-index="__ROW_INDEX__"
                    data-field-position="0"
                    class="mforms-builder-field-card"
                >
                    @include('meros::forms.builder-move-control', ['moveGroup' => false])
                    <button
                        type="button"
                        class="mforms-builder-remove"
                        aria-label="Remove field"
                        title="Remove field"
                        @click.stop.prevent="removeField($el.closest('[data-form-field-component]'))"
                    >×</button>
                    <div class="mforms-builder-field-content">
                        {!! $this->getNewFieldComponent() !!}
                    </div>
                </div>
            </div>
        </template>

        <template x-ref="newRepeaterTemplate">
            @include('meros::forms.builder-repeater-template', ['componentName' => "form_components[0]['fields'][0]"])
        </template>

        <template x-ref="newGroupTemplate">
            <div class="mforms-row" data-row-type="section">
                <div class="mforms-group-component mforms-builder-group-card" data-form-group-component id="mforms-section-template">
                    <div
                        class="mforms-group-builder-header"
                        aria-label="Group component. Drag to reorder."
                    >
                        @include('meros::forms.builder-move-control', ['moveGroup' => true])
                        <label>
                            <span>Group title</span>
                            <input type="text" name="form_components[0]['childGroup']['title']" value="New Group">
                        </label>
                        <div class="mforms-group-header-actions">
                            @if($isFormTemplate)
                                <div class="mforms-builder-lock-controls">
                                    @foreach(['__properties_locked_by_template' => 'Lock Group Properties', '__move_locked_by_template' => 'Prevent Moving', '__remove_locked_by_template' => 'Prevent Removal'] as $flag => $label)
                                        <input type="hidden" name="form_components[0]['childGroup'][{{ $flag }}]" value="0">
                                        <label><input type="checkbox" name="form_components[0]['childGroup'][{{ $flag }}]" value="1"> {{ $label }}</label>
                                    @endforeach
                                </div>
                            @endif
                            <button type="button" class="button" @click.stop.prevent="configureConditions($el.closest('[data-form-group-component]'))">Configure conditions</button>
                            <button
                                type="button"
                                class="mforms-builder-remove"
                                aria-label="Remove group"
                                @click.stop.prevent="removeGroup($el.closest('[data-form-group-component]'))"
                            >Remove</button>
                        </div>
                        <input type="hidden" name="form_components[0]['childGroup']['name']" value="new_group">
                        <input type="hidden" name="form_components[0]['childGroup']['id']" value="mforms-section-template">
                        <input type="hidden" data-builder-conditions name="form_components[0]['childGroup']['__builder_conditions']" value="[]">
                    </div>
                    <div class="mforms-group-description-control">
                        {!! $this->renderDescriptionControl("form_components[0]['childGroup']['description']", 'mforms-section-template-description', 'Group description') !!}
                    </div>
                    <div
                        id="mforms-group-builder-template"
                        class="meros-form-builder mforms-form meros-group-form-builder"
                        data-rows-name-prefix="form_components[0]['childGroup']['rows']"
                        x-data="{}"
                        x-init="initialize($el)"
                    >
                        <div class="mforms-body">
                            <div class="mforms-row" data-row-index="0" data-row-type="fields"></div>
                            <template x-ref="newFieldTemplate">
                                <div class="mforms-row" data-row-type="fields">
                                    <div
                                        id="mforms-group-field-component-template-0"
                                        data-form-field-component
                                        class="mforms-builder-field-card"
                                    >
                                        @include('meros::forms.builder-move-control', ['moveGroup' => false])
                                        <button
                                            type="button"
                                            class="mforms-builder-remove"
                                            aria-label="Remove field"
                                            title="Remove field"
                                            @click.stop.prevent="removeField($el.closest('[data-form-field-component]'))"
                                        >×</button>
                                        <div class="mforms-builder-field-content">
                                            {!! $this->getNewFieldComponent("form_components[0]['childGroup']['rows'][0]['fields'][0]") !!}
                                        </div>
                                    </div>
                                </div>
                            </template>
                            <template x-ref="newRepeaterTemplate">
                                @include('meros::forms.builder-repeater-template', ['componentName' => "form_components[0]['childGroup']['rows'][0]['fields'][0]"])
                            </template>
                            <div class="mforms-builder-actions">
                                <button
                                    type="button"
                                    class="mforms-builder-add-field"
                                    @click="addField($el.closest('.meros-form-builder'))"
                                >
                                    <span aria-hidden="true">+</span>
                                    Add field
                                </button>
                                @if($templateSettings['allow_add_components'] ?? true)
                                    <button type="button" class="mforms-builder-add-field" @click="addField($el.closest('.meros-form-builder'), 'repeater')">
                                        <span aria-hidden="true">+</span> Add repeater field
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <div class="mforms-builder-actions">
            @if($templateSettings['allow_add_components'] ?? true)
                <button
                    type="button"
                    class="mforms-builder-add-field"
                    @click="addField($el.closest('.meros-form-builder'))"
                >
                    <span aria-hidden="true">+</span>
                    Add field
                </button>
                <button type="button" class="mforms-builder-add-field" @click="addField($el.closest('.meros-form-builder'), 'repeater')">
                    <span aria-hidden="true">+</span> Add repeater field
                </button>
                <button
                    type="button"
                    class="mforms-builder-add-field"
                    @click="addGroup($el.closest('.meros-form-builder'))"
                >
                    <span aria-hidden="true">+</span>
                    Add Group
                </button>
            @endif
            @if($templateRows !== null)
                <button type="button" class="mforms-builder-add-field" @click="resetToTemplate($el.closest('.meros-form-builder'))">
                    Reset to template
                </button>
            @endif
        </div>
    </div>
</div>
