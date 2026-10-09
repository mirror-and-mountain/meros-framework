<?php

namespace MM\Meros\App\Components;

use MM\Meros\App\Components\Fields\Checkbox;
use MM\Meros\App\Components\Fields\Checkboxes;
use MM\Meros\App\Components\Fields\Date;
use MM\Meros\App\Components\Fields\Email;
use MM\Meros\App\Components\Fields\Hidden;
use MM\Meros\App\Components\Fields\Lookup;
use MM\Meros\App\Components\Fields\Number;
use MM\Meros\App\Components\Fields\Password;
use MM\Meros\App\Components\Fields\PostsLookup;
use MM\Meros\App\Components\Fields\Radio;
use MM\Meros\App\Components\Fields\Repeater;
use MM\Meros\App\Components\Fields\Select;
use MM\Meros\App\Components\Fields\Tel;
use MM\Meros\App\Components\Fields\Text;
use MM\Meros\App\Components\Fields\TextArea;
use MM\Meros\App\Components\Fields\Time;
use MM\Meros\App\Components\Fields\Url;
use MM\Meros\App\Components\Fields\UsersLookup;

use MM\Meros\App\Components\FieldGroups\SimpleContact;

use MM\Meros\Contracts\Orchestrators\ComponentsOrchestrator;

use MM\Meros\Facades\Support\Ajax;
use MM\Meros\Facades\Components\Fields;

use Illuminate\Support\Facades\Log;

class Orchestrator extends ComponentsOrchestrator {
    private array $fields = [
        'checkbox'     => Checkbox::class,
        'checkboxes'   => Checkboxes::class,
        'date'         => Date::class,
        'email'        => Email::class,
        'hidden'       => Hidden::class,
        'lookup'       => Lookup::class,
        'number'       => Number::class,
        'password'     => Password::class,
        'posts-lookup' => PostsLookup::class,
        'radio'        => Radio::class,
        'repeater'     => Repeater::class,
        'select'       => Select::class,
        'tel'          => Tel::class,
        'text'         => Text::class,
        'textarea'     => TextArea::class,
        'time'         => Time::class,
        'url'          => Url::class,
        'users-lookup' => UsersLookup::class,
    ];

    private array $fieldGroups = [
        'simple-contact-fields' => SimpleContact::class,
    ];

    protected function configure(): void {
        foreach ($this->fields as $alias => $fieldClass) {
            $this->fields()->register($fieldClass, $alias);
        }

        foreach ($this->fieldGroups as $alias => $groupClass) {
            $this->fieldGroups()->register($groupClass, $alias);
        }

        $this->initRepeaterAjax();
    }

    private function initRepeaterAjax(): void {
        Ajax::addAction('meros_open_repeater_edit_form', function (array $args) {
            $name = $args['repeater_name'] ?? '';
            $originalName = $args['repeater_original_name'] ?? '';

            $repeater = Fields::all()->firstWhere(function ($field) use ($name, $originalName) {
                return $field->getName() === $name || $field->getName() === $originalName;
            });

            if ($repeater === null) {
                wp_send_json_error(['message' => 'Unable to get repeater edit form.']);
                exit;
            }

            if ($name !== $originalName && $repeater->getName() === $originalName) {
                $repeater = Fields::cloneField($repeater, $name);
            }

            $rowData = $args['row_data'] ?? '';
            $rowData = json_decode(wp_unslash($rowData), true);

            $html = $repeater->renderEditForm($rowData);

            wp_send_json_success([
                'html' => $html
            ]);

            exit;
        });
    }
}