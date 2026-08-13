<div class="mforms-field-group">
    @foreach($rows as $row)
        @include('meros::forms.field-row', $row['properties'] ?? [])
    @endforeach
</div>