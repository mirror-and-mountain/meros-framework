<div class="flex h-screen" x-show="currentTab === 'settings'" x-data="{ settingsPage: 'general' }" x-transition.opacity>
    @include('meros::toolbox.forms.builder.settings.sidebar')

    <div class="w-1/3 p-4 overflow-y-auto" wire:key="form-builder-settings-panel">
        @include('meros::toolbox.forms.builder.canvas.header', ['sectionTitle' => '', 'showSaveButton' => false])
        <div 
            x-show="settingsPage === 'general'" 
            class="flex flex-col gap-4 p-4 overflow-y-auto min-w-0" 
            wire:key="form-builder-settings-general"
        >
            {{-- Form Title --}}
            <div class="nice-form-group">
                <label for="form-title">Form Title</label>
                <small class="whitespace-normal">The form's title</small>
                <input 
                    id="form-title" 
                    type="text"
                    class="nice-form-control"
                    :value="$store.formBuilder.formTitle"
                    @change="$store.formBuilder.setFormTitle($event.target.value)"
                />
            </div>

            {{-- Form Slug --}}
            <div class="nice-form-group">
                <label for="form-slug">Form Slug</label>
                <small class="whitespace-normal">The form's slug</small>
                <input 
                    id="form-slug" 
                    type="text" 
                    class="nice-form-control"
                    :value="$store.formBuilder.formSlug"
                    @change="$store.formBuilder.setFormSlug($event.target.value)"
                />
            </div>

            {{-- Form Description --}}
            <div class="nice-form-group meros-rich-textarea-wrapper" wire:ignore>
                <label for="form-description" class="form-label">Form Description</label>
                <small class="whitespace-normal">The form's description</small>
                <div
                    id="form-description-editor"
                    class="meros-rich-textarea meros-form-description"
                ></div>
            </div>

            {{-- Form Status --}}
            <div class="nice-form-group mb-8">
                <label for="form-status">Form Status</label>
                <small class="whitespace-normal">The form's status</small>
                <select 
                    id="form-status" 
                    class="nice-form-control"
                    :value="$store.formBuilder.formStatus"
                    @change="$store.formBuilder.setFormStatus($event.target.value)"
                >
                    <option value="draft">Draft</option>
                    <option value="publish">Published</option>
                    <option value="pending">Pending Review</option>
                </select>
            </div>

            @include('meros::toolbox.forms.builder.canvas.header', ['sectionTitle' => '', 'showHeader' => false])
        </div>
    </div>
</div>