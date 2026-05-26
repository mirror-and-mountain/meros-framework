<header class="flex justify-between bg-[#1d2327] text-white px-4 rounded-t sticky top-0 z-50 h-12">
    <div class="flex items-center">
        @include('meros::toolbox.partials.wordpress')
        <h1 class="text-xl font-bold">Toolbox | Form Builder</h1>
    </div>
    <div class="flex h-full">
        <div class="flex h-full">
            @foreach($navItems as $item)
                <div 
                    class="flex items-center justify-center px-6 hover:bg-gray-300 hover:text-black cursor-pointer h-full" 
                    :class="{ 'bg-gray-300 text-black': currentTab === '{{ strtolower($item) }}' }" 
                    @click="currentTab = '{{ strtolower($item) }}'"
                >
                        <span>{{ $item }}</span>
                </div>
            @endforeach
        </div>
    </div>
</header>