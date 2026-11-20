<a href="https://mirrorandmountain.com" target="_blank"><img src="https://github.com/mirror-and-mountain/assets/raw/main/logos/png/mm-logo-slate-transparent-512.png" width="100" alt="MIRROR AND MOUNTAIN Logo"></a>

## MEROS FRAMEWORK
> **NOTE:** The Meros framework is intended for use with a Meros starter theme for Wordpress. To get started with a theme, go to the [Meros Blocks repository.](https://github.com/mirror-and-mountain/meros-blocks)

### About
This framework underpins our Meros starter themes for Wordpress. Under the hood it's an extension of the incredible [Acorn](https://roots.io/acorn/) package from the team at [Roots.io](https://roots.io). In turn, Acorn brings the power of [Laravel](https://laravel.com) to Wordpress. Our goal with this framework is to provide a developer-friendly environment for creating Wordpress themes that benefit from a standardised approach for adding features through a Laravel-esque syntax and overall experience.

### Documentation
- [Forms](docs/FORMS.md): the PHP form API, WordPress form hosts and templates, client-side builder, conditions, pagination, submissions and field extension points.

#### Nested dynamic blocks

A Meros dynamic block can declare its direct parent with `$child->parent([$parent])`.
The parent gets a native block inserter and an editable area for its children.
By default, the inserter allows the dynamic blocks that declared that parent.
Use `$parent->allowedBlocks([...])` to set the permitted block names explicitly.

WordPress saves the nested blocks with the parent. To display the rendered
children on the front end, include `$args['content']` in the parent's
`renderCallback` output.

#### Dynamic block inspector controls

The attribute's `type` is its WordPress storage type. Use `control => true`
to infer a control from that type, or `control => ['type' => 'url']` to show
a labeled URL field for a `string` attribute. The field fits the inspector
sidebar. Paste a URL or type to search for an existing link using WordPress
suggestions. Set `placeholder` in the control options to override the default prompt.

Set `conditions` in the control options to show it only when other attributes
have the specified values:

```php
$this->attribute('form_id', [
    'type' => 'integer',
    'default' => 0,
    'control' => [
        'conditions' => ['use_current_form_id' => false],
    ],
]);
```

Conditions reference attributes defined on the same block and accept scalar
or null values. All entries must match, using strict equality (for example,
`false` is different from `0` or `'false'`). Updates toggle visibility immediately
without clearing the hidden attribute's value. Conditions affect only the
inspector, not front-end rendering or server-side validation.

### License 
The Meros Framework is open-sourced software licensed under the MIT license.
