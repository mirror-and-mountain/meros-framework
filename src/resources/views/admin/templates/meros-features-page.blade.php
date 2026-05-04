@php
    $provider = $_GET['provider'] ?? null;
@endphp
@if($provider)
    <div class="wrap">
        <h1>{{ \Illuminate\Support\Str::title($provider) }} Settings</h1>
        <form method="post" action="options.php">
            @php
                settings_fields( $provider . '_settings_group' );
                do_settings_sections( $pageSlug . '-' . $provider );
                submit_button();
            @endphp
        </form>
    </div>
@else
    @include('meros::admin.templates.tabbed-page')
@endif
