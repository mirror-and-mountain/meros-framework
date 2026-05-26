<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Toolbox' }}</title>
    @viteAssets('framework')
    @livewireStyles
</head>
<body x-data="{ currentTab: '{{ strtolower($navItems[0]) }}' }">
    @include('meros::toolbox.partials.header')
    <main>
        {{ $slot }}
    </main>

    <footer>
        <!-- Footer content -->
    </footer>
    @livewireScripts
</body>
</html>
