<?php 

namespace MM\Meros\Services\Concerns;

use MM\Meros\Services\Setting;
use MM\Meros\Services\Contracts\AdminFieldRegistrant;

trait HasAdminField {
    /**
     * The admin field registrant providing context and data for the field.
     *
     * @var AdminFieldRegistrant
     */
    protected AdminFieldRegistrant $registrant;

    /**
     * The name of the Blade component used to render the field's wrapper in the admin context.
     *
     * @var string
     */
    protected string $adminWrapper = 'meros::components.fields.wrappers.admin-field';

    /**
     * Initialises the field with properties from the admin field registrant.
     *
     * @param AdminFieldRegistrant $registrant The registrant providing field data.
     * @param array                $props      Additional properties to set on the field.
     *
     * @return void
     */
    public function initAdminField(
        AdminFieldRegistrant $registrant,
        array                $props = []
    ) {
        $this->registrant  = $registrant;
        $this->name        = $this->makeName();
        $this->id          = $this->registrant->getID();
        $this->label       = $this->registrant->getLabel();
        $this->helpText    = $this->registrant->getDescription();
        $this->value       = $this->registrant->getValue();

        $registrantProperties = [
            'name',
            'id',
            'label',
            'helpText',
            'value'
        ];

        foreach ($props as $key => $value) {
            if (in_array($key, $registrantProperties)) {
                continue; // Skip properties that are already set from the registrant
            }

            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    /**
     * Renders the field using its designated view component in the admin context.
     *
     * @return void
     */
    public function renderAdmin(): void {
        if ($this->registrant instanceof Setting) {
            $this->adminWrapper = 'meros::components.fields.wrappers.setting-field';
        }

        echo view($this->adminWrapper, [
            'component' => $this->getFieldComponent(),
            'field'     => $this
        ]);
    }

    /**
     * Sets the name of the Blade component used to render the field's wrapper in the admin context.
     *
     * @param string $wrapper The name of the Blade component.
     *
     * @return self
     */
    public function adminWrapper(string $wrapper): self {
        $this->adminWrapper = $wrapper;
        return $this;
    }

    /**
     * Retrieves the name of the Blade component used to render the field's wrapper in the admin context.
     *
     * @return string
     */
    public function getAdminWrapper(): string {
        return $this->adminWrapper;
    }

    /**
     * Generates the 'name' attribute for the field input.
     * 
     * @param  int|null $index Optional index for fields that are part of a repeater or similar structure.
     * 
     * @return string
     */
    protected function makeName(?int $index = null): string {
        $root = $this->getRootRegistrar();
        $name = $root->getName();

        $segments = explode('.', $this->registrant->path);

        // Remove root from segments
        array_shift($segments);

        foreach ($segments as $segment) {
            if ($segment === '*') {
                $segment = $index ?? 0;
            }

            $name .= "[{$segment}]";
        }

        return $name;
    }

    /**
     * Traverses up the registrant hierarchy to find the root registrant.
     *
     * @return AdminFieldRegistrant
     */
    protected function getRootRegistrar(): AdminFieldRegistrant {
        $current = $this->registrant;

        while (method_exists($current, 'hasParent') && $current->hasParent()) {
            $current = $current->parent;
        }

        return $current;
    }
}