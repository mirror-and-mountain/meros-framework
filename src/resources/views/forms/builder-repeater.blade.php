<div class="mforms-builder-repeater-editor">
    <input type="hidden" name="{{ $name }}[field_type]" value="repeater">
    <input type="hidden" name="{{ $name }}[__is_form_template]" value="{{ $isFormTemplate ? '1' : '0' }}">
    <input type="hidden" name="{{ $name }}[__is_template_field]" value="{{ $isTemplateField ? '1' : '0' }}">
    <input type="hidden" name="{{ $name }}[__form_data]" value="{{ json_encode($field, JSON_THROW_ON_ERROR) }}">
    <input type="hidden" data-builder-conditions name="{{ $name }}[__builder_conditions]" value="{{ json_encode($field['conditions'] ?? [], JSON_THROW_ON_ERROR) }}">
    <input type="hidden" data-builder-repeater-defaults name="{{ $name }}[__builder_repeater_defaults]" value="{{ json_encode($field['defaultValue'] ?? [], JSON_THROW_ON_ERROR) }}">

    @foreach($locks as $flag => $value)
        <input type="hidden" name="{{ $name }}[{{ $flag }}]" value="{{ $isFormTemplate ? '0' : ($value ? '1' : '0') }}">
    @endforeach
    @if(!$isFormTemplate && $locked)
        <p>This field's properties are locked by the form's template.</p>
    @endif

    <div @if($locked && !$isFormTemplate) inert aria-disabled="true" @endif>
        @include($nameControl['wrapper'], $nameControl)
        @include($labelControl['wrapper'], $labelControl)
        <div class="mforms-builder-component-actions">
            <button type="button" class="button" @click.stop.prevent="configureConditions($el.closest('[data-form-field-component]'))">Configure conditions</button>
            <button type="button" class="button" @click.stop.prevent="configureRepeaterDefaults($el.closest('[data-form-field-component]'))">Set default value</button>
        </div>
        @if($isFormTemplate)
            <div class="mforms-builder-lock-controls">
                @foreach($locks as $flag => $value)
                    @include($lockControls[$flag]['wrapper'], $lockControls[$flag])
                @endforeach
            </div>
        @endif
        <div class="mforms-group-description-control">
            {!! $descriptionHtml !!}
        </div>
        {!! $columnsHtml !!}
    </div>
</div>
