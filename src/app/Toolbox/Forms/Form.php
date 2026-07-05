<?php

namespace MM\Meros\App\Toolbox\Forms;

use Livewire\Component;

use MM\Meros\App\Models\Form as FormModel;
use MM\Meros\App\Models\PostMeta as FormMeta;

use MM\Meros\Services\Contracts\Forms\Field;
use MM\Meros\Services\Contracts\Forms\FormRow;
use MM\Meros\Services\Contracts\Forms\FieldGroup;

class Form extends Component {
    public bool $showTitle = false;
    public bool $showDescription = false;

    public string|int $formID = '';
    public ?FormModel $form = null;
    public string $formTitle = '';
    public string $formDescription = '';

    public array $schema = [];
    public array $rows = [];
    
    public int $activeGroupPage = 0;
    public int $totalGroupPages = 0;
    public string $groupPageDirection = 'forward';
    public bool $isPagedView = false;

    public function mount(string|int $formID, bool $showTitle = false, bool $showDescription = true, bool $isPagedView = false) {
        $this->formID = $formID;
        $this->showTitle = $showTitle;
        $this->showDescription = $showDescription;
        $this->isPagedView = $isPagedView;

        if ($this->formID) {
            $this->form = FormModel::find($formID);

            if ($this->form) {
                $this->loadFormSchema();

                foreach ($this->rows as $index => $row) {
                    if (!$row instanceof FormRow) {
                        $this->rows[$index] = FormRow::initFromData(array_merge($row, ['index' => $index]));
                    }
                }

                $this->recalculateGroupPages();
            }
        }
    }

    public function render() {
        $this->recalculateGroupPages();

        return view('meros::forms.form', [
            'showTitle' => $this->showTitle,
            'showDescription' => $this->showDescription,
            'formID' => $this->formID,
            'formTitle' => $this->formTitle,
            'formDescription' => $this->formDescription,
        ]);
    }

    public function submitForm(): void {
        dd('gere');
    }

    public function goToGroupPage(int $index): void {
        if ($this->totalGroupPages <= 0) {
            $this->activeGroupPage = 0;
            $this->groupPageDirection = 'forward';
            return;
        }

        $targetIndex = max(0, min($index, $this->totalGroupPages - 1));

        if ($targetIndex === $this->activeGroupPage) {
            return;
        }

        $this->groupPageDirection = $targetIndex > $this->activeGroupPage ? 'forward' : 'backward';
        $this->activeGroupPage = $targetIndex;
    }

    public function nextGroupPage(): void {
        $this->goToGroupPage($this->activeGroupPage + 1);
    }

    public function prevGroupPage(): void {
        $this->goToGroupPage($this->activeGroupPage - 1);
    }

    public function setPagedView(): void {
        $this->isPagedView = true;
        $this->recalculateGroupPages();
    }

    public function setFullView(): void {
        $this->isPagedView = false;
    }

    /**
     * Initialises form settings and schema from the form model, if it exists, 
     * or sets defaults for a new form.
     *
     * @return void
     */
    private function loadFormSchema(): void {
        if (!$this->form) {
            return;
        }

        $schema = is_array($this->form->schema) ? $this->form->schema : json_decode($this->form->schema, true);

        $this->schema = is_array($schema) ? $schema : [
            'rows'    => [],
            'actions' => []
        ];

        $this->rows = $this->schema['rows'] ?? [];
        $this->formTitle = $this->form->post_title ?? '';
        $this->formDescription = $this->form->post_content ?? '';
    }

    private function recalculateGroupPages(): void {
        $groupPages = 0;
        $hasUngroupedRows = false;

        foreach ($this->rows as $row) {
            if (($row->type ?? null) === 'group') {
                $groupPages++;
                continue;
            }

            $hasUngroupedRows = true;
        }

        $this->totalGroupPages = $groupPages + ($hasUngroupedRows ? 1 : 0);

        if ($this->totalGroupPages <= 0) {
            $this->activeGroupPage = 0;
            return;
        }

        if ($this->activeGroupPage > $this->totalGroupPages - 1) {
            $this->activeGroupPage = $this->totalGroupPages - 1;
        }

        if ($this->activeGroupPage < 0) {
            $this->activeGroupPage = 0;
        }
    }
}