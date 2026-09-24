<?php 

namespace MM\Meros\Contracts\Features\Components\Concerns;

use Illuminate\Support\Collection;
use MM\Meros\Contracts\Features\Components\Field;

use MM\Meros\Contracts\Concerns\UsesAjax;

trait HandlesFieldConditions {
    /**
     * An array of field instances whos value affects other fields' conditions.
     *
     * @var array<Field>
     */
    private array $influencers = [];

    use UsesAjax;

    abstract public function getName(): string;
    abstract public function getFields(bool $collect = false): array|Collection;

    /**
     * Initialises server-side condition handling for associated fields, if they exist.
     *
     * @return void
     */
    private function initFieldConditions(): void {
        if ($this->form !== null) {
            return;
        }

        $conditionalFields = $this->getFieldsWithConditions();

        if (empty($conditionalFields)) {
            return;
        }

        $this->initInfluencerFields($conditionalFields);

        if ($this->influencers === []) {
            return;
        }

        $this->initAjax("meros_handle_field_conditions_{$this->getName()}", function (array $postData) {
            if (!array_key_exists('influencer', $postData)) {
                wp_send_json_error(['message' => 'Receieved field change, but the influencing field could not be resolved']);
                return;
            }

            if (!array_key_exists('value', $postData)) {
                wp_send_json_error(['message' => 'Receieved field change, but the field value could not be resolved']);
                return;
            }

            $influencerName  = $postData['influencer'];
            $influencerField = collect($this->influencers)->firstWhere(function (Field $field) use ($influencerName) {
                return $field->getName() === $influencerName;
            });

            if (!($influencerField instanceof Field)) {
                wp_send_json_error(['message' => 'Receieved field change, but the influencing field could not be resolved']);
                return;
            }

            $conditionalFields = $influencerField->getInfluencedFields();
            $showFields = [];
            $hideFields = [];

            foreach($conditionalFields as $conditional) {
                $conditions = $conditional->getConditions();

                foreach ($conditions as $influencer => $callbacks) {
                    if ($influencer !== $influencerField->getOriginalName() && 
                        $influencer !== $influencerField->getName()
                    ) {
                        continue;
                    }

                    foreach ($callbacks as $callback) {
                        if (!is_callable($callback)) {
                            continue;
                        }

                        $result = call_user_func($callback, $postData['value']);
                        $conditionalFieldName = $conditional->getName();

                        if ($result === 'show' && !in_array($conditionalFieldName, $showFields)) {
                            $showFields[] = $conditionalFieldName;
                            continue;
                        }

                        if ($result === 'hide' && !in_array($conditionalFieldName, $hideFields)) {
                            $hideFields[] = $conditionalFieldName;
                        }
                    }
                }
            }

            wp_send_json_success([
                'message'    => 'Receieved influencing field change.',
                'showFields' => $showFields,
                'hideFields' => $hideFields
            ]);
        });
    }

    /**
     * Sets the array of associated influencer fields that influence other fields' conditions.
     *
     * @param array<Field> $conditionalFields
     *
     * @return void
     */
    private function initInfluencerFields(array $conditionalFields): void {
        $influencers = [];

        foreach ($conditionalFields as $field) {
            $conditions = $field->getConditions();

            foreach ($conditions as $influencer => $condition) {
                $influencer = $this->getFields(true)
                    ->firstWhere(function (Field $f) use ($influencer) {
                        return $f->getOriginalName() === $influencer;
                    });

                if (!($influencer instanceof Field)) {
                    continue;
                }

                if (!in_array($influencer, $influencers)) {
                    $influencers[] = $influencer;
                }

                $influencer->__influences($field);
                $influencer->attribute('data-influencer', 'group');
            }
        }

        $this->influencers = $influencers;
    }

    /**
     * Evaluates a single field's conditions, if they exist.
     *
     * @param Field $field
     *
     * @return void
     */
    private function evalFieldConditions(Field $field): void {
        $conditions = $field->getConditions();

        foreach ($this->influencers as $influencerField) {
            $name  = $influencerField->getOriginalName();
            $value = $influencerField->getDefaultValue();

            if (in_array($name, array_keys($conditions))) {
                $callbacks = $conditions[$name];
                
                foreach($callbacks as $callback) {
                    if (!is_callable($callback)) {
                        continue;
                    }

                    $result = call_user_func($callback, $value);

                    if ($result === 'show') {
                        $field->show();
                    }

                    if ($result === 'hide') {
                        $field->hide();
                    }
                }
            }
        }
    }

    /**
     * Retrieves fields in the group that have conditions.
     * 
     * @param bool $collect
     *
     * @return Collection|array
     */
    private function getFieldsWithConditions(bool $collect = false): Collection|array {
        $fields = $this->getFields(true)->where(function (Field $field) {
            return $field->hasConditions();
        });

        return $collect ? $fields : $fields->toArray();
    }
}