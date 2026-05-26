<?php 

namespace MM\Meros\App\Toolbox\Forms\Concerns;

use Illuminate\Support\Str;

use MM\Meros\App\Models\MerosForm as Form;

use MM\Meros\Facades\Fields;
use MM\Meros\Facades\FieldGroups;

trait ManagesFormSchema {
    /**
     * The form being built or edited.
     *
     * @var Form|null
     */
    public ?Form $form = null;

    /**
     * The ID of the form being built or edited.
     *
     * @var string|null
     */
    public ?string $formID = null;

    /**
     * The title of the form being built or edited.
     *
     * @var string
     */
    public string $formTitle = '';

    /**
     * The description of the form being built or edited.
     *
     * @var string
     */
    public string $formDescription = '';

    /**
     * The available field types for the form.
     *
     * @var array
     */
    public array $fieldTypes = [];

    /**
     * The available field categories for the form.
     *
     * @var array
     */
    public array $fieldCategories = [];

    /**
     * The available field groups for the form.
     *
     * @var array
     */
    public array $fieldGroups = [];

    /**
     * The form schema as an array.
     *
     * @var array
     */
    public array $schema = [];

    /**
     * The form's row payloads
     *
     * @var array
     */
    public array $rowPayloads = [];

    /**
     * The form's settings
     *
     * @var array
     */
    public array $settings = [];

    /**
     * Retrieves available field types from the Fields register 
     * and sets available field categories.
     *
     * @return void
     */
    private function initialiseFields(): void {
        foreach (Fields::getRegistered() as $handle => $fieldType) {
            if (!$fieldType::$showInFormBuilder) {
                continue;
            }

            $this->fieldTypes[ $handle ] = $fieldType;
            $category                    = $fieldType::getCategory();

            $this->fieldCategories[ $category ][ $handle ] = [
                'handle' => $handle,
                'class'  => $fieldType,
                'label'  => Str::title(Str::replace(['-', '_'], ' ', $handle)),
                'icon'   => $fieldType::getIcon(),
            ];
        }
    }

    /**
     * Retrieves available field groups from the FieldGroups register.
     *
     * @return void
     */
    private function initialiseFieldGroups(): void {
        foreach (FieldGroups::getRegistered() as $handle => $fieldGroup) {
            $this->fieldGroups[ $handle ] = [
                'handle' => $handle,
                'class'  => $fieldGroup,
                'label'  => Str::title(Str::replace(['-', '_'], ' ', $handle))
            ];
        }
    }

    /**
     * Loads a form schema from a JSON string or array and return it as an array.
     *
     * @param string|array $schema
     *
     * @return array
     */
    private function loadFormSchema(string|array $schema): array {
        $decoded = is_array($schema) ? $schema : json_decode($schema, true);

        if (!is_array($decoded)) {
            return [];
        }

        return $decoded;
    }

    public function getRichTextPayloads(): array {
        $richTextObjects = [];

        foreach ($this->rowPayloads as $rowIndex => $row) {
            if ($row['_type'] === 'group') {
                $group = $row['group'] ?? null;
                if ($group && !empty($group['description'])) {
                    $richTextObjects[] = [
                        'rt_id'   => $rowIndex,
                        'content' => $group['description'],
                    ];
                }

                $groupFields = $group['fields'] ?? [];
                foreach ($groupFields as $groupFieldIndex => $groupField) {
                    if (($groupField['handle'] ?? '') === 'rich_text') {
                        $richTextObjects[] = [
                            'rt_id'   => "{$rowIndex}_{$groupFieldIndex}",
                            'content' => $groupField['value'] ?? '',
                        ];
                    }
                }
            }

            else {
                foreach ($row['fields'] as $fieldIndex => $field) {
                    if (($field['handle'] ?? '') === 'rich_text') {
                        $richTextObjects[] = [
                            'rt_id'   => "{$rowIndex}_{$fieldIndex}",
                            'content' => $field['value'] ?? '',
                        ];
                    }
                }
            }
        }

        return $richTextObjects;
    }

    public function renderQuillContent(string $deltaJson): string {
        $ops = json_decode($deltaJson, true) ?? [];
        $html = '';

        foreach ($ops as $op) {
            $text = $op['insert'] ?? '';
            $attrs = $op['attributes'] ?? [];

            // Escape HTML
            $text = e($text);

            if (!empty($attrs['bold'])) {
                $text = "<strong>{$text}</strong>";
            }
            if (!empty($attrs['underline'])) {
                $text = "<u>{$text}</u>";
            }
            if (!empty($attrs['link'])) {
                $url = htmlspecialchars($attrs['link'], ENT_QUOTES, 'UTF-8');
                $text = "<a href=\"{$url}\" target=\"_blank\" rel=\"noopener\">{$text}</a>";
            }
            if (!empty($attrs['italic'])) {
                $text = "<em>{$text}</em>";
            }

            // Handle newlines (Quill uses "\n" as a separate insert)
            if ($text === "\n") {
                // If you are wrapping in <p>, you might skip this or use <br>
                // For a single root element, we often skip standalone newlines
                continue; 
            }

            $html .= $text;
        }

        return nl2br($html); // Convert newlines to <br> for HTML output
    }
}