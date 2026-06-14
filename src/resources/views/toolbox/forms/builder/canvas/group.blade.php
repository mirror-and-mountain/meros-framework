@php
    $id = $group->getId();
    $title = $group->getTitle();
    $description = $group->getDescription();
    $hasContent = $group->hasContent();
@endphp
<div
    x-data="canvasgroup('{{ $id }}')"
    class="canvas-field-group mb-1 mx-2 border border-gray-300 rounded-lg bg-white flex-1 min-w-0" 
    data-group-id="{{ $id }}"
    data-group-title="{{ $title }}"
    data-row-index="{{ $rowIndex }}"
    wire:key="group-{{ $id }}">
    <div
        class="px-4 py-3 border-b border-gray-300 bg-gray-50"
    >
        <div
            class="relative z-20 mb-3 flex items-center justify-between gap-3 rounded-md border border-dashed border-gray-300 bg-gray-100 px-3 py-2 text-xs text-gray-700 cursor-move active:cursor-grabbing select-none transition-colors duration-150 motion-reduce:transition-none hover:border-blue-500 hover:bg-blue-50 hover:text-blue-800"
            draggable="true"
            data-group-id="{{ $id }}"
            title="Drag to move section"
            aria-label="Move section"
            aria-description="Drag to move this section to a different position in the form"
            :aria-grabbed="moving ? 'true' : 'false'"
            @dragstart="handleDragStart($event)"
            @dragend="moving = false"
        >
            <div class="flex items-center gap-2 min-w-0">
                <span class="text-sm leading-none">⠿</span>
                <span class="truncate font-medium">Move section: {{ $title }}</span>
            </div>

            <div class="flex items-center gap-2 shrink-0">
                <div>
                    <button
                        type="button"
                        class="shrink-0 text-gray-500 hover:text-blue-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-600 focus-visible:ring-offset-1 rounded-sm transition-colors text-xl leading-none cursor-pointer"
                        title="Edit section"
                    >
                        @include('meros::toolbox.svgs.settings')
                    </button>
                </div>
                <div @confirm="confirm('Are you sure you want to delete this section? This action cannot be undone.') ? $wire.removeGroup('{{ $id }}', '{{ $rowIndex }}') : null">
                    <button
                        type="button"
                        class="shrink-0 text-gray-500 hover:text-red-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-600 focus-visible:ring-offset-1 rounded-sm transition-colors text-xl leading-none cursor-pointer"
                        title="Remove section"
                        @click="$dispatch('confirm')"
                    >
                        @include('meros::toolbox.svgs.remove')
                    </button>
                </div>
            </div>
        </div>

        <div class="meros-form-group-header min-w-0">
            <h3 class="font-semibold text-gray-900">{{ $title }}</h3>
            @if(!empty($description))
                <div class="text-sm text-gray-700">{!! $this->renderQuillContent($description) !!}</div>
            @endif
        </div>
    </div>

    <div class="meros-form-builder-group-elements p-3">
        {{-- Empty group drop zone --}}
        @if(!$hasContent)
            <div
                class="group-canvas-drop-zone row-drop-zone flex items-center justify-center h-64 rounded-2xl border-2 border-dashed border-slate-400 bg-slate-100 px-6 text-slate-700 transition-all duration-150 motion-reduce:transition-none"
                data-row-index="0"
                @dragover.prevent="$el.classList.add('border-blue-500', 'bg-blue-50', 'text-blue-800', 'ring-2', 'ring-blue-300');"
                @dragleave.prevent="$el.classList.remove('border-blue-500', 'bg-blue-50', 'text-blue-800', 'ring-2', 'ring-blue-300')"
                @drop.prevent="isDragging = false; handleDrop($event, $el); $el.classList.remove('border-blue-500', 'bg-blue-50', 'text-blue-800', 'ring-2', 'ring-blue-300')"
            >
                <p class="text-sm font-semibold">Drag fields here to start building the group</p>
            </div>
        @else
        <div class="space-y-3">
            {{-- Top row drop-zone for inserting new elements at the top of the group --}}
            @include('meros::toolbox.forms.builder.canvas.dropzone-row', [
                'rowIndex' => -1,
            ])
        </div>
        @endif

        @foreach($group->getRows() as $groupRowIndex => $groupRow)
            <div 
                class="field-group-row flex gap-2 mb-3"
                wire:key="form-row-{{ $groupRowIndex }}"
            >

            @foreach($groupRow->getFields() as $groupFieldIndex => $groupField)
                {{-- Left drop-zone for inserting elements before the field --}}
                @include('meros::toolbox.forms.builder.canvas.dropzone-field', [
                    'rowIndex'      => $groupRowIndex,
                    'fieldPosition' => $groupFieldIndex,
                    'rowFieldCount' => count($groupRow->getFields()),
                ])

                {{-- The group field --}}
                @include('meros::toolbox.forms.builder.canvas.field', [
                    'field'         => $groupField,
                    'fieldRowIndex' => $groupRowIndex,
                    'fieldPosition' => $groupFieldIndex,
                ])

                  {{-- Right drop-zone for inserting elements after the field --}}
                @if($groupFieldIndex === count($groupRow->getFields()) - 1)
                    @include('meros::toolbox.forms.builder.canvas.dropzone-field', [
                        'rowIndex'      => $groupRowIndex,
                        'fieldPosition' => $groupFieldIndex + 1,
                        'rowFieldCount' => count($groupRow->getFields()),
                    ])
                @endif
            @endforeach
            </div>

            @if(!$loop->last)
                {{-- Row drop-zone between rows --}}
                @include('meros::toolbox.forms.builder.canvas.dropzone-row', [
                    'rowIndex' => $groupRowIndex + 1,
                    'wireKey'  => 'form-row-drop-zone-between-' . $groupRowIndex,
                ])
            @endif
        @endforeach

         {{-- Single bottom row drop-zone --}}
        @include('meros::toolbox.forms.builder.canvas.dropzone-row', [
            'rowIndex' => count($group->getRows()),
            'wireKey'  => 'form-row-drop-zone-bottom',
        ])
    </div>
</div>