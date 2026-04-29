<div class="meros-field-group" style="margin-right:1rem;">
    <div class="meros-field-group-header">
        <h3>{{ $label }}</h3>
        @if($description)
            <p class="meros-field-group-description">{{ $description }}</p>
        @endif
    </div>
    <div class="meros-field-group-body meros-field-group-grid">
        @foreach($fields as $item)
            @php
                $field = $item['field'] ?? null;
            @endphp
            @if (is_null($field))
                @continue
            @endif
            <div class="meros-field-group-field" style="grid-column: span {{ $item['span'] }}">
                {!! $field->render() !!}
            </div>
        @endforeach
    </div>
</div>