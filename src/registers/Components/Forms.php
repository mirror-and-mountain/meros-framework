<?php 

namespace MM\Meros\Registers\Components;

use Illuminate\Support\Collection;

use MM\Meros\Contracts\Register;
use MM\Meros\Contracts\Features\Components\Form;
use MM\Meros\Contracts\Features\Content\Interfaces\FormHost;

use MM\Meros\Contracts\Registers\RegistrarMaker;
use MM\Meros\Contracts\Registers\Concerns\IsRegistrarMaker;

use MM\Meros\Facades\Content\PostTypes;
use MM\Meros\Facades\Components\Forms as FormsFacade;

use MM\Meros\App\Models\Post;
use MM\Meros\App\Models\FormResponse;

class Forms extends Register implements RegistrarMaker {
    private Form|FormHost|null $currentForm = null;
    private string|int|null $currentFormId = null;

    use IsRegistrarMaker;

    protected function configure(): void {
        $this->contract(Form::class);
        $this->unique(true);
        $this->facade(FormsFacade::class);
        $this->identifierFormat('slug');
    }

    /**
     * Finds and sets the current form by its ID.
     *
     * @param string|integer $id
     *
     * @return static
     */
    public function find(string|int $id): static {
        if (is_string($id)) {
            $this->currentForm = $this->get($id);
            $this->currentFormId = $id;
        }

        else if (is_int($id)) {
            $model = Post::find($id);

            if ($model instanceof Post) {
                $postType = PostTypes::get($model->post_type);

                if ($postType instanceof FormHost) {
                    $this->currentForm = $postType;
                    $this->currentFormId = $id;
                }
            }
        }

        return $this;
    }

    /**
     * Sets the current form type by its post type.
     *
     * @param string $postType
     *
     * @return static
     */
    public function type(string $postType): static {
        $type = PostTypes::get($postType);

        if ($type instanceof FormHost) {
            $this->currentForm = $type;
        }

        return $this;
    }

    /**
     * Retrieves the structure of the current form.
     *
     * @param boolean $refresh
     *
     * @return array|null
     */
    public function structure(bool $refresh = false): ?array {
        if ($this->currentForm === null && $this->currentFormId === null) {
            return null;
        }

        if ($this->currentForm instanceof FormHost) {
            $structure = $this->currentForm->getFormStructure($this->currentFormId, $refresh);
            $this->clearCurrentForm();
            return $structure;
        }

        if ($this->currentForm instanceof Form) {
            $structure = $this->currentForm->serialize('storage');
            $this->clearCurrentForm();
            return $structure;
        }

        $this->clearCurrentForm();
        return null;
    }

    /**
     * Retrieves the actions of the current form.
     *
     * @param boolean $refresh
     *
     * @return array|null
     */
    public function actions(bool $refresh = false): ?array {
        if ($this->currentForm === null && $this->currentFormId === null) {
            return null;
        }

        if ($this->currentForm instanceof FormHost) {
            $actions = $this->currentForm->getFormActions($this->currentFormId, $refresh);
            $this->clearCurrentForm();
            return $actions;
        }

        $this->clearCurrentForm();
        return null;
    }

    /**
     * Retrieves the settings of the current form.
     *
     * @param boolean $refresh
     *
     * @return array|null
     */
    public function settings(bool $refresh = false): ?array {
        if ($this->currentForm === null && $this->currentFormId === null) {
            return null;
        }

        if ($this->currentForm instanceof FormHost) {
            $settings = $this->currentForm->getFormSettings($this->currentFormId, $refresh);
            $this->clearCurrentForm();
            return $settings;
        }

        $this->clearCurrentForm();
        return null;
    }

    /**
     * Retrieves the responses of the current form.
     *
     * @param boolean $collect
     *
     * @return Collection|array|null
     */
    public function responses(bool $collect = false): Collection|array|null {
        if ($this->currentForm === null || $this->currentFormId === null) {
            return null;
        }

        if ($this->currentForm instanceof FormHost) {
            $responses = FormResponse::where('form_id', $this->currentFormId)->get();
            $this->clearCurrentForm();
            return $collect ? collect($responses->toArray()) : $responses->toArray();
        }

        $this->clearCurrentForm();
        return null;
    }

    /**
     * Clears the current form and its ID.
     *
     * @return void
     */
    private function clearCurrentForm(): void {
        $this->currentForm = null;
        $this->currentFormId = null;
    }
}