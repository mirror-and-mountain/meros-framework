<div class="flex h-screen w-full">
    @include('meros::toolbox.form-builder.preview.sidebar')
    <div class="w-full h-auto p-4 overflow-y-auto">
        <livewire:toolbox::site-form 
            :schema="$this->schema" 
            :rows="$rows" 
            :key="'site-form-preview-' . md5(json_encode($rows))" 
        />
    </div>
</div>