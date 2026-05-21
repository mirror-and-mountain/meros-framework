@include('meros::toolbox.forms.builder.canvas.index')

@script
    <script>
        document.addEventListener('livewire:initialized', () => {
            const getSchemaRows = async () => {
                return await $wire.getRows();
            }

            $store.formBuilder.setRowsUpdater((rows) => $wire.updateSchemaRows(rows));
            
            getSchemaRows().then(rows => {
                $store.formBuilder.setRows(rows);
            });
        });
    </script>
@endscript