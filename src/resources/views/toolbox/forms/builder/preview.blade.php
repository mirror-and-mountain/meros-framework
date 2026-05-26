<div class="flex justify-center" x-show="currentTab === 'preview'" x-transition.opacity>
    <div class="max-w-[60%] w-full p-4">
        @include('meros::toolbox.forms.builder.canvas.header', ['sectionTitle' => 'Form Preview'])
        <div class="border border-gray-300 rounded-lg bg-white shadow-sm p-6 mb-6">
            @include('meros::toolbox.forms.site-form.index', [
                'formTitle' => $formTitle,
                'formRows'  => $canvasRows
            ])
        </div>
    </div>
</div>