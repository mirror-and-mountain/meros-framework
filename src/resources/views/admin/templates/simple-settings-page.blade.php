<div class="wrap">
    <h1>{{ $title }}</h1>
    <form method="post" action="options.php">
        @php
            settings_fields($pageSlug);
            do_settings_sections($pageSlug);
            submit_button();
        @endphp
    </form>
</div>