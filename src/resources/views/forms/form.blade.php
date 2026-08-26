@php
    $hideSubmitButton = $hideSubmitButton ?? false;
    $submitButtonClass = 'mforms-submit-button button button-primary';
    if ($hideSubmitButton) {
        $submitButtonClass .= ' mforms-submit-button--hidden';
    }
@endphp

<form
    x-data="mform"
    id="{{ $id }}" 
    class="mforms-form"
    data-name="{{ $name }}"
    data-ajax-url="{{ $ajaxUrl }}"
    data-ajax-nonce="{{ $ajaxNonce }}"
    data-invalid-text="{{ $invalidText }}"
    {!! $attributeString !!}
    {{ is_string($onSubmit) ? "data-onsubmit={$onSubmit}" : '' }}
    @submit.prevent="submitForm()"
>
    @if($title !== '')
        <h2 class="mforms-form-title">{{ $title }}</h2>
    @endif
    @if($description !== '')
        <p class="mforms-form-description">{{ $description }}</p>
    @endif
    <div class="mforms-body">
        @foreach($rows as $row)
            @include('meros::forms.field-row', $row)
        @endforeach
    </div>
    <div class="mforms-footer">
        <button 
            type="submit" 
            class="{{ $submitButtonClass }}"
        >
            {{ $submitText }}
        </button>
    </div>
</form>