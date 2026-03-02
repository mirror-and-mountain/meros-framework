<!DOCTYPE html>
<html {{ language_attributes() }} >
    <head>
        <meta charset="{{ bloginfo('charset') }}" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>{{ the_title() }}</title>
        <?php wp_head(); ?>
    </head>
    <body {{ body_class() }}>
    @php wp_body_open(); @endphp
        {{ $slot }}
        @php wp_footer(); @endphp
    </body>
</html>