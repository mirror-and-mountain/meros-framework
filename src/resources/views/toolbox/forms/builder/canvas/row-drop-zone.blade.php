{{-- Shared row drop zone for canvas/group rows --}}
@php
    $isGroupRow = $isGroupRow ?? false;
    $isRepeaterField = $isRepeaterField ?? false;

    $dropHandler = '';

    if ($isRepeaterField) {
        $dropHandler = 'handleRepeaterFieldGapDrop';
    } elseif ($isGroupRow) {
        $dropHandler = 'handleGroupRowGapDrop';
    } else {
        $dropHandler = 'handleCanvasRowGapDrop';
    }

    $dropArgs = [];

    if ($isRepeaterField) {
        $dropArgs = [($newPosition ?? -1)];
    } elseif ($isGroupRow) {
        $dropArgs = [($groupRowIndex ?? -1), ($groupRowInnerIndex ?? -1)];
    } else {
        $dropArgs = [($rowIndex + 1), $rowIndex];
    }

@endphp

<div
    class="h-2 mb-1 rounded-sm transition-all duration-150"
    @dragover.prevent="$store.formBuilder.handleCanvasRowGapDragOver($event, $el)"
    @dragleave="$store.formBuilder.hideRowGap($el)"
    @drop.prevent="$store.formBuilder.{{ $dropHandler }}($event, $el, {{ $dropArgs[0] }}, {{ $dropArgs[1] ?? 'null' }})"
></div>