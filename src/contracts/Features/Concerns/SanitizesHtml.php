<?php 

namespace MM\Meros\Contracts\Features\Concerns;

trait SanitizesHtml {
    /**
     * Removes HTML comments and surrounding whitespace without changing content.
     *
     * @param string $html The HTML string to sanitise.
     * @return string The sanitised HTML string.
     */
    protected function sanitizeHtml(string $html): string {
        $sanitized = preg_replace('/<!--(.|\s)*?-->/', '', $html);

        if (!is_string($sanitized)) {
            return trim($html);
        }

        return is_string($sanitized) ? trim($sanitized) : trim($html);
    }
}