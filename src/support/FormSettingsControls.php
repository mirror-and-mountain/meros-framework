<?php

namespace MM\Meros\Support;

use MM\Meros\Contracts\Features\Components\FieldGroup;
use MM\Meros\Facades\Components\FieldGroups;
use MM\Meros\Facades\Framework;

class FormSettingsControls {
    /**
     * The ID of the form settings group.
     */
    private const FORM_GROUP = 'form-settings';

    /**
     * The ID of the host settings group.
     */
    protected const HOST_GROUP = 'form-host-settings';

    /**
     * The ID of the template settings group.
     */
    protected const TEMPLATE_GROUP = 'form-template-settings';

    /**
     * The ID of the currently active group.
     */
    protected const GROUP_ID = self::FORM_GROUP;

    private FieldGroup $group;

    public function __construct(private array $templateSettings = []) {
        $provider = Framework::get();
        $this->group = FieldGroups::checkout($provider)->get(static::GROUP_ID)
            ?? FieldGroups::checkout($provider)->make(function (FieldGroup $group) {
                $group->id(static::GROUP_ID);
                $group->name(str_replace('-', '_', static::GROUP_ID));
                $group->title(static::GROUP_ID === self::TEMPLATE_GROUP ? 'Form Template Settings' : 'Form Settings');

                if (static::GROUP_ID === self::TEMPLATE_GROUP) {
                    $this->addTemplatePermissions($group);
                    $this->addPagination($group);
                    $this->addToggle($group, 'lock_display_as_pages', 'Lock Pagination Setting', 'Prevent forms using this template from overriding Display as Pages.', false);
                    $this->addToggle($group, 'allow_actions_overrides', 'Allow Actions Overrides', 'Allow overriding default actions for the form.');
                    $this->addSubmissionText($group);
                    $this->addSuccessOptions($group);
                    return;
                }

                if (static::GROUP_ID === self::HOST_GROUP) {
                    $this->addHostTitles($group);
                    $this->addSubmissionText($group);
                    $this->addPagination($group);
                } else {
                    $this->addPagination($group);
                    $this->addSubmissionText($group);
                }
                $this->addSuccessOptions($group);

                foreach (['open', 'close'] as $prefix) {
                    $group->row(function ($row) use ($prefix) {
                        $row->field('date', ['name' => $prefix . '_date', 'label' => ucfirst($prefix) . ' Date']);
                        $row->field('time', ['name' => $prefix . '_time', 'label' => ucfirst($prefix) . ' Time']);
                    });
                }

                if (static::GROUP_ID === self::HOST_GROUP) {
                    $group->row(function ($row) {
                        $row->field('text', ['name' => 'form_template', 'label' => 'Form Template', 'readonly' => true]);
                        $row->field('number', ['name' => 'form_template_id', 'label' => 'Form Template ID', 'readonly' => true]);
                    });
                }
            });
    }

    /**
     * Get the field group containing the form settings controls.
     *
     * @return FieldGroup
     */
    public function getControls(): FieldGroup {
        return $this->group;
    }

    /**
     * Add the pagination control to the specified field group.
     *
     * @param FieldGroup $group
     *
     * @return void
     */
    private function addPagination(FieldGroup $group): void {
        $description = match (static::GROUP_ID) {
            self::FORM_GROUP => 'Display the form as paginated pages, unless this setting is locked by the template.',
            self::HOST_GROUP => 'Display the form as paginated pages. This setting may be locked by the template.',
            default => 'Display the form as paginated pages.',
        };
        $properties = static::GROUP_ID === self::HOST_GROUP
            ? ['disabled' => $this->templateSettings['lock_display_as_pages'] ?? false]
            : [];
        $this->addToggle($group, 'display_as_pages', 'Display as Pages', $description, $this->templateDefault('display_as_pages', false), $properties);
    }

    /**
     * Add submission text controls to the specified field group.
     *
     * @param FieldGroup $group
     *
     * @return void
     */
    private function addSubmissionText(FieldGroup $group): void {
        foreach ([
            'submit_button_text' => ['text', 'Submit Button Text', 'Text displayed on the form submit button.', 'Submit'],
            'success_message' => ['richtext', 'Success Message', 'For stored forms, use a field name in double braces to include its submitted value, e.g. Thanks, {{first_name}}.', 'Form Submitted Successfully.'],
        ] as $name => [$type, $label, $description, $default]) {
            $permission = 'allow_edit_' . $name;
            if (static::GROUP_ID === self::TEMPLATE_GROUP) {
                $this->addToggle($group, $permission, 'Allow Editing ' . $label, 'Allow editing the ' . strtolower($label) . ' for the form.');
            }
            $this->addText($group, $type, $name, $label, $description, $name, $default);
        }
    }

    /**
     * Add success options controls to the specified field group.
     *
     * @param FieldGroup $group
     *
     * @return void
     */
    private function addSuccessOptions(FieldGroup $group): void {
        foreach ([
            'show_success_on_new_page' => ['Show Success Message on a New Page', 'Replace the form with its confirmation without navigating away.'],
            'show_submit_another_response' => ['Show Button to Submit Another Response', 'Add a button to the confirmation page that returns to the reset form.'],
        ] as $name => [$label, $description]) {
            $lock = 'lock_' . $name;
            $properties = static::GROUP_ID === self::HOST_GROUP
                ? ['disabled' => filter_var($this->templateSettings[$lock] ?? false, FILTER_VALIDATE_BOOLEAN)]
                : [];
            $this->addToggle($group, $name, $label, $description, $this->templateDefault($name, false), $properties);
            if (static::GROUP_ID === self::TEMPLATE_GROUP) {
                $this->addToggle($group, $lock, 'Lock: ' . $label, 'Prevent forms using this template from overriding this setting.', false);
            }
        }
    }

    /**
     * Add host title controls to the specified field group.
     *
     * @param FieldGroup $group
     *
     * @return void
     */
    private function addHostTitles(FieldGroup $group): void {
        $this->addText($group, 'text', 'form_title', 'Form Title', 'The title of the form. This setting may be locked by the template.', 'template_title');
        $this->addText($group, 'richtext', 'form_description', 'Form Description', 'The description of the form. This setting may be locked by the template.', 'template_description');
    }

    /**
     * Add a text control to the specified field group.
     *
     * @param FieldGroup $group
     * @param string     $type
     * @param string     $name
     * @param string     $label
     * @param string     $description
     * @param string     $defaultKey
     * @param string     $default
     *
     * @return void
     */
    private function addText(FieldGroup $group, string $type, string $name, string $label, string $description, string $defaultKey, string $default = ''): void {
        $properties = [
            'name' => $name,
            'label' => $label,
            'description' => $description,
            'default' => $this->templateDefault($defaultKey, $default),
        ];
        if (static::GROUP_ID === self::HOST_GROUP) {
            $permission = 'allow_edit_' . $name;
            $properties['readonly'] = array_key_exists($permission, $this->templateSettings) && !$this->templateSettings[$permission];
        }
        $group->field($type, $properties, false);
    }

    /**
     * Get the default value for the specified template key.
     *
     * @param string $key
     * @param mixed  $default
     *
     * @return mixed
     */
    private function templateDefault(string $key, mixed $default): mixed {
        return static::GROUP_ID === self::HOST_GROUP && array_key_exists($key, $this->templateSettings)
            ? $this->templateSettings[$key]
            : $default;
    }

    /**
     * Add template permission controls to the specified field group.
     *
     * @param FieldGroup $group
     *
     * @return void
     */
    private function addTemplatePermissions(FieldGroup $group): void {
        foreach ([
            'allow_edit_form_title' => ['Allow Editing Form Title', 'Allow editing the form title.'],
            'allow_edit_form_description' => ['Allow Editing Form Description', 'Allow editing the form description.'],
            'allow_add_components' => ['Allow New Components', 'Allow adding new components (fields or groups) to the form.'],
            'allow_remove_components' => ['Allow Removing Components', 'Allow removing components (fields or groups) from the form.'],
            'allow_move_components' => ['Allow Moving Components', 'Allow moving components (fields or groups) within the form.'],
            'allow_edit_field_properties' => ['Allow Editing Field Properties', 'Allow editing properties of individual fields within the form.'],
        ] as $name => [$label, $description]) {
            $this->addToggle($group, $name, $label, $description);
        }
    }

    /**
     * Add a toggle control to the specified field group.
     *
     * @param FieldGroup $group
     * @param string     $name
     * @param string     $label
     * @param string     $description
     * @param mixed      $default
     * @param array      $properties
     *
     * @return void
     */
    private function addToggle(FieldGroup $group, string $name, string $label, string $description, mixed $default = true, array $properties = []): void {
        $group->field('checkbox', [
            'name' => $name,
            'label' => $label,
            'description' => $description,
            'default' => $default,
            'toggle' => true,
        ] + $properties, false);
    }
}
