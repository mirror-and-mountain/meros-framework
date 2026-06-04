<div 
    x-show="settingsPage === 'general'" 
    class="h-full gap-4 p-4 overflow-y-auto overscroll-contain w-1/3 min-w-0" 
    wire:key="form-builder-settings-general"
>
    <h2 class="text-lg font-bold">General Settings</h2>
    
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
        <label id="form-description" class="form-label">Form Description</label>
        <small class="whitespace-normal">The form's description</small>
        <div
            class="meros-rich-textarea meros-form-description"
            aria-labelledby="form-description"
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

    @include('meros::toolbox.forms.builder.canvas.action-button', ['reverse' => true])
</div>