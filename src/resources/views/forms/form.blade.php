@php
    $hideSubmitButton = $hideSubmitButton ?? false;
    $hideTitle = $hideTitle ?? false;
    $hideDescription = $hideDescription ?? false;
    $hasDescription = preg_match('/\A(?:\s|&nbsp;|<p>|<\/p>|<br\s*\/?>)*\z/i', $description ?? '') !== 1;
    $displayAsPages = $displayAsPages ?? false;
    $successMessage = wp_kses_post($successMessage ?? 'Form Submitted Successfully.');
    $submitButtonClass = 'mforms-button mforms-submit-button';
    if ($hideSubmitButton) {
        $submitButtonClass .= ' mforms-submit-button--hidden';
    }
@endphp

<form
    x-data="mform"
    id="{{ $id }}" 
    class="mforms-form"
    :class="{ working: isSubmitting }"
    :aria-busy="isSubmitting"
    data-name="{{ $name }}"
    data-ajax-url="{{ $ajaxUrl }}"
    data-ajax-nonce="{{ $ajaxNonce }}"
    data-invalid-text="{{ $invalidText }}"
    data-success-message="{{ $successMessage }}"
    data-paginated="{{ $displayAsPages ? '1' : '0' }}"
    {!! $attributeString !!}
    {{ is_string($onSubmit) ? "data-onsubmit={$onSubmit}" : '' }}
    @submit.prevent="hasNextPage ? nextPage() : submitForm()"
    style="{{ $style ?? '' }}"
>
    @if(!$hideTitle && $title !== '')
        <h2 class="mforms-form-title">{{ $title }}</h2>
    @endif
    @if(!$hideDescription && $hasDescription)
        <div class="mforms-form-description">{!! $description !!}</div>
    @endif
    @if($displayAsPages)
        <div class="mforms-progress" x-cloak x-show="pageCount &gt; 1">
            <p role="status" aria-live="polite" aria-atomic="true" x-text="pageStatus"></p>
            <progress aria-label="Form progress" max="1" value="0" :max="Math.max(pageCount, 1)" :value="pageNumber"></progress>
        </div>
    @endif
    <div class="mforms-body" :inert="isSubmitting">
        @if($displayAsPages)
            @foreach($pages as $page)
                <section
                    class="mforms-page"
                    data-form-page
                    x-cloak
                    x-show="activePage === $el"
                    x-transition:enter.opacity.duration.200ms
                    data-page-title="{{ $page['title'] }}"
                    aria-label="{{ $page['title'] }}"
                    tabindex="-1"
                >
                    @foreach($page['rows'] as $row)
                        @include('meros::forms.field-row', $row)
                    @endforeach
                </section>
            @endforeach
        @else
            @foreach($rows as $row)
                @include('meros::forms.field-row', $row)
            @endforeach
        @endif
    </div>
    <div class="mforms-footer">
        @if($displayAsPages)
            <div class="mforms-page-navigation" x-cloak x-show="pageCount &gt; 1">
                <button type="button" class="mforms-button" x-show="hasPreviousPage" :disabled="isSubmitting" @click="previousPage()">Previous</button>
                <button type="button" class="mforms-button" x-show="hasNextPage" :disabled="isSubmitting" @click="nextPage()">Next</button>
            </div>
        @endif
        <button 
            type="submit" 
            class="{{ $submitButtonClass }}"
            :class="{ working: isSubmitting }"
            :disabled="isSubmitting"
            :aria-busy="isSubmitting"
            @if($displayAsPages) x-show="!hasNextPage" @endif
        >
            {{ $submitText }}
        </button>
        <div
            class="mforms-feedback"
            x-cloak
            x-show="feedbackMessage"
            :class="`mforms-feedback--${feedbackType}`"
            :role="feedbackType === 'error' ? 'alert' : 'status'"
            aria-live="polite"
        >
            <div x-show="feedbackType === 'success'" x-html="feedbackType === 'success' ? feedbackMessage : ''"></div>
            <div x-show="feedbackType !== 'success'" x-text="feedbackMessage"></div>
        </div>
    </div>
</form>