<?php

namespace MM\Meros\App\Models;

class MerosEmailTemplate extends Post {
    /**
     * Scope the query to only include posts of the 'meros-email-template' post type.
     *
     * @return void
     */
    public function newQuery() {
        return parent::newQuery()->where('post_type', 'meros-email-template');
    }

    /**
     * Gets the template's merge tags, either as a JSON string or as an associative array.
     *
     * @param boolean $asArray
     *
     * @return string|array
     */
    public function mergeTags(bool $asArray = true): string|array {
        $meta = null;
        $tags = null;

        try {
            $meta = $this->meta->where('meta_key', '_meros_email_template_meta')->first();
        } catch (\Exception $e) {
            return $asArray ? [] : json_encode([]);
        }

        if ($meta !== null) {
            $decoded = maybe_unserialize($meta->meta_value);
            $tags = $decoded['merge_tags'] ?? [];
            return $asArray ? json_decode($tags, true) : $tags;
        }

        if ($tags) {
            return $asArray ?  $tags : json_encode($tags);
        }
        
        return $asArray ? [] : json_encode([]);
    }

    /**
     * Sends an email using this template, replacing any merge tags in the content with values from the provided config.
     *
     * @param array $config An associative array of configuration options for sending the email, which may include:
     * - 'to' (string): The recipient email address(es), which can be a single email as a string or an array of emails for multiple recipients.
     * - 'subject' (string): The subject of the email.
     * - 'from' (string): The sender's email address.
     * - 'cc' (array): An array of email addresses to send a carbon copy (CC) to.
     * - 'bcc' (array): An array of email addresses to send a blind carbon copy (BCC) to.
     * - 'replyTo' (string): The email address to use for the Reply-To header.
     * - 'attachments' (array): An array of file paths to attach to the email.
     * - 'tagMap' (array): An associative array mapping merge tag names to their replacement values, which will be used to replace any merge tags found in the template content before sending the email.
     *
     * @return boolean
     */
    public function send(array $config): bool {
        // Check we have the required 'to' field
        $to = $config['to'] ?? '';
        
        if ($to === '') {
            return false;
        }

        // Get the template content and merge tags
        $tags = $this->mergeTags();
        $content = $this->post_content;

        // Parse tags
        foreach ($tags as $tag) {
            $placeholder = '{{' . $tag . '}}';
            $value = $config['tagMap'][ $tag ] ?? $this->resolveGlobalTagValue($tag);
            $content = str_replace($placeholder, $value, $content);
        }

        // Parse blocks
        $blocks = parse_blocks($content);

        // Use an email safe default font family
        $siteFontFamily = 'Arial, Helvetica, sans-serif';

        // Get CSS variables for resolving colors, spacing, etc.
        $cssVars = $this->getWpCssVariables();

        // Render the blocks to email-safe HTML and wrap in the email template view
        $formattedHtml = $this->formatAsEmailHtml($blocks, $siteFontFamily, $cssVars);

        // Remove whitespace between HTML tags to prevent unwanted gaps in email clients
        $formattedHtml = preg_replace('/>\s+</', '><', $formattedHtml);

        // Configure the subject and email headers
        $subject = $config['subject'] ?? $this->post_title ?? '';
        $headers = ['Content-Type: text/html; charset=UTF-8'];

        // Set the From header, defaulting to the site name and admin email if not provided
        $from = $config['from'] ?? (get_bloginfo('name') . ' <' . get_bloginfo('admin_email') . '>');
        if (is_string($from) && $from !== '') {
            $headers[] = 'From: ' . $from;
        }

        // Add any CC recipients
        foreach ((array) ($config['cc'] ?? []) as $cc) {
            if (is_string($cc) && $cc !== '') {
                $headers[] = 'Cc: ' . $cc;
            }
        }

        // Add any BCC recipients
        foreach ((array) ($config['bcc'] ?? []) as $bcc) {
            if (is_string($bcc) && $bcc !== '') {
                $headers[] = 'Bcc: ' . $bcc;
            }
        }

        // Add a Reply-To header if specified
        $replyTo = $config['replyTo'] ?? null;
        if (is_string($replyTo) && $replyTo !== '') {
            $headers[] = 'Reply-To: ' . $replyTo;
        }

        // Send the email using WordPress's wp_mail function, passing the formatted HTML content and headers
        return wp_mail($to, $subject, $formattedHtml, $headers, $config['attachments'] ?? []);
    }

    // =========================================================================
    // Email Formatting
    // =========================================================================

    /**
     * Renders all blocks to email-safe <tr> rows and wraps them in the email
     * template view.
     * 
     * @param array  $blocks The list of blocks to render as the email body content, which may include various block types such as groups, columns, headings, paragraphs, images, and buttons, each with their own attributes and inner blocks that need to be processed and converted into email-safe HTML.
     * @param string $fontFamily The default font family to apply to the email content, which is typically a web-safe font stack that ensures consistent rendering across different email clients.
     * @param array  $cssVars An associative array of resolved CSS variables from the theme, used to resolve any preset color slugs, font sizes, spacing values, or other style definitions referenced in the block attributes for accurate rendering in the email context.
     * 
     * @return string The complete HTML string for the email content, which includes the rendered blocks wrapped in a default email template structure with inline styles for fonts, colors, and layout to ensure compatibility with various email clients. The method also generates a preheader text based on the rendered content for improved email previews in inboxes.
     */
    private function formatAsEmailHtml(array $blocks, string $fontFamily, array $cssVars = []): string {
        $bodyRows  = $this->renderEmailBlocks($blocks, $cssVars, true);
        $preheader = $this->buildPreheaderText($bodyRows);

        return view('meros::email.default-template', [
            'fontFamily' => esc_html($fontFamily),
            'bodyRows'   => $bodyRows,
            'preheader'  => $preheader,
        ])->render();
    }

    // =========================================================================
    // Block Renderers
    // =========================================================================

    /**
     * Renders a list of blocks to email-safe HTML, dispatching each block to 
     * the appropriate renderer and applying block gap wrappers as needed.
     *
     * @param array   $blocks
     * @param array   $cssVars
     * @param boolean $asRow
     * @param string  $blockGap
     * @param string  $gapAxis
     *
     * @return string
     */
    private function renderEmailBlocks(
        array  $blocks,
        array  $cssVars,
        bool   $asRow = false,
        string $blockGap = '',
        string $gapAxis = 'vertical'
    ): string {
        $html = '';
        $renderedCount = 0;

        foreach ($blocks as $block) {
            if (empty($block['blockName'])) {
                continue; // skip null / whitespace blocks
            }

            $rendered = $this->renderEmailBlock($block, $cssVars, $asRow);
            if ($rendered === '') {
                continue;
            }

            if (!$asRow && $blockGap !== '' && $renderedCount > 0) {
                $gap = esc_attr($blockGap);
                if ($gapAxis === 'horizontal') {
                    $html .= sprintf(
                        '<div class="email-gap-item email-gap-horizontal" style="--email-gap:%1$s;margin-left:%1$s">%2$s</div>',
                        $gap,
                        $rendered
                    );
                } else {
                    $html .= sprintf(
                        '<div class="email-gap-item email-gap-vertical" style="--email-gap:%1$s;margin-top:%1$s">%2$s</div>',
                        $gap,
                        $rendered
                    );
                }
            } else {
                $html .= $rendered;
            }

            $renderedCount++;
        }
        return $html;
    }

    /**
     * Dispatches a single block to the appropriate renderer.
     * 
     * @param array $block The block data to render, which includes the block name, attributes, inner blocks, and any other relevant information needed for rendering the block in an email-safe format.
     * @param array $cssVars The resolved CSS variables from the theme, used to resolve any preset color slugs or style values defined in the block's attributes for accurate rendering in the email context.
     * @param bool  $asRow Whether the block is being rendered as a row within a larger structure, which affects how certain blocks like groups and columns are rendered (e.g., whether they should render as full-width sections or nested elements).
     * 
     * @return string The HTML string for the rendered block, which is generated by dispatching the block to the appropriate renderer method based on its block name. The method also handles wrapping non-structural blocks in a row wrapper when necessary to ensure proper layout in the email context.
     */
    private function renderEmailBlock(array $block, array $cssVars, bool $asRow = false): string {
        $name = $block['blockName'] ?? '';

        $rendered = match ($name) {
            'core/group'     => $this->renderGroupBlock($block, $cssVars, $asRow),
            'core/columns'   => $this->renderColumnsBlock($block, $cssVars, $asRow),
            'core/spacer'    => $this->renderSpacerBlock($block),
            'core/heading'   => $this->renderHeadingBlock($block, $cssVars),
            'core/paragraph' => $this->renderParagraphBlock($block, $cssVars),
            'core/image'     => $this->renderImageBlock($block, $cssVars),
            'core/buttons'   => $this->renderButtonsBlock($block, $cssVars),
            'core/button'    => $this->renderButtonBlock($block, $cssVars),
            default          => '',
        };

        // Non-structural top-level blocks need a row wrapper.
        if ($asRow && !in_array($name, ['core/group', 'core/columns'], true) && $rendered !== '') {
            return '<tr><td style="padding:24px;">' . $rendered . '</td></tr>';
        }

        return $rendered;
    }

    /**
     * core/group — section wrappers (meros-email-*) become <tr><td>; nested
     * groups become a styled <div>.
     * 
     * @param array $block The block data for the group block, which may include attributes like 'backgroundColor', 'textColor', 'layout', and style definitions that need to be resolved for proper rendering in the email context, as well as an 'innerBlocks' array containing the child blocks to render within the group structure.
     * @param array $cssVars The resolved CSS variables from the theme, used to resolve any preset color slugs or style values defined in the block's attributes for accurate color rendering in the email context.
     * @param bool  $asRow Whether the group block is being rendered as a row within a larger structure, which affects whether it should render as a full-width <tr><td> or as a nested <div>.
     * 
     * @return string The HTML string for the rendered group block, which consists of either a <tr><td> wrapper for section groups or a styled <div> for nested groups, with inline styles for background color, text color, padding, and layout as defined in the block's attributes. The method also handles special cases for header and footer sections to ensure consistent spacing and alignment in the email context.
     */
    private function renderGroupBlock(array $block, array $cssVars, bool $asRow): string {
        $attrs     = $block['attrs'] ?? [];
        $className = $attrs['className'] ?? '';
        $layout    = $attrs['layout'] ?? [];
        $style     = $attrs['style'] ?? [];
        $isSection = (bool) preg_match('/\bmeros-email-(header|body|footer)\b/', $className);
        $isHeaderOrFooter = (bool) preg_match('/\bmeros-email-(header|footer)\b/', $className);
        $blockGap = $this->resolveEffectiveBlockGap($attrs, $cssVars);

        $isHorizontalFlex = ($layout['type'] ?? '') === 'flex' && ($layout['orientation'] ?? 'horizontal') !== 'vertical';

        $innerHtml = $isHorizontalFlex
            ? $this->renderRowGroupAsTable($block['innerBlocks'] ?? [], $cssVars, $blockGap)
            : $this->renderEmailBlocks($block['innerBlocks'] ?? [], $cssVars, false, $blockGap, 'vertical');

        $bgColor = $this->resolveColorAttr($attrs['backgroundColor'] ?? '', $cssVars)
            ?: $this->resolveValue($style['color']['background'] ?? '', $cssVars);
        $textColor = $this->resolveColorAttr($attrs['textColor'] ?? '', $cssVars)
            ?: $this->resolveValue($style['color']['text'] ?? '', $cssVars);
        $padding  = $this->buildPaddingStyle($style['spacing']['padding'] ?? [], $cssVars);
        $radius   = $this->buildBorderRadiusStyle($style['border'] ?? [], $cssVars);

        if ($asRow || $isSection) {
            if ($isHeaderOrFooter) {
                $innerHtml = $this->normaliseSectionInnerSpacing($innerHtml);
            }

            $s = [];
            if ($bgColor)  $s[] = 'background-color:' . $bgColor;
            if ($textColor) $s[] = 'color:' . $textColor;
            $s[] = $padding ?: 'padding:24px';
            if ($isHeaderOrFooter) {
                $s[] = 'vertical-align:middle';
            }
            return sprintf('<tr><td style="%s">%s</td></tr>', implode(';', $s), $innerHtml);
        }

        // Nested group
        $s = ['display:block'];
        if ($bgColor)  $s[] = 'background-color:' . $bgColor;
        if ($textColor) $s[] = 'color:' . $textColor;
        if ($padding)  $s[] = $padding;
        if ($radius)   $s[] = $radius;
        foreach ($this->buildLayoutStyles($layout) as $ls) {
            $s[] = $ls;
        }
        $layoutType = $layout['type'] ?? '';
        $classAttr = in_array($layoutType, ['flex', 'grid'], true) ? ' class="email-stack-group"' : '';
        return sprintf('<div%s style="%s">%s</div>', $classAttr, implode(';', $s), $innerHtml);
    }

    /**
     * Renders a horizontal flex-style group as a table row for email clients.
     *
     * This method is used for row-style `core/group` blocks so side-by-side
     * content remains stable in Outlook and other clients with weak flex support.
     * It also applies a configurable horizontal gap and image-aware width tuning
     * for the common two-cell text+image pattern.
     *
     * @param array  $blocks The row group's inner block list to render as table cells.
     * @param array  $cssVars Resolved theme CSS variables used by nested block renderers.
     * @param string $blockGap The resolved block gap value used as inter-cell spacing.
     *
     * @return string Email-safe table HTML representing the row group.
     */
    private function renderRowGroupAsTable(array $blocks, array $cssVars, string $blockGap): string {
        $renderedBlocks = [];
        foreach ($blocks as $block) {
            if (empty($block['blockName'])) {
                continue;
            }

            $rendered = $this->renderEmailBlock($block, $cssVars, false);
            if ($rendered !== '') {
                $renderedBlocks[] = [
                    'name' => $block['blockName'],
                    'attrs' => $block['attrs'] ?? [],
                    'html' => $rendered,
                ];
            }
        }

        if (empty($renderedBlocks)) {
            return '';
        }

        $gap = $blockGap !== '' ? $blockGap : '20px';
        $count = count($renderedBlocks);

        $imageIndex = null;
        $imageCount = 0;
        foreach ($renderedBlocks as $index => $block) {
            if ($block['name'] === 'core/image') {
                $imageIndex = $index;
                $imageCount++;
            }
        }

        $isSingleImageRow = $count === 2 && $imageCount === 1 && $imageIndex !== null;

        $cells = '';
        foreach ($renderedBlocks as $index => $block) {
            $gapClass = $index > 0 ? ' email-row-cell-gap' : '';
            $gapStyle = $index > 0 ? ('--email-gap:' . esc_attr($gap) . ';padding-left:' . esc_attr($gap) . ';') : '';

            $widthAttr = '';
            $widthStyle = '';

            if ($isSingleImageRow && $index === $imageIndex) {
                $imageWidth = isset($block['attrs']['width']) ? (int) $block['attrs']['width'] : 0;
                $targetWidth = $imageWidth > 0 ? max(160, min(260, $imageWidth)) : 220;

                $widthAttr = sprintf(' width="%d"', $targetWidth);
                $widthStyle = 'width:' . $targetWidth . 'px;';
            }

            $cells .= sprintf(
                '<td class="email-row-cell%1$s"%2$s style="vertical-align:top;%3$s%4$s">%5$s</td>',
                $gapClass,
                $widthAttr,
                $gapStyle,
                $widthStyle,
                $block['html']
            );
        }

        return sprintf(
            '<table class="email-row-table email-stack-group" role="presentation" width="100%%" cellpadding="0" cellspacing="0" border="0" style="width:100%%;table-layout:auto;"><tr>%s</tr></table>',
            $cells
        );
    }

    /**
     * core/columns — renders as a presentation table; each core/column is a <td>.
     * 
     * @param array $block The block data for the columns block, which may include attributes like 'backgroundColor', 'textColor', and style definitions that need to be resolved for proper rendering in the email context, as well as an 'innerBlocks' array containing the column blocks to render within the table structure.
     * @param array $cssVars The resolved CSS variables from the theme, used to resolve any preset color slugs or style values defined in the block's attributes for accurate color rendering in the email context.
     * @param bool $asRow Whether the columns block is being rendered as a row within a larger structure.
     * 
     * @return string The HTML string for the rendered columns block, which consists of a table structure with inline styles for background color, text color, and padding as defined in the block's attributes, and includes each child column block rendered as a <td> cell within a single <tr>. The method also handles the application of column gaps and individual column styles for proper layout and spacing in the email context.
     */
    private function renderColumnsBlock(array $block, array $cssVars, bool $asRow): string {
        $attrs = $block['attrs'] ?? [];
        $style = $attrs['style'] ?? [];
        $columnGap = $this->resolveEffectiveBlockGap($attrs, $cssVars);
        $columns = $block['innerBlocks'] ?? [];
        if (empty($columns)) {
            return '';
        }

        $blockBg = $this->resolveColorValue(
            $attrs['backgroundColor'] ?? '',
            $style['color']['background'] ?? '',
            $cssVars
        );
        $blockText = $this->resolveColorValue(
            $attrs['textColor'] ?? '',
            $style['color']['text'] ?? '',
            $cssVars
        );
        $blockPadding = $this->buildPaddingStyle($style['spacing']['padding'] ?? [], $cssVars);

        $cells = '';
        foreach ($columns as $index => $column) {
            $ca    = $column['attrs'] ?? [];
            $cs    = $ca['style'] ?? [];
            $width = $ca['width'] ?? null;

            $s = ['vertical-align:top'];
            $s[] = $this->buildPaddingStyle($cs['spacing']['padding'] ?? [], $cssVars) ?: 'padding:8px';
            if ($index > 0 && $columnGap !== '') {
                $s[] = 'padding-left:' . $columnGap;
            }

            $bg = $this->resolveColorValue(
                $ca['backgroundColor'] ?? '',
                $cs['color']['background'] ?? '',
                $cssVars
            );
            if ($bg) $s[] = 'background-color:' . $bg;

            $tc = $this->resolveColorValue(
                $ca['textColor'] ?? '',
                $cs['color']['text'] ?? '',
                $cssVars
            );
            if ($tc) {
                $s[] = 'color:' . $tc;
            } elseif ($blockText) {
                $s[] = 'color:' . $blockText;
            }

            $wAttr = $width !== null ? sprintf(' width="%s"', esc_attr($width)) : '';
            $gapClass = ($index > 0 && $columnGap !== '') ? ' email-col-gap' : '';
            $cells .= sprintf(
                '<td class="email-col-cell%s"%s style="%s">%s</td>',
                $gapClass,
                $wAttr,
                implode(';', $s),
                $this->renderEmailBlocks(
                    $column['innerBlocks'] ?? [],
                    $cssVars,
                    false,
                    $this->resolveBlockGap($ca, $cssVars),
                    'vertical'
                )
            );
        }

        $tableStyles = [];
        if ($blockBg) {
            $tableStyles[] = 'background-color:' . $blockBg;
        }
        if ($blockText) {
            $tableStyles[] = 'color:' . $blockText;
        }
        if ($blockPadding) {
            $tableStyles[] = $blockPadding;
        }

        $table = sprintf(
            '<table class="email-col-table" role="presentation" width="100%%" cellpadding="0" cellspacing="0" border="0"%s><tr>%s</tr></table>',
            $tableStyles !== [] ? ' style="' . implode(';', $tableStyles) . '"' : '',
            $cells
        );

        return $asRow ? '<tr><td style="padding:0">' . $table . '</td></tr>' : $table;
    }

    /**
     * core/spacer — renders as a fixed-height spacer div.
     * 
     * @param array $block The block data for the spacer block, which may include attributes like 'height' that define the vertical space to render, and any style definitions that need to be resolved for proper rendering in the email context.
     * 
     * @return string The HTML string for the rendered spacer block, which consists of a <div> with inline styles to create vertical space in the email layout according to the specified height attribute, ensuring consistent spacing across different email clients.
     */
    private function renderSpacerBlock(array $block): string {
        $height = $block['attrs']['height'] ?? 24;
        if (is_numeric($height)) {
            $height .= 'px';
        }
        $h = esc_attr((string) $height);
        return sprintf('<div style="height:%1$s;line-height:%1$s;font-size:1px;">&nbsp;</div>', $h);
    }

    /**
     * core/heading — renders as an h1-h6 with full inline styles.
     * 
     * @param array $block The block data for the heading block, which may include attributes like 'level', 'content', 'textColor', 'backgroundColor', and style definitions that need to be resolved for proper rendering in the email context.
     * @param array $cssVars The resolved CSS variables from the theme, used to resolve any preset color slugs or style values defined in the block's attributes for accurate color rendering in the email context.
     * 
     * @return string The HTML string for the rendered heading block, which consists of an <h1> to <h6> element with inline styles for font size, color, background color, text alignment, and padding as defined in the block's attributes. The method also handles the extraction of heading content from either the 'content' attribute or the innerHTML of the block, and applies default font sizes based on the heading level if no custom font size is specified.
     */
    private function renderHeadingBlock(array $block, array $cssVars): string {
        $attrs   = $block['attrs'] ?? [];
        $level   = max(1, min(6, (int) ($attrs['level'] ?? 2)));
        $tag     = 'h' . $level;
        $content = $attrs['content'] ?? $this->extractTagInnerHtml($block['innerHTML'] ?? '');

        $defaultSizes = [1 => '36px', 2 => '28px', 3 => '22px', 4 => '18px', 5 => '16px', 6 => '14px'];
        $s = [
            'margin:0 0 12px',
            'line-height:1.3',
            'font-size:' . $defaultSizes[$level],
            'mso-line-height-rule:exactly',
        ];

        if (!empty($attrs['fontSize'])) {
            $size = $cssVars['--wp--preset--font-size--' . $attrs['fontSize']] ?? '';
            if ($size) {
                $s = array_values(array_filter($s, fn($v) => !str_starts_with($v, 'font-size:')));
                $s[] = 'font-size:' . $size;
            }
        } elseif (!empty($attrs['style']['typography']['fontSize'])) {
            $size = $this->resolveValue($attrs['style']['typography']['fontSize'], $cssVars);
            $s = array_values(array_filter($s, fn($v) => !str_starts_with($v, 'font-size:')));
            $s[] = 'font-size:' . $size;
        }

        $tc = $this->resolveColorAttr($attrs['textColor'] ?? '', $cssVars)
            ?: $this->resolveValue($attrs['style']['color']['text'] ?? '', $cssVars);
        if ($tc) $s[] = 'color:' . $tc;

        $bg = $this->resolveColorAttr($attrs['backgroundColor'] ?? '', $cssVars)
            ?: $this->resolveValue($attrs['style']['color']['background'] ?? '', $cssVars);
        if ($bg) $s[] = 'background-color:' . $bg;

        if (!empty($attrs['textAlign'])) $s[] = 'text-align:' . $attrs['textAlign'];

        $pad = $this->buildPaddingStyle($attrs['style']['spacing']['padding'] ?? [], $cssVars);
        if ($pad) $s[] = $pad;

        return sprintf('<%1$s style="%2$s">%3$s</%1$s>', $tag, implode(';', $s), $content);
    }

    /**
     * core/paragraph — renders as a <p> with full inline styles.
     * 
     * @param array $block The block data for the paragraph block, which may include attributes like 'content', 'textColor', 'backgroundColor', 'textAlign', and style definitions that need to be resolved for proper rendering in the email context.
     * @param array $cssVars The resolved CSS variables from the theme, used to resolve any preset color slugs or style values defined in the block's attributes for accurate color rendering in the email context.
     * 
     * @return string The HTML string for the rendered paragraph block, which consists of a <p> element with inline styles for margin, line height, font size, color, background color, and text alignment as defined in the block's attributes. The method also handles the extraction of paragraph content from either the 'content' attribute or the innerHTML of the block, and applies default styles for consistent spacing and readability in the email context.
     */
    private function renderParagraphBlock(array $block, array $cssVars): string {
        $attrs   = $block['attrs'] ?? [];
        $content = $attrs['content'] ?? $this->extractTagInnerHtml($block['innerHTML'] ?? '');
        $s = ['margin:0 0 16px', 'line-height:1.6', 'mso-line-height-rule:exactly'];

        if (!empty($attrs['fontSize'])) {
            $size = $cssVars['--wp--preset--font-size--' . $attrs['fontSize']] ?? '';
            if ($size) $s[] = 'font-size:' . $size;
        } elseif (!empty($attrs['style']['typography']['fontSize'])) {
            $s[] = 'font-size:' . $this->resolveValue($attrs['style']['typography']['fontSize'], $cssVars);
        }

        $tc = $this->resolveColorAttr($attrs['textColor'] ?? '', $cssVars)
            ?: $this->resolveValue($attrs['style']['color']['text'] ?? '', $cssVars);
        if ($tc) $s[] = 'color:' . $tc;

        $bg = $this->resolveColorAttr($attrs['backgroundColor'] ?? '', $cssVars)
            ?: $this->resolveValue($attrs['style']['color']['background'] ?? '', $cssVars);
        if ($bg) $s[] = 'background-color:' . $bg;

        if (!empty($attrs['textAlign'])) $s[] = 'text-align:' . $attrs['textAlign'];

        return sprintf('<p style="%s">%s</p>', implode(';', $s), $content);
    }

    /**
     * core/image — renders as a <figure> with an email-safe <img>.
     * 
     * @param array $block The block data for the image block, which may include attributes like 'url', 'alt', 'width', 'height', 'align', and style definitions that need to be resolved for proper rendering in the email context.
     * @param array $cssVars The resolved CSS variables from the theme, used to resolve any preset color slugs or style values defined in the block's attributes for accurate color rendering in the image caption or other style aspects.
     * 
     * @return string The HTML string for the rendered image block, which consists of a <figure> element containing an <img> with inline styles for dimensions and alignment, and an optional <figcaption> if a caption is provided in the block attributes. The method also handles various ways the image URL can be defined in the block attributes or innerHTML, and ensures that the image is rendered in an email-safe way with appropriate fallbacks and styling.
     */
    private function renderImageBlock(array $block, array $cssVars): string {
        $attrs = $block['attrs'] ?? [];
        $url = $attrs['url'] ?? '';

        if ($url === '' && !empty($attrs['id']) && function_exists('wp_get_attachment_image_url')) {
            $attachmentUrl = wp_get_attachment_image_url((int) $attrs['id'], $attrs['sizeSlug'] ?? 'full');
            if (is_string($attachmentUrl) && $attachmentUrl !== '') {
                $url = $attachmentUrl;
            }
        }

        if ($url === '' && !empty($block['innerHTML']) && preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $block['innerHTML'], $m)) {
            $url = $m[1];
        }

        $url = esc_url($url);
        if (!$url) {
            return '';
        }

        $alt     = esc_attr($attrs['alt'] ?? '');
        $href    = $attrs['href'] ?? '';
        $caption = $attrs['caption'] ?? '';
        $style   = $attrs['style'] ?? [];

        $width  = isset($attrs['width'])  ? (int) $attrs['width']  : null;
        $height = isset($attrs['height']) ? (int) $attrs['height'] : null;
            if ($width === null && !empty($attrs['id']) && function_exists('wp_get_attachment_metadata')) {
                $meta = wp_get_attachment_metadata((int) $attrs['id']);
                if (is_array($meta)) {
                    $metaWidth = isset($meta['width']) ? (int) $meta['width'] : null;
                    $metaHeight = isset($meta['height']) ? (int) $meta['height'] : null;
                    $width = $metaWidth ?: $width;
                    $height = $metaHeight ?: $height;
                }
            }

        if ($width !== null && $width > 680) {
            $scale  = 680 / $width;
            $width  = 680;
            $height = $height !== null ? (int) round($height * $scale) : null;
        }

        // Resolve alignment
        $align = $attrs['align'] ?? '';
        if (!$align) {
            $cls = $attrs['className'] ?? '';
            if (str_contains($cls, 'aligncenter'))      $align = 'center';
            elseif (str_contains($cls, 'alignleft'))    $align = 'left';
            elseif (str_contains($cls, 'alignright'))   $align = 'right';
        }

        $radius   = $this->buildBorderRadiusStyle($style['border'] ?? [], $cssVars);
        $imgStyle = $width !== null
            ? ('width:100%;max-width:' . (int) $width . 'px;height:auto;display:block;border:0;outline:none;text-decoration:none;' . ($radius ? $radius . ';' : ''))
            : ('max-width:100%;height:auto;display:block;border:0;outline:none;text-decoration:none;' . ($radius ? $radius . ';' : ''));

        $wAttr = $width  !== null ? " width=\"{$width}\""   : '';
        $hAttr = $height !== null ? " height=\"{$height}\"" : '';
        $img   = sprintf('<img src="%s" alt="%s"%s%s style="%s" />', $url, $alt, $wAttr, $hAttr, $imgStyle);

        if ($href) {
            $img = sprintf('<a href="%s" style="display:block;border:0;text-decoration:none;">%s</a>', esc_url($href), $img);
        }

        $figStyle = 'display:block;margin:0 0 16px';
        if ($align === 'center')     $figStyle .= ';text-align:center;margin-left:auto;margin-right:auto';
        elseif ($align === 'left')   $figStyle .= ';float:left;margin:0 16px 8px 0';
        elseif ($align === 'right')  $figStyle .= ';float:right;margin:0 0 8px 16px';

        $cap = $caption
            ? sprintf('<figcaption style="font-size:13px;color:#555;text-align:center;margin-top:6px;line-height:1.4;">%s</figcaption>', $caption)
            : '';

        return sprintf('<figure style="%s">%s%s</figure>', $figStyle, $img, $cap);
    }

    /**
     * core/buttons — wrapper that aligns its core/button children.
     * 
     * @param array $block The block data for the buttons block, which may include attributes like 'layout' that define the alignment and arrangement of the child button blocks, as well as any style definitions that need to be resolved for proper rendering in the email context.
     * @param array $cssVars The resolved CSS variables from the theme, used to resolve any preset color slugs or style values defined in the block's attributes for accurate color rendering in the
     * 
     * @return string The HTML string for the rendered buttons block, which consists of a container div with inline styles for alignment and spacing, and includes the rendered HTML of each child button block as defined in the block's innerBlocks.
     */
    private function renderButtonsBlock(array $block, array $cssVars): string {
        $layout = $block['attrs']['layout'] ?? [];
        $alignMap = ['left' => 'left', 'center' => 'center', 'right' => 'right'];
        $align = $alignMap[$layout['justifyContent'] ?? 'left'] ?? 'left';

        $html = '';
        foreach ($block['innerBlocks'] ?? [] as $btn) {
            $html .= $this->renderButtonBlock($btn, $cssVars);
        }

        $classAttr = (($layout['type'] ?? '') === 'flex') ? ' class="email-flex-group"' : '';
        return sprintf('<div%s style="text-align:%s;margin:16px 0">%s</div>', $classAttr, $align, $html);
    }

    /**
     * core/button — table-based button compatible with all major email clients.
     * 
     * @param array $block The block data for the button block, which may include attributes like 'text', 'url', 'backgroundColor', 'textColor', and style definitions that need to be resolved to render the button correctly.
     * @param array $cssVars The resolved CSS variables from the theme, used to resolve any preset color slugs or style values defined in the block's attributes for accurate color rendering in the email context.
     * 
     * @return string The HTML string for the rendered button, which consists of a table structure with inline styles to ensure compatibility across email clients, and includes the button text and link as defined in the block's attributes.
     */
    private function renderButtonBlock(array $block, array $cssVars): string {
        $attrs = $block['attrs'] ?? [];
        $text  = $attrs['text'] ?? '';
        $url   = esc_url($attrs['url'] ?? '#');

        // Fall back to parsing innerHTML if attrs are absent
        if (!$text && !empty($block['innerHTML'])) {
            if (preg_match('/<a[^>]*>(.*?)<\/a>/si', $block['innerHTML'], $m)) {
                $text = strip_tags($m[1]);
            }
            if ($url === esc_url('#') && preg_match('/<a[^>]+href="([^"]+)"/i', $block['innerHTML'], $m)) {
                $url = esc_url($m[1]);
            }
        }
        if (!$text) {
            return '';
        }

        $style = $attrs['style'] ?? [];
        $bg    = $this->resolveColorAttr($attrs['backgroundColor'] ?? '', $cssVars)
            ?: $this->resolveValue($style['color']['background'] ?? '', $cssVars)
            ?: '#0b57d0';
        $tc    = $this->resolveColorAttr($attrs['textColor'] ?? '', $cssVars)
            ?: $this->resolveValue($style['color']['text'] ?? '', $cssVars)
            ?: '#ffffff';

        $radius  = $this->buildBorderRadiusStyle($style['border'] ?? [], $cssVars);
        $rVal    = $radius ? str_replace('border-radius:', '', $radius) : '4px';
        $pad     = $this->buildPaddingStyle($style['spacing']['padding'] ?? [], $cssVars);
        $padVal  = $pad ? str_replace('padding:', '', $pad) : '12px 24px';

        $tdStyle = "background-color:{$bg};border-radius:{$rVal};padding:{$padVal};text-align:center";
        $aStyle  = "color:{$tc};text-decoration:none;font-weight:bold;display:inline-block;"
            . 'font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1';

        return sprintf(
            '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="display:inline-table;margin:4px">'
            . '<tr><td style="%s"><a href="%s" style="%s">%s</a></td></tr></table>',
            $tdStyle, $url, $aStyle, $text
        );
    }

    // =========================================================================
    // Style Helpers
    // =========================================================================

    /**
     * Resolves a WordPress preset colour slug to its concrete value via CSS vars.
     * If the preset name is empty or cannot be resolved, returns an empty string to 
     * allow for graceful fallback to other color definitions or defaults.
     * 
     * @param string $presetName The name of the color preset to resolve, typically from an attribute like 'backgroundColor' or 'textColor'.
     * @param array  $cssVars The resolved CSS variables from the theme, used to resolve the preset slug to a concrete color value.
     * 
     * @return string The resolved color value for the given preset name, or an empty string if the preset name is empty or cannot be resolved.
     */
    private function resolveColorAttr(string $presetName, array $cssVars): string {
        if (empty($presetName)) {
            return '';
        }
        $key = '--wp--preset--color--' . strtolower(str_replace([' ', '_'], '-', $presetName));
        return $cssVars[$key] ?? '';
    }

    /**
     * Resolves color from either preset slug attributes or style color values.
     * Preset attributes take precedence over style values, as they are more likely to be intentionally set by the user rather than default fallbacks.
     * 
     * @param string $presetName The name of the color preset to resolve, typically from an attribute like 'backgroundColor' or 'textColor'.
     * @param string $styleValue The CSS value from the block's style attribute, which may include var(--wp--*) tokens that also need to be resolved.
     * @param array  $cssVars The resolved CSS variables from the theme, used to resolve any var() tokens in the style value.
     * 
     * @return string The resolved color value, with preset slugs taking precedence over style values, and any var(--wp--*) tokens resolved to concrete values where possible.
     */
    private function resolveColorValue(string $presetName, string $styleValue, array $cssVars): string {
        return $this->resolveColorAttr($presetName, $cssVars)
            ?: $this->resolveValue($styleValue, $cssVars);
    }

    /**
     * Resolves any var(--wp--*) tokens inside a CSS value to their concrete values.
     * If a token cannot be resolved, it is left as-is to fall back to CSS variable support in email clients that have it.
     * 
     * @param string $value The CSS value to resolve, which may include var(--wp--*) tokens or WordPress preset token formats.
     * @param array $cssVars The resolved CSS variables from the theme, used to resolve any var() tokens in the value.
     * 
     * @return string The CSS value with any var(--wp--*) tokens resolved to concrete values where possible.
     */
    private function resolveValue(string $value, array $cssVars): string {
        if (empty($value)) {
            return '';
        }

        // Convert WordPress shorthand token format (var:preset|spacing|40) to concrete values.
        $value = preg_replace_callback(
            '/var:preset\|([a-z0-9\-]+)\|([a-z0-9\-]+)/i',
            static function ($m) use ($cssVars) {
                $key = '--wp--preset--' . strtolower($m[1]) . '--' . strtolower($m[2]);
                return $cssVars[$key] ?? ('var(' . $key . ')');
            },
            $value
        );

        return preg_replace_callback(
            '/var\((--wp--[a-z0-9\-]+)\)/i',
            static fn($m) => $cssVars[$m[1]] ?? $m[0],
            $value
        );
    }

    /**
     * Builds a CSS padding shorthand from a WordPress spacing padding array.
     * The padding array may include 'top', 'right', 'bottom', and 'left' values, which can be CSS values or WordPress preset tokens.
     * 
     * @param array $padding The spacing padding array from block attributes, which may include 'top', 'right', 'bottom', and 'left' keys with CSS values or WordPress preset tokens.
     * @param array $cssVars The resolved CSS variables from the theme, used to resolve any var() tokens in padding values.
     * 
     * @return string A CSS declaration for padding (e.g. 'padding:16px' or 'padding:16px 8px 16px 8px'), or an empty string if no padding is defined.
     */
    private function buildPaddingStyle(array $padding, array $cssVars): string {
        if (empty($padding)) {
            return '';
        }
        $t = $this->resolveValue($padding['top']    ?? '0', $cssVars);
        $r = $this->resolveValue($padding['right']  ?? '0', $cssVars);
        $b = $this->resolveValue($padding['bottom'] ?? '0', $cssVars);
        $l = $this->resolveValue($padding['left']   ?? '0', $cssVars);
        if ($t === $r && $r === $b && $b === $l) {
            return 'padding:' . $t;
        }
        return "padding:{$t} {$r} {$b} {$l}";
    }

    /**
     * Builds a CSS border-radius declaration from a WordPress border style array.
     * Supports both the shorthand 'radius' property and individual corner properties.
     * 
     * @param array $border The border style array from block attributes, which may include 'radius' as a string or an array with 'topLeft', 'topRight', 'bottomRight', and 'bottomLeft'.
     * @param array $cssVars The resolved CSS variables from the theme, used to resolve any var() tokens in radius values.
     * 
     * @return string A CSS declaration for border-radius (e.g. 'border-radius:4px' or 'border-radius:4px 0 4px 0'), or an empty string if no radius is defined.
     */
    private function buildBorderRadiusStyle(array $border, array $cssVars): string {
        if (empty($border)) {
            return '';
        }
        if (isset($border['radius'])) {
            if (is_string($border['radius'])) {
                return 'border-radius:' . $this->resolveValue($border['radius'], $cssVars);
            }
            if (is_array($border['radius'])) {
                $tl = $this->resolveValue($border['radius']['topLeft']     ?? '0', $cssVars);
                $tr = $this->resolveValue($border['radius']['topRight']    ?? '0', $cssVars);
                $br = $this->resolveValue($border['radius']['bottomRight'] ?? '0', $cssVars);
                $bl = $this->resolveValue($border['radius']['bottomLeft']  ?? '0', $cssVars);
                return "border-radius:{$tl} {$tr} {$br} {$bl}";
            }
        }
        return '';
    }

    /**
     * Builds display/layout CSS declarations for a WordPress block layout descriptor.
     * The layout descriptor can specify flex or grid layouts, and this function translates 
     * those into appropriate CSS with fallbacks for email client compatibility.
     * 
     * @param array $layout The layout descriptor from block attributes, which may include 'type', 'orientation', 'justifyContent', 'flexWrap', 'verticalAlignment', and 'columnCount' or 'minimumColumnWidth' for grids.
     * 
     * @return array An array of CSS declarations as strings (e.g. ['display:flex', 'justify-content:center']), including fallbacks for legacy email clients that may not support flex or grid.
     */
    private function buildLayoutStyles(array $layout): array {
        switch ($layout['type'] ?? '') {
            case 'flex':
                // Progressive enhancement: keep a block fallback first for legacy
                // clients, then enable flex for clients that support it.
                $s = ['display:block', 'display:flex'];

                if (($layout['orientation'] ?? 'horizontal') === 'vertical') {
                    $s[] = 'flex-direction:column';
                }

                $jMap = ['left' => 'flex-start', 'center' => 'center', 'right' => 'flex-end', 'space-between' => 'space-between'];
                if (isset($jMap[$layout['justifyContent'] ?? ''])) {
                    $s[] = 'justify-content:' . $jMap[$layout['justifyContent']];
                }

                $s[] = 'flex-wrap:' . (($layout['flexWrap'] ?? 'wrap') === 'nowrap' ? 'nowrap' : 'wrap');

                $aMap = ['top' => 'flex-start', 'center' => 'center', 'bottom' => 'flex-end'];
                if (isset($aMap[$layout['verticalAlignment'] ?? ''])) {
                    $s[] = 'align-items:' . $aMap[$layout['verticalAlignment']];
                }

                // Fallback alignment for clients that ignore flex.
                $alignMap = ['left' => 'left', 'center' => 'center', 'right' => 'right'];
                if (isset($alignMap[$layout['justifyContent'] ?? ''])) {
                    $s[] = 'text-align:' . $alignMap[$layout['justifyContent']];
                }
                return $s;

            case 'grid':
                // Progressive enhancement for grid-aware clients.
                $s = ['display:block', 'display:grid'];
                if (!empty($layout['columnCount'])) {
                    $s[] = 'grid-template-columns:repeat(' . (int) $layout['columnCount'] . ',1fr)';
                } elseif (!empty($layout['minimumColumnWidth'])) {
                    $s[] = 'grid-template-columns:repeat(auto-fill,minmax(' . $layout['minimumColumnWidth'] . ',1fr))';
                }
                return $s;

            default:
                return [];
        }
    }

    /**
     * Resolves Gutenberg blockGap from style/layout attributes into a concrete CSS length.
     * Checks block-specific style first, then layout, and resolves any var() tokens using the provided CSS variables.
     * 
     * @param array $attrs The block attributes which may contain style/layout gap settings.
     * @param array $cssVars The resolved CSS variables from the theme, used to resolve any var() tokens in gap values.
     * 
     * @return string The resolved block gap as a CSS length value (e.g. '20px'), or an empty string if no valid gap is found or if it contains unresolved var() tokens.
     */
    private function resolveBlockGap(array $attrs, array $cssVars): string {
        $gap = $attrs['style']['spacing']['blockGap']
            ?? $attrs['layout']['blockGap']
            ?? '';

        if (is_array($gap)) {
            $gap = $gap['top'] ?? $gap['vertical'] ?? $gap['left'] ?? '';
        }

        if (!is_string($gap) || trim($gap) === '') {
            return '';
        }

        $resolved = $this->resolveValue(trim($gap), $cssVars);

        // Email clients cannot resolve unresolved theme var() tokens reliably.
        if (str_contains($resolved, 'var(')) {
            return '';
        }

        return $resolved;
    }

    /**
     * Uses block-specific gap when present, otherwise falls back to theme global block gap.
     * If neither is set, returns a default of 24px which matches the default in the editor.
     * 
     * @param array $attrs The block attributes which may contain style/layout gap settings.
     * @param array $cssVars The resolved CSS variables from the theme, used to resolve any var() tokens in gap values.
     * 
     * @return string The effective block gap to use for spacing between blocks, as a CSS length value (e.g. '20px').
     */
    private function resolveEffectiveBlockGap(array $attrs, array $cssVars): string {
        $gap = $this->resolveBlockGap($attrs, $cssVars);
        if ($gap !== '') {
            return $gap;
        }

        $globalGap = $this->resolveValue($cssVars['--wp--style--block-gap'] ?? '', $cssVars);
        if ($globalGap !== '' && !str_contains($globalGap, 'var(')) {
            return $globalGap;
        }

        return '24px';
    }

    /**
     * Strips the outermost tag from an HTML fragment, returning the inner content.
     * 
     * This is a fallback for older blocks that don't provide content in attrs, 
     * but may still wrap content in a single tag in innerHTML. It won't unwrap multiple 
     * nested tags, but that's unlikely to be an issue since it's only intended for simple 
     * text blocks like headings and paragraphs that historically didn't always 
     * populate attrs.content.
     * 
     * @param string $html The HTML fragment from which to extract inner content.
     * 
     * @return string The inner HTML content with the outermost tag removed, or the original HTML if no single wrapping tag is detected.
     */
    private function extractTagInnerHtml(string $html): string {
        $html = trim($html);
        if (preg_match('/^<[^>]+>(.*)<\/[a-z][a-z0-9]*>$/si', $html, $m)) {
            return $m[1];
        }
        return $html;
    }

    /**
     * Header/footer sections usually contain a single heading. Removing default
     * trailing block margins keeps text visually centered within section padding.
     * 
     * @param string $html The rendered inner HTML of a header/footer group block, from which to normalise spacing.
     * 
     * @return string The HTML with normalised spacing for better header/footer appearance.
     */
    private function normaliseSectionInnerSpacing(string $html): string {
        $html = str_replace('margin:0 0 12px', 'margin:0', $html);
        return str_replace('margin:0 0 16px', 'margin:0', $html);
    }

    /**
     * Builds a readable preheader by preserving block boundaries as spaces.
     * 
     * @return string $html The full HTML of the email body, from which to extract the preheader text.
     */
    private function buildPreheaderText(string $html): string {
        $separated = preg_replace('/>\s*</', '> <', $html) ?? $html;
        $plain = trim(wp_strip_all_tags($separated));
        $plain = preg_replace('/\s+/', ' ', $plain) ?? $plain;

        return wp_html_excerpt($plain, 90, '...');
    }

    // =========================================================================
    // WordPress Helpers
    // =========================================================================

    /**
     * Collects all --wp--* CSS variables and their computed values from the theme
     * global stylesheet so that spacing/colour presets can be resolved inline.
     * 
     * @return array<string, string>
     */
    private function getWpCssVariables(): array {
        $cssVars = [];
        if (function_exists('wp_get_global_stylesheet')) {
            $css = wp_get_global_stylesheet();
            if (preg_match_all('/(--wp--[a-z0-9\-]+)\s*:\s*([^;]+);/i', $css, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $m) {
                    $cssVars[$m[1]] = trim($m[2]);
                }
            }
        }
        return $cssVars;
    }

    /**
     * Resolves the value for a given global merge tag.
     *
     * @param string $tag
     *
     * @return string
     */
    private function resolveGlobalTagValue(string $tag): string {
        if (str_starts_with($tag, 'user_')) {
            $user  = wp_get_current_user();
            $field = substr($tag, 5);
            return (string) ($user->{$field} ?? '');
        }

        return match ($tag) {
            'current_date' => date_i18n(get_option('date_format')),
            'site_name'    => get_bloginfo('name'),
            default        => '',
        };
    }
}