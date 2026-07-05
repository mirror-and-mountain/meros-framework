<?php

namespace MM\Meros\App\FormActions;

use MM\Meros\Services\Contracts\Forms\FormAction;
use MM\Meros\Support\Integrations\CRM\SyncJob;
use MM\Meros\Support\Integrations\CRM\SyncJobRunner;
use MM\Meros\Support\MergeFields;

use MM\Meros\Facades\FieldGroups;
use MM\Meros\Facades\Framework;
use MM\Meros\Facades\Integrations;

final class RunCrmSyncJobs extends FormAction {
    public string $handle = 'run_crm_sync_jobs';

    public string $label = 'Run CRM sync jobs';

    public string $description = 'Runs one or more CRM sync jobs (object writes) using mapped form values or merge fields.';

    public function execute(array $submission, array $context = []): mixed {
        $jobs = is_array($this->config['jobs'] ?? null) ? $this->config['jobs'] : [];

        if ($jobs === []) {
            return [];
        }

        $syncJobs = [];

        foreach ($jobs as $jobData) {
            if (!is_array($jobData)) {
                continue;
            }

            $job = SyncJob::fromArray($jobData);

            if ($job->isValid()) {
                $syncJobs[] = $job;
            }
        }

        if ($syncJobs === []) {
            return [];
        }

        /** @var SyncJobRunner $runner */
        $runner = app(SyncJobRunner::class);

        return $runner->runMany($syncJobs, $submission, $context);
    }

    public function renderConfigurationDialog(array $formFields, array $currentConfig): string {
        $crmIntegrations = $this->getCrmIntegrationOptions();
        $sourceOptions = $this->buildSourceOptions($formFields);

        $jobsDefault = is_array($currentConfig['jobs'] ?? null) ? $currentConfig['jobs'] : [];

        if ($jobsDefault === []) {
            $defaultIntegration = array_key_exists('salesforce', $crmIntegrations)
                ? 'salesforce'
                : (array_key_first($crmIntegrations) ?? '');

            if ($defaultIntegration !== '') {
                $jobsDefault = [[
                    'integration_handle' => $defaultIntegration,
                    'object' => $defaultIntegration === 'salesforce' ? 'sobjects/Contact' : '',
                    'method' => 'POST',
                    'endpoint' => '',
                    'connection_label' => '',
                    'mappings' => [[
                        'target_field' => 'LastName',
                        'source' => 'merge:user_lastname',
                        'fallback' => 'Website Lead',
                    ]],
                ]];
            }
        }

        $fieldGroup = FieldGroups::checkout(Framework::get())->make(function ($fieldGroup) use ($crmIntegrations, $sourceOptions, $jobsDefault) {
            $fieldGroup->id('action-run-crm-sync-jobs-config');
            $fieldGroup->title('CRM Sync Jobs Configuration');

            $fieldGroup->field('repeater', function ($jobsRepeater) use ($crmIntegrations, $sourceOptions, $jobsDefault) {
                $jobsRepeater->id('jobs');
                $jobsRepeater->name('jobs');
                $jobsRepeater->label('Sync Jobs');
                $jobsRepeater->allowConfigure(true);
                $jobsRepeater->allowAdd(true);
                $jobsRepeater->allowRemove(true);
                $jobsRepeater->allowReorder(true);
                $jobsRepeater->configureRequiredFields(['integration_handle', 'object']);
                $jobsRepeater->addRowText('Add Sync Job');
                $jobsRepeater->configureRowText('Configure Job');
                $jobsRepeater->removeRowText('Remove Job');

                $jobsRepeater->field('select')
                    ->id('integration_handle')
                    ->name('integration_handle')
                    ->label('CRM Integration')
                    ->options(array_merge(['' => 'Select integration...'], $crmIntegrations));

                $jobsRepeater->field('text')
                    ->id('object')
                    ->name('object')
                    ->label('Object / Resource')
                    ->helpText('Examples: sobjects/Contact, contacts, leads');

                $jobsRepeater->customConfigurationDialog(function ($dialog) use ($crmIntegrations, $sourceOptions) {
                    $dialog->id('sync-job-config-dialog');
                    $dialog->title('Sync Job Details');

                    $dialog->field('select')
                        ->id('integration_handle')
                        ->name('integration_handle')
                        ->label('CRM Integration')
                        ->options(array_merge(['' => 'Select integration...'], $crmIntegrations));

                    $dialog->field('text')
                        ->id('object')
                        ->name('object')
                        ->label('Object / Resource')
                        ->helpText('Examples: sobjects/Contact, contacts, leads');

                    $dialog->field('select')
                        ->id('method')
                        ->name('method')
                        ->label('HTTP Method')
                        ->options([
                            'POST' => 'POST',
                            'PUT' => 'PUT',
                            'PATCH' => 'PATCH',
                        ])
                        ->default('POST');

                    $dialog->field('text')
                        ->id('endpoint')
                        ->name('endpoint')
                        ->label('Endpoint Override')
                        ->helpText('Optional. If blank, object/resource value is used as the endpoint.');

                    $dialog->field('text')
                        ->id('connection_label')
                        ->name('connection_label')
                        ->label('Connection Label')
                        ->helpText('Optional. Uses first active connection if blank.');

                    $dialog->field('select')
                        ->id('environment')
                        ->name('environment')
                        ->label('Environment')
                        ->options([
                            '' => 'Default / First Active',
                            'production' => 'Production',
                            'sandbox' => 'Sandbox',
                            'test' => 'Test',
                            'live' => 'Live',
                        ])
                        ->default('');

                    $dialog->field('repeater', function ($mappingsRepeater) use ($sourceOptions) {
                        $mappingsRepeater->id('mappings');
                        $mappingsRepeater->name('mappings');
                        $mappingsRepeater->label('Field Mappings');
                        $mappingsRepeater->allowConfigure(false);
                        $mappingsRepeater->allowAdd(true);
                        $mappingsRepeater->allowRemove(true);
                        $mappingsRepeater->allowReorder(true);
                        $mappingsRepeater->addRowText('Add Mapping');

                        $mappingsRepeater->field('text')
                            ->id('target_field')
                            ->name('target_field')
                            ->label('Target Field');

                        $mappingsRepeater->field('select')
                            ->id('source')
                            ->name('source')
                            ->label('Source')
                            ->options($sourceOptions);

                        $mappingsRepeater->field('text')
                            ->id('fallback')
                            ->name('fallback')
                            ->label('Fallback')
                            ->helpText('Optional fallback when source is empty.');
                    });
                }, []);

                $jobsRepeater->default($jobsDefault);
            });
        });

        return $fieldGroup->html();
    }

    private function getCrmIntegrationOptions(): array {
        $options = [];

        foreach (Integrations::checkout(Framework::get())->getRegistered() as $handle => $_) {
            $integration = Integrations::checkout(Framework::get())->makeFrom($handle);

            if ($integration->getCategory() !== 'crm') {
                continue;
            }

            $options[$handle] = $integration->getLabel();
        }

        return $options;
    }

    private function buildSourceOptions(array $formFields): array {
        $options = [
            '' => 'Select a source...',
        ];

        foreach ($formFields as $fieldName => $fieldLabel) {
            $options['form:' . $fieldName] = 'Form: ' . $fieldLabel;
        }

        foreach (MergeFields::get()->toOptions('string') as $mergeKey => $mergeLabel) {
            $options['merge:' . $mergeKey] = 'Merge: ' . $mergeLabel;
        }

        $options['value:'] = 'Static Value (prefix with value:)';

        return $options;
    }
}
