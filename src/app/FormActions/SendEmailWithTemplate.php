<?php 

namespace MM\Meros\App\FormActions;

use MM\Meros\Services\Contracts\Forms\FormAction;
use MM\Meros\App\Models\EmailTemplate;

use MM\Meros\Facades\FieldGroups;
use MM\Meros\Facades\Framework;

final class SendEmailWithTemplate extends FormAction {
    /**
     * The unique handle of the form action.
     *
     * @var string
     */
    public string $handle = 'send_email_with_template';

    /**
     * A human-readable label for the form action.
     *
     * @var string
     */
    public string $label = 'Send an email using a template';

    /**
     * A description of what the form action does.
     *
     * @var string
     */
    public string $description = 'Sends an email using a specified template, allowing for dynamic content through merge tags.';

    /**
     * The ID of the email template to use.
     *
     * @var string
     */
    protected string $templateId = '';

    /**
     * The email template model instance to use when sending the email.
     *
     * @var EmailTemplate|null
     */
    protected ?EmailTemplate $template = null;

    /***************************
     * Public Chainable methods
     ***************************/

    /**
     * Sets the email template to use for this action by its ID.
     *
     * @param string $templateId
     *
     * @return static
     * @throws \InvalidArgumentException if the email template with the specified ID is not found.
     */
    public function template(string $templateId): static {
        $this->templateId = $templateId;
        $this->template = EmailTemplate::find($templateId);

        if (!$this->template) {
            throw new \InvalidArgumentException("Email template with ID '{$templateId}' not found.");
        }

        return $this;
    }

    /**
     * Sets the recipient(s) of the email.
     * 
     * @param string|array $to A single email address as a string or an array of email addresses for multiple recipients.
     * 
     * @return static
     */
    public function to(string|array $to): static {
        $this->config['to'] = $to;
        return $this;
    }

    /**
     * Sets the sender's email address.
     *
     * @param string $from
     *
     * @return static
     */
    public function from(string $from): static {
        $this->config['from'] = $from;
        return $this;
    }

    /**
     * Sets the carbon copy (CC) recipients of the email.
     *
     * @param array $cc An array of email addresses to send a carbon copy (CC) to.
     *
     * @return static
     */
    public function cc(array $cc): static {
        $this->config['cc'] = $cc;
        return $this;
    }

    /**
     * Sets the blind carbon copy (BCC) recipients of the email.
     *
     * @param array $bcc An array of email addresses to send a blind carbon copy (BCC) to.
     *
     * @return static
     */
    public function bcc(array $bcc): static {
        $this->config['bcc'] = $bcc;
        return $this;
    }

    /**
     * Sets the subject of the email.
     *
     * @param string $subject
     *
     * @return static
     */
    public function subject(string $subject): static {
        $this->config['subject'] = $subject;
        return $this;
    }

    /**
     * Sets the Reply-To email address for the email.
     *
     * @param string $replyTo The email address to use for the Reply-To header.
     *
     * @return static
     */
    public function replyTo(string $replyTo): static {
        $this->config['replyTo'] = $replyTo;
        return $this;
    }

    /**
     * Sets file attachments for the email.
     *
     * @param array $attachments An array of file paths to attach to the email.
     *
     * @return static
     */
    public function attachments(array $attachments): static {
        $this->config['attachments'] = $attachments;
        return $this;
    }

    /**
     * Sets the tag map for replacing merge tags in the email template content.
     *
     * @param array $tagMap An associative array mapping merge tag names to their replacement values, which will be used to replace any merge tags found in the template content before sending the email.
     *
     * @return static
     */
    public function tags(array $tagMap): static {
        $this->config['tagMap'] = $tagMap;
        return $this;
    }

    /**
     * Sends the email using the specified template and configuration options. 
     * This method will replace any merge tags in the template content with 
     * values from the 'tagMap' configuration before sending the email.
     *
     * @return bool True if the email was sent successfully, false otherwise.
     * @throws \RuntimeException if the email template is not set or the recipient email address(es) are not set.
     */
    public function send(): bool {
        if (!$this->template) {
            throw new \RuntimeException("Email template not set. Please set the template using the template() method before sending the email.");
        }

        if (empty($this->config['to'])) {
            throw new \RuntimeException("Recipient email address(es) not set. Please set the recipient(s) using the to() method before sending the email.");
        }

        return $this->template->send($this->config);
    }

    /***************************
     * Getters
     ***************************/

    /**
     * Retrieves the ID of the email template being used for this form action.
     *
     * @return string
     */
    public function getTemplateId(): string {
        return $this->templateId;
    }

    /**
     * Retrieves the email template model instance being used for this form action if set.
     *
     * @return EmailTemplate|null
     */
    public function getTemplate(): ?EmailTemplate {
        return $this->template;
    }

    /**
     * Renders the configuration dialogue for the form action, 
     * which will be displayed in the admin interface when 
     * configuring the form action for a form. 
     * 
     * This should return an HTML string containing the form 
     * fields for configuring the action's settings.
     * 
     * @param array $formFields    An array of the form's fields, which can be used to populate options in the configuration dialogue if needed.
     * @param array $currentConfig The current configuration for the form action, which can be used to prepopulate the configuration dialogue with existing values.
     *
     * @return string
     */
    public function renderConfigurationDialog(array $formFields, array $currentConfig): string {
        $html      = '';
        $template  = '';
        $mergeTags = [];
        $tagMap    = [];

        $templateOptions = EmailTemplate::all()
            ->where('post_status', 'publish')
            ->pluck('post_title', 'post_name')
            ->toArray();

        if ($currentConfig !== []) {
            $template = $currentConfig['action-send-email-template-config-template'] ?? null;

            if ($template) {
                $templateModel = EmailTemplate::where('post_name', $template)->first();

                if ($templateModel) {
                    $mergeTags = $templateModel->merge_tags ?? [];
                }
            }

            $tagMap = $currentConfig['action-send-email-template-config-tagmap'] ?? [];

            if (collect($tagMap)->pluck('tag_name')->toArray() !== $mergeTags) {
                $tagMap = [];
            }

            if ($tagMap === [] && $mergeTags !== []) {
                foreach ($mergeTags as $tag) {
                    $tagMap[] = [
                        'field_name' => '',
                        'tag_name' => $tag,
                    ];
                }
            }
        }

        $fieldGroup = FieldGroups::checkout(Framework::get())->make(function ($fieldGroup) use($templateOptions, $template, $mergeTags, $formFields, $tagMap) {
            $fieldGroup->id('action-send-email-template-config');
            $fieldGroup->title('Email Configuration');

            $fieldGroup->field('select')->id('action-send-email-template-config-template')
                ->label('Select Template')
                ->options(array_merge(['' => 'Select a template...'], $templateOptions))
                ->default($template !== '' ? $template : '')
                ->onChange('$store.formBuilder.refreshActionConfigurationDialog');

            if ($template !== '') {
                $fieldGroup->field('repeater', function ($repeater) use ($mergeTags, $formFields, $tagMap) {
                    $repeater->id('action-send-email-template-config-tagmap');
                    $repeater->name('tag_map');
                    $repeater->allowConfigure(false);
                    $repeater->allowAdd(false);
                    $repeater->allowRemove(false);
                    $repeater->allowReorder(false);

                    $repeater->field('select')
                        ->id('action-send-email-template-config-field-name')
                        ->name('field_name')
                        ->label('Field Name')
                        ->options(array_merge(['' => ''], $formFields));

                    $repeater->field('select')
                        ->id('action-send-email-template-config-tag-name')
                        ->name('tag_name')
                        ->label('Tag Name')
                        ->options(array_merge(['' => ''], $mergeTags));

                    $repeater->default($tagMap);
                });
            }
        });

        $html = $fieldGroup->html();
        return $html;
    }
}