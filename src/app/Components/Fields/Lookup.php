<?php

namespace MM\Meros\App\Components\Fields;

use MM\Meros\Facades\Support\Ajax;

class Lookup extends Select {
    /**
     * The model for the lookup to query.
     *
     * @var string
     */
    protected string $model = '';

    /**
     * The object for the lookup to query.
     *
     * @var string
     */
    protected string $object = '';

    /**
     * The primary key to use when querying the object.
     *
     * @var string
     */
    protected string $key = '';

    /**
     * The property to label option values by.
     *
     * @var string
     */
    protected string $labelledBy = '';

    /**
     * Query filters.
     *
     * @var array
     */
    protected array $filters = [];

    /**
     * The number of results to return per-request.
     *
     * @var integer
     */
    protected int $limit = 10;

    /**
     * WP's ajax url.
     *
     * @var string|null
     */
    private ?string $ajaxUrl = null;

    /**
     * The ajax nonce used for handling the lookup's query.
     * 
     * @var string|null
     */
    private ?string $ajaxNonce = null;

    final protected function init(): void {
        parent::init();     
        $this->setSerializableProperties([
            'object',
            'key',
            'filters',
            'limit',
            'ajaxUrl',
            'ajaxNonce'
        ]);

        $this->setNonPersistableProperties([
            'ajaxUrl',
            'ajaxNonce',
        ]);
    }

    final protected function whenConfigured(): void {
        parent::whenConfigured();
        $this->searchable();
        $this->initAjaxLookup();
    }

    /**
     * Initialises the wp ajax action and callback for the lookup field.
     *
     * @return void
     */
    private function initAjaxLookup(): void {
        $this->ajaxUrl = Ajax::getUrl();
        $this->ajaxNonce = Ajax::getActionNonce('meros_handle_lookup_query');

        $this->attribute('data-ajax-url', $this->ajaxUrl);
        $this->attribute('data-ajax-nonce', $this->ajaxNonce);
        $this->attribute('data-origin-name', $this->getOriginalName());
    }

    public function getAjaxUrl(): ?string {
        return $this->ajaxUrl;
    }

    public function getAjaxNonce(): ?string {
        return $this->ajaxNonce;
    }

    /**
     * Resolves options for the lookup field using the given search string.
     *
     * @param string $search
     *
     * @return array
     */
    public function resolve(string $search): array {
        if ($search === '') {
            return [];
        }

        if ($this->object !== '') {
            $query = $this->model::{$this->object}();
        } else {
            $query = $this->model::query();
        }

        foreach ($this->filters as $filter) {
            $query->where(
                $filter['field'],
                $filter['operator'],
                $filter['value']
            );
        }

        $options = $query
            ->where($this->labelledBy, 'LIKE', '%' . $search . '%')
            ->limit($this->limit)
            ->get([$this->labelledBy, $this->key]);

        return $options->mapWithKeys(function ($option): array {
            $value = is_array($option)
                ? ($option[$this->key] ?? null)
                : ($option->{$this->key} ?? null);
            $label = is_array($option)
                ? ($option[$this->labelledBy] ?? null)
                : ($option->{$this->labelledBy} ?? null);

            if ($value === null || $label === null) {
                return [];
            }

            return [(string) $value => $label];
        })->all();
    }

    /**
     * Overridden to ensure default values are resolved before rendering the lookup field.
     *
     * @return void
     */
    protected function whenDefaultSet(): void {
        parent::whenDefaultSet();

        $defaultValue = $this->getDefaultValue();
        $defaultValues = is_array($defaultValue) ? $defaultValue : [$defaultValue];

        foreach ($defaultValues as $value) {
            $option = null;

            if ($this->object !== '' && !empty($value)) {
                $option = $this->model::find($this->object, $value);
            } else if (!empty($value)) {
                $option = $this->model::find($value);
            }
            
            if ($option !== null) {
                if (property_exists($option, $this->key) && property_exists($option, $this->labelledBy)) {
                    $this->options[(string) $option->{$this->key}] = $option->{$this->labelledBy};
                }
            }
        }
    }

    /**
     * Sets the model to be queried by the lookup.
     *
     * @param string $model
     *
     * @return void
     */
    final public function model(string $model): void {
        $this->model = $model;
    }

    /**
     * Sets the object to be queried by the lookup.
     *
     * @param string $object
     *
     * @return void
     */
    final public function object(string $object): void {
        $this->object = $object;
    }

    /**
     * Sets the primary key of the object to be queried.
     *
     * @param string $key
     *
     * @return void
     */
    final public function key(string $key): void {
        $this->key = $key;
    }

    /**
     * Sets the labelledBy property which is translated into option labels for the lookup field.
     *
     * @param string $label
     *
     * @return void
     */
    final public function labelledBy(string $label): void {
        $this->labelledBy = $label;
    }

    /**
     * Adds 'where' type filters to the lookup query.
     *
     * @param string $field
     * @param string $operator
     * @param mixed  $value
     *
     * @return void
     */
    final public function filter(string $field, string $operator, mixed $value = null): void {
        $args = func_get_args();

        if (count($args) === 2) {
            $value = $operator;
            $operator = '=';
        }

        $this->filters[] = compact('field', 'operator', 'value');
    }

    /**
     * Sets the number of records for the query to return in each request.
     *
     * @param integer $limit
     *
     * @return void
     */
    final public function limit(int $limit): void {
        $this->limit = $limit;
    }
}