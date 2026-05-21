<div>
    <div id="meros-form-builder" class="flex h-screen" x-show="currentTab === 'canvas'">
        @include('meros::toolbox.form-builder.canvas.index')
    </div>
    <div id="meros-form-preview" class="flex" x-show="currentTab === 'preview'">
        @include('meros::toolbox.form-builder.preview.index')
    </div>
</div>