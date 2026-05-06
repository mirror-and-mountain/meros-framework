@php
    $provider  = $_GET['provider'] ?? null;
    $operation = $_GET['operation'] ?? null;
    $providerInstance = $provider && $provider !== 'theme' ? \MM\Meros\Facades\Packages::get($provider) : null;
@endphp
@if($provider && $providerInstance)
    @if($operation === 'installed')
        <div class="notice notice-success is-dismissible">
            <p>{{ $providerInstance->getName() }} has been successfully installed!</p>
        </div>
        @include('meros::admin.templates.tabbed-page')
    @elseif($operation === 'updated')
        <div class="notice notice-success is-dismissible">
            <p>{{ $providerInstance->getName() }} has been successfully updated!</p>
        </div>
        @include('meros::admin.templates.tabbed-page')
    @elseif($operation === 'uninstalled')
        <div class="notice notice-success is-dismissible">
            <p>{{ $providerInstance->getName() }} has been successfully uninstalled.</p>
        </div>
        @include('meros::admin.templates.tabbed-page')
    @else
        <div class="wrap">
            <h1>{{ $providerInstance->getName() }} Settings</h1>
            <form method="post" action="options.php">
                @php
                    settings_fields( $provider . '_settings_group' );
                    do_settings_sections( $pageSlug . '-' . $provider );
                    submit_button();
                @endphp
            </form>
        </div>
    @endif
@else
    @include('meros::admin.templates.tabbed-page')
@endif
