<a href="https://mirrorandmountain.com" target="_blank"><img src="https://github.com/mirror-and-mountain/assets/raw/main/logos/png/mm-logo-slate-transparent-512.png" width="100" alt="MIRROR AND MOUNTAIN Logo"></a>

## MEROS FRAMEWORK
> **NOTE:** The Meros framework is intended for use with a Meros starter theme for Wordpress. To get started with a theme, go to the [Meros Blocks repository.](https://github.com/mirror-and-mountain/meros-blocks)

### About
This framework underpins our Meros starter themes for Wordpress. Under the hood it's an extension of the incredible [Acorn](https://roots.io/acorn/) package from the team at [Roots.io](https://roots.io). In turn, Acorn brings the power of [Laravel](https://laravel.com) to Wordpress. Our goal with this framework is to provide a developer-friendly environment for creating Wordpress themes that benefit from a standardised approach for adding features through a Laravel-esque syntax and overall experience.

### Form template locks
Template fields, repeaters and groups can independently lock their properties, movement and removal. Template-wide settings override those locks for template components; components added to a form remain independently editable. A group cannot be moved or removed if doing so would move or remove a locked child. Existing `__is_locked_by_template` field data remains compatible with the renamed `__properties_locked_by_template` flag.
Individual lock controls are hidden when their corresponding global permission is disabled, without changing their saved values.
In template field edit dialogs, the lock controls are stacked below the field settings and conditions.

### Form builder accessibility
Use each component's Move button to choose a new position without dragging. Repeater rows also provide stacked Move up and Move down buttons beside the drag handle. Dialogs contain keyboard focus, support Escape to cancel when available, and restore focus on closing; dynamic changes announce their result and keep focus on a useful control.
Modal headers, Close buttons and action buttons remain visible while the body scrolls. Closing is disabled alongside cancellation while an operation is in progress.

### Form pagination
Enable **Display as Pages** in form, form-host or template settings, or call `$form->displayAsPages()`.
Each top-level group becomes one page; consecutive ungrouped fields share a page. Nested groups and repeaters stay together.
Previous/Next controls and a progress indicator guide navigation. Next validates the current page; submission validates all visible fields and reveals any earlier invalid page. Pages containing only hidden fields are skipped, and all pages' values are submitted together.
Templates provide the default pagination setting. Enable **Lock Pagination Setting** (`lock_display_as_pages`) to enforce the template's value, including when pagination is disabled.
Without JavaScript, all pages remain visible with the normal submit button. Conditional hiding also removes required constraints, restoring the appropriate original or conditional requirement when fields are shown again.

### License 
The Meros Framework is open-sourced software licensed under the MIT license.
