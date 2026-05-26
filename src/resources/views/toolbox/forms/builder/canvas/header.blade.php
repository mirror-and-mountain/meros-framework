<div class="flex items-center justify-between mb-4">
    <h2 class="text-lg font-bold">{!! $sectionTitle !!}</h2>
    <div class="flex items-center gap-2">
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
</div>