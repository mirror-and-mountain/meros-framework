<div
    x-data="fieldConditions(
        @js(\Illuminate\Support\Arr::keyBy($this->getFields(true, true, ['id', 'name', 'label', 'attributes', 'isInputType', 'isChoiceType', 'isMultiSelect']), 'id')),
        @js($activeField !== null ? $activeField::getConditionTypes() : [])
    )"
    id="meros-form-builder-conditions-editor" 
    class="flex-1 h-full p-4 pb-25 overflow-y-auto overscroll-contain min-w-0 bg-slate-100" 
    wire:key="form-builder-conditions-editor-{{ $activeFieldId }}"
>
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-bold text-slate-900">Editing Conditions for Field: {{ $activeField->getLabel() }}</h2>
        @include('meros::toolbox.forms.builder.canvas.action-button', [
            'buttonText' => 'Update and Close',
            'action'     => '$wire.updateFieldConditions(getConditions())',
        ])
    </div>

    <div class="min-h-96">
        @if($activeField !== null)
            {!! $this->getConditionsRepeaterHtml() !!}
        @endif
    </div>
</div>