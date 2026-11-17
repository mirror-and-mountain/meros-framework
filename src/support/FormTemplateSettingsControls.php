<?php

namespace MM\Meros\Support;

use MM\Meros\Contracts\Features\Components\FieldGroup;

use MM\Meros\Facades\Framework;
use MM\Meros\Facades\Components\FieldGroups;

class FormTemplateSettingsControls {
    /**
     * The constructed FlowControls instance used to configure the FlowAction in WP-Admin.
     *
     * @var FieldGroup
     */
    private FieldGroup $group;

    // =========================================================================
    // Initialisation
    // =========================================================================

    /**
     * Constructs a new FormSettingsControls instance.
     *
     */
    public function __construct() {
        $this->init();
    }

    /**
     * Initialises a FieldGroup instance to contain a form's settings controls.
     *
     * @return void
     */
    private function init(): void {
        $group = FieldGroups::checkout(Framework::get())->get('form-template-settings');

        if ($group !== null) {
            $this->group = $group;
            return;
        }

        $this->group = FieldGroups::checkout(Framework::get())
            ->make(function (FieldGroup $group) {
                $group->id('form-template-settings');
                $group->title('Form Template Settings');
                $group->name('form_template_settings');

                $group->field('checkbox', [
                    'name'        => 'allow_edit_form_title',
                    'label'       => 'Allow Editing Form Title',
                    'description' => 'Allow editing the form title.',
                    'default'     => true,
                    'toggle'      => true,
                ], false);

                $group->field('checkbox', [
                    'name'        => 'allow_edit_form_description',
                    'label'       => 'Allow Editing Form Description',
                    'description' => 'Allow editing the form description.',
                    'default'     => true,
                    'toggle'      => true,
                ], false);

                $group->field('checkbox', [
                    'name'        => 'allow_add_components',
                    'label'       => 'Allow New Components',
                    'description' => 'Allow adding new components (fields or groups) to the form.',
                    'default'     => true,
                    'toggle'      => true,
                ], false);

                $group->field('checkbox', [
                    'name'        => 'allow_remove_components',
                    'label'       => 'Allow Removing Components',
                    'description' => 'Allow removing components (fields or groups) from the form.',
                    'default'     => true,
                    'toggle'      => true,
                ], false);

                $group->field('checkbox', [
                    'name'        => 'allow_move_components',
                    'label'       => 'Allow Moving Components',
                    'description' => 'Allow moving components (fields or groups) within the form.',
                    'default'     => true,
                    'toggle'      => true,
                ], false);

                $group->field('checkbox', [
                    'name'        => 'allow_edit_field_properties',
                    'label'       => 'Allow Editing Field Properties',
                    'description' => 'Allow editing properties of individual fields within the form.',
                    'default'     => true,
                    'toggle'      => true,
                ], false);

                $group->field('checkbox', [
                    'name'        => 'display_as_pages',
                    'label'       => 'Display as Pages',
                    'description' => 'Display the form as paginated pages.',
                    'default'     => false,
                    'toggle'      => true,
                ], false);

                $group->field('checkbox', [
                    'name'        => 'lock_display_as_pages',
                    'label'       => 'Lock Pagination Setting',
                    'description' => 'Prevent forms using this template from overriding Display as Pages.',
                    'default'     => false,
                    'toggle'      => true,
                ], false);

                $group->field('checkbox', [
                    'name'        => 'allow_actions_overrides',
                    'label'       => 'Allow Actions Overrides',
                    'description' => 'Allow overriding default actions for the form.',
                    'default'     => true,
                    'toggle'      => true,
                ], false);

                $group->field('checkbox', [
                    'name'        => 'allow_edit_submit_button_text',
                    'label'       => 'Allow Editing Submit Button Text',
                    'description' => 'Allow editing the submit button text for the form.',
                    'default'     => true,
                    'toggle'      => true,
                ], false);

                 $group->field('text', [
                    'name'        => 'submit_button_text',
                    'label'       => 'Submit Button Text',
                    'description' => 'Text displayed on the form submit button.',
                    'default'     => 'Submit',
                ], false);

                $group->field('checkbox', [
                    'name' => 'allow_edit_success_message',
                    'label'       => 'Allow Editing Success Message',
                    'description' => 'Allow editing the success message for the form.',
                    'default'     => true,
                    'toggle'      => true,
                ], false);

                $group->field('richtext', [
                    'name'        => 'success_message',
                    'label'       => 'Success Message',
                    'description' => "Message displayed upon successful form submission.",
                    'default'     => 'Form Submitted Successfully.',
                ], false);
            });
    }

    // =========================================================================
    // Getters
    // =========================================================================

    /**
     * Returns the constructed FieldGroup instance used to configure the form's settings in WP-Admin.
     *
     * @return FieldGroup
     */
    public function getControls(): FieldGroup {
        return $this->group;
    }
}