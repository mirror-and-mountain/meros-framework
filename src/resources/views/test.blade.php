@php
    $field = (object) [
        'variation' => 'text',
        'name' => 'example_input',
        'id' => 'example_input',
        'value' => '',
        'placeholder' => 'Example input field',
    ];
@endphp

<p>Hello I am a blade view. It's working!</p>

<p>
	Example attribute value:
	{{ $data['exampleText'] ?? 'No exampleText attribute set yet.' }}
</p>

<x-admin.fields.input :field="$field" />