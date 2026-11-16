<div class="mforms-row" data-row-type="fields">
    <div data-form-field-component data-repeater-component class="mforms-builder-field-card">
        @include('meros::forms.builder-move-control', ['moveGroup' => false])
        <button
            type="button"
            class="mforms-builder-remove"
            aria-label="Remove field"
            title="Remove field"
            @click.stop.prevent="removeField($el.closest('[data-form-field-component]'))"
        >×</button>
        <div class="mforms-builder-field-content">
            {!! $this->getNewRepeaterComponent($componentName) !!}
        </div>
    </div>
</div>
