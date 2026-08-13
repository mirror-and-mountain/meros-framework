<div class="wrap">
    <h1>{{ $title }}</h1>
    @if(!empty($pageIntro))
        <p>{{ $pageIntro }}</p>
    @endif
    <form method="post" action="options.php">
        @php
            settings_fields($settingsGroup);
            do_settings_sections($settingsSection);
            submit_button();
        @endphp
    </form>
</div>