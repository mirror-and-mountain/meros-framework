<?php

namespace MM\Meros\Support;

use MM\Meros\Contracts\Features\Components\FieldGroup;

use MM\Meros\Facades\Framework;
use MM\Meros\Facades\Components\FieldGroups;

class FormSettingsControls {
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
        $group = FieldGroups::checkout(Framework::get())->get('form-settings');

        if ($group !== null) {
            $this->group = $group;
            return;
        }

        $this->group = FieldGroups::checkout(Framework::get())
            ->make(function (FieldGroup $group) {
                $group->id('form-settings');
                $group->title('Form Settings');
                $group->name('form_settings');

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