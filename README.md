# Meros Framework

Theme/package providers, feature contracts, registers and WordPress-facing
services for Meros themes, powered by Meros Foundations.

## Documentation

- [Framework guides](https://github.com/mirror-and-mountain/meros-docs/blob/main/framework/README.md)
- [Getting started with a Meros theme](https://github.com/mirror-and-mountain/meros-docs/blob/main/theme/getting-started.md)
- [Shared testing for theme and package developers](https://github.com/mirror-and-mountain/meros-docs/blob/main/framework/testing/README.md)

Detailed documentation is maintained in [meros-docs](https://github.com/mirror-and-mountain/meros-docs).

## Forms on custom routes

Views rendered by non-WordPress routes can include the framework's built form
assets directly with `@merosFormStyles()` in the document head and
`@merosFormScripts()` before `@livewireScripts`. The script directive must run
before Alpine starts so the form components are registered. Both directives
include the dependencies from `resources/assets/wordpress/build/mforms` before
the main bundle, using the framework's public asset URLs without WordPress
enqueue hooks. External script dependencies declared in `index.asset.php` are
printed through WordPress's script registry, including their inline initialization
(for example, Quill's `window.lodash` dependency). Alpine is supplied by Livewire
(or by the consuming layout). Livewire `@script` blocks listening for form submit
events must contain a `<script>` element.
Asset URLs include a content-based version so rebuilt bundles bypass browser
caches, including when the Blade view is already compiled.

Forms with `onSubmit('eventName')` dispatch `mforms::eventName` with
`{ form, formId, formName, formData }`. `form` is the Alpine form instance.
Callbacks now own completion: dispatching the event does not automatically
display feedback or reset the inputs. Existing callbacks that want success
feedback must explicitly call `form.showSuccess(message)` (omit the message to
use the configured success message); `form.showError(message)` displays an error
without resetting inputs. Redirecting callbacks can leave feedback untouched.
Validation and forms using the default AJAX submission retain their existing
behavior. `submitForm(true)` still returns the validation/dispatch outcome for
repeater editors, not the outcome of asynchronous callback logic.
Livewire actions that return callback results without changing server-rendered
content should use `#[\Livewire\Attributes\Renderless]` so their response does not
replace the form before client-side feedback is applied. Attach component-local
listeners to `$wire.$el` rather than `document` to avoid stale navigation listeners.
For asynchronous callbacks, set `form.isSubmitting = true` before the request
and clear it in the promise's `finally` handler. This activates the existing
submit-button spinner, busy overlay, and duplicate-submission protection.

## Taxonomies

Extend `MM\Meros\Contracts\Features\Content\Taxonomy` and configure it like a
post type:

```php
class Genre extends \MM\Meros\Contracts\Features\Content\Taxonomy
{
    protected function configure(): void
    {
        $this->handle('genre');
        $this->label('Genre', 'Genres');
        $this->public()->hierarchical()->showInRest()->showAdminColumn();
    }
}
```

In a theme or package provider, `$this->taxonomies()->register(Genre::class)`
registers the feature class, and `$this->taxonomies()->makeFrom(Genre::class)`
instantiates it. Use `$this->taxonomies()->make(fn ($taxonomy) => ...)` to create
a taxonomy at runtime. The `MM\Meros\Facades\Content\Taxonomies` facade exposes
the same register; check it out with a provider before creating features.
Standalone taxonomies can associate post types using `postType('book')` or
`postTypes(['book', 'film'])`.

Inside a `PostType` feature's `configure()` method:

```php
$this->taxonomy(Genre::class);
$this->taxonomy(function (\MM\Meros\Contracts\Features\Content\Taxonomy $taxonomy) {
    $taxonomy->handle('reading-level')->label('Reading Level');
    $taxonomy->public()->showInRest();
});
```

`taxonomy()` returns the taxonomy feature and automatically associates the
current post type, including when its handle is inferred from its class name.
Class-based taxonomies are reused across post types. Associations are additive,
including for already-registered WordPress taxonomies. Use `core()` with an
existing handle such as `category` to associate without registering it anew.
Taxonomies register on WordPress `init` after post types. The existing
`PostType::taxonomies(array $handles)` API remains available for raw handles.
Use `args([...])` for additional WordPress taxonomy registration options.

### Taxonomy models

`App\Models\Taxonomy` represents a `term_taxonomy` record, not a taxonomy feature
definition. Its `term()` relationship exposes the associated `App\Models\Term`
record containing the term's name and slug. `Term::taxonomies()` returns its
taxonomy records.

`Post::taxonomies()` and `Taxonomy::posts()` use WordPress's `term_relationships`
table, including the pivot's `term_order`. Post subclasses inherit this relationship.

```php
$post = \MM\Meros\App\Models\Post::with('taxonomies.term')->findOrFail($postId);
$tags = $post->taxonomies()->where('taxonomy', 'form-tags')->with('term')->get();
```

Use WordPress taxonomy APIs for writes so term counts and caches stay in sync.

`PostType::inspect($postId)` includes a `taxonomies` key grouped by associated
taxonomy handle. Each assigned term is an array containing `term_id`, `name`,
`slug`, and `term_group`. Unassigned taxonomies have empty lists; calling
`inspect()` without a post ID returns empty lists for all associated taxonomies.
Associations include configured taxonomy features, raw handles and WordPress
registrations. Existing post attributes and metadata remain alongside this data.

### Built-in form taxonomies

The framework's built-in `Form` and `FormTemplate` post types share the
non-hierarchical `form-tags` taxonomy, configured by `App\Content\FormTags`.
It is available in the REST API and adds a tags column to their admin lists.
Both post types are grouped under the framework's top-level **Forms** menu,
alongside a single **Form Tags** link. Their native list and editor screens
remain available. `PostType::showInMenu()` accepts either a boolean or a parent
menu slug when grouping other post types.
Form Templates are non-public, with their WordPress admin UI explicitly enabled.
They are provisioned by code rather than created with WordPress's Add New action,
and their list titles show a **Provided by theme** label. Existing templates
remain editable.
Manual deletion and trashing are also denied through WordPress capabilities;
template provisioning through the `UsesForms` trait remains unchanged.

Forms and Form Templates use `read_forms` for list access and `edit_forms` for
editing. Form Readers can browse the lists, while Form Managers can edit both
types and create/delete regular forms. Templates cannot be created/deleted
manually by any role. Editors and administrators receive both capabilities by
default; grants remain configurable through Security settings.
The shared Form Tags screen is readable with `read_forms`; changing or assigning
tags requires `edit_forms`. Explicit meta capabilities separate read-only list
access from editing, without changing access to ordinary WordPress posts.
All four form capabilities opt into assignment to default WordPress roles in
Security > Manage Permissions. For other capabilities, call both
`userConfigurable()` and `assignableToDefaultRoles()` to enable this. The latter
does not grant capabilities automatically; `roles([...])` still defines defaults.
Administrator remains read-only and receives every registered capability.
The special `form` post at slug `f` is hidden from the Forms admin list, but
remains available for front-end form-host rendering.
WordPress permission checks prevent editing, trashing or deleting it, including
through direct editor links or API requests. Its former editor redirect is no
longer needed; normal forms remain editable by users with `edit_forms`.

## License

MIT.
