<?php 

namespace MM\Meros\Services;

use Illuminate\Support\Str;

use MM\Meros\Services\Contracts\Feature;
use MM\Meros\Services\Contracts\FeatureProvider;

class BlockVariation extends Feature {
    public string $name = '';
    public string $parentBlock = '';
    public array  $args = [
        'scope'     => ['block', 'inserter'],
        'isDefault' => false,
    ];

    protected bool   $initialised = false;
    protected string $initError = 'Configuration methods cannot be called until the parent block is set via either the parent() or of() method.';

    public function __construct(
        public FeatureProvider $source,
        string $name = '',
        array  $args = []
    ) {
        $this->name = $name;
        $this->args['title'] = Str::title(Str::replace(['-', '_'], ' ', $name)); // Default title based on name, can be overridden by calling title() method.
        $this->args = array_merge($this->args, $args);
    }

    /**
     * Sets the block variation as ready (or not) based on the current configuration.
     * Note: Block variations are always ready as they don't have any required configuration 
     * beyond the name and parent block, which are set in the constructor and of() method respectively.
     * 
     * This method is here to satisfy the contract of the Feature class only.
     * 
     * @return void
     */
    protected function setReady(): void {
        // Do nothing...
    }

    protected function load(Feature $instance): void {
        // Do nothing...
    }


    /***************************
     * Public Chainable methods
     ***************************/

    public function parent(string $parentBlock): self {
        return $this->of($parentBlock);
    }

    public function of(string $parentBlock): self {
        $this->parentBlock = $parentBlock;
        $this->initialised = true;

        add_filter(
            'get_block_type_variations', 

            function(array $variations, \WP_Block_Type $blockType) {
                if ($blockType->name !== $this->parentBlock) {
                    return $variations;
                }

                $variations[] = [
                    'name' => $this->name,
                    ...$this->args
                ];

                $this->loaded = true;
                return $variations;
            }, 10, 2
        );

        return $this;
    }

    public function name(string $name): self {
        if (!$this->initialised) {
            throw new \Exception($this->initError);
        }

        $this->name = $name;
        return $this;
    }

    public function title(string $title): self {
        if (!$this->initialised) {
            throw new \Exception($this->initError);
        }

        $this->args['title'] = $title;
        return $this;
    }

    public function description(string $description): self {
        if (!$this->initialised) {
            throw new \Exception($this->initError);
        }
        
        $this->args['description'] = $description;
        return $this;
    }

    public function category(string $category): self {
        if (!$this->initialised) {
            throw new \Exception($this->initError);
        }
        
        $this->args['category'] = $category;
        return $this;
    }

    public function keywords(array $keywords): self {
        if (!$this->initialised) {
            throw new \Exception($this->initError);
        }
        
        $this->args['keywords'] = $keywords;
        return $this;
    }

    public function icon(string $icon): self {
        if (!$this->initialised) {
            throw new \Exception($this->initError);
        }
        
        $this->args['icon'] = $icon;
        return $this;
    }

    public function attributes(array $attributes): self {
        if (!$this->initialised) {
            throw new \Exception($this->initError);
        }
        
        $this->args['attributes'] = $attributes;
        return $this;
    }

    public function innerBlocks(array $innerBlocks): self {
        if (!$this->initialised) {
            throw new \Exception($this->initError);
        }
        
        $this->args['innerBlocks'] = $innerBlocks;
        return $this;
    }

    public function example(array $example): self {
        if (!$this->initialised) {
            throw new \Exception($this->initError);
        }
        
        $this->args['example'] = $example;
        return $this;
    }

    public function scope(array $scope): self {
        if (!$this->initialised) {
            throw new \Exception($this->initError);
        }
        
        foreach($scope as $scopeItem) {
            if (!in_array($scopeItem, ['block', 'inserter', 'transform'])) {
                return $this; // Invalid scope item, ignore the scope configuration.
            }

            $this->args['scope'][] = $scopeItem;
        }

        return $this;
    }

    public function isDefault(bool $isDefault = true): self {
        if (!$this->initialised) {
            throw new \Exception($this->initError);
        }
        
        $this->args['isDefault'] = $isDefault;
        return $this;
    }

    public function isActive(array $conditions): self {
        if (!$this->initialised) {
            throw new \Exception($this->initError);
        }
        
        $this->args['isActive'] = $conditions;
        return $this;
    }
}