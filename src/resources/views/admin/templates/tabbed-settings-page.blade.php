@if($tabs === [])
    <div class="wrap">
        <h1>{{ $title }}</h1>
        <p>{{ __('No Settings Available.', 'meros') }}</p>
    </div>
@else
    @php
        $activeTab = $_GET['tab'] ?? array_key_first($tabs);
        $provider  = $_GET['provider'] ?? '';
    @endphp
    @if($provider === '')
        <div class="wrap">
            <h1>{{ $tabs[$activeTab] }} Settings</h1>
            <form method="post" action="options.php">
                <nav class="nav-tab-wrapper">
                    @foreach($tabs as $slug => $label)
                        <a href="?page={{ $pageSlug }}&tab={{ $slug }}" class="nav-tab @if($activeTab === $slug) nav-tab-active @endif">
                            {{ $label }}
                        </a>
                    @endforeach
                </nav>
                <div class="tab-content">
                    @php
                        settings_fields($pageSlug . '_' . $activeTab);
                        do_settings_sections($pageSlug . '_' . $activeTab);
                        submit_button();
                    @endphp
                </div>
            </form>
        </div>
    @elseif ($pageSlug === 'meros-features' && $provider !== '')
        @include('meros::admin.templates.provider-settings-page', [
            'title'    => \Illuminate\Support\Str::title(str_replace(['-', '_'], ' ', $provider)) . ' Settings',
            'pageSlug' => $pageSlug,
            'provider' => $provider
        ])
    @endif
@endif