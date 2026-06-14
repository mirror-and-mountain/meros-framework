{{-- div root element --}}
<div>
    @include('meros::toolbox.forms.builder.header', [
        'returnUrl'  => $returnUrl, 
        'navItems'   => $navItems
    ])
    <main wire:key="form-builder-main-content">
        @include('meros::toolbox.forms.builder.canvas.index')
        {{-- @if($screen === 'settings-main')
            @include('meros::toolbox.forms.builder.settings.index', [
                'formID'          => $formID,
                'formTitle'       => $formTitle,
                'formDescription' => $formDescription,
                'formSlug'        => $formSlug
            ])
        @else
            @include('meros::toolbox.forms.builder.canvas.main')
        @endif --}}
    </main>
</div>