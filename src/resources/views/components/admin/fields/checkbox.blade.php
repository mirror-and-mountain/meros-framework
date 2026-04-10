<input type="hidden" name="{{ $name }}" value="0">

<input
    type="checkbox"
    id="{{ $id }}"
    name="{{ $name }}"
    value="1"
    class="meros-settings-field"
    @checked($isChecked())
>