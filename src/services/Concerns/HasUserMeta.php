<?php

namespace MM\Meros\Services\Concerns;

use Closure;
use Illuminate\Support\Str;
use MM\Meros\Services\Contracts\Forms\FieldGroup;
use MM\Meros\Services\Contracts\UserMeta;

use MM\Meros\Facades\FieldGroups;
use MM\Meros\Facades\UserMetaDefinitions;

use MM\Meros\Services\Registers\UserMetaDefinitions as UserMetaRegister;

trait HasUserMeta {
    /**
     * User meta containers associated with the current provider.
     *
     * @var array<string, UserMeta>
     */
    protected array $userMetaContainers = [];

    /**
     * Retrieves a user meta definition if a key is provided or the user meta
     * definition register if no key is provided.
     *
     * @param string       $key
     * @param Closure|null $callback
     *
     * @return UserMeta|UserMetaRegister|null
     */
    protected function userMeta(string $key = '', ?Closure $callback = null): UserMeta|UserMetaRegister|null {
        if (empty($key)) {
            return UserMetaDefinitions::checkout($this);
        }

        return UserMetaDefinitions::checkout($this)->get($key, $callback);
    }

    /**
     * Alias of userMeta() for users who prefer snake_case method names.
     *
     * @param string       $key
     * @param Closure|null $callback
     *
     * @return UserMeta|UserMetaRegister|null
     */
    protected function user_meta(string $key = '', ?Closure $callback = null): UserMeta|UserMetaRegister|null {
        return $this->userMeta($key, $callback);
    }

    /**
     * Retrieves the requested user meta container, creating it if necessary.
     *
     * @param string $key
     *
     * @return UserMeta
     */
    protected function userMetaContainer(string $key = 'default'): UserMeta {
        $key = $key === '' ? 'default' : Str::snake($key);

        if (isset($this->userMetaContainers[$key])) {
            return $this->userMetaContainers[$key];
        }

        $containerName = $key === 'default'
            ? '_' . Str::replace('-', '_', $this->getHandle()) . '_user_meta'
            : '_' . Str::replace('-', '_', $this->getHandle()) . '_' . $key . '_user_meta';

        $container = UserMetaDefinitions::checkout($this)->make([
            'name' => $containerName,
            'type' => 'object',
            'auto_queue' => true,
        ]);

        $this->userMetaContainers[$key] = $container;

        return $container;
    }

    /**
     * Snake-case alias of userMetaContainer().
     *
     * @param string $key
     *
     * @return UserMeta
     */
    protected function user_meta_container(string $key = 'default'): UserMeta {
        return $this->userMetaContainer($key);
    }

    /**
     * Attaches a field group to the given user meta container.
     *
     * @param FieldGroup|string $fieldGroup
     * @param string            $container
     *
     * @return static
     */
    protected function userMetaFields(FieldGroup|string $fieldGroup, string $container = 'default'): static {
        if (is_string($fieldGroup)) {
            $fieldGroup = FieldGroups::checkout($this)->get($fieldGroup);
        }

        if (!$fieldGroup instanceof FieldGroup) {
            return $this;
        }

        $attachedGroup = $this->userMetaContainer($container)
            ->setFieldGroup($fieldGroup)
            ->getFieldGroup();

        foreach ($attachedGroup->getFields() as $field) {
            $attachedGroup->addMetaField($field, false);
        }

        return $this;
    }

    /**
     * Snake-case alias of userMetaFields().
     *
     * @param FieldGroup|string $fieldGroup
     * @param string            $container
     *
     * @return static
     */
    protected function user_meta_fields(FieldGroup|string $fieldGroup, string $container = 'default'): static {
        return $this->userMetaFields($fieldGroup, $container);
    }

    /**
     * Builds a field group against the given user meta container.
     *
     * @param string  $label
     * @param Closure $callback
     * @param string  $container
     *
     * @return static
     */
    protected function userMetaFieldGroup(string $label, Closure $callback, string $container = 'default'): static {
        $fieldGroup = $this->userMetaContainer($container)->getFieldGroup();
        $fieldGroup->title($label);
        $callback($fieldGroup);

        return $this;
    }

    /**
     * Snake-case alias of userMetaFieldGroup().
     *
     * @param string  $label
     * @param Closure $callback
     * @param string  $container
     *
     * @return static
     */
    protected function user_meta_field_group(string $label, Closure $callback, string $container = 'default'): static {
        return $this->userMetaFieldGroup($label, $callback, $container);
    }
}
