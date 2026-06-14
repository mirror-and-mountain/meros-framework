<div
    x-data="{
        repeaterId: '{{ $activeRepeaterId }}',

        setRepeaterDefaultValue() {
            const value = mforms.getFieldValue(this.repeaterId);

            if (Array.isArray(value) && value.length > 0) {
                $wire.setRepeaterDefaultValue(value);
                return;
            }

            $wire.setRepeaterDefaultValue([]);
        }
    }"
    id="meros-form-builder-repeater-editor" 
    class="flex-1 h-full p-4 pb-25 overflow-y-auto overscroll-contain min-w-0 bg-slate-100" 
    wire:key="form-builder-repeater-editor-{{ $activeRepeaterId }}"
>
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-bold text-slate-900">Editing Repeater: {{ $activeRepeater->getLabel() }}</h2>
        @include('meros::toolbox.forms.builder.canvas.action-button', [
            'buttonText' => 'Update and Close',
            'action'     => '$wire.closeRepeaterEditor()',
        ])
    </div>
    <div 
        id="meros-form-builder-repeater-canvas" 
        class="canvas-repeater-field min-h-96" 
        data-repeater-id="{{ $activeRepeaterId }}"
    >
        {!! $activeRepeater->render(true, ['label' => false, 'helpText' => false]) !!}
        <div>
            @php
                $fields = $activeRepeater->getFields();
            @endphp
            @if($fields->isEmpty())
                {{-- Default drop zone for new elements --}}
                 <div
                    class="canvas-drop-zone row-drop-zone flex items-center justify-center h-64 rounded-2xl border-2 border-dashed border-slate-400 bg-slate-100 px-6 text-slate-700 transition-all duration-150 motion-reduce:transition-none"
                    data-row-index="0"
                    @dragover.prevent="$el.classList.add('border-blue-500', 'bg-blue-50', 'text-blue-800', 'ring-2', 'ring-blue-300');"
                    @dragleave.prevent="$el.classList.remove('border-blue-500', 'bg-blue-50', 'text-blue-800', 'ring-2', 'ring-blue-300')"
                    @drop.prevent="isDragging = false; handleDrop($event, $el); $el.classList.remove('border-blue-500', 'bg-blue-50', 'text-blue-800', 'ring-2', 'ring-blue-300')"
                >
                    <p class="text-sm font-semibold">Drag fields here to start building your repeater</p>
                </div>
            @else
                <div class="space-y-3">
                    <button 
                        type="button" 
                        class="cursor-pointer py-2 px-4 my-4 bg-blue-600 text-white rounded hover:bg-blue-700 active:bg-blue-800 font-medium text-sm transition-colors"
                        @click="setRepeaterDefaultValue()"
                    >
                        Save As Default Value
                    </button>
                    @foreach($fields as $fieldIndex => $field)
                        @if($fieldIndex === 0)
                            {{-- Top drop-zone for inserting new fields at the start of the repeater --}}
                            @include('meros::toolbox.forms.builder.canvas.dropzone-row', [
                                'rowIndex' => -1,
                            ])
                        @endif

                        {{-- The field --}}
                        @include('meros::toolbox.forms.builder.canvas.field', [
                            'field'         => $field,
                            'fieldRowIndex' => 0,
                            'fieldPosition' => $fieldIndex,
                        ])

                        @if(!$loop->last)
                            {{-- Drop-zone for inserting new fields between existing fields --}}
                            @include('meros::toolbox.forms.builder.canvas.dropzone-row', [
                                'rowIndex' => 0,
                                'fieldPosition' => $fieldIndex,
                            ])
                        @endif
                    @endforeach


                    {{-- Bottom drop-zone for inserting new fields at the end of the repeater --}}
                    @include('meros::toolbox.forms.builder.canvas.dropzone-row', [
                        'rowIndex' => count($fields)
                    ])
                </div>
            @endif
        </div>
    </div>
</div>