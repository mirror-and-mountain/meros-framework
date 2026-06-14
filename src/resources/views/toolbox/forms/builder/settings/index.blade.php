<div 
    class="flex h-screen overflow-hidden" 
    x-data="{ settingsPage: 'general' }"
    wire:key="form-builder-settings-main"
>
    @include('meros::toolbox.forms.builder.settings.sidebar')

    <div class="w-full h-full p-4 overflow-hidden min-w-0" wire:key="form-builder-settings-panel">
        @include('meros::toolbox.forms.builder.settings.settings-general')
        @include('meros::toolbox.forms.builder.settings.settings-actions')
    </div>
</div>