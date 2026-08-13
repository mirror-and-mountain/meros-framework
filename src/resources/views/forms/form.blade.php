<form id="{{ $id }}" class="mforms-form">
    @if($title !== '')
        <h2 class="mforms-form-title">{{ $title }}</h2>
    @endif
    @if($description !== '')
        <p class="mforms-form-description">{{ $description }}</p>
    @endif
    <div class="mforms-body">
        @foreach($rows as $row)
            @include('meros::forms.field-row', $row['properties'] ?? [])
        @endforeach
    </div>
</form>