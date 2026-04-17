<div class="meros-field-group">
    <div class="meros-field-group-header">
        <h3>{{ $label }}</h3>
        @if($description)
            <p class="meros-field-group-description">{{ $description }}</p>
        @endif
    </div>
    <div class="meros-field-group-body meros-field-group-grid">
        @foreach($fields as $item)
            <div class="meros-field-group-field" style="grid-column: span {{ $item['span'] }}">
                {!! $item['field']->render() !!}
            </div>
        @endforeach
    </div>
</div>