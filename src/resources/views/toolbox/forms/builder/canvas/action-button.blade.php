<div class="flex items-center gap-2">
    @if(!($reverse ?? false))
        <div wire:loading>
            @include('meros::toolbox.svgs.spinner')
        </div>
    @endif
    <div class="flex items-center gap-2" @if($reverse ?? false) style="flex-direction: row-reverse;" @endif>
        <div id="flash-container">
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
        </div>
        <button 
            id="meros-form-builder-save-button"
            class="cursor-pointer py-2 px-4 bg-blue-600 text-white rounded hover:bg-blue-700 active:bg-blue-800 font-medium text-sm transition-colors" 
            type="button" 
            @click.prevent="{{ $action ?? '$wire.saveForm()' }}" 
        >
            {{ $buttonText ?? 'Save Form' }}
        </button>
    </div>
    @if($reverse ?? false)
        <div wire:loading>
            @include('meros::toolbox.svgs.spinner')
        </div>
    @endif
</div>