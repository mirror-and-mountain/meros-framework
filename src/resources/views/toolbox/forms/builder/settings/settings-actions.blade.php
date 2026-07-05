<div 
    x-show="settingsPage === 'actions'" 
    x-data="{ isEditor: false }"
    @mforms:field-updated.window="if (($event.detail?.id ?? '') === 'form-builder-actions') { $wire.set('schema.actions', $event.detail?.value ?? []); }"
    class="h-full gap-4 p-4 overflow-y-auto overscroll-contain min-w-0 w-3/4" 
    wire:key="form-builder-settings-actions"
>
    <h2 class="text-lg font-bold">Actions</h2>

    <div class="mb-4" wire:ignore>
        <p class="mb-2 text-sm text-gray-600">Configure the actions that should be taken when the form is submitted.</p>
        {!! $this->getActionsRepeaterField()->render() !!}
    </div>

    @include('meros::toolbox.forms.builder.canvas.action-button', [
        'reverse' => true,
    ])
</div>