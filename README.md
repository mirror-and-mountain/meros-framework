# Meros Framework

Theme/package providers, feature contracts, registers and WordPress-facing
services for Meros themes, powered by Meros Foundations.

## Documentation

- [Framework guides](https://github.com/mirror-and-mountain/meros-docs/blob/main/framework/README.md)
- [Getting started with a Meros theme](https://github.com/mirror-and-mountain/meros-docs/blob/main/theme/getting-started.md)
- [Shared testing for theme and package developers](https://github.com/mirror-and-mountain/meros-docs/blob/main/framework/testing/README.md)

Detailed documentation is maintained in [meros-docs](https://github.com/mirror-and-mountain/meros-docs).

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

The framework's built-in `Form` and `FormTemplate` post types share the
non-hierarchical `form-tags` taxonomy, configured by `App\Content\FormTags`.
It is available in the REST API and adds a tags column to their admin lists.
Both post types are grouped under the framework's top-level **Forms** menu,
alongside a single **Form Tags** link. Their native list and editor screens
remain available. `PostType::showInMenu()` accepts either a boolean or a parent
menu slug when grouping other post types.
Form Templates are non-public, with their WordPress admin UI explicitly enabled.

## License

MIT.
