<?php

namespace MM\Meros\Support;

/**
 * Handles the locking of form template components based on form template settings.
 */
class FormTemplateComponentLocks {
    public const SETTINGS = [
        '__properties_locked_by_template' => 'allow_edit_field_properties',
        '__move_locked_by_template'       => 'allow_move_components',
        '__remove_locked_by_template'     => 'allow_remove_components',
    ];

    /**
     * Returns the lock flags for a given component based on the template and settings.
     *
     * @param array      $component
     * @param array|null $template
     * @param array      $settings
     * @param boolean    $editingTemplate
     *
     * @return array
     */
    public static function flags(array $component, ?array $template, array $settings, bool $editingTemplate): array {
        $source = $editingTemplate ? $component : $template;
        $locks = [];
        foreach (self::SETTINGS as $flag => $setting) {
            $value = $source[$flag] ?? ($flag === '__properties_locked_by_template'
                ? ($source['__is_locked_by_template'] ?? $source['locked'] ?? false)
                : false);
            $locks[$flag] = $source !== null && (
                filter_var($value, FILTER_VALIDATE_BOOLEAN) ||
                (!$editingTemplate && !filter_var($settings[$setting] ?? true, FILTER_VALIDATE_BOOLEAN))
            );
        }
        return $locks;
    }

    /**
     * Returns the groups of child components within the given rows.
     *
     * @param array $rows
     *
     * @return array
     */
    public static function groups(array $rows): array {
        $groups = [];
        foreach ($rows as $row) {
            $group = $row['childGroup'] ?? null;
            if (!is_array($group) || empty($group['name'])) {
                continue;
            }
            $groups[$group['name']] = $group;
            $groups += self::groups($group['rows'] ?? []);
        }
        return $groups;
    }
}
