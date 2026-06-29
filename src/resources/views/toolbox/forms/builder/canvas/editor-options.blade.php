<div
    x-data="{
        updateOptions() {
            this.$nextTick(() => {
                const options = mforms.getFieldValue('field-options-editor') || [];
                $wire.updateFieldOptions(options);
            });
        }
    
    }"
    id="meros-form-builder-options-editor" 
    class="flex-1 h-full p-4 pb-25 overflow-y-auto overscroll-contain min-w-0 bg-slate-100" 
    wire:key="form-builder-options-editor-{{ $activeFieldId }}"
>
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-bold text-slate-900">Editing Options for Field: {{ $activeField->getLabel() }}</h2>
        @include('meros::toolbox.forms.builder.canvas.action-button', [
            'buttonText' => 'Update and Close',
            'action'     => 'updateOptions;',
        ])
    </div>

    <div class="min-h-96">
        @if($activeField !== null)
            {!! $this->getOptionsRepeaterFieldHtml() !!}
        @endif
    </div>
</div>