<div class="flex items-center justify-between mb-4">
    @if($showHeader ?? true)
        <h2 
            class="text-lg font-bold" @if(empty($sectionTitle)) 
            x-text="settingsPage.charAt(0).toUpperCase() + settingsPage.slice(1) + ' Settings'" @endif
        >
            @if(!empty($sectionTitle))
                {!! $sectionTitle !!}
            @endif
        </h2>
    @endif
    @if($showSaveButton ?? true)
        <div class="flex items-center gap-2 @if(!$showHeader) flex-row-reverse @endif">
            <div wire:loading>
                @include('meros::toolbox.svgs.spinner')
            </div>
            @if (session('updateStatus'))
                <div 
                    class="text-sm text-green-600" 
                    x-data="{ show: true }" 
                    x-show="show" 
                    x-transition
                    x-init="setTimeout(() => show = false, 3000)"
                >
                    {{ session('updateStatus') }}
                </div>
            @endif
            <button 
                id="meros-form-builder-save-button"
                class="cursor-pointer py-2 px-4 bg-blue-600 text-white rounded hover:bg-blue-700 active:bg-blue-800 font-medium text-sm transition-colors" 
                type="button" 
                wire:click="saveForm" 
            >Save Form
            </button>
        </div>
    @endif
</div>