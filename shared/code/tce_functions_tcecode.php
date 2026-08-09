<?php

//============================================================+
// File name   : tce_functions_tcecode.php
// Begin       : 2002-01-09
// Last Update : 2025-06-13
//
// Description : Functions to translate TCExam code into XHTML.
//               The TCExam code is compatible to the common BBCode.
//               Supports LaTeX and MathML.
//
// License:
//    Copyright (C) 2004-2026 Nicola Asuni - Tecnick.com LTD
//    See LICENSE file for more information.
//============================================================+

/**
 * @file
 * Functions to translate TCExam proprietary code into XHTML.
 * The TCExam code is compatible to the common BBCode.
 * @package com.tecnick.tcexam.shared
 * @author Nicola Asuni
 * @since 2002-01-09
 */

/**
 * Decode the string submitted to the TCECode preview endpoint.
 */
function f_tcecode_preview_input(mixed $value): string
{
    if (!is_string($value)) {
        return '';
    }

    $value = str_replace('+', '~#PLUS#~', $value);
    $value = stripslashes(urldecode($value));
    return str_replace('~#PLUS#~', '+', $value);
}

/**
 * Returns XHTML code from text marked-up with TCExam Code Tags
 * @param $text_to_decode (string) text to convert
 * @return string XHTML code
 */
function F_decode_tcecode(mixed $text_to_decode): string
{
    require_once '../config/tce_config.php';
    global $l, $db;

    $text_to_decode = (string) $text_to_decode;

    // Patterns and replacements
    $pattern = [];
    $replacement = [];
    $i = 0;

    if (empty($text_to_decode)) {
        return '';
    }

    // Some external question banks store descriptions as HTML rather than
    // TCExam code. Render that markup through a strict allow-list instead of
    // showing the tags as text (or trusting imported HTML verbatim).
    if (F_has_html_markup($text_to_decode)) {
        return F_sanitize_html_content($text_to_decode);
    }

    // escape some special HTML characters
    $newtext = htmlspecialchars($text_to_decode ?? '', ENT_QUOTES, $l['a_meta_charset']);

    $newtext = F_bbcode_to_tcecode($newtext);

    // [tex]LaTeX_code[/tex]
    $newtext = preg_replace_callback("#\[tex\](.*?)\[/tex\]#si", 'f_latex_callback', $newtext) ?? $newtext;

    // [mathml]MathML_code[/mathml]
    $newtext = preg_replace_callback("#\[mathml\](.*?)\[/mathml\]#si", 'f_mathml_callback', $newtext) ?? $newtext;

    // [object]object_url[/object:width:height:alt]
    $newtext = preg_replace_callback(
        "#\[object\](.*?)\.(.*?)\[/object\:(.*?)\:(.*?)\:(.*?)\]#si",
        'F_objects_callback',
        $newtext,
    ) ?? $newtext;
    // [object]object_url[/object:width:height]
    $newtext = preg_replace_callback(
        "#\[object\](.*?)\.(.*?)\[/object\:(.*?)\:(.*?)\]#si",
        'F_objects_callback',
        $newtext,
    ) ?? $newtext;
    // [object]object_url[/object]
    $newtext = preg_replace_callback("#\[object\](.*?)\.(.*?)\[/object\]#si", 'F_objects_callback', $newtext) ?? $newtext;

    while (preg_match("'\[code\](.*?) (.*?)\[/code\]'si", $newtext)) {
        $newtext = preg_replace("'\[code\](.*?) (.*?)\[/code\]'si", "[code]\\1&nbsp;\\2[/code]", $newtext) ?? $newtext;
    }

    $newtext = F_tcecode_url($newtext);
    $newtext = F_tcecode_tag($newtext);
    $newtext = F_tcecode_tag_arg($newtext);

    if (empty($newtext)) {
        return '';
    }

    // Convert multiple spaces to &nbsp; to support indentation.
    preg_match_all('#[ ]{2,}#', $newtext, $matches);
    if (isset($matches[0])) {
        foreach ($matches[0] as $match) {
            $pos = strpos($newtext, $match);
            if ($pos !== false) {
                $len = strlen($match);
                $newtext = substr_replace($newtext, str_repeat('&nbsp;', $len), $pos, $len);
            }
        }
    }

    // line breaks
    $newtext = preg_replace("'(\r\n|\n|\r)'", '<br />', $newtext) ?? $newtext;
    $newtext = str_replace('<br /><li', '<li', $newtext);
    $newtext = str_replace('</li><br />', '</li>', $newtext);
    return str_replace('<br /><param', '<param', $newtext);
}

/**
 * Returns true when the content contains supported HTML markup.
 * Comparisons such as "x < y" must continue through the regular TCECode path.
 * @param $text (string) content to inspect
 * @return bool true for HTML content
 */
function f_has_html_markup(string $text): bool
{
    return preg_match(
        '/<\/?(?:a|b|blockquote|br|code|del|div|em|h[1-6]|hr|i|img|li|mark|ol|p|pre|s|small|span|strong|sub|sup|table|tbody|td|tfoot|th|thead|tr|u|ul)(?:\s|\/?>)/i',
        $text,
    ) === 1;
}

/**
 * Sanitize imported HTML while preserving useful question formatting.
 * @param $html (string) imported HTML fragment
 * @return string safe HTML fragment
 */
function F_sanitize_html_content(string $html): string
{
    if ($html === '' || !class_exists('DOMDocument')) {
        return htmlspecialchars($html, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    $document = new DOMDocument('1.0', 'UTF-8');
    $previous_errors = libxml_use_internal_errors(true);
    $loaded = $document->loadHTML(
        '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>'
            . '<div id="tce-imported-content">' . $html . '</div></body></html>',
        LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING,
    );
    libxml_clear_errors();
    libxml_use_internal_errors($previous_errors);
    if (!$loaded) {
        return htmlspecialchars($html, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    $container = $document->getElementById('tce-imported-content');
    if (!$container instanceof DOMElement) {
        return htmlspecialchars($html, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    F_sanitize_html_node($container);
    $result = '';
    foreach ($container->childNodes as $child) {
        $result .= $document->saveHTML($child);
    }

    return $result;
}

/**
 * Recursively sanitize one imported HTML branch.
 * @param $parent (DOMNode) branch to sanitize
 * @return void
 */
function F_sanitize_html_node(DOMNode $parent): void
{
    $allowed_tags = [
        'a', 'b', 'blockquote', 'br', 'code', 'del', 'div', 'em', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'hr',
        'i', 'img', 'li', 'mark', 'ol', 'p', 'pre', 's', 'small', 'span', 'strong', 'sub', 'sup', 'table',
        'tbody', 'td', 'tfoot', 'th', 'thead', 'tr', 'u', 'ul',
    ];
    $drop_with_contents = ['applet', 'embed', 'form', 'iframe', 'math', 'object', 'script', 'style', 'svg', 'template'];

    for ($node = $parent->firstChild; $node !== null;) {
        $next = $node->nextSibling;
        if ($node instanceof DOMComment) {
            $parent->removeChild($node);
        } elseif ($node instanceof DOMElement) {
            $tag = strtolower($node->tagName);
            if (in_array($tag, $drop_with_contents, true)) {
                $parent->removeChild($node);
            } elseif (!in_array($tag, $allowed_tags, true)) {
                F_sanitize_html_node($node);
                while ($node->firstChild !== null) {
                    $parent->insertBefore($node->firstChild, $node);
                }

                $parent->removeChild($node);
            } else {
                F_sanitize_html_attributes($node, $tag);
                F_sanitize_html_node($node);
            }
        }

        $node = $next;
    }
}

/**
 * Keep only safe attributes used by formatted questions.
 * @param $element (DOMElement) element to sanitize
 * @param $tag (string) normalized tag name
 * @return void
 */
function F_sanitize_html_attributes(DOMElement $element, string $tag): void
{
    $allowed = ['dir', 'lang', 'style', 'title'];
    if ($tag === 'a') {
        $allowed = array_merge($allowed, ['href', 'target']);
    } elseif ($tag === 'img') {
        $allowed = array_merge($allowed, ['alt', 'height', 'src', 'width']);
    } elseif ($tag === 'td' || $tag === 'th') {
        $allowed = array_merge($allowed, ['colspan', 'rowspan', 'scope']);
    } elseif ($tag === 'ol') {
        $allowed[] = 'start';
    }

    foreach (iterator_to_array($element->attributes) as $attribute) {
        $name = strtolower($attribute->name);
        if (!in_array($name, $allowed, true)) {
            $element->removeAttributeNode($attribute);
            continue;
        }

        if (($name === 'href' || $name === 'src') && !F_is_safe_html_url($attribute->value, $name === 'src')) {
            $element->removeAttributeNode($attribute);
        } elseif ($name === 'style') {
            $style = F_sanitize_html_style($attribute->value);
            if ($style === '') {
                $element->removeAttribute('style');
            } else {
                $element->setAttribute('style', $style);
            }
        } elseif (in_array($name, ['height', 'width', 'colspan', 'rowspan', 'start'], true)
            && preg_match('/^\d{1,4}$/', $attribute->value) !== 1
        ) {
            $element->removeAttributeNode($attribute);
        } elseif ($name === 'target' && !in_array($attribute->value, ['_blank', '_self'], true)) {
            $element->removeAttributeNode($attribute);
        }
    }

    if ($tag === 'a' && $element->getAttribute('target') === '_blank') {
        $element->setAttribute('rel', 'noopener noreferrer');
    }
}

/**
 * Validate a URL found in imported HTML.
 * @param $url (string) URL to validate
 * @param $image (bool) true for an image source
 * @return bool true when safe to render
 */
function f_is_safe_html_url(string $url, bool $image = false): bool
{
    $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($url === '' || preg_match('/[\x00-\x20]/', $url) === 1) {
        return false;
    }

    if ($url[0] === '/' || $url[0] === '#' || str_starts_with($url, './') || str_starts_with($url, '../')) {
        return true;
    }

    if (preg_match('/^https?:\/\//i', $url) === 1) {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    return !$image && preg_match('/^mailto:[^\s@]+@[^\s@]+$/i', $url) === 1;
}

/**
 * Retain a small, non-executable subset of inline presentation styles.
 * @param $style (string) style declaration
 * @return string safe style declaration
 */
function f_sanitize_html_style(string $style): string
{
    $allowed = [
        'font-style' => ['italic', 'normal'],
        'font-weight' => ['bold', 'normal'],
        'text-align' => ['center', 'justify', 'left', 'right'],
        'text-decoration' => ['line-through', 'none', 'underline'],
        'vertical-align' => ['baseline', 'middle', 'sub', 'super', 'text-bottom', 'text-top'],
        'white-space' => ['normal', 'pre', 'pre-line', 'pre-wrap'],
    ];
    $safe = [];
    foreach (explode(';', $style) as $declaration) {
        $parts = explode(':', $declaration, 2);
        if (count($parts) !== 2) {
            continue;
        }

        $property = strtolower(trim($parts[0]));
        $value = strtolower(trim($parts[1]));
        if (isset($allowed[$property]) && in_array($value, $allowed[$property], true)) {
            $safe[] = $property . ': ' . $value;
        }
    }

    return implode('; ', $safe);
}

// ============================================================

/**
 * Convert some BBCode-style to TCECode.
 * @param string $text
 * @return string
 */
function f_bbcode_to_tcecode(string $text): string
{
    // [*]list item - convert to new [li] tag
    $text = preg_replace("'\[\*\](.*?)\n'i", "[li]\\1[/li]", $text) ?? $text;
    // [img]image[/img] - convert to new object tag
    $text = preg_replace("'\[img\](.*?)\[/img\]'si", "[object]\\1[/object]", $text) ?? $text;
    // [img=WIDTHxHEIGHT]image[/img] - convert to new object tag
    return preg_replace("'\[img=(.*?)x(.*?)\](.*?)\[/img\]'si", "[object]\\3[/object:\\1:\\2]", $text) ?? $text;
}

/**
 * Convert [url]...[/url] and [url=...]...[/url] to HTML anchor tags.
 * @param string $text
 * @return string
 */
function F_tcecode_url(string $text): string
{
    if (empty($text)) {
        return '';
    }
    $text = preg_replace_callback(
        '#\[url\](.*?)\[/url\]#si',
        static function (array $matches): string {
            $url = $matches[1] ?? '';
            // Optionally validate URL
            if (!preg_match('/^https?:\/\//i', $url) || !filter_var($url, FILTER_VALIDATE_URL)) {
                return $url;
            }
            return '<a class="tcecode" href="' . $url . '" rel="noopener noreferrer" target="_blank">' . $url . '</a>';
        },
        $text,
    ) ?? $text;
    return preg_replace_callback(
        '#\[url=(.*?)\](.*?)\[/url\]#si',
        static function (array $matches): string {
            $url = $matches[1] ?? '';
            $label = $matches[2] ?? '';
            if (!preg_match('/^https?:\/\//i', $url) || !filter_var($url, FILTER_VALIDATE_URL)) {
                return $label;
            }
            return (
                '<a class="tcecode" href="' . $url . '" rel="noopener noreferrer" target="_blank">' . $label . '</a>'
            );
        },
        $text,
    ) ?? $text;
}

/**
 * Convert TCECode simple tags to XHTML tags.
 * @param string $text
 * @return string
 */
function F_tcecode_tag(string $text): string
{
    // Patterns and replacements
    $tag = [
        '#\[dir=ltr\](.*?)\[/dir\]#si' => '<span dir="ltr">\1</span>',
        '#\[dir=rtl\](.*?)\[/dir\]#si' => '<span dir="rtl">\1</span>',
        "#\[small\](.*?)\[/small\]#si" => '<small class="tcecode">\1</small>',
        "#\[b\](.*?)\[/b\]#si" => '<strong class="tcecode">\1</strong>',
        "#\[i\](.*?)\[/i\]#si" => '<em class="tcecode">\1</em>',
        "#\[s\](.*?)\[/s\]#si" => '<span style="text-decoration:line-through;">\1</span>',
        "#\[u\](.*?)\[/u\]#si" => '<span style="text-decoration:underline;">\1</span>',
        "#\[o\](.*?)\[/o\]#si" => '<span style="text-decoration:overline;">\1</span>',
        "#\[sub\](.*?)\[/sub\]#si" => '<sub class="tcecode">\1</sub>',
        "#\[sup\](.*?)\[/sup\]#si" => '<sup class="tcecode">\1</sup>',
        "#\[ulist\](.*?)\[/ulist\]#si" => '<ul class="tcecode">\1</ul>',
        "#\[olist\](.*?)\[/olist\]#si" => '<ol class="tcecode">\1</ol>',
        "#\[olist=1\](.*?)\[/olist\]#si" => '<ol class="tcecode" style="list-style-type:arabic-numbers">\1</ol>',
        "#\[olist=a\](.*?)\[/olist\]#si" => '<ol class="tcecode" style="list-style-type:lower-alpha">\1</ol>',
        "#\[li\](.*?)\[/li\]#si" => '<li class="tcecode">\1</li>',
        "#\[code\](.*?)\[/code\]#si" => '<div class="tcecodepre">\1</div>',
    ];

    foreach ($tag as $pattern => $replacement) {
        if (empty($text)) {
            break;
        }
        $text = preg_replace_callback(
            $pattern,
            static fn(array $matches): string => str_replace('\1', $matches[1] ?? '', $replacement),
            $text,
        ) ?? $text;
    }

    return $text;
}

/**
 * Convert TCECode tags with arguments to XHTML tags.
 * @param string $text
 * @return string
 */
function F_tcecode_tag_arg(string $text): string
{
    // Patterns and replacements
    $tag = [
        "#\[align=(left|right|center|justify)\](.*?)\[/align\]#si" => '<span style="text-align:\1;">\2</span>',
        "#\[color=(\#[0-9a-fA-F]{6})\](.*?)\[/color\]#si" => '<span style="color:\1">\2</span>',
        "#\[color=(rgb\(\d{1,3},\d{1,3},\d{1,3}\))\](.*?)\[/color\]#si" => '<span style="color:\1">\2</span>',
        "#\[color=([a-zA-Z]+)\](.*?)\[/color\]#si" => '<span style="color:\1">\2</span>',
        "#\[bgcolor=(\#[0-9a-fA-F]{6})\](.*?)\[/bgcolor\]#si" => '<span style="background-color:\1">\2</span>',
        "#\[bgcolor=(rgb\(\d{1,3},\d{1,3},\d{1,3}\))\](.*?)\[/bgcolor\]#si" => '<span style="background-color:\1">\2</span>',
        "#\[bgcolor=([a-zA-Z]+)\](.*?)\[/bgcolor\]#si" => '<span style="background-color:\1">\2</span>',
        "#\[font=([a-zA-Z0-9 \-_,]+)\](.*?)\[/font\]#si" => '<span style="font-family:\1">\2</span>',
        "#\[size=([+\-]?[0-9a-z\-]+[%]?)\](.*?)\[/size\]#si" => '<span style="font-size:\1">\2</span>',
    ];

    foreach ($tag as $pattern => $replacement) {
        if (empty($text)) {
            break;
        }
        $text = preg_replace_callback(
            $pattern,
            static fn(array $matches): string => str_replace(
                ['\1', '\2'],
                [$matches[1] ?? '', $matches[2] ?? ''],
                $replacement,
            ),
            $text,
        ) ?? $text;
    }

    return $text;
}

// ============================================================

/**
 * Run a renderer without invoking a command shell.
 *
 * @param list<string> $command Executable and arguments.
 * @return array{0:int,1:string} Exit status and combined diagnostic output.
 */
function F_tcecode_run_process(array $command, string $working_directory): array
{
    if ($working_directory === '') {
        return [1, 'renderer working directory is empty'];
    }
    $stdout_file = tempnam(sys_get_temp_dir(), 'openvsosh-render-out-');
    $stderr_file = tempnam(sys_get_temp_dir(), 'openvsosh-render-err-');
    if ($stdout_file === false || $stderr_file === false) {
        if (is_string($stdout_file) && is_file($stdout_file)) {
            unlink($stdout_file);
        }
        if (is_string($stderr_file) && is_file($stderr_file)) {
            unlink($stderr_file);
        }
        return [1, 'unable to create renderer diagnostic files'];
    }
    if ($stdout_file === '' || $stderr_file === '') {
        if ($stdout_file !== '') {
            unlink($stdout_file);
        }
        if ($stderr_file !== '') {
            unlink($stderr_file);
        }
        return [1, 'renderer diagnostic file path is empty'];
    }

    $null_device = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
    $descriptors = [
        0 => ['file', $null_device, 'r'],
        1 => ['file', $stdout_file, 'w'],
        2 => ['file', $stderr_file, 'w'],
    ];
    $launch_error = '';
    set_error_handler(static function (int $_severity, string $message) use (&$launch_error): bool {
        $launch_error = $message;
        return true;
    });
    $pipes = [];
    try {
        $process = proc_open($command, $descriptors, $pipes, $working_directory);
    } finally {
        restore_error_handler();
    }
    if (is_resource($process)) {
        $launched = true;
        $status = proc_close($process);
    } else {
        $launched = false;
        $status = 1;
    }
    $output = (string) file_get_contents($stdout_file) . (string) file_get_contents($stderr_file);
    if (!$launched && $launch_error !== '') {
        $output .= $launch_error;
    }
    unlink($stdout_file);
    unlink($stderr_file);
    return [$status, $output];
}

/**
 * Callback function for preg_replace_callback (LaTeX replacement).
 * Returns replacement image for LaTeX code.
 * @param $matches (string) array containing matches: $matches[0] is the complete match, $matches[1] the match for the first subpattern enclosed in '(...)' (the LaTeX code)
 * @return string replacement HTML code string to include the equivalent LaTeX image.
 */
function f_latex_callback(mixed $matches): mixed
{
    require_once '../../shared/config/tce_latex.php';
    $picture_path = (string) K_LATEX_PATH_PICTURE;
    // extract latex code and convert some entities
    $latex = unhtmlentities($matches[1], true);

    $dr = 3; // density ratio
    // generate file name
    $filename = K_LATEX_IMG_PREFIX . md5($latex);
    $imgpath = K_LATEX_PATH_PICTURE . $filename;
    $imgurl = false;
    $error = '';
    // check if file is already cached
    if (is_file($imgpath . '.' . K_LATEX_IMG_FORMAT)) {
        $imgurl = K_LATEX_PATH_PICTURE_HTTPD . $filename . '.' . K_LATEX_IMG_FORMAT;
    } elseif (strlen($latex) > K_LATEX_MAX_LENGHT) {
        // check if the formula
        $error = 'the formula is too long';
    } elseif (
        preg_match(
            '/(include|def|command|loop|repeat|open|toks|output|input|catcode|name|[\^]{2}|\\\\every|\\\\errhelp|\\\\errorstopmode|\\\\scrollmode|\\\\nonstopmode|\\\\batchmode|\\\\read|\\\\write|csname|\\\\newhelp|\\\\uppercase|\\\\lowercase|\\\\relax|\\\\aftergroup|\\\\afterassignment|\\\\expandafter|\\\\noexpand|\\\\special)/i',
            $latex,
        ) > 0
    ) {
        $error = 'invalid command';
    } else {
        // wrap the formula
        $ltx = '\nonstopmode' . "\n";
        $ltx .= '\documentclass{' . K_LATEX_CLASS . '}' . "\n";
        $ltx .= '\usepackage[T1]{fontenc}' . "\n";
        $ltx .= '\usepackage{amsmath,amsfonts,amssymb,wasysym,latexsym,marvosym,txfonts}' . "\n";
        $ltx .= '\usepackage[pdftex]{color}' . "\n";
        $ltx .= '\pagestyle{empty}' . "\n";
        $ltx .= '\begin{document}' . "\n";
        $ltx .= '\fontsize{' . K_LATEX_FONT_SIZE . '}{' . (2 * K_LATEX_FONT_SIZE) . '}' . "\n";
        $ltx .= '\selectfont' . "\n";
        $ltx .= '\color{black}' . "\n";
        $ltx .= '\pagecolor{white}' . "\n";
        $ltx .= '$' . $latex . '$' . "\n";
        $ltx .= '\end{document}' . "\n";
        if (file_put_contents($imgpath . '.tex', $ltx) === false) {
            $error = 'unable to write on the cache folder';
        } else {
            [$ret, $output] = F_tcecode_run_process([
                K_LATEX_PDFLATEX,
                '-no-shell-escape',
                '-interaction=nonstopmode',
                '-halt-on-error',
                basename($imgpath . '.tex'),
            ], $picture_path);
            if ($ret !== 0) {
                $error = $output;
            } else {
                // convert code using ImageMagick
                [$ret, $output] = F_tcecode_run_process([
                    K_LATEX_PATH_CONVERT,
                    '-density',
                    (string) (K_LATEX_FORMULA_DENSITY * $dr),
                    '-trim',
                    '+repage',
                    basename($imgpath . '.pdf'),
                    '-depth',
                    '8',
                    '-quality',
                    '100',
                    basename($imgpath . '.' . K_LATEX_IMG_FORMAT),
                ], $picture_path);
                if ($ret !== 0) {
                    $error = $output;
                } else {
                    // @mago-expect lint:no-error-control-operator -- renderer output validation handles an unreadable image as a failed render
                    $imsize = @getimagesize($imgpath . '.' . K_LATEX_IMG_FORMAT);
                    [$w, $h] = $imsize;
                    if (($w / $dr) > K_LATEX_MAX_WIDTH || ($h / $dr) > K_LATEX_MAX_HEIGHT) {
                        $error = 'image size exceed limits';
                    } else {
                        $imgurl = K_LATEX_PATH_PICTURE_HTTPD . $filename . '.' . K_LATEX_IMG_FORMAT;
                    }
                }
            }
        }

        // remove temporary files (if any)
        $tmpext = ['tex', 'aux', 'log', 'pdf'];
        foreach ($tmpext as $ext) {
            if (F_file_exists($imgpath . '.' . $ext)) {
                // @mago-expect lint:no-error-control-operator -- renderer temporary-file cleanup is best effort
                @unlink($imgpath . '.' . $ext);
            }
        }
    }

    if ($imgurl === false) {
        // Keep the placeholder concise. Surface the TeX "! ..." error line when available, but
        // never echo the full pdflatex log into the rendered output (it leaks server paths).
        $msg = '';
        if ($error !== '' && preg_match('/^!\s*(.+)$/m', $error, $em)) {
            $msg = ': ' . trim($em[1]);
        }
        return '[LaTeX error' . htmlspecialchars($msg, ENT_QUOTES) . ']';
    }

    // alternative text to image
    $alt_latex = '[LaTeX]' . "\n" . htmlentities($latex, ENT_QUOTES);
    $replaceTable = [
        "\r" => '&#13;',
        "\n" => '&#10;',
    ];
    $alt_latex = strtr($alt_latex, $replaceTable);
    // XHTML code for image
    // @mago-expect lint:no-error-control-operator -- the generated image may disappear between rendering and response assembly
    $imsize = @getimagesize($imgpath . '.' . K_LATEX_IMG_FORMAT);
    [$w, $h] = $imsize;

    return (
        '<img src="'
        . $imgurl
        . '" alt="'
        . $alt_latex
        . '" class="tcecode" width="'
        . round($w / $dr)
        . '" height="'
        . round($h / $dr)
        . '" />'
    );
}

/**
 * Callback function for preg_replace_callback (MathML replacement).
 * Returns replacement code for MathML code.
 * @param $matches (string) array containing matches: $matches[0] is the complete match, $matches[1] the match for the first subpattern enclosed in '(...)' (the MathML code)
 * @return string MathML code.
 */
function f_mathml_callback(mixed $matches): mixed
{
    $mathml_tags = '<abs><and><annotation><annotation-xml><apply><approx><arccos><arccosh><arccot><arccoth><arccsc><arccsch><arcsec><arcsech><arcsin><arcsinh><arctan><arctanh><arg><bind><bvar><card><cartesianproduct><cbytes><ceiling><cerror><ci><cn><codomain><complexes><compose><condition><conjugate><cos><cosh><cot><coth><cs><csc><csch><csymbol><curl><declare><degree><determinant><diff><divergence><divide><domain><domainofapplication><el><emptyset><eq><equivalent><eulergamma><exists><exp><exponentiale><factorial><factorof><false><floor><fn><forall><gcd><geq><grad><gt><ident><image><imaginary><imaginaryi><implies><in><infinity><int><integers><intersect><interval><inverse><lambda><laplacian><lcm><leq><limit><list><ln><log><logbase><lowlimit><lt><maction><malign><maligngroup><malignmark><malignscope><math><matrix><matrixrow><max><mean><median><menclose><merror><mfenced><mfrac><mfraction><mglyph><mi><min><minus><mlabeledtr><mlongdiv><mmultiscripts><mn><mo><mode><moment><momentabout><mover><mpadded><mphantom><mprescripts><mroot><mrow><ms><mscarries><mscarry><msgroup><msline><mspace><msqrt><msrow><mstack><mstyle><msub><msubsup><msup><mtable><mtd><mtext><mtr><munder><munderover><naturalnumbers><neq><none><not><notanumber><note><notin><notprsubset><notsubset><or><otherwise><outerproduct><partialdiff><pi><piece><piecewise><plus><power><primes><product><prsubset><quotient><rationals><real><reals><reln><rem><root><scalarproduct><sdev><sec><sech><selector><semantics><sep><set><setdiff><share><sin><sinh><subset><sum><tan><tanh><tendsto><times><transpose><true><union><uplimit><variance><vector><vectorproduct><xor>';
    // extract latex code and convert some entities
    $mathml = unhtmlentities($matches[1], true);
    $mathml = F_sanitize_mathml_content($mathml, $mathml_tags);
    if (!str_starts_with($mathml, '<math')) {
        // add default math parent tag
        return '<math xmlns="http://www.w3.org/1998/Math/MathML">' . $mathml . '</math>';
    }

    return $mathml;
}

/**
 * Sanitize a MathML fragment while preserving a conservative set of presentation attributes.
 */
function F_sanitize_mathml_content(string $mathml, string $allowed_tag_string): string
{
    if ($mathml === '' || !class_exists('DOMDocument')) {
        return htmlspecialchars($mathml, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
    preg_match_all('/<([a-z0-9-]+)>/i', $allowed_tag_string, $tag_matches);
    $allowed_tags = array_map('strtolower', $tag_matches[1] ?? []);

    $document = new DOMDocument('1.0', 'UTF-8');
    $previous_errors = libxml_use_internal_errors(true);
    $loaded = $document->loadHTML(
        '<!DOCTYPE html><html><body><div id="tce-mathml">' . $mathml . '</div></body></html>',
        LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING,
    );
    libxml_clear_errors();
    libxml_use_internal_errors($previous_errors);
    $container = $loaded ? $document->getElementById('tce-mathml') : null;
    if (!$container instanceof DOMElement) {
        return htmlspecialchars($mathml, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
    F_sanitize_mathml_node($container, $allowed_tags);
    $result = '';
    foreach ($container->childNodes as $child) {
        $result .= $document->saveHTML($child);
    }
    return trim((string) preg_replace('/[\n\r\s]+/', ' ', $result));
}

/**
 * Recursively remove non-MathML elements and executable attributes.
 *
 * @param list<string> $allowed_tags
 */
function F_sanitize_mathml_node(DOMNode $parent, array $allowed_tags): void
{
    $allowed_attributes = [
        'accent', 'accentunder', 'align', 'bevelled', 'close', 'columnalign', 'columnlines',
        'columnspacing', 'columnspan', 'denomalign', 'depth', 'dir', 'display', 'displaystyle', 'fence',
        'form', 'frame', 'framespacing', 'height', 'linethickness', 'lspace', 'mathbackground',
        'mathcolor', 'mathsize', 'mathvariant', 'maxsize', 'minsize', 'movablelimits', 'notation',
        'numalign', 'open', 'rowalign', 'rowlines', 'rowspacing', 'rowspan', 'rspace', 'scriptlevel',
        'scriptminsize', 'scriptsizemultiplier', 'separator', 'separators', 'stretchy', 'symmetric',
        'voffset', 'width', 'xmlns',
    ];

    for ($node = $parent->firstChild; $node !== null;) {
        $next = $node->nextSibling;
        if ($node instanceof DOMComment) {
            $parent->removeChild($node);
        } elseif ($node instanceof DOMElement) {
            $tag = strtolower($node->tagName);
            if (!in_array($tag, $allowed_tags, true)) {
                F_sanitize_mathml_node($node, $allowed_tags);
                while ($node->firstChild !== null) {
                    $parent->insertBefore($node->firstChild, $node);
                }
                $parent->removeChild($node);
            } else {
                $attributes = $node->attributes;
                foreach ($attributes === null ? [] : iterator_to_array($attributes) as $attribute) {
                    $name = strtolower($attribute->name);
                    $value = $attribute->value;
                    if (
                        !in_array($name, $allowed_attributes, true)
                        || preg_match('/[\x00-\x1f<>&]/', $value) === 1
                        || preg_match('/(?:javascript|data)\s*:|url\s*\(/i', $value) === 1
                        || ($name === 'xmlns' && $value !== 'http://www.w3.org/1998/Math/MathML')
                    ) {
                        $node->removeAttributeNode($attribute);
                    }
                }
                F_sanitize_mathml_node($node, $allowed_tags);
            }
        }
        $node = $next;
    }
}

/**
 * Callback function for preg_replace_callback.
 * Returns replacement code by MIME type.
 * @param $matches (string) array containing matches: $matches[0] is the complete match, $matches[1] the match for the first subpattern enclosed in '(...)' and so on
 * @return string replacement string by file extension
 */
function F_objects_callback(mixed $matches): string
{
    $width = 0;
    $height = 0;
    $alt = '';
    if (isset($matches[3]) && $matches[3] > 0) {
        $width = intval($matches[3]);
    }

    if (isset($matches[4]) && $matches[4] > 0) {
        $height = intval($matches[4]);
    }

    if (isset($matches[5]) && !empty($matches[5])) {
        $alt = F_tcecodeToTitle($matches[5]);
    }

    return F_objects_replacement($matches[1], $matches[2], $width, $height, $alt);
}

/**
 * Returns the xhtml code needed to display the object by MIME type.
 * @param $name (string) object path excluded extension
 * @param $extension (string) object extension (e.g.: gif, jpg, swf, ...)
 * @param $width (int) object width
 * @param $height (int) object height
 * @param $alt (string) alternative content
 * @param $maxwidth (int) object max or default width
 * @param $maxheight (int) object max or default height
 * @return string replacement string
 */
function F_objects_replacement(mixed $name, mixed $extension, mixed $width = 0, mixed $height = 0, mixed $alt = '', mixed &$maxwidth = 0, mixed &$maxheight = 0): string
{
    require_once '../config/tce_config.php';
    global $l, $db;
    $filename = $name . '.' . $extension;
    $extension = strtolower($extension);
    $htmlcode = '';
    switch ($extension) {
        case 'gif':
        case 'jpg':
        case 'jpeg':
        case 'png':
        case 'svg':
            // images
                $htmlcode = '<img src="' . K_PATH_URL_CACHE . $filename . '"';
                if (!empty($alt)) {
                    $htmlcode .= ' alt="' . $alt . '"';
                } else {
                    $htmlcode .= ' alt="image:' . $filename . '"';
                }

                // @mago-expect lint:no-error-control-operator -- missing or invalid cached media falls back to caller-provided dimensions
                $imsize = @getimagesize(K_PATH_CACHE . $filename);
                if ($imsize !== false) {
                    [$pixw, $pixh] = $imsize;
                    if ($width <= 0 && $height <= 0) {
                        // get default size
                        $width = $pixw;
                        $height = $pixh;
                    } elseif ($width <= 0) {
                        $width = ($height * $pixw) / $pixh;
                    } elseif ($height <= 0) {
                        $height = ($width * $pixh) / $pixw;
                    }
                }

                $ratio = 1;
                if ($width > 0 && $height > 0) {
                    $ratio = $width / $height;
                }

                // fit image on max dimensions
                if ($maxwidth > 0 && $width > $maxwidth) {
                    $width = $maxwidth;
                    $height = round($width / $ratio);
                    $maxheight = min($maxheight, $height);
                }

                if ($maxheight > 0 && $height > $maxheight) {
                    $height = $maxheight;
                    $width = round($height * $ratio);
                }

                // print size
                if ($width > 0) {
                    $htmlcode .= ' width="' . $width . '"';
                }

                if ($height > 0) {
                    $htmlcode .= ' height="' . $height . '"';
                }

                $htmlcode .= ' class="tcecode" />';
                if ($imsize !== false) {
                    $maxwidth = $pixw;
                    $maxheight = $pixh;
                }

                break;
        default:
                include '../../shared/config/tce_mime.php';
                if (isset($mime[$extension])) {
                    $htmlcode = '<object type="' . $mime[$extension] . '" data="' . K_PATH_URL_CACHE . $filename . '"';
                    if ($width > 0) {
                        $htmlcode .= ' width="' . $width . '"';
                    } elseif ($maxwidth > 0) {
                        $htmlcode .= ' width="' . $maxwidth . '"';
                    }

                    if ($height > 0) {
                        $htmlcode .= ' height="' . $height . '"';
                    } elseif ($maxheight > 0) {
                        $htmlcode .= ' height="' . $maxheight . '"';
                    }

                    $htmlcode .= '>';
                    $htmlcode .= '<param name="type" value="' . $mime[$extension] . '" />';
                    $htmlcode .= '<param name="src" value="' . K_PATH_URL_CACHE . $filename . '" />';
                    $htmlcode .= '<param name="filename" value="' . K_PATH_URL_CACHE . $filename . '" />';
                    if ($width > 0) {
                        $htmlcode .= '<param name="width" value="' . $width . '" />';
                    } elseif ($maxwidth > 0) {
                        $htmlcode .= '<param name="width" value="' . $maxwidth . '" />';
                    }

                    if ($height > 0) {
                        $htmlcode .= '<param name="height" value="' . $height . '" />';
                    } elseif ($maxheight > 0) {
                        $htmlcode .= '<param name="height" value="' . $maxheight . '" />';
                    }

                    if (!empty($alt)) {
                        $htmlcode .= '' . $alt . '';
                    } else {
                        $htmlcode .= '[' . $mime[$extension] . ']:' . $filename . '';
                    }

                    $htmlcode .= '</object>';
                } else {
                    $htmlcode = '[ERROR - UNKNOW MIME TYPE FOR: ' . $extension . ']';
                }

                break;
    }

    return $htmlcode;
}

/**
 * Returns specified string without tcecode mark-up tags
 * @param $str (string) text to process
 * @return string without tcecode markup tags
 */
function f_remove_tcecode(mixed $str): mixed
{
    /** @var string $str */
    $str = preg_replace("'\[object\](.*?)\[/object([^\]]*?)\]'si", '[OBJ]', $str) ?? $str;
    $str = preg_replace("'\[img([^\]]*?)\](.*?)\[/img\]'si", '[IMG]', $str) ?? $str;
    $str = preg_replace("'\[code\](.*?)\[/code\]'si", '\1', $str) ?? $str;
    $str = preg_replace("'\[b\](.*?)\[/b\]'si", '\1', $str) ?? $str;
    $str = preg_replace("'\[i\](.*?)\[/i\]'si", '\1', $str) ?? $str;
    $str = preg_replace("'\[s\](.*?)\[/s\]'si", '\1', $str) ?? $str;
    $str = preg_replace("'\[u\](.*?)\[/u\]'si", '\1', $str) ?? $str;
    $str = preg_replace("'\[o\](.*?)\[/o\]'si", '\1', $str) ?? $str;
    $str = preg_replace("'\[color([^\]]*?)\](.*?)\[/color\]'si", '\2', $str) ?? $str;
    $str = preg_replace("'\[bgcolor([^\]]*?)\](.*?)\[/bgcolor\]'si", '\2', $str) ?? $str;
    $str = preg_replace("'\[font([^\]]*?)\](.*?)\[/font\]'si", '\2', $str) ?? $str;
    $str = preg_replace("'\[size([^\]]*?)\](.*?)\[/size\]'si", '\2', $str) ?? $str;
    $str = preg_replace("'\[small\](.*?)\[/small\]'si", '\1', $str) ?? $str;
    $str = preg_replace("'\[sub\](.*?)\[/sub\]'si", '\1', $str) ?? $str;
    $str = preg_replace("'\[sup\](.*?)\[/sup\]'si", '\1', $str) ?? $str;
    $str = preg_replace("'\[url([^\]]*?)\](.*?)\[/url\]'si", '\2', $str) ?? $str;
    $str = preg_replace("'\[li\](.*?)\[/li\]'si", ' * \1', $str) ?? $str;
    $str = preg_replace("'\[\*\](.*?)\n'i", ' * \1', $str) ?? $str;
    $str = preg_replace("'\[ulist\](.*?)\[/ulist\]'si", '\1', $str) ?? $str;
    $str = preg_replace("'\[olist([^\]]*?)\](.*?)\[/olist\]'si", '\2', $str) ?? $str;
    return preg_replace("'\[tex\](.*?)\[/tex\]'si", '[TEX]', $str) ?? $str;
}

/**
 * Converts tcecode text to a single XHTML string removing some objects.
 * @param $str (string) text to process
 * return string
 */
function f_tcecode_to_line(mixed $str): mixed
{
    $str = (string) $str;
    if (F_has_html_markup($str)) {
        $str = html_entity_decode(strip_tags(F_sanitize_html_content($str)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $str = f_compact_string($str);
        $str = htmlspecialchars($str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        if (strlen($str) > K_QUESTION_LINE_MAX_LENGTH) {
            return F_substrHTML($str, K_QUESTION_LINE_MAX_LENGTH, 20) . ' ...';
        }

        return $str;
    }

    $str = preg_replace("'\[object\](.*?)\[/object([^\]]*?)\]'si", '[OBJ]', $str);
    $str = preg_replace("'\[img([^\]]*?)\](.*?)\[/img\]'si", '[IMG]', $str);
    $str = preg_replace("'\[code\](.*?)\[/code\]'si", '\1', $str);
    $str = preg_replace("'\[li\](.*?)\[/li\]'si", ' * \1', $str);
    $str = preg_replace("'\[\*\](.*?)\n'i", ' * \1', $str);
    $str = preg_replace("'\[ulist\](.*?)\[/ulist\]'si", '\1', $str);
    $str = preg_replace("'\[olist([^\]]*?)\](.*?)\[/olist\]'si", '\2', $str);
    $str = preg_replace("'\[url([^\]]*?)\](.*?)\[/url\]'si", '\2', $str);
    $str = preg_replace("'\[tex\](.*?)\[/tex\]'si", '[TEX]', $str);
    $str = f_compact_string($str);
    $str = F_decode_tcecode($str);
    $str = f_compact_string($str);
    if (strlen($str) > K_QUESTION_LINE_MAX_LENGTH) {
        return F_substrHTML($str, K_QUESTION_LINE_MAX_LENGTH, 20) . ' ...';
    }

    return $str;
}

/**
 * Converts tcecode text to simple string for XHTML title attribute.
 * @param $str (string) text to process
 * return string
 */
function F_tcecodeToTitle(mixed $str): string
{
    require_once '../config/tce_config.php';
    global $l;
    $str = (string) $str;
    if (F_has_html_markup($str)) {
        $str = html_entity_decode(strip_tags(F_sanitize_html_content($str)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    } else {
        $str = f_remove_tcecode($str);
    }
    $str = f_compact_string($str);
    return htmlspecialchars($str, ENT_QUOTES | ENT_COMPAT, $l['a_meta_charset']);
}

/**
 * Return a substring of XHTML code while making sure no html tags are chopped.
 * It also prevents chopping while a tag is still open.
 * this function is based on a public-domain script posted on www.php.net by fox@conskript.server and mr@bbp.biz
 * @param string $htmltext
 * @param int $min_length (default=100) the approximate length you want the concatenated text to be
 * @param int $offset_length (default=20) the max variation in how long the text can be
 */
function F_substrHTML(string $htmltext, int $min_length = 100, int $offset_length = 20): string
{
    // Reset tag counter and quote checker
    $tag_counter = 0;
    $quotes_on = false;
    // Check if the text is too long
    if (strlen($htmltext) > $min_length) {
        // Reset the tag_counter and pass through (part of) the entire text
        $c = 0;
        for ($i = 0; $i < strlen($htmltext); ++$i) {
            // Load the current character and the next one if the string has not arrived at the last character
            $current_char = substr($htmltext, $i, 1);
            $next_char = $i < (strlen($htmltext) - 1) ? substr($htmltext, $i + 1, 1) : '';

            // First check if quotes are on
            if (!$quotes_on) {
                // Check if it's a tag On a "<" add 3 if it's an opening tag (like <a href...) or add only 1 if it's an ending tag (like </a>)
                if ($current_char === '<') {
                    if ($next_char === '/') {
                        ++$tag_counter;
                    } else {
                        $tag_counter += 3;
                    }
                }

                // Slash signifies an ending (like </a> or ... />) substract 2
                if ($current_char === '/' && $tag_counter !== 0) {
                    $tag_counter -= 2;
                }

                // On a ">" substract 1
                if ($current_char === '>') {
                    --$tag_counter;
                }

                // If quotes are encountered, start ignoring the tags (for directory slashes)
                if ($current_char === '"') {
                    $quotes_on = true;
                }
            } elseif ($current_char === '"') {
                // IF quotes are encountered again, turn it back off
                $quotes_on = false;
            }

            // Count only the chars outside html tags
            if ($tag_counter === 2 || $tag_counter === 0) {
                ++$c;
            }

            // Check if the counter has reached the minimum length yet,
            // then wait for the tag_counter to become 0, and chop the string there
            if ($c > ($min_length - $offset_length) && $tag_counter === 0 && $next_char === ' ') {
                return substr($htmltext, 0, $i + 1);
            }
        }
    }

    return $htmltext;
}
