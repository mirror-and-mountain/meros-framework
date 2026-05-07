@php
    $package   = $_GET['provider'] ?? null;
    $operation = $_GET['operation'] ?? null;
    $packageInstance = $package
        ? \MM\Meros\Facades\Packages::get($package)
        : null;

    $operationMessage = $operation && $packageInstance
        ? match ($operation) {
            'installed'   => $packageInstance->getName() . ' has been successfully installed!',
            'updated'     => $packageInstance->getName() . ' has been successfully updated!',
            'rolled-back' => $packageInstance->getName() . ' has been successfully rolled back to the previous version!',
            'uninstalled' => $packageInstance->getName() . ' has been successfully uninstalled.',
            default => null,
        }
        : null;
@endphp
@if($package && $packageInstance)
    @if($operationMessage !== null)
        @include('meros::admin.templates.meros-install-operations-banner', ['operationMessage' => $operationMessage])
        @include('meros::admin.templates.tabbed-page')
    @else
        @php
            $backUrl = admin_url('options-general.php?page=' . $pageSlug . '&tab=' . ($_GET['origin'] ?? 'packages'));
            $hasSettings = $packageInstance->hasSettings();
        @endphp
        <div class="wrap">
            <a 
                href="{{ $backUrl }}" 
                @if(!$hasSettings)
                    style="display:none;"
                @endif
                >&larr; Back
            </a>
            <h1>{{ $packageInstance->getName() }} Settings</h1>
            <p>{{ $packageInstance->getDescription() }}</p>
            @if($hasSettings)
                <form method="post" action="options.php">
                    @php
                        settings_fields( $package . '_settings_container' );
                        do_settings_sections( $pageSlug . '-' . $package);
                        submit_button();
                    @endphp
                </form>
            @else
                <p>No settings available for this package. It may need to be enabled first.</p>
                <a class="button" href="{{ $backUrl }}">&larr; Back</a>
            @endif
        </div>
    @endif
@else
    <div class="wrap">
        <h1>Meros Features</h1>
        <p>Manage features registered by your Meros-powered theme and its packages.</p>
        @include('meros::admin.templates.tabbed-page')
    </div>
@endif
