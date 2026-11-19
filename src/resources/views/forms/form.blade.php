@php
    $hideSubmitButton = $hideSubmitButton ?? false;
    $hideTitle = $hideTitle ?? false;
    $hideDescription = $hideDescription ?? false;
    $hasDescription = preg_match('/\A(?:\s|&nbsp;|<p>|<\/p>|<br\s*\/?>)*\z/i', $description ?? '') !== 1;
    $displayAsPages = $displayAsPages ?? false;
    $showSuccessOnNewPage = $showSuccessOnNewPage ?? false;
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
    @if($showSuccessOnNewPage) data-success-on-new-page tabindex="-1" @endif
    data-paginated="{{ $displayAsPages ? '1' : '0' }}"
    data-condition-values="{{ json_encode((object) ($conditionValues ?? []), JSON_THROW_ON_ERROR) }}"
    @if(($conditionValues['__user_signed_in'] ?? null) === 'true') data-user-signed-in @endif
    {!! $attributeString !!}
    {{ is_string($onSubmit) ? "data-onsubmit={$onSubmit}" : '' }}
    @submit.prevent="hasNextPage ? nextPage() : submitForm()"
    style="{{ $style ?? '' }}"
>
    @if(!$hideTitle && $title !== '')
        <h2 class="mforms-form-title" @if($showSuccessOnNewPage) x-show="!showingConfirmation" @endif>{{ $title }}</h2>
    @endif
    @if(!$hideDescription && $hasDescription)
        <div class="mforms-form-description"@if($showSuccessOnNewPage) x-show="!showingConfirmation"@endif>{!! $description !!}</div>
    @endif
    @if($displayAsPages)
        <div class="mforms-progress" x-cloak x-show="{{ $showSuccessOnNewPage ? '!showingConfirmation && ' : '' }}pageCount &gt; 1">
            <p role="status" aria-live="polite" aria-atomic="true" x-text="pageStatus"></p>
            <progress aria-label="Form progress" max="1" value="0" :max="Math.max(pageCount, 1)" :value="pageNumber"></progress>
            <div class="mforms-progress-track" aria-hidden="true">
                <span class="mforms-progress-fill" :style="{ width: `${pageNumber / Math.max(pageCount, 1) * 100}%` }"></span>
            </div>
        </div>
    @endif
    <div class="mforms-body" :inert="isSubmitting" @if($showSuccessOnNewPage) x-show="!showingConfirmation" @endif>
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
    <div class="mforms-footer" @if($showSuccessOnNewPage) x-show="!showingConfirmation" @endif>
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
            @if(!$showSuccessOnNewPage)
                <div x-show="feedbackType === 'success'" x-html="feedbackType === 'success' ? feedbackMessage : ''"></div>
            @endif
            <div x-show="feedbackType !== 'success'" x-text="feedbackMessage"></div>
        </div>
    </div>
    @if($showSuccessOnNewPage)
        <template x-if="showingConfirmation">
            <section class="mforms-confirmation" tabindex="-1" aria-label="Submission confirmation">
                <div class="mforms-feedback mforms-feedback--success" x-html="feedbackType === 'success' ? feedbackMessage : ''"></div>
                @if($showSubmitAnotherResponse ?? false)
                    <button type="button" class="mforms-button" @click="startAnotherResponse()">Submit another response</button>
                @endif
            </section>
        </template>
    @endif
</form>