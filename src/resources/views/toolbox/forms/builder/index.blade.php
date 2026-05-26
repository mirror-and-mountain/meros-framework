<div>
    @include('meros::toolbox.forms.builder.canvas.index')
    @include('meros::toolbox.forms.builder.preview')
</div>

@script
    <script>
        this.$on('schema-updated', (schema) => {
            window.dispatchEvent(new CustomEvent('meros-form-builder-schema-updated', {
                detail: {
                    rows: schema[0].rows,
                    advancedSelects: schema[0].advancedSelects
                }
            }));

            $store.formBuilder.setRows(schema[0].rows);
        });

        document.addEventListener('livewire:initialized', () => {
            const getSchemaRows = async () => {
                return await $wire.getRows();
            }

            const getRichTextPayloads = async () => {
                return await $wire.getRichTextPayloads();
            }

            // Inform the repeater field store that we're in the editor context
            $store.repeaterField.setIsEditor(true);

            // Set up the form builder store
            $store.formBuilder.setRowsUpdater((rows) => $wire.updateSchemaRows(rows));

            // Callbacks for repeater field editing
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
            
            getSchemaRows().then(rows => {
                $store.formBuilder.setRows(rows);
            });

            getRichTextPayloads().then(payloads => {
                $store.formBuilder.setRichTextPayloads(payloads);
                window.dispatchEvent(new CustomEvent('meros-form-builder-rich-text-updated', {
                    detail: {
                        richTextPayloads: payloads
                    }
                }));
            });
        });
    </script>
@endscript