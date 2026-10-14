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

use MM\Meros\App\Components\FormActions\StoreResponse;

use MM\Meros\Contracts\Orchestrators\ComponentsOrchestrator;

use MM\Meros\Facades\Support\Ajax;

use MM\Meros\Facades\Components\Fields;
use MM\Meros\Facades\Components\FormActions;

use MM\Meros\App\Models\Post;

use MM\Meros\App\Content\Form as FormPostType;
use MM\Meros\Facades\Content\PostTypes;
use MM\Meros\Contracts\Features\Components\Field;

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

    protected function configure(): void {
        foreach ($this->fields as $alias => $fieldClass) {
            $this->fields()->register($fieldClass, $alias);
        }

        $this->registerFormActions();
        $this->initFormAjax();
        $this->initRepeaterAjax();
        $this->initLookupAjax();
    }

    private function registerFormActions(): void {
        $this->formActions()->register(StoreResponse::class, 'store_response');
    }

    /**
     * Initialises ajax requests for form submissions.
     *
     * @return void
     */
    private function initFormAjax(): void {
        Ajax::addAction('meros_submit_form', function (array $args) {
            $id = $args['form_id'] ?? null;

            if ($id === null) {
                wp_send_json_error([
                    'message' => 'Unable to locate form: Null ID.'
                ]);
                exit;
            }

            $data = json_decode(stripslashes($args['form_data'] ?? '{}'), true);

            $form = Post::find($id);

            if ($form === null) {
                wp_send_json_error([
                    'message' => 'Unable to locate form: Null Form Model.'
                ]);
                exit;
            }

            Log::info('Actions', FormActions::getRegisteredFeatures());

            wp_send_json_success([
                'message' => 'Form submitted successfully.',
                'data'    => $data
            ]);
            exit;

        });
    }

    /**
     * Initialises ajax requests for repeater row editing forms.
     *
     * @return void
     */
    private function initRepeaterAjax(): void {
        Ajax::addAction('meros_open_repeater_edit_form', function (array $args) {
            $name = is_string($args['repeater_name'] ?? null) ? $args['repeater_name'] : '';
            $originalName = is_string($args['repeater_original_name'] ?? null) ? $args['repeater_original_name'] : '';

            $repeater = $this->findFormField($args, $name, $originalName, Repeater::class);

            if ($repeater === null) {
                wp_send_json_error(['message' => 'Unable to get repeater edit form.']);
                exit;
            }

            if ($name !== $originalName && $repeater->getName() === $originalName) {
                $repeater = Fields::cloneField($repeater, $name);
            }

            $rowData = $args['row_data'] ?? '';
            $rowData = json_decode(wp_unslash($rowData), true);

            $repeaterValue = $args['current_value'] ?? '';
            $repeaterValue = json_decode(wp_unslash($repeaterValue), true);

            $html = $repeater->renderEditForm($rowData, $repeaterValue);

            wp_send_json_success([
                'html' => $html
            ]);

            exit;
        }, [], true);
    }

    /**
     * Intialises ajax requests for looup field queries.
     *
     * @return void
     */
    private function initLookupAjax(): void {
        Ajax::addAction('meros_handle_lookup_query', function (array $args) {
            $name = is_string($args['lookup_name'] ?? null) ? $args['lookup_name'] : '';
            $originalName = is_string($args['lookup_original_name'] ?? null) ? $args['lookup_original_name'] : '';

            Log::info('Lookup', [
                'name' => $name,
                'originName' => $originalName
            ]);

            $lookup = $this->findFormField($args, $name, $originalName, Lookup::class);

            if (!($lookup instanceof Lookup)) {
                wp_send_json_error(['message' => 'Unable to get lookup field.']);
                exit;
            }

            if ($name !== $originalName && $lookup->getName() === $originalName) {
                $lookup = Fields::cloneField($lookup, $name);
            }

            $search = $args['search'] ?? '';

            wp_send_json_success([
                'results' => $lookup->resolve($search)
            ]);
            exit;
        }, [], true);
    }

    /**
     * Helper to resolve a form field for ajax requests at ajax runtime.
     *
     * @param array  $args
     * @param string $name
     * @param string $originalName
     * @param string $fieldClass
     *
     * @return Field|null
     */
    private function findFormField(
        array $args,
        string $name,
        string $originalName,
        string $fieldClass
    ): ?Field {
        $formId = is_scalar($args['form_id'] ?? null) ? (string) $args['form_id'] : '';

        if ($formId !== '') {
            $formPostId = filter_var($formId, FILTER_VALIDATE_INT);
            $formType = PostTypes::get('form');

            if ($formPostId !== false && $formPostId > 0 && $formType instanceof FormPostType) {
                $field = $formType->findField($formPostId, $name, $originalName, $fieldClass);
                if ($field !== null) {
                    return $field;
                }
            }
        }

        foreach (array_unique(array_filter([$name, $originalName])) as $candidateName) {
            $field = Fields::all()->firstWhere(function ($field) use ($candidateName, $fieldClass, $formId) {
                if (!($field instanceof $fieldClass) || $field->getName() !== $candidateName) {
                    return false;
                }

                $fieldForm = $field->getForm();
                return $formId === '' || $fieldForm === null || (string) $fieldForm->getId() === $formId;
            });

            if ($field instanceof Field) {
                return $field;
            }
        }

        return null;
    }
}