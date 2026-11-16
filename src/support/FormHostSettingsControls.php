<?php

namespace MM\Meros\Support;

use Illuminate\Support\Str;

use MM\Meros\Contracts\Providers\FeatureProvider;
use MM\Meros\Contracts\Features\Components\FieldGroup;


use MM\Meros\Contracts\Features\Content\Interfaces\FormHost;

use MM\Meros\App\Components\Fields\Select;
use MM\Meros\App\Components\Fields\Textarea;
use MM\Meros\App\Components\Fields\Hidden;
use MM\Meros\App\Components\Fields\Repeater;

use MM\Meros\App\Content\Form;
use MM\Meros\App\Content\FormTemplate;

use MM\Meros\Facades\Framework;
use MM\Meros\Facades\Components\FieldGroups;

class FormHostSettingsControls {
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
                
                $group->field('text', [
                    'name' => 'form_title',
                    'label' => 'Form Title',
                ], false);

                $group->field('textarea', [
                    'name' => 'form_description',
                    'label' => 'Form Description',
                ], false);

                $group->field('checkbox', [
                    'name'        => 'display_as_pages',
                    'label'       => 'Display as Pages',
                    'description' => 'Display the form as paginated pages, unless this setting is locked by the template.',
                    'default'     => false,
                    'toggle'      => true,
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

    // =========================================================================
    // Helpers
    // =========================================================================
}