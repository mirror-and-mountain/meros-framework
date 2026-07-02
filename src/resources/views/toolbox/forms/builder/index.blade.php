{{-- div root element --}}
<div class="flex h-dvh flex-col overflow-hidden">
    @include('meros::toolbox.forms.builder.header', [
        'returnUrl'  => $returnUrl, 
        'navItems'   => $navItems
    ])
    <main class="flex-1 min-h-0" wire:key="form-builder-main-content">
        @if($screen === 'settings-main')
            @include('meros::toolbox.forms.builder.settings.index', [
                'formID'          => $formID,
                'formTitle'       => $formTitle,
                'formDescription' => $formDescription,
                'formSlug'        => $formSlug
            ])
        @else
            @include('meros::toolbox.forms.builder.canvas.index')
        @endif
    </main>
</div>