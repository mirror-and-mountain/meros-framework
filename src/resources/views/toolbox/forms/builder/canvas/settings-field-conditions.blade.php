<div id="meros-form-builder-field-conditions-settings-{{ $editingFieldID }}" class="flex-1 h-full p-4 pb-25 overflow-y-auto overscroll-contain min-w-0" wire:key="form-builder-field-conditions-{{ $editingFieldID }}" wire:transition>
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-bold">Editing Field Conditions: {{ $editingField->getLabel() }}</h2>
        @include('meros::toolbox.forms.builder.canvas.action-button', [
            'buttonText' => 'Update and Close',
            'action'     => '$store.formBuilder.saveFieldConditions()'
        ])
    </div>
    <div id="field-conditions-errors">
    </div>
    <div class="space-y-4">
        @foreach($this->getFieldConditionsRepeaters() as $type => $repeater)
            <div class="mb-4 p-4 bg-gray-100 border border-gray-300 rounded-lg" wire:key="field-conditions-{{ $type }}" wire:ignore>
                <div>
                    <h3 class="text-md font-semibold -mb-4">{{ ucfirst(str_replace('_', ' ', $type)) }}</h3>
                    <div class="nice-form-group">
                        <label 
                            for="field-conditions-{{ $type }}-logic" 
                            class="block mb-1 text-sm font-medium text-gray-700"
                        >
                            @if($type === 'optional')
                                Make this field optional when:
                            @elseif($type === 'require')
                                Make this field required when:
                            @else
                                {{ ucfirst(str_replace('_', ' ', $type)) }} this field when:
                            @endif
                        </label>
                        <select
                            id="field-conditions-{{ $type }}-logic"
                            class="field-conditions-logic-selector mb-3 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                        >
                            <option value="and">all the following conditions are met</option>
                            <option value="or">any of the following conditions is met</option>
                        </select>
                    </div>
                </div>
                <div>
                    {!! $repeater->render(false, false) !!}
                </div>
            </div>
        @endforeach
    </div>
</div>