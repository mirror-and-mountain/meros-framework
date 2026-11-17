<?php

namespace MM\Meros\Support;

use MM\Meros\Contracts\Features\Components\FieldGroup;

use MM\Meros\Facades\Framework;
use MM\Meros\Facades\Components\FieldGroups;

class FormHostSettingsControls {
    /**
     * The constructed FlowControls instance used to configure the FlowAction in WP-Admin.
     *
     * @var FieldGroup
     */
    private FieldGroup $group;

    /**
     * Holds the template-specific settings for the form, if any.
     *
     * @var array
     */
    private array $templateSettings = [];

    // =========================================================================
    // Initialisation
    // =========================================================================

    /**
     * Constructs a new FormSettingsControls instance.
     *
     */
    public function __construct(array $templateSettings = []) {
        $this->templateSettings = $templateSettings;
        $this->init();
    }

    /**
     * Initialises a FieldGroup instance to contain a form's settings controls.
     *
     * @return void
     */
    private function init(): void {
        $group = FieldGroups::checkout(Framework::get())->get('form-host-settings');

        if ($group !== null) {
            $this->group = $group;
            return;
        }

        $this->group = FieldGroups::checkout(Framework::get())
            ->make(function (FieldGroup $group) {
                $group->id('form-host-settings');
                $group->title('Form Settings');
                $group->name('form_host_settings');

                $title = array_key_exists('template_title', $this->templateSettings)
                    ? $this->templateSettings['template_title']
                    : '';

                $titleLocked = array_key_exists('allow_edit_form_title', $this->templateSettings)
                    ? !$this->templateSettings['allow_edit_form_title']
                    : false;

                $description = array_key_exists('template_description', $this->templateSettings)
                    ? $this->templateSettings['template_description']
                    : '';

                $descriptionLocked = array_key_exists('allow_edit_form_description', $this->templateSettings)
                    ? !$this->templateSettings['allow_edit_form_description']
                    : false;

                $submitButtonText = array_key_exists('submit_button_text', $this->templateSettings)
                    ? $this->templateSettings['submit_button_text']
                    : 'Submit';

                $submitButtonTextLocked = array_key_exists('allow_edit_submit_button_text', $this->templateSettings)
                    ? !$this->templateSettings['allow_edit_submit_button_text']
                    : false;

                $successMessage = array_key_exists('success_message', $this->templateSettings)
                    ? $this->templateSettings['success_message']
                    : 'Form Submitted Successfully.';

                $successMessageLocked = array_key_exists('allow_edit_success_message', $this->templateSettings)
                    ? !$this->templateSettings['allow_edit_success_message']
                    : false;

                $group->field('text', [
                    'name'        => 'form_title',
                    'label'       => 'Form Title',
                    'description' => 'The title of the form. This setting may be locked by the template.',
                    'default'     => $title,
                    'readonly'    => $titleLocked
                ], false);

                $group->field('richtext', [
                    'name'        => 'form_description',
                    'label'       => 'Form Description',
                    'description' => 'The description of the form. This setting may be locked by the template.',
                    'default'     => $description,
                    'readonly'    => $descriptionLocked
                ], false);

                $group->field('text', [
                    'name'        => 'submit_button_text',
                    'label'       => 'Submit Button Text',
                    'description' => 'Text displayed on the form submit button.',
                    'default'     => $submitButtonText,
                    'readonly'    => $submitButtonTextLocked,
                ], false);

                $group->field('richtext', [
                    'name'        => 'success_message',
                    'label'       => 'Success Message',
                    'description' => "Message displayed upon successful form submission.",
                    'default'     => $successMessage,
                    'readonly'    => $successMessageLocked,
                ], false);

                $group->field('checkbox', [
                    'name'        => 'display_as_pages',
                    'label'       => 'Display as Pages',
                    'description' => 'Display the form as paginated pages. This setting may be locked by the template.',
                    'default'     => array_key_exists('display_as_pages', $this->templateSettings)
                        ? $this->templateSettings['display_as_pages']
                        : false,
                    'toggle'      => true,
                    'disabled'    => array_key_exists('lock_display_as_pages', $this->templateSettings)
                        ? $this->templateSettings['lock_display_as_pages']
                        : false
                ], false);

                foreach (['open', 'close'] as $prefix) {
                    $group->row(function ($row) use ($prefix) {
                        $row->field('date', [
                            'name' => $prefix . '_date',
                            'label' => ucfirst($prefix) . ' Date',
                        ]);

                        $row->field('time', [
                            'name' => $prefix . '_time',
                            'label' => ucfirst($prefix) . ' Time',
                        ]);
                    });
                }

                $group->row(function ($row) {
                    $row->field('text', [
                        'name'     => 'form_template',
                        'label'    => 'Form Template',
                        'readonly' => true
                    ]);

                    $row->field('number', [
                        'name'     => 'form_template_id',
                        'label'    => 'Form Template ID',
                        'readonly' => true
                    ]);
                });
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