@php
    $rowIndex = $rowIndex ?? 0;
    $fieldPosition = $fieldPosition ?? 0;
    $rowFieldCount = $rowFieldCount ?? 0;
@endphp
<div
    class="field-drop-zone w-0 rounded-full opacity-0 border-dotted transition-all duration-150 ease-out motion-reduce:transition-none motion-reduce:duration-0"
    :class="isDragging && shouldShowFieldDropZone($el) ? 'bg-slate-100 border border-dotted border-slate-400 w-3 opacity-100' : ''"
    data-row-index="{{ $rowIndex }}"
    data-field-position="{{ $fieldPosition }}"
    data-row-field-count="{{ $rowFieldCount }}"
    @dragover.prevent="if (isGroupDrag($event) || !shouldShowFieldDropZone($el)) { return; } $el.classList.remove('bg-slate-100', 'border-slate-400'); $el.classList.add('bg-blue-100', 'border-blue-500')"
    @dragleave.prevent="if (!shouldShowFieldDropZone($el)) { return; } $el.classList.remove('bg-blue-100', 'border-blue-500'); $el.classList.add('bg-slate-100', 'border-slate-400')"
    @dragend.prevent="$el.classList.remove('bg-slate-100', 'bg-blue-100', 'border-slate-400', 'border-blue-500', 'w-3', 'opacity-100'); $el.classList.add('w-0', 'opacity-0');"
    @drop.prevent="if (isGroupDrag($event) || !shouldShowFieldDropZone($el)) { return; } isDragging = false; handleDrop($event, $el); $el.classList.remove('bg-slate-100', 'bg-blue-100', 'border-slate-400', 'border-blue-500', 'w-3', 'opacity-100'); $el.classList.add('w-0', 'opacity-0');"
></div>
