{{-- Shared row drop zone for canvas/group rows --}}
@php
    $isGroupRow = $isGroupRow ?? false;

    $dropHandler = $isGroupRow
        ? 'handleGroupRowGapDrop'
        : 'handleCanvasRowGapDrop';

    $dropArgs = $isGroupRow
        ? [($groupRowIndex ?? -1), ($groupRowInnerIndex ?? -1)]
        : [($rowIndex + 1), $rowIndex];
@endphp

<div
    class="h-2 mb-1 rounded-sm transition-all duration-150"
    @dragover.prevent="$store.formBuilder.handleCanvasRowGapDragOver($event, $el)"
    @dragleave="$store.formBuilder.hideRowGap($el)"
    @drop.prevent="$store.formBuilder.{{ $dropHandler }}($event, $el, {{ $dropArgs[0] }}, {{ $dropArgs[1] }})"
></div>