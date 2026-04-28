<div class="wrap">
    <h1>{{ $title }}</h1>
    <form method="post" action="options.php">
        @php
            settings_fields('meros-features_meros');
            do_settings_sections('meros-features');
            submit_button();
        @endphp
    </form>
</div>
