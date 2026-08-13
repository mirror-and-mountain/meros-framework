<?php

namespace MM\Meros\App\Fields;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

use MM\Meros\Services\Controllers\FieldsController;

use MM\Meros\App\Fields\Wrappers\AdminDefault;
use MM\Meros\App\Fields\Wrappers\AdminSettings;
use MM\Meros\App\Fields\Wrappers\SiteDefault;

use MM\Meros\Facades\Fields;
use MM\Meros\Facades\Context;

class Controller extends FieldsController {
    protected function load(): void {
        if (Context::isAdmin()) {
            add_action('wp_ajax_meros_handle_repeater_row_config_call', [$this, 'handleRepeaterAjaxRequest']);
        } else {
            add_action('wp_ajax_nopriv_meros_handle_repeater_row_config_call', [$this, 'handleRepeaterAjaxRequest']);
        }

        $this->registerFields();
        $this->registerFieldWrappers();
    }

    private function registerFields(): void {
        // Basic Fields
        $this->fields()->register('admin_button', AdminButton::class);
        $this->fields()->register('text', Text::class);
        $this->fields()->register('textarea', Textarea::class);
        $this->fields()->register('email', Email::class);
        $this->fields()->register('tel', Tel::class);
        $this->fields()->register('url', Url::class);
        $this->fields()->register('number', Number::class);
        $this->fields()->register('range', Range::class);
        $this->fields()->register('checkbox', Checkbox::class);
        $this->fields()->register('color', Color::class);

        // Choice Fields
        $this->fields()->register('select', Select::class);
        $this->fields()->register('multi_select', MultiSelect::class);
        $this->fields()->register('advanced_select', AdvancedSelect::class);
        $this->fields()->register('radio', Radio::class);
        $this->fields()->register('checkboxes', Checkboxes::class);

        // Date Time Fields
        $this->fields()->register('date', Date::class);
        $this->fields()->register('time', Time::class);

        // Special Fields
        $this->fields()->register('hidden', Hidden::class);
        $this->fields()->register('repeater', Repeater::class);
        $this->fields()->register('rich_text', RichText::class);
        $this->fields()->register('password', Password::class);
    }

    private function registerFieldWrappers(): void {
        $this->fieldWrappers()->register('site_default', SiteDefault::class);
        $this->fieldWrappers()->register('admin_default', AdminDefault::class);
        $this->fieldWrappers()->register('admin_settings', AdminSettings::class);
    }

    /**
     * Handles AJAX requests for repeater row configuration dialogs, allowing fields to return HTML
     * dynamically based on the current row's data value.
     *
     * @return void
     */
    public function handleRepeaterAjaxRequest() {
        $repeaterId       = sanitize_text_field($_POST['repeater_id'] ?? '');
        $repeaterRowData  = $_POST['row_data'] ?? '';
        $repeaterRowData  = !empty($repeaterRowData) ? json_decode(stripslashes($repeaterRowData), true) : [];
        $nonce            = sanitize_text_field($_POST['nonce'] ?? '');

        $repeaterField = Fields::getById($repeaterId);
        $repeaterFound = $repeaterField instanceof Repeater;

        $isValid = 
            $repeaterFound && 
            is_array($repeaterRowData) && 
            wp_verify_nonce($nonce, 'meros_repeater_row_action_' . $repeaterField->getName());

        if (!$isValid) {
            wp_send_json_error(['message' => 'Invalid request.']);
            exit;
        }

        $html = $repeaterField->renderRowConfigurationDialog($repeaterRowData);

        wp_send_json_success([
            'html' => $html,
        ]);
    }
}