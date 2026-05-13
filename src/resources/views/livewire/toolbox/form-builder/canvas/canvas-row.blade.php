@php
    $rowFields = $canvasRow['fields'] ?? [];
    $fieldRowIndex = $canvasRow['rowIndex'];
@endphp

<div class="flex gap-3 mb-1" wire:key="row-{{ $rowIndex }}">
    @foreach($rowFields as $fieldIndex => $field)
        @include('meros::livewire.toolbox.form-builder.canvas.field', [
            'scope' => 'top',
            'rowFields' => $rowFields,
            'fieldRowIndex' => $fieldRowIndex,
            'fieldIndex' => $fieldIndex,
            'field' => $field,
        ])
    @endforeach
</div>
