<?php 

namespace MM\Meros\Services\Contracts;

abstract class FormAction extends FeatureDefinition {
    /**
     * The unique handle for the form action, used as the identifier when processing form submissions.
     *
     * @var string
     */
    public string $handle;

    /**
     * A human-readable label for the form action, used in the admin interface.
     *
     * @var string
     */
    public string $label;

    /**
     * A description of what the form action does, displayed in the admin interface.
     *
     * @var string
     */
    public string $description;

    /***************************
     * Contract methods
     ***************************/

    protected function queue(): void {
        // No need to queue actions, as they will be executed directly when processing the form submission.
    }
}