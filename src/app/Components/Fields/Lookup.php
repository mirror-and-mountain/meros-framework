<?php

namespace MM\Meros\App\Components\Fields;

use Closure;
use Illuminate\Database\Eloquent\Model;

use MM\Meros\Contracts\Features\Integrations\ResolvesLookupOptions;

use MM\Meros\Contracts\Concerns\UsesAjax;
use MM\Meros\Support\ClassInfo;

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
     * The ajax action used to perform the lookup.
     *
     * @var string
     */
    private string $action = '';

    /**
     * Whether the field's ajax action has been initialised.
     *
     * @var boolean
     */
    private bool $initialised = false;

    /**
     * The callback for the ajax action.
     *
     * @var Closure|null
     */
    private ?Closure $callback = null;

    use UsesAjax;

    final protected function init(): void {
        parent::init();     
        $this->setSerializableProperties([
            'object',
            'key',
            'filters',
            'limit'
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
        if (empty($this->model)) {
            return;
        }

        $modelClass  = ClassInfo::get($this->model);
        $isModel     = $modelClass->extends(Model::class);
        $hasContract = false;

        if (!$isModel) {
            $instance = null;

            try {
                $instance = $this->model::getInstance();
            } catch (\Exception $e) {
                return;
            }
            
            if ($instance !== null && $instance instanceof ResolvesLookupOptions) {
                $hasContract = true;
            }
        }

        if (!$isModel && !$hasContract) {
            return;
        }

        $this->action = $this->getAjaxAction($this->getName());

        $this->callback = function (array $postData) {
            $search = $postData['search'] ?? '';

            wp_send_json_success([
                'results' => $this->resolve($search)
            ]);
        };

        $this->initAjax($this->action, $this->callback);
        $this->attribute('data-ajax-url', $this->getAjaxUrl());
        $this->attribute('data-ajax-nonce', $this->getAjaxNonce());
        $this->attribute('data-ajax-action', $this->action);

        $this->initialised = true;
    }

    /**
     * Resolves options for the lookup field using the given search string.
     *
     * @param string $search
     *
     * @return array
     */
    protected function resolve(string $search): array {
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
     * Updates the ajax lookup action when the name changes.
     *
     * @return void
     */
    protected function whenNameSet(): void {
        if ($this->initialised === false) {
            $this->initAjaxLookup();
            return;
        }
        
        $oldName = $this->getOriginalName();
        $newName = $this->getName();

        $this->removeAjax($this->getAjaxAction($oldName));
        $this->action = $this->getAjaxAction($newName);

        $this->initAjax(
            $this->action, 
            $this->callback
        );

        $this->attribute('data-ajax-action', $this->action);
        $this->attribute('data-ajax-nonce', $this->getAjaxNonce($this->action));
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
            if ($this->object !== '') {
                $option = $this->model::find($this->object, $value);
            } else {
                $option = $this->model::find($value);
            }
            
            if ($option !== null) {
                $this->options[(string) $option->{$this->key}] = $option->{$this->labelledBy};
            }
        }
    }

    /**
     * Retrieves the sanitized wp ajax action for the lookup.
     *
     * @param string $name
     *
     * @return string
     */
    private function getAjaxAction(string $name): string {
        $name = trim(sanitize_key($name), '_-');

        return $name === ''
            ? 'meros_handle_lookup_query'
            : "meros_handle_lookup_query_{$name}";
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