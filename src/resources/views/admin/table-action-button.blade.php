<a 
    href="#" 
    class="meros-admin-button meros-small-button meros-table-card-action-button button-primary" 
    title="{{ ($disabled ?? false) ? 'The package must be disabled to perform this action.' : $title }}"
    data-table="{{ $name }}" 
    data-action="{{ $action }}" 
    data-nonce="{{ $nonce }}"
    data-provider="{{ $provider }}"
    {{ ($disabled ?? false) ? 'disabled' : '' }}
>   
    {{ $label }}
</a>