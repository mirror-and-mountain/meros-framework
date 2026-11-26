<?php

namespace MM\Meros\App\Livewire\Concerns;

use Livewire\Attributes\Renderless;
use MM\Meros\Contracts\Features\Components\Form;

trait InteractsWithForms {
    abstract protected function makeMerosForm(string $formId): Form;

    #[Renderless]
    public function renderRepeaterEditForm(
        string $formId,
        string $name,
        string $originalName,
        array $rowData = [],
        array $currentValue = []
    ): string {
        $form = $this->makeMerosForm($formId);
        if ($form->getId() !== $formId) {
            throw new \InvalidArgumentException('Unable to locate the requested form.');
        }

        return $form->renderRepeaterEditForm($name, $originalName, $rowData, $currentValue);
    }
}
