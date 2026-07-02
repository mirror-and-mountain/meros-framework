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
            value="{{ $formTitle }}"
            wire:change="updateSetting('title', $event.target.value)"
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
            value="{{ $formSlug }}"
            wire:change="updateSetting('slug', $event.target.value)"
        />
    </div>

    {{-- Form Description --}}
    <fieldset class="nice-form-group meros-field" wire:ignore>
        <legend id="form-description-label" class="form-label">Form Description</legend>
        <small class="whitespace-normal">The form's description</small>
        <div
            x-data="merosRichTextField('form-description', {}, (event) => $wire.updateSetting('description', event.target.innerHTML))"
            id="form-description"
            name="form-description"
            class="nice-form-control"
            aria-labelledby="form-description-label"
        >
            {!! $formDescription !!}
        </div>
    </fieldset>

    {{-- Form Status --}}
    <div class="nice-form-group mb-8">
        <label for="form-status">Form Status</label>
        <small class="whitespace-normal">The form's status</small>
        <select 
            id="form-status" 
            class="nice-form-control"
            value="{{ $formStatus }}"
            wire:change="updateSetting('status', $event.target.value)"
        >
            <option @selected($formStatus === 'draft') value="draft">Draft</option>
            <option @selected($formStatus === 'publish') value="publish">Published</option>
            <option @selected($formStatus === 'pending') value="pending">Pending Review</option>
        </select>
    </div>

    @include('meros::toolbox.forms.builder.canvas.action-button', ['reverse' => true])
</div>