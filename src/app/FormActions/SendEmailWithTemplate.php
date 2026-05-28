<?php 

namespace MM\Meros\App\FormActions;

use MM\Meros\Services\Contracts\FormAction;
use MM\Meros\App\Models\MerosEmailTemplate as EmailTemplate;

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

    /**
     * An associative array of configuration options for sending the email, which may include:
     * - 'to' (string): The recipient email address(es), which can be a single email as a string or an array of emails for multiple recipients.
     * - 'subject' (string): The subject of the email.
     * - 'from' (string): The sender's email address.
     * - 'cc' (array): An array of email addresses to send a carbon copy (CC) to.
     * - 'bcc' (array): An array of email addresses to send a blind carbon copy (BCC) to.
     * - 'replyTo' (string): The email address to use for the Reply-To header.
     * - 'attachments' (array): An array of file paths to attach to the email.
     * - 'tagMap' (array): An associative array mapping merge tag names to their replacement values, which will be used to replace any merge tags found in the template content before sending the email.
     *
     * @var array
     */
    protected array $config = [];

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
     * Sets the configuration options for sending the email.
     *
     * @param array $config
     *
     * @return static
     */
    public function config(array $config): static {
        $this->config = $config;
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
     * @return void
     * @throws \RuntimeException if the email template is not set or the recipient email address(es) are not set.
     */
    public function send(): void {
        if (!$this->template) {
            throw new \RuntimeException("Email template not set. Please set the template using the template() method before sending the email.");
        }

        if (empty($this->config['to'])) {
            throw new \RuntimeException("Recipient email address(es) not set. Please set the recipient(s) using the to() method before sending the email.");
        }

        $this->template->send($this->config);
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
     * Retrieves the current configuration array for this form action.
     *
     * @return array
     */
    public function getConfig(): array {
        return $this->config;
    }
}