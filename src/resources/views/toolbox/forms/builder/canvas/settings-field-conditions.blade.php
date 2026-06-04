<div id="meros-form-builder-field-conditions-settings-{{ $editingFieldID }}" class="flex-1 h-full p-4 pb-25 overflow-y-auto overscroll-contain min-w-0" wire:key="form-builder-field-conditions-{{ $editingFieldID }}">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-bold"><a class="underline hover:text-blue-700" title="Back to Canvas" href="#" @click.prevent="$store.formBuilder.activeField = null" wire:click="setEditingRepeaterID(null)">Canvas</a> / Field Conditions</h2>
        @include('meros::toolbox.forms.builder.canvas.action-button')
    </div>
    <div class="space-y-4">
        @foreach($this->getFieldConditionsRepeaters() as $type => $repeater)
            <div class="mb-4 p-4 bg-gray-50 border border-gray-300 rounded-lg" wire:key="field-conditions-{{ $type }}">
                <div>
                    <h3 class="text-md font-semibold -mb-4">{{ ucfirst(str_replace('_', ' ', $type)) }}</h3>
                    <div class="nice-form-group">
                        <label 
                            for="field-conditions-{{ $type }}-logic" 
                            class="block mb-1 text-sm font-medium text-gray-700"
                        >
                            {{ ucfirst(str_replace('_', ' ', $type)) }} this field when:
                        </label>
                        <select
                            id="field-conditions-{{ $type }}-logic"
                            class="mb-3 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                            {{-- x-on:change="$store.formBuilder.updateFieldConditionsLogic('{{ $type }}', $event.target.value)" --}}
                        >
                            <option value="all">all the following conditions are met</option>
                            <option value="any">any of the following conditions is met</option>
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