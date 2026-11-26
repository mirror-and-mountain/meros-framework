@script
    <script>
        $wire.$el.addEventListener('mforms::livewireRepeaterEditForm', event => {
            const { formId, name, originalName, rowData, currentValue, respondWith } = event.detail;
            respondWith($wire.renderRepeaterEditForm(formId, name, originalName, rowData, currentValue));
        });
    </script>
@endscript
