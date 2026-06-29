<div
    x-data="{
        updateRuleProperty(rule, value) {
            const oppositeRuleName = rule.startsWith('min') 
                ? rule.replace(/^min/, 'max') 
                : rule.replace(/^max/, 'min');

            const oppositeRuleControl = document.querySelector(
                `[data-rule-control][data-rule-name='${oppositeRuleName}']`
            );

            if (oppositeRuleControl) {
                const oppositeValue = oppositeRuleControl.value !== '' ? parseInt(oppositeRuleControl.value) : null;
                
                if (oppositeValue) {
                    value = value !== '' ? parseInt(value) : null;

                    if (rule.startsWith('min') && value > oppositeValue || 
                        rule.startsWith('max') && value < oppositeValue
                    ) {
                        
                        $wire.updateActiveFieldProperty('rules', [
                            { rule: rule, value: value },
                            { rule: oppositeRuleName, value: value }
                        ]);

                        this.$nextTick(() => {
                            oppositeRuleControl.value = value !== null ? value : '';
                        });

                        return;
                    }
                }
            }

            $wire.updateActiveFieldProperty('rule', {
                rule: rule,
                value: value
            });
        },

        updateHint(type, value) {
            const hintProperty = type === 'min' ? 'showMinHint' : 'showMaxHint';
            $wire.updateActiveFieldProperty(hintProperty, value);
        },

        closeRulesEditor() {
            const ruleControls = document.querySelectorAll('[data-rule-control]');

            ruleControls.forEach(control => {
                const ruleName = control.dataset.ruleName;

                if (ruleName === 'type') {
                    return;
                }

                const value = control.value !== '' ? parseInt(control.value) : null;
                updateRuleProperty(ruleName, value);
            });

            const hintControls = document.querySelectorAll('.hint-control');

            hintControls.forEach(control => {
                const hintType = control.id.includes('min') ? 'min' : 'max';
                const value = control.checked ? true : false;
                updateHint(hintType, value);
            });

            $wire.closeRulesEditor();
        }
    }"
    id="meros-form-builder-rules-editor" 
    class="flex-1 h-full p-4 pb-25 overflow-y-auto overscroll-contain min-w-0 bg-slate-100" 
    wire:key="form-builder-rules-editor-{{ $activeFieldId }}"
>
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-bold text-slate-900">Editing Rules for Field: {{ $activeField->getLabel() }}</h2>
        @include('meros::toolbox.forms.builder.canvas.action-button', [
            'buttonText' => 'Update and Close',
            'action'     => '$wire.closeRulesEditor()',
        ])
    </div>
    <div class="min-h-96">
        @if($activeField !== null)
            {!! $activeField->getRuleControlsHtml() !!}


            @if(!$activeField->hasRule('min') && !$activeField->hasRule('max'))
                {{-- Minimum Hint --}}
                <div class="nice-form-group -mt-2">
                    <label for="field-show-min-hint" class="form-label">Show Minimum Hint</label>
                    <small class="whitespace-normal">Whether to show the minimum hint below the field.</small>
                    <input
                        id="field-show-min-hint"
                        class="switch hint-control"
                        type="checkbox"
                        @checked($activeField->showMinHint ?? false)
                        @change="updateHint('min', $event.target.checked);"
                    />
                </div>

                {{-- Maximum Hint --}}
                <div class="nice-form-group">
                    <label for="field-show-max-hint" class="form-label">Show Maximum Hint</label>
                    <small class="whitespace-normal">Whether to show the maximum hint below the field.</small>
                    <input
                        id="field-show-max-hint"
                        class="switch hint-control"
                        type="checkbox"
                        @checked($activeField->showMaxHint ?? true)
                        @change="updateHint('max', $event.target.checked);"
                    />
                </div>
            @endif
        @endif
    </div>
</div>