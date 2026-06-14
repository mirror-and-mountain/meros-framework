<header class="flex justify-between bg-[#1d2327] text-white px-4 rounded-t sticky top-0 z-50 h-12">
    <div class="flex items-center">
        @include('meros::toolbox.wordpress', ['returnUrl' => $returnUrl])
        <h1 class="text-xl font-bold">Toolbox | Form Builder</h1>
    </div>
    <div class="flex h-full">
        <div class="flex h-full">
            @foreach($navItems as $slug => $label)
                @if($slug !== 'preview')
                    <div 
                        class="flex items-center justify-center px-6 hover:bg-gray-300 hover:text-black cursor-pointer h-full" 
                        :class="{ 'bg-gray-300 text-black': $wire.screen === '{{ $slug }}' }" 
                        wire:click="setScreen('{{ $slug }}')"
                    >
                            <span>{{ $label }}</span>
                    </div>
                @else
                    <div 
                        class="flex items-center justify-center px-6 hover:bg-gray-300 hover:text-black cursor-pointer h-full"
                    >
                        <span class="flex items-center gap-1" @click="window.open('{{ $label }}', '_blank')">
                            Preview
                            @include('meros::toolbox.svgs.external-link')
                        </span>
                    </div>
                @endif
            @endforeach
        </div>
    </div>
</header>