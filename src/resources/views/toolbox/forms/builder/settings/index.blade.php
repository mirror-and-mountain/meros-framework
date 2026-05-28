<div class="flex h-screen" x-show="currentTab === 'settings'" x-data="{ settingsPage: 'general' }" x-transition.opacity>
    @include('meros::toolbox.forms.builder.settings.sidebar')

    <div class="w-1/3 p-4 overflow-y-auto" wire:key="form-builder-settings-panel">
        @include('meros::toolbox.forms.builder.canvas.header', ['sectionTitle' => '', 'showSaveButton' => false])
        @include('meros::toolbox.forms.builder.settings.settings-general')
        @include('meros::toolbox.forms.builder.settings.settings-actions')
    </div>
</div>