<div class="space-y-4">

    {{-- Group Title --}}

    <div class="nice-form-group">
        <label for="group-title" class="form-label">Title</label>
        <small class="whitespace-normal">The group's title</small>
        <input
            id="group-title"
            type="text"
            required
            :value="activeField?.title ?? ''"
            @change="$store.formBuilder.updateActiveGroupProperty('title', $event.target.value)"
        />
    </div>

    {{-- Group Description --}}

    <div class="nice-form-group meros-rich-textarea-wrapper" wire:ignore>
        <label for="group-description" class="form-label">Description</label>
        <small class="whitespace-normal">The group's description</small>
        <div
            id="group-description-editor"
            class="meros-rich-textarea meros-form-group-description"
        ></div>
    </div>
</div>