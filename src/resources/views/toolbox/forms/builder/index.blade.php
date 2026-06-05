{{-- div root element --}}
<div>
    @include('meros::toolbox.forms.builder.settings.index', [
        'formID'          => $formID,
        'formTitle'       => $formTitle,
        'formDescription' => $formDescription,
        'formSlug'        => $formSlug
    ])
    @include('meros::toolbox.forms.builder.canvas.index')
</div>

@script
    <script>
        this.$on('schema-updated', (schema) => {
            window.dispatchEvent(new CustomEvent('meros-form-builder-schema-updated', {
                detail: {
                    rows: schema[0].rows,
                    richTextPayloads: schema[0].richTextPayloads,
                    ignoredFields: schema[0].ignoredFields
                }
            }));

            $store.formBuilder.setRows(schema[0].rows);
        });

        document.addEventListener('livewire:initialized', () => {
            const getSettings = async () => {
                return await $wire.getSettings();
            }

            const getRows = async () => {
                return await $wire.getRows();
            }

            const getActions = async () => {
                return await $wire.getActions();
            }

            const getActionPayloads = async () => {
                return await $wire.getActionPayloads();
            }

            const getRichTextPayloads = async () => {
                return await $wire.getRichTextPayloads();
            }

            // Inform the repeater field store that we're in the editor context
            $store.repeaterField.setIsEditor(true);

            // Set up the form builder store
            $store.formBuilder.setSettingsUpdater((key, value) => $wire.updateSettings(key, value));
            $store.formBuilder.setRowsUpdater((rows, closeEditingPanel) => $wire.updateRows(rows, closeEditingPanel));
            $store.formBuilder.setFieldConditionsEditCallback((fieldId) => $wire.setEditingFieldID(fieldId));
            $store.formBuilder.setActionsUpdater((actions) => $wire.updateActions(actions));
            $store.formBuilder.setActionConfigCallback((actionHandle, fields, config) => $wire.getActionConfigurationDialog(actionHandle, fields, config));
            $store.formBuilder.setFieldConditionOperatorMap(@js($this->getFieldConditionOperatorMap()));

            getSettings().then(settings => {
                $store.formBuilder.formTitle = settings.title;
                $store.formBuilder.formDescription = settings.description;
                $store.formBuilder.formSlug = settings.slug;
                $store.formBuilder.formStatus = settings.status;
            });
            
            getRows().then(rows => {
                $store.formBuilder.setRows(rows);
            });

            getActions().then(actions => {
                $store.formBuilder.setActions(actions);
            });

            getActionPayloads().then(payloads => {
                $store.formBuilder.setActions(payloads);
            });

            getRichTextPayloads().then(payloads => {
                $store.formBuilder.setRichTextPayloads(payloads);
  
                window.dispatchEvent(new CustomEvent('meros-form-builder-rich-text-updated', {
                    detail: {
                        richTextPayloads: payloads
                    }
                }));
            });

            // Set callbacks for repeater field editing
            $store.formBuilder.setRepeaterEditCallback((repeaterId) =>
                $wire.setEditingRepeaterID(repeaterId)
            );

            $store.formBuilder.setRepeaterFieldMoveCallback((repeaterId, fieldId, newIndex) =>
                $wire.moveRepeaterField(repeaterId, fieldId, newIndex)
            );

            $store.formBuilder.setRepeaterFieldAddCallback((repeaterId, fieldType, position) =>
                $wire.addRepeaterField(repeaterId, fieldType, position)
            );

            $store.formBuilder.setRepeaterFieldUpdateCallback((repeaterId, fieldId, property, value) =>
                $wire.updateRepeaterField(repeaterId, fieldId, property, value)
            );

            $store.formBuilder.setRepeaterUpdateValueCallback((repeaterId, value) =>
                $wire.updateRepeaterDefaultValue(repeaterId, value)
            );
        });
    </script>
@endscript