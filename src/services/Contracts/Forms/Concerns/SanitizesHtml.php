<?php 

namespace MM\Meros\Services\Contracts\Forms\Concerns;

trait SanitizesHtml {

    /**
     * Sanitises the given HTML string by removing comments, extra whitespace, and normalising spaces.
     *
     * @param string $html The HTML string to sanitise.
     * @return string The sanitised HTML string.
     */
    private function sanitizeHtml(string $html): string {
        $sanitized = preg_replace('/<!--(.|\s)*?-->/', '', $html);

        if (!is_string($sanitized)) {
            return trim($html);
        }

        $sanitized = preg_replace('/>\s+</', '><', $sanitized);
        $sanitized = preg_replace('/\s+\/>/', '/>', $sanitized);
        $sanitized = preg_replace('/\s+>/', '>', $sanitized);
        $sanitized = preg_replace('/>\s+([^<]*?)\s+</', '>$1<', $sanitized);
        $sanitized = preg_replace('/\s{2,}/', ' ', $sanitized);

        return is_string($sanitized) ? trim($sanitized) : trim($html);
    }
}