<div 
    id="meros-form-builder-canvas" 
    class="flex-1 h-full p-4 pb-25 overflow-y-auto overscroll-contain min-w-0 bg-slate-100" 
    wire:key="form-builder-canvas"
>
    <div class="flex items-center justify-between mb-4">
        <h2 wire:click="dumpRows" class="text-lg font-bold text-slate-900">Canvas</h2>
        @include('meros::toolbox.forms.builder.canvas.action-button')
    </div>
    <div id="meros-form-builder-elements" class="min-h-96">
        {{-- Empty canvas drop zone --}}
        @if(empty($rows))
            <div
                class="canvas-drop-zone row-drop-zone flex items-center justify-center h-64 rounded-2xl border-2 border-dashed border-slate-400 bg-slate-100 px-6 text-slate-700 transition-all duration-150 motion-reduce:transition-none"
                data-row-index="0"
                @dragover.prevent="$el.classList.add('border-blue-500', 'bg-blue-50', 'text-blue-800', 'ring-2', 'ring-blue-300');"
                @dragleave.prevent="$el.classList.remove('border-blue-500', 'bg-blue-50', 'text-blue-800', 'ring-2', 'ring-blue-300')"
                @drop.prevent="isDragging = false; handleDrop($event, $el); $el.classList.remove('border-blue-500', 'bg-blue-50', 'text-blue-800', 'ring-2', 'ring-blue-300')"
            >
                <p class="text-sm font-semibold">Drag fields or groups here to start building your form</p>
            </div>
        @else
            <div class="space-y-3">
                {{-- Top row drop-zone for inserting new elements at the top of the form --}}
                @include('meros::toolbox.forms.builder.canvas.dropzone-row', [
                    'rowIndex' => -1,
                ])

                @foreach($rows as $rowIndex => $row)
                    <div 
                        class="form-row flex gap-2 mb-3"
                        wire:key="form-row-{{ $rowIndex }}"
                    >

                    @if($row->type === 'fields')
                        @foreach($row->getFields() as $fieldIndex => $field)
                            {{-- Left drop-zone for inserting elements before the field --}}
                            @include('meros::toolbox.forms.builder.canvas.dropzone-field', [
                                'rowIndex'      => $rowIndex,
                                'fieldPosition' => $fieldIndex,
                                'rowFieldCount' => count($row->getFields()),
                            ])

                            {{-- The field --}}
                            @include('meros::toolbox.forms.builder.canvas.field', [
                                'field'         => $field,
                                'fieldRowIndex' => $rowIndex,
                                'fieldPosition' => $fieldIndex,
                            ])

                            {{-- Right drop-zone for inserting elements after the field --}}
                            @if($fieldIndex === count($row->getFields()) - 1)
                                @include('meros::toolbox.forms.builder.canvas.dropzone-field', [
                                    'rowIndex'      => $rowIndex,
                                    'fieldPosition' => $fieldIndex + 1,
                                    'rowFieldCount' => count($row->getFields()),
                                ])
                            @endif
                        @endforeach
                    @elseif($row->type === 'group')
                        @include('meros::toolbox.forms.builder.canvas.group', [
                            'group'    => $row->getGroup(),
                            'rowIndex' => $rowIndex,
                        ])
                    @endif
                    </div>

                    @if(!$loop->last)
                        {{-- Row drop-zone between rows --}}
                        @include('meros::toolbox.forms.builder.canvas.dropzone-row', [
                            'rowIndex' => $rowIndex + 1,
                            'wireKey'  => 'form-row-drop-zone-between-' . $rowIndex,
                        ])
                    @endif
                @endforeach

                {{-- Single bottom row drop-zone --}}
                @include('meros::toolbox.forms.builder.canvas.dropzone-row', [
                    'rowIndex' => count($rows),
                    'wireKey'  => 'form-row-drop-zone-bottom',
                ])
            </div>
        @endif
    </div>
</div>