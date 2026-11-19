# Forms

Meros uses the same PHP form components for developer-defined forms, WordPress settings controls, and forms edited through the visual builder. The builder stores a definition; PHP reconstructs that definition into components; Blade renders the form; Alpine supplies the interactive behaviour.

This guide describes the current framework implementation. Examples assume a bootstrapped Meros/Acorn WordPress application with the relevant component registrations and assets available.

## Contents

- [Choosing a form workflow](#choosing-a-form-workflow)
- [Creating a form in PHP](#creating-a-form-in-php)
- [Rows, groups and fields](#rows-groups-and-fields)
- [Defaults and dynamic values](#defaults-and-dynamic-values)
- [Conditions](#conditions)
- [Repeaters](#repeaters)
- [WordPress form hosts and templates](#wordpress-form-hosts-and-templates)
- [Using the visual builder](#using-the-visual-builder)
- [Settings and template locks](#settings-and-template-locks)
- [Pagination](#pagination)
- [Submissions and responses](#submissions-and-responses)
- [Client-side API](#client-side-api)
- [Rich text, assets and extension points](#rich-text-assets-and-extension-points)
- [Implementation map](#implementation-map)

## Choosing a form workflow

| Workflow | Definition | Submission |
| --- | --- | --- |
| Developer-defined form | Use `$this->forms()->make(...)` inside your package provider. | Use `onSubmit()` to dispatch a browser event, then handle the data yourself. |
| Stored WordPress form | Use the built-in Form post type and visual builder. | The framework reconstructs the form, stores a response and runs its configured actions. |
| Form attached to another post type | Implement `FormHost` and use `UsesForms`. | Uses the same stored-form submission pipeline. |
| Settings fields | Use fields and groups through the settings/data-container APIs. | Their containing WordPress/settings save handler persists the values; this is separate from front-end form submission. |

A standalone PHP form does not gain a custom server handler merely by having an ID. The built-in AJAX submission endpoint expects the ID of a WordPress post whose registered post-type controller implements `FormHost`.

## Creating a form in PHP

Create developer-defined forms inside a method on your [package provider](../src/app/Package.php). The [ProvidesComponents trait](../src/contracts/Providers/Concerns/ProvidesComponents.php) supplies `forms()`, which gives you the forms register for that provider.

```php
use MM\Meros\Contracts\Features\Components\Form;

$form = $this->forms()->make(function (Form $form) {
    $form->id('contact-form');
    $form->name('contact');
    $form->title('Contact us');
    $form->description('<p>Send us a message.</p>');
    $form->submitText('Send message');
    $form->successMessage('<p>Thank you. We will be in touch.</p>');
    $form->invalidText('Please complete the required fields.');
    $form->onSubmit('contact');

    $form->field('email', [
        'name' => 'email',
        'label' => 'Email address',
        'required' => true,
    ], false);

    $form->field('textarea', [
        'name' => 'message',
        'label' => 'Message',
        'required' => true,
    ], false);
});
```

Render the resulting component in Blade:

```blade
{!! $form->html() !!}
```

`html()` returns the rendered HTML; `render()` echoes it. Keep IDs unique on a page and field names stable: names identify submission values, condition sources and template fields.

Common form methods:

| Method | Purpose |
| --- | --- |
| `id()`, `name()`, `title()`, `description()` | Set identity and introductory content. |
| `submitText()`, `successMessage()`, `invalidText()` | Set button and feedback text. |
| `hideTitle()`, `hideDescription()`, `hideSubmitButton()` | Hide the corresponding UI; pass `false` to restore it. |
| `displayAsPages()` | Enable group-based pagination; pass `false` to disable it. |
| `resolveDynamicValues(false)` | Preserve literal dynamic-default tokens when rendering an editor. |
| `attribute($key, $value)` | Add an HTML attribute. Boolean attributes are included only when true. |
| `getFields(true)` | Retrieve fields as a collection, including fields in form groups. |
| `getPages()` | Retrieve computed page definitions when pagination is enabled. |
| `toArray()` / `toStorageArray()` | Obtain render-oriented / persistable component data. |

Use the component serializers instead of persisting render data yourself. Runtime values such as AJAX URLs/nonces and computed pages are excluded from storage.

## Rows, groups and fields

### Layout

Forms contain `FieldRow` objects. A row has three layout positions; field types determine how many they occupy. `field($type, $propertiesOrClosure, $autoRow = true)` fills the current row when it has capacity, otherwise it starts another row.

At viewport widths of 782px or less, fields within each row stack at full width in their original order, including rows inside groups and paginated forms. Stacked fields use the same vertical spacing whether they share a row or are in consecutive rows.

Pass `false` as the third argument to place a field on a fresh row. Use `row()` for explicit arrangements and `section()` for a field group:

```php
use MM\Meros\Contracts\Features\Components\FieldGroup;
use MM\Meros\Contracts\Features\Components\FieldRow;

$form->row(function (FieldRow $row) {
    $row->field('text', ['name' => 'first_name', 'label' => 'First name']);
    $row->field('text', ['name' => 'last_name', 'label' => 'Last name']);
});

$form->section(function (FieldGroup $group) {
    $group->name('details');
    $group->title('Details');
    $group->description('<p>A little more information.</p>');
    $group->field('textarea', ['name' => 'notes', 'label' => 'Notes']);
});
```

Groups organise layout, descriptions, visibility and pagination. They do not automatically nest submission keys: the example's field remains `notes`, not `details[notes]`.

`Form::field()` returns the form for chaining. `FieldGroup::field()` normally returns the group, but accepts a fourth `$returnField` argument. `Repeater::field()` returns the created field. To configure a field fluently without depending on those return differences, use its configuration closure.

### Field types and properties

The built-in registration includes:

- Basic inputs: `text`, `email`, `password`, `tel`, `url`, `number`, `date`, `time`, `color`, `hidden`.
- Larger content: `textarea`, `richtext`.
- Choices: `checkbox`, `checkboxes`, `radio`, `select`.
- Lookups: `posts_lookup`, `users_lookup`; the base `lookup` type is also registered but hidden from the visual builder.
- Repeating data: `repeater`.

The builder shows registered types whose `useInFormBuilder()` returns true. Its palette can therefore differ from the full registered set.

Common field properties include `name`, `label`, `description`, `default`, `required`, `readonly`, `disabled`, `placeholder`, `attributes` and `classes`. Support for individual properties depends on the type. The persisted default property is `defaultValue`; construction also accepts `default`.

```php
use MM\Meros\App\Components\Fields\Select;

$form->field('select', function (Select $field) {
    $field->name('interests');
    $field->label('Interests');
    $field->multiple();
    $field->options([
        'design' => 'Design',
        'development' => 'Development',
    ]);
    $field->default(['development']);
    $field->searchable();
});
```

Choice options accept value-to-label maps or records containing `value` and `label`. Use stable string values. Single-choice defaults use a scalar; multiple-choice defaults use an array of values. Searchable/multiple selects are enhanced by Tom Select.

### Field validation

**Next** validates the current page; **Submit** validates all pages. Conditionally hidden or disabled fields do not block either action. Required radio groups need one selection. Required checkbox groups, multi-selects and repeaters need at least one selection or row, unless a higher minimum is configured.

In the builder, set **Minimum** and **Maximum** selections for checkbox groups and multi-selects, rows for repeaters, or values for number fields. Leave a bound blank for no explicit limit. Counts must be non-negative whole numbers. At capacity, a repeater disables **Add Row** and re-enables it when a row is removed. PHP field definitions can also specify `min` and `max`, for example `['name' => 'quantity', 'min' => 1, 'max' => 10]`. These validation rules run in the interactive form; they do not add server-side submission validation.

## Defaults and dynamic values

For literal defaults, use `default($value)` or the `default`/`defaultValue` property. A repeater default is a list of row objects, each keyed by its column names.

Defaults can also use a complete `{{token}}` value:

```php
$form->field('email', function ($field) {
    $field->name('email');
    $field->label('Email address');
    $field->default('{{user_email}}');
});
```

Built-in tokens:

| Group | Tokens |
| --- | --- |
| Current user | `user_id`, `user_display_name`, `user_email`, `user_first_name`, `user_last_name`, `user_nickname` |
| Current WordPress post | `post_id`, `post_title`, `post_slug` |

Tokens resolve from the render-time WordPress context, not necessarily the post that stores the form. This is whole-value resolution, not general interpolation: do not assume `Hello {{user_first_name}}` will be substituted.

In the visual builder, input-field editors use the field's own input type for a literal default. Turn on **Use Dynamic Default Value** to enter a complete token instead. Type `{{` to see matching dynamic-value suggestions; use the arrow keys and Enter, or click a suggestion. Selecting an option closes the list. Use its **Close** button, or Escape while suggestions are open, to dismiss suggestions without closing the field editor modal; editing the text brings suggestions back. Suggestions expose names and declared types, never current values. Only types accepted by the field are offered: number fields accept `integer` and `number`, whereas text/email fields accept `string`. Unknown or incompatible manually entered tokens prevent saving. The selected token is stored as the field's `defaultValue` and resolved when the form renders. Success-message suggestions are not type-restricted.

Extend the grouped values with the `meros_dynamic_form_values` WordPress filter:

```php
add_filter('meros_dynamic_form_values', function (array $values): array {
    $values['site_values'] = [
        'site_name' => [
            'value' => get_bloginfo('name'),
            'type' => 'string',
        ],
        'item_count' => [
            'value' => 12,
            'type' => 'integer',
        ],
    ];
    return $values;
});
```

The new token is `{{site_name}}`. Keep token names unique across groups. Builder editing disables dynamic resolution where it needs to preserve the literal configuration.

Every item returned by this filter must now contain both `value` and `type`; migrate older filters that supplied raw values. Supported types are `string`, `integer`, `number` (integer or float), `boolean`, `array`, `array.scalar`, `array.object` (arrays of row arrays), and `null`. Values must match their declared type; malformed definitions throw `UnexpectedValueException`, rather than coercing text to numbers. A `null` value is allowed with any declared type when its context is unavailable (for example, `post_id` outside a post).

`FormDynamicValues::get()` returns these grouped definitions. `resolve('user_id')` still returns the underlying value, not its definition; parsing and flow actions retain that behavior. `choices()` returns only `{name, type}` records for editor metadata. Built-in IDs declare `integer`; other built-in user/post tokens declare `string`. Field compatibility uses the field's primary and additional data types, not HTML-format validation: an email default must still contain an email address when the form is submitted.

## Conditions

### PHP conditions

Conditions refer to field names. A comparison rule is `[sourceName, operator, comparisonValue]`; logic is `AND` by default, or `OR`.

```php
$form->field('checkbox', ['name' => 'has_company', 'label' => 'Registering a company']);

$form->field('text', function ($field) {
    $field->name('company_name');
    $field->label('Company name');
    $field->required();
    $field->showWhen([['has_company', '=', true]]);
});
```

| API | Behaviour |
| --- | --- |
| `showWhen()` / `hideWhen()` | Show or hide a field. Also available on groups. |
| `requireWhen()` / `makeOptionalWhen()` | Change whether a field is required. |
| `disableWhen()` / `enableWhen()` | Change whether a field is disabled. |
| `setValueWhen()` | Set a value or copy it from a source. |
| `setOptionsWhen()` | Replace options on types using the choice-options API. |

PHP comparison operators are `=`, `!=`, `LIKE`, `CONTAINS`, `NOT CONTAINS`, `EMPTY`, `NOT EMPTY`, `<`, `>`, `=>` and `=<`. Supply a third rule element, even `null`, for emptiness checks. Use `=>` and `=<` for greater/less than or equal in PHP rule definitions.

Value/option setters can take a fourth destination value:

```php
$field->setValueWhen([['status', '=', 'approved', 'Ready']]);
$select->setOptionsWhen([
    ['category', '=', 'business', [
        ['value' => 'consulting', 'label' => 'Consulting'],
        ['value' => 'support', 'label' => 'Support'],
    ]],
]);
```

For `setValueWhen()`, omitting the fourth element copies the source value when the comparison matches. `['source', 'CHANGED']` copies only when the source changes, not on initialisation. Destination strings support `{value}` and `{unique_id}`, plus `{row_index}` inside repeaters.

### Visibility and required state

Condition-hidden fields stop being required; revealing them reinstates their original required state or the applicable require/optional rule. Hiding a group similarly suppresses requirements for its descendants.

Hiding a field or group does not clear its values. Hidden fields do not block browser validation. Repeater child processors intentionally do not apply show/hide rules within rows.

### Signed-in conditions

Show/hide rules can use the reserved source `__user_signed_in`:

```php
$field->showWhen([['__user_signed_in', '=', true]]);
```

PHP resolves sign-in status at render time. `mforms_user_signed_in` still filters that flag, and the form component exposes it as `userSignedIn`. In the builder this source is labelled **User signed in**, with **Signed in** and **Signed out** values; it is available even before any fields.

All these conditions control presentation. Validate submitted data and enforce permissions on the server; neither hidden fields nor a client-side sign-in flag provides authorization.

### Registering context sources

Use the `meros_form_condition_sources` WordPress filter to add a source or change an existing source's labels. Register the filter on both admin and front-end requests so rules remain available when editing, saving and rendering.

```php
add_filter('meros_form_condition_sources', function (array $sources): array {
    $sources['__site_mode'] = [
        'label' => 'Site mode',
        'values' => ['standard' => 'Standard', 'preview' => 'Preview'],
        'resolve' => static fn (): string => get_option('example_preview_mode')
            ? 'preview'
            : 'standard',
    ];

    return $sources;
});
```

Use a unique `__`-prefixed lowercase key. Sources are available for show/hide rules with `=` and `!=`. The server validates stored rules against the registered values, so keep source and value keys stable when renaming labels. The resolver returns a configured key (a boolean maps to `'true'` or `'false'`), or `null` when unavailable. An unavailable source does not match either operator.

The Livewire builder receives labels and choices, not resolver callbacks. Resolved values are visible in front-end markup, so return only public, non-sensitive state and keep resolvers inexpensive. Do not use these rules for authorization.

## Repeaters

Repeaters define columns and collect an ordered list of rows:

```php
use MM\Meros\App\Components\Fields\Repeater;

$form->field('repeater', function (Repeater $repeater) {
    $repeater->name('attendees');
    $repeater->label('Attendees');
    $repeater->field('text', ['name' => 'name', 'label' => 'Name']);
    $repeater->field('email', ['name' => 'email', 'label' => 'Email']);
    $repeater->default([['name' => '', 'email' => '']]);
    $repeater->maxRows(10);
    $repeater->addRowText('Add attendee');
});
```

Controls include `allowAdd()`, `allowRemove()`, `allowReorder()`, `maxRows()` (`0` means no limit), `emptyText()`, and button-text setters. Each row supports drag reordering and stacked Move up/Move down buttons beside the drag handle. Wide tables can scroll horizontally.

For more complex row editing, `editForm($callback, $buttonText, $formTitle, $resetField)` defines a modal form. The callback receives the form and row data; use the existing row-edit mechanism rather than treating that modal as a separate stored form.

On the client, the repeater's `getValue()` resolves its row data. Template rows are excluded from the submitted payload.

### Repeaters in dynamic block inspectors

Use an `array` attribute with `control => true` and a `control_callback`, or set
`control => ['type' => 'repeater']` explicitly:

```php
use MM\Meros\App\Components\Fields\Repeater;
use MM\Meros\Contracts\Features\Components\Field;

$block->attribute('menu_links', [
    'type' => 'array',
    'default' => [],
    'control' => true,
    'control_callback' => function (Repeater $repeater): void {
        $repeater->field('text', function (Field $field): void {
            $field->name('label')->label('Label');
        });
        $repeater->field('url', function (Field $field): void {
            $field->name('url')->label('URL');
        });
    },
]);
```

The inspector button opens a WordPress modal populated from the current attribute.
In `field()`, the first argument is the registered Meros field type (such as
`text` or `url`); the field's `name` is its key in each saved row.
**Apply** stores the rows in their displayed order as an array of objects keyed by
field name. **Cancel** or closing the modal leaves the attribute unchanged. Save
the post to persist the block changes.

Fields can be edited directly in the table or through a repeater's `editForm()`.
The callback must return the `Form` after adding its fields:

```php
use MM\Meros\Contracts\Features\Components\Form;

$repeater->editForm(function (Form $form): Form {
    return $form->field('posts_lookup', [
        'name' => 'linked_post',
        'label' => 'Linked post',
    ]);
});
```

For a `posts_lookup` whose saved value should be a permalink instead of an ID,
call `$field->key('url')`. Using `$field->postType('any')` searches Posts, Pages,
and the custom post types listed in the form builder, subject to the configured
post status and any additional filters.

**Edit row** opens the form in the same modal. **Save row** updates the draft
repeater, and **Cancel row edit** returns to the table without changing the row.
Only **Apply** writes the draft to the block attribute. Form-only values are
stored under the row's `form_data` key and restored when the modal is reopened.

The control callback runs on the server for rendering and lookup searches,
including lookups inside row edit forms. The editor asset group loads the standard Meros field runtime
and its dependencies, including Tom Select and Quill. Register additional
field scripts with an editor-area Asset or attach their registered handles
with `$block->editorScript()`. Load styles for the modal in the admin screen
since the inspector is outside the block canvas.

## WordPress form hosts and templates

The built-in [Form post type](../src/app/Content/Form.php) enables the visual builder, actions editor and settings. To attach the same facilities to your own post type, implement [FormHost](../src/contracts/Features/Content/Interfaces/FormHost.php) and use [UsesForms](../src/contracts/Features/Content/Concerns/UsesForms.php).

Inside that post-type class's `configure()` method:

```php
$this->hasEditableForm()
    ->withActionsEditor()
    ->withFormSettings();
```

These are trait configuration helpers used from the owning class, not public methods to call on an arbitrary controller instance.

Template choices:

- `withTemplate($name, $componentTemplate, $actions = [], $settings = [])` associates a developer-defined `Form` or configuration closure.
- `withConfigurableTemplate($name, $componentTemplate = null, $actions = [], $settings = [])` associates a stored Form Template. A component definition can seed a missing template; creation takes place in the relevant admin editing context.
- `hasForm()` is the alternative to `hasEditableForm()` when attaching a component-template form without enabling a per-post builder.

For example, within a host's `configure()`:

```php
$this->hasEditableForm()
    ->withConfigurableTemplate('registration', function (
        \MM\Meros\Contracts\Features\Components\Form $form
    ) {
        $form->title('Registration');
        $form->field('email', [
            'name' => 'email',
            'label' => 'Email address',
            'required' => true,
        ]);
    })
    ->withActionsEditor()
    ->withFormSettings();
```

### Reconstructing and rendering a stored form

Resolve the post and its registered host, checking that both exist:

```php
use MM\Meros\App\Models\Post;
use MM\Meros\Contracts\Features\Content\Interfaces\FormHost;
use MM\Meros\Facades\Content\PostTypes;

$post = Post::find($postId);
if ($post === null) {
    throw new \RuntimeException('Form post not found.');
}

$host = PostTypes::get($post->post_type);
if (!($host instanceof FormHost)) {
    throw new \RuntimeException('The post type does not host a form.');
}

$form = $host->getFormInstance($post);
echo $form->html();
```

Use `getFormInstance()` rather than manually rendering stored rows: reconstruction applies available field types, template components/locks, condition filtering, pagination and submission text settings. The built-in Form post type also renders its stored structure through its content callback.

The host trait includes a form-block helper with title/description visibility controls and an editor placeholder; it must be wired into the application's block registration before relying on it. Its front-end rendering also uses `getFormInstance()`.

### Reading definitions and responses

```php
use MM\Meros\Facades\Components\Forms;

$structure = Forms::find($postId)->structure();
$settings = Forms::find($postId)->settings();
$actions = Forms::find($postId)->actions();
$responses = Forms::find($postId)->responses();
```

Use an integer post ID for stored forms. A string passed to `find()` instead identifies a registered form component. Each terminal lookup clears the register's current selection, so repeat `find()` for each operation. `responses(true)` returns a collection rather than an array.

The stored structure lives under the `_meros_form_structure` post-meta key and uses `rows`, with each row holding `fields` or a `childGroup`; group definitions hold their own `rows`. Fields contain a registered `type`, `name` and serializable properties. Prefer the builder/component APIs over directly editing this schema.

## Using the visual builder

The admin builder is rendered by Livewire and manipulated by the Alpine `mformsBuilder` component. It edits configuration, not end-user response data.

1. **Add field**, **Add repeater field**, or **Add Group** to build the structure. Template permissions may hide these controls.
2. Edit a field to set its name, label, rich-text description, required state, metadata and type-specific settings. For choice fields, configure option values and labels.
3. Configure defaults using the controls appropriate to the field type. Choice defaults use option selection; repeater defaults open a row editor based on the configured columns. Add columns before editing repeater defaults.
4. Configure show/hide conditions on fields and groups. Eligible choice fields also expose **Set options**.
5. Drag components, or use their **Move** button to choose a destination. Rows and fields are reindexed while preserving their logical configuration.
6. Save/update the WordPress post to persist the definition. Saving an editor dialog alone does not save the whole post.

When a template is available, **Reset to template** replaces the builder's current structure after confirmation. Save the post to persist that reset. This is distinct from resetting input values after a successful front-end submission.

### Builder JavaScript integration

For custom admin integrations, get the initialised builder with `Alpine.$data(root)`, where `root` is its `.meros-form-builder[x-data="mformsBuilder"]` element. Existing operations include:

| Method | Target |
| --- | --- |
| `addField(root)` | Add a normal field to a builder container. |
| `addField(root, 'repeater')` | Add a repeater card. |
| `addGroup(root)` | Add a group to the top-level builder. |
| `removeField(card)` / `removeGroup(card)` | Remove a component, subject to locks. |
| `configureMove(card)` | Open the keyboard-accessible move dialog. |
| `configureConditions(card)` | Open field/group condition configuration. |
| `configureRepeaterDefaults(card)` | Open a repeater's default-row editor. |
| `resetToTemplate(root)` | Confirm and restore the template structure. |

These are implementation-level methods tied to the rendered builder markup, not an independent JSON editing API. Reuse the existing templates and operations instead of manually changing input names or row indices. Changes still need the containing WordPress post to be saved.

### Builder conditions

- Ordinary sources must precede the target in form order. Reordering/removing fields can invalidate references; server-side reconstruction/save filtering removes invalid rules.
- **All rules match** corresponds to `AND`; **Any rule matches** corresponds to `OR`.
- Available operators and value controls depend on the source type: checkboxes use checked/unchecked values, dates/times/numbers support ordered comparisons, choices offer their configured options, and repeaters support emptiness checks.
- **User signed in** is an additional show/hide source, not a set-options source.
- The visual editor exposes a subset of the PHP condition API. Do not expect PHP-only require/optional, enable/disable or set-value rules to have corresponding visual controls.

### Accessibility

Move controls provide a keyboard alternative to dragging. Dialogs contain focus, support Escape cancellation when enabled, and restore focus when closed. Dynamic changes announce results and retain useful focus.

Modal headers, Close controls and Save/Cancel actions stay visible while the body scrolls. Close/cancel is disabled during operations that disable cancellation. The builder also has a full-screen toggle.

## Settings and template locks

Form and host settings include **Display as Pages**, **Submit Button Text**, a rich-text **Success Message**, and two confirmation options. Host settings additionally expose form title/description and associated template information. Open/close date/time controls are also defined; their presence alone should not be treated as server-side submission enforcement.

The three settings panels share control definitions in [FormSettingsControls](../src/support/FormSettingsControls.php), but keep distinct field groups and storage keys. The template panel defines defaults and permissions; the host panel uses those values to set its defaults and render locked fields as read-only or disabled.

| Setting | PHP/rendering effect | Template control |
| --- | --- | --- |
| `display_as_pages` | Calls `displayAsPages()`. | `lock_display_as_pages` enforces the template value, whether true or false. |
| `submit_button_text` | Calls `submitText()`. | `allow_edit_submit_button_text = false` enforces template text. |
| `success_message` | Calls `successMessage()`. | `allow_edit_success_message = false` enforces the template message. |
| `show_success_on_new_page` | **Show Success Message on a New Page** calls `showSuccessOnNewPage()`. Success replaces the form on the same URL. | `lock_show_success_on_new_page` enforces the template value. |
| `show_submit_another_response` | **Show Button to Submit Another Response** calls `showSubmitAnotherResponse()`. The button is only shown on the confirmation page. | `lock_show_submit_another_response` enforces the template value independently. |
| `form_title`, `form_description` | Used by the host block rendering path. | `allow_edit_form_title`, `allow_edit_form_description` control editing. |

For pagination, submission text, and confirmation options, an unlocked local value takes precedence over the template value; absent values fall back to the template, then component defaults. Both confirmation options default to off. Direct component rendering does not automatically apply all block-specific title/description overrides.

### Component locks

Template fields, repeaters and groups have independent locks:

| Stored flag | Effect | Template-wide permission |
| --- | --- | --- |
| `__properties_locked_by_template` | Locks component properties. | `allow_edit_field_properties` |
| `__move_locked_by_template` | Prevents movement. | `allow_move_components` |
| `__remove_locked_by_template` | Prevents removal. | `allow_remove_components` |

Disabling a template-wide permission enforces its corresponding lock for template components. Components added locally remain independently editable. A group cannot be moved/removed if that would move/remove a locked child.

Individual lock controls are hidden when the corresponding global permission is disabled, preserving their saved values. In template field dialogs, these controls sit below field settings and conditions. Legacy `__is_locked_by_template` property locks remain supported.

Other template settings include `allow_add_components` and `allow_actions_overrides`. PHP fields can use `lock()` to set the property lock; movement/removal remain separate flags.

## Pagination

Enable **Display as Pages** in settings or call `$form->displayAsPages()`.

Pages follow top-level structure:

- Each group occupies one page.
- Each consecutive run of ungrouped fields/rows occupies one page.
- Nested content and repeaters stay with their containing page.
- Empty rows do not create pages.

For example, three ungrouped fields, one group, then two more ungrouped fields produce **three pages**, not six.

The browser displays progress and Previous/Next controls. Next validates the current page before advancing; Previous preserves values. Final submission checks all conditionally visible fields and reveals an earlier page if it contains an invalid control. Pages with no conditionally visible non-hidden fields are skipped and progress updates accordingly.

Inactive pages retain enabled controls and values so their data remains part of submission. On success, the form returns to its first available page. Pagination and the enhanced submission flow require the JavaScript assets; do not assume a native no-JavaScript POST fallback.

## Submissions and responses

While a built-in AJAX submission is pending, the form has `aria-busy="true"` and the `working` class. An overlay and an inert form body prevent editing, Submit/Previous/Next are disabled, and the submit button displays a spinner (static for reduced-motion preferences). The form unlocks after either success or failure; repeated submissions and page navigation are ignored while pending. Override `--mforms-working-overlay-color` to customise the overlay. Event-based `onSubmit()` callbacks remain synchronous and do not track asynchronous work started by listeners.

### Browser-event submissions

`onSubmit('contact')` dispatches `mforms::contact` instead of sending the framework's AJAX request:

```js
window.addEventListener('mforms::contact', event => {
    const { formId, formName, formData } = event.detail;
    // Send or otherwise handle this plain object in your application.
});
```

`formData` is a processed plain object, not a browser `FormData` instance. Rich text is read from its component, enhanced selects use their component values, single checkboxes are normalised, and repeaters resolve to row arrays.

Dispatching the event is treated as success immediately. The framework does not await asynchronous work inside your event listener: it displays the configured success message and resets the inputs. If success must depend on a server response, use the stored-form AJAX workflow or implement that lifecycle yourself.

### Stored-form AJAX submissions

Without a string `onSubmit` callback, the front end posts to the configured AJAX URL:

- `action`: `meros_submit_form`
- `nonce`: the generated action nonce
- `id`: the stored form/host post ID
- `form_name`: the component name
- `form_data`: JSON-encoded processed field values

The endpoint resolves the post's `FormHost` controller and calls `handleSubmission($id, $data)`. It reconstructs the component, builds a submission payload, stores a `FormResponse` with status `processing`, runs `FormActionsRunner`, then updates the response with completed actions and a success/error status.

The action payload contains:

```text
form_id
form_title
submission_time
user_id
data[field_name]
    field_name
    user_value
    user_value_data_type
    submission_state
    field_type, field_data_type, field_metadata
    field_group, group_index, row_index, field_index
```

Field-definition metadata is added when the submitted key matches a known field. Building this payload is not itself a complete server-side validation or authorization layer. Validate accepted values and permissions in your application/action pipeline.

Each submitted field records `submission_state` as `hidden` if a visibility marker was present on that field or an ancestor, or `shown` otherwise. Hidden fields retain their submitted values, including defaults and values entered before they became hidden. Fields on inactive pagination steps are not hidden for this purpose. Submissions without visibility metadata default to `shown`. This state is reported by the browser and must not be used for authorization or server-side validation.

Controls that are independently disabled remain excluded from submission. For a custom `FormHost` that implements submission handling without `UsesForms`, accept the optional third `array $hiddenFields = []` argument and pass it to `buildSubmissionPayload($data, $hiddenFields)` to record the same visibility states.

Actions are configured through the host's actions editor. Available actions depend on registered flow actions. At runtime, action inputs can resolve current user/post tokens and custom groups from `meros_dynamic_form_values`, as well as submitted fields and earlier action outputs (`output_name_source_action_id`). `FormActionsRunner` adds `form_id` and `form_response_id` through that same filter for its own lookups.

The existing user ID, user display name and form identifiers take precedence over submitted fields of the same name. Other submitted field names take precedence over shared context tokens, so adding a token does not change an existing field reference.

#### Adding dynamic value choices to the action editor

Use `meros_flow_controls_dynamic_options` to add, relabel, or remove dynamic value choices in the action editor. The filter receives the built-in choices merged with any additional options supplied to [FlowControls](../src/support/FlowControls.php). Each entry has a `name` (the token key without braces), a `label`, and a `data_type` that must match the action input's type.

For example, `user_first_name` is already available at runtime but is not listed by default. To expose it in the editor, register this filter in your plugin or theme:

```php
add_filter('meros_flow_controls_dynamic_options', function (array $options): array {
    $options['user_first_name'] = [
        'name'      => 'user_first_name',
        'label'     => 'User: First Name',
        'data_type' => 'string',
    ];

    return $options;
});
```

**User: First Name** now appears in the **Value** dropdown when **Use Dynamic Value** is enabled for a compatible string input. This filter only changes the editor's choices. If you add a custom token that isn't already resolved at runtime, supply its value separately through `meros_dynamic_form_values` using the same token key.

### Feedback and reset

An explicit action success message takes priority over the configured form success message. Successful messages are passed through `wp_kses_post()` and rendered as HTML; errors remain plain text. An action flow may intentionally return an error-as-success outcome, which the browser also treats as success.

For stored forms submitted over AJAX, type a placeholder into the **Success Message** editor using the field's name, not its label. For example, `Thanks, {{first_name}}!` uses the submitted `first_name` value. Type `{{` in the rich-text editor to see suggestions for current form fields and shared dynamic values. Placeholders are resolved in message text, not in links' URLs or other HTML attributes. Values are escaped as text, scalar lists are joined with commas, and missing values render as empty text. Password fields are not exposed. Built-in user and post tokens keep precedence over identically named fields. Action-provided messages and client-only `onSubmit()` callbacks do not use this interpolation.

After an ordinary successful submission:

- Native fields and enhanced components return to their configured initial defaults.
- Repeaters restore their initial rows, removing added rows and reinstating removed rows.
- Conditions and required states are reevaluated.
- Pagination returns to the first available page.
- Success feedback remains visible inline by default. If **Show Success Message on a New Page** is enabled, the form is replaced by a confirmation without reloading or changing the URL. If **Show Button to Submit Another Response** is also enabled, the button returns to the reset form without submitting again.

Values remain untouched while an AJAX request is pending or when it fails. Resetting restores render-time defaults; it does not fetch fresh dynamic token values from the server.

## Client-side API

Once Alpine has initialised the form, obtain its component with `Alpine.$data(element)`:

```js
const element = document.getElementById('contact-form');
const component = Alpine.$data(element);
await component.submitForm();
```

| Method/state | Purpose |
| --- | --- |
| `submitForm()` | Validate and submit, then update feedback and reset on success. AJAX returns a promise; event submissions complete synchronously. |
| `submitForm(true)` | Return the submission outcome without applying completion feedback or resetting. Still validates and dispatches/sends the submission, and clears prior feedback at the start. |
| `resetForm()` | Restore native/enhanced defaults and first-page state without submitting or clearing feedback. |
| `nextPage()` / `previousPage()` | Navigate using page-aware validation. |
| `pageNumber`, `pageCount`, `pageTitle`, `pageStatus` | Read progress state. |
| `hasNextPage`, `hasPreviousPage` | Read navigation availability. |
| `feedbackMessage`, `feedbackType` | Read submission feedback. |
| `userSignedIn` | Read the initial form-level sign-in flag used by visibility rules. |

To inspect an outcome:

```js
const outcome = await component.submitForm(true);
if (!outcome.success) {
    console.error(outcome.error);
}
```

This is not a dry run: a successful call can already have stored a response or dispatched a callback event. Do not immediately submit again merely to display feedback. `success`, `error`, optional `message`, and optional `invalid` describe the outcome; the AJAX wire format wraps its result in WordPress's `success`/`data` envelope.

## Rich text, assets and extension points

### Rich text

Quill edits a `div`, while a named hidden input mirrors its HTML for native form/settings submission. `mformsRichText.getValue()` reads editor HTML; `setValue()` updates both editor and input. The processor uses the component API rather than treating the editor's visible text as its value.

Rich-text string data items are marked with schema format `html`, allowing the data sanitizer to preserve WordPress-permitted markup with `wp_kses_post()`. Ordinary text settings keep their plain-text sanitization. Use real registered rich-text fields/data items rather than assuming any arbitrary string containing HTML gets this treatment.

Empty form descriptions, including Quill's `<p><br></p>` placeholder, omit the description wrapper. Nonempty descriptions use a block wrapper so paragraph markup is not nested inside another paragraph.

### Assets and styling

The framework's [MForms asset group](../src/app/Assets/MForms.php) supplies form JavaScript and styles; its [dependency group](../src/app/Assets/MFormsDeps.php) supplies Nice Forms, Tom Select and Quill assets. The components register on `alpine:init`; load the form bundle before that event in a custom asset setup.

Build changes with:

```sh
npm run build:assets
```

Global form styling is mapped into `--mforms-*` custom properties by the PHP component, with form styles in [style.scss](../src/resources/assets/wordpress/src/mforms/style.scss).

When editing Alpine expressions in Blade attributes, encode comparison characters such as `>` as `&gt;`. WordPress content typography can otherwise corrupt attribute quotes. Keep this in mind especially for forms rendered through post content.

### Adding a field type

Extend an existing field or the base `Field`, and register the class with the fields register under an alias in your provider/component registration code:

```php
use MM\Meros\Facades\Components\Fields;

Fields::checkout($provider)->register(\App\Components\Fields\CustomField::class, 'custom_field');
```

Define the field's type, data type, view and supported features using the existing field classes as examples. `$showInFormBuilder` controls palette visibility; `addBuilderControls()` adds type-specific editor settings. Persist reconstructible configuration through the component's serialization properties, not runtime editor objects or nonces.

For an enhanced browser field, follow the existing `getValue()`/`setValue()` component contract and keep the native named input in sync. Check [fields-processor.js](../src/resources/assets/wordpress/src/mforms/fields-processor.js) and [form-processor.js](../src/resources/assets/wordpress/src/mforms/form-processor.js) for how components, wrappers, conditions and submission values are resolved.

## Implementation map

| Area | Entry points |
| --- | --- |
| PHP form API | [Form](../src/contracts/Features/Components/Form.php), [FieldGroup](../src/contracts/Features/Components/FieldGroup.php), [FieldRow](../src/contracts/Features/Components/FieldRow.php), [Field](../src/contracts/Features/Components/Field.php) |
| Form register and lookup | [Forms register](../src/registers/Components/Forms.php) |
| Stored form/host integration | [UsesForms](../src/contracts/Features/Content/Concerns/UsesForms.php), [FormHost](../src/contracts/Features/Content/Interfaces/FormHost.php) |
| Builder registration, saving and reconstruction | [FormBuilder support class](../src/support/FormBuilder.php) |
| Admin rendering | [Livewire FormBuilder](../src/app/Livewire/FormBuilder.php), [builder Blade](../src/resources/views/forms/builder.blade.php) |
| Browser builder | [builder.js](../src/resources/assets/wordpress/src/mforms/builder.js), [builder-conditions.js](../src/resources/assets/wordpress/src/mforms/builder-conditions.js) |
| Front-end lifecycle | [form.js](../src/resources/assets/wordpress/src/mforms/form.js), [form Blade](../src/resources/views/forms/form.blade.php) |
| Dynamic defaults | [FormDynamicValues](../src/support/FormDynamicValues.php) |
| Settings | [Form settings](../src/support/FormSettingsControls.php), [host settings](../src/support/FormHostSettingsControls.php), [template settings](../src/support/FormTemplateSettingsControls.php) |
| Template locks | [FormTemplateComponentLocks](../src/support/FormTemplateComponentLocks.php) |
| AJAX and action execution | [components orchestrator](../src/app/Components/Orchestrator.php), [FormActionsRunner](../src/support/FormActionsRunner.php) |
