<a href="https://mirrorandmountain.com" target="_blank"><img src="https://github.com/mirror-and-mountain/assets/raw/main/logos/png/mm-logo-slate-transparent-512.png" width="100" alt="MIRROR AND MOUNTAIN Logo"></a>

## MEROS FRAMEWORK
> **NOTE:** The Meros framework is intended for use with a Meros starter theme for Wordpress. To get started with a theme, go to the [Meros Blocks repository.](https://github.com/mirror-and-mountain/meros-blocks)

### About
This framework underpins our Meros starter themes for Wordpress. Under the hood it's powered by [Meros Foundations](../meros-foundations), our lean integration layer that brings the power of [Laravel](https://laravel.com) to Wordpress. Our goal with this framework is to provide a developer-friendly environment for creating Wordpress themes that benefit from a standardised approach for adding features through a Laravel-esque syntax and overall experience.

### Documentation
- [Forms](docs/FORMS.md): the PHP form API, WordPress form hosts and templates, client-side builder, conditions, pagination, submissions and field extension points.
- [MFA](docs/MFA.md): native WordPress login enrollment, authenticator challenges, recovery and package integration.

#### Using integrations

Resolve a registered integration and check its local readiness before use:

```php
use MM\Meros\Facades\Integrations;

$integration = Integrations::connect('mailchimp');
if ($integration->ready('marketing')) {
    $audiences = $integration->getAudiences();
}
```

`connect()` accepts a registered identifier, an integration class, or a unique
snake-case class name. It returns the integration even when it is not ready and
throws an `InvalidArgumentException` for unknown or ambiguous names. Readiness
checks the enabled setting and integration-specific prerequisites in the current
environment; OAuth integrations require a connected record and access token for
the selected audience. It does not contact the remote service or guarantee that
the next request will succeed. API request boundaries must enforce readiness too;
existing integration-specific facades remain available but are deprecated.

Integration authors can override `isReady(?string $capability): bool` for
additional local checks. Call `parent::isReady($capability)` when extending an
integration with its own prerequisites. The base implementation checks only the
enabled state and rejects unsupported capabilities. A readiness check is
side-effect-free apart from reading settings and connections; API failures should
still be logged and surfaced according to the integration's return contract.

#### OAuth connection settings

OAuth integrations require saved, non-empty values for their `UsesBaseUrl`,
`UsesClientId` and `UsesClientSecret` settings before connecting. Values must be
saved for the current environment. Override `requiredConnectionSettings()` to
add custom setting names or exclude optional credentials:

```php
protected function requiredConnectionSettings(): array {
    return [...parent::requiredConnectionSettings(), 'tenant_id'];
}
```

Use setting names without environment suffixes. The same check applies when
starting the OAuth flow directly.

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
