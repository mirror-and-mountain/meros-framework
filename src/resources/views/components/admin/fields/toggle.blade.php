<button {{ $attributes->merge(['class' => 'meros-toggle-switch meros-settings-field']) }}>
    <span class="meros-toggle-track">
        <span class="meros-toggle-thumb"></span>
    </span>
    <span class="meros-toggle-label">{{ $label }}</span>
</button>