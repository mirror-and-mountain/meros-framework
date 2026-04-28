<div class="provider-settings-section" id="{{ $id }}">
    <h3 class="provider-settings-section-title" style="margin-bottom: -8px;">Provided by {{ $author }}</h3>
    <p style="margin-top: -8px;">
        @if ($authorUri !== '')
            <a href="{{ $authorUri }}" target="_blank" class="provider-settings-section-link">Website</a>
        @endif
        @if ($authorSupportUri !== '')
            <a href="{{ $authorSupportUri }}" target="_blank" class="provider-settings-section-link">Support</a>
        @endif
    </p>
</div>
