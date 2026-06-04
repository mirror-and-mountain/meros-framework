<div class="flex h-screen overflow-hidden" x-show="currentTab === 'settings'" x-data="{ settingsPage: 'general' }" x-transition.opacity>
    @include('meros::toolbox.forms.builder.settings.sidebar')

    <div class="w-full h-full p-4 overflow-hidden min-w-0" wire:key="form-builder-settings-panel">
        @include('meros::toolbox.forms.builder.settings.settings-general')
        @include('meros::toolbox.forms.builder.settings.settings-actions')
    </div>
</div>