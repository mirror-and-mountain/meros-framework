@php
    $rowIndex = $rowIndex ?? -1;
    $wireKey = $wireKey ?? null;
@endphp
<div
    class="row-drop-zone h-0 mb-2 rounded-full opacity-0 border-dotted transition-all duration-150 ease-out motion-reduce:transition-none motion-reduce:duration-0"
    :class="isDragging && !isSelfGroupDrag($el) ? 'bg-slate-100 border border-dotted border-slate-400 h-3 opacity-100' : ''"
    data-row-index="{{ $rowIndex }}"
    @if($wireKey !== null)
        wire:key="{{ $wireKey }}"
    @endif
    @dragover.prevent="if (isSelfGroupDrag($el)) { $el.classList.remove('bg-slate-100', 'bg-blue-100', 'border-slate-400', 'border-blue-500', 'h-3', 'opacity-100'); $el.classList.add('h-0', 'opacity-0'); return; } $el.classList.remove('bg-slate-100', 'border-slate-400'); $el.classList.add('bg-blue-100', 'border-blue-500')"
    @dragleave.prevent="if (isSelfGroupDrag($el)) { $el.classList.remove('bg-slate-100', 'bg-blue-100', 'border-slate-400', 'border-blue-500', 'h-3', 'opacity-100'); $el.classList.add('h-0', 'opacity-0'); return; } $el.classList.remove('bg-blue-100', 'border-blue-500'); $el.classList.add('bg-slate-100', 'border-slate-400')"
    @dragend.prevent="$el.classList.remove('bg-slate-100', 'bg-blue-100', 'border-slate-400', 'border-blue-500', 'h-3', 'opacity-100'); $el.classList.add('h-0', 'opacity-0');"
    @drop.prevent="if (isSelfGroupDrag($el)) { return; } isDragging = false; handleDrop($event, $el); $el.classList.remove('bg-slate-100', 'bg-blue-100', 'border-slate-400', 'border-blue-500', 'h-3', 'opacity-100'); $el.classList.add('h-0', 'opacity-0');"
></div>
