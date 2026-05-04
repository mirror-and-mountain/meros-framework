@if($tabs === [])
    <div class="wrap">
        <h1>{{ $title }}</h1>
        <p>{{ __('No Settings Available.', 'meros') }}</p>
    </div>
@else
    @php
        $activeTab = $_GET['tab'] ?? array_key_first($tabs);
    @endphp
    @if(!array_key_exists($activeTab, $tabs))
        @php
            $activeTab = array_key_first($tabs);
        @endphp
    @endif
    <div class="wrap">
        <form method="post" action="options.php">
            <nav class="nav-tab-wrapper">
                @foreach($tabs as $slug => $tab)
                    <a href="?page={{ $pageSlug }}&tab={{ $slug }}" class="nav-tab @if($activeTab === $slug) nav-tab-active @endif">
                        {{ $tab['label'] }}
                    </a>
                @endforeach
            </nav>
            <div class="tab-content" style="margin-top: 20px;">
                @php
                    call_user_func($tabs[$activeTab]['callback']);
                @endphp
            </div>
        </form>
    </div>
@endif