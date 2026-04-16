<?php 

namespace MM\Meros\App\Support\Fields;

/**
 * Used for field rendering in a non DataField context e.g. 
 * when the field is rendered on the site.
 * 
 * @see IsTextArea for available methods and properties.
 */
class Textarea extends Field {
    use Concerns\IsTextArea;
}