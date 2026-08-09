<?php

/**
 * Add translations shipped by a newer release to an existing runtime TMX file.
 *
 * Existing runtime translations are never replaced. Missing translation units and
 * missing language variants are copied from the shipped default file.
 *
 * @throws RuntimeException when either TMX document cannot be read or saved.
 * @throws Random\RandomException when a secure temporary filename cannot be generated.
 */
function F_sync_tmx_translations(string $default_file, string $runtime_file): bool
{
    $default = new DOMDocument();
    $runtime = new DOMDocument();
    $default->preserveWhiteSpace = true;
    $runtime->preserveWhiteSpace = true;

    $previous_internal_errors = libxml_use_internal_errors(true);
    $default_loaded = $default->load($default_file, LIBXML_NONET);
    $runtime_loaded = $runtime->load($runtime_file, LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($previous_internal_errors);

    if (!$default_loaded) {
        throw new RuntimeException('Unable to read the default TMX file: ' . $default_file);
    }
    if (!$runtime_loaded) {
        throw new RuntimeException('Unable to read the runtime TMX file: ' . $runtime_file);
    }

    $default_xpath = new DOMXPath($default);
    $runtime_xpath = new DOMXPath($runtime);
    $runtime_body = $runtime_xpath->query('/*[local-name()="tmx"]/*[local-name()="body"]')->item(0);
    if (!$runtime_body instanceof DOMElement) {
        throw new RuntimeException('The runtime TMX file has no body element: ' . $runtime_file);
    }

    $changed = false;
    foreach ($default_xpath->query('/*[local-name()="tmx"]/*[local-name()="body"]/*[local-name()="tu"]') as $default_tu) {
        if (!$default_tu instanceof DOMElement) {
            continue;
        }
        $key = $default_tu->getAttribute('tuid');
        if ($key === '') {
            continue;
        }

        $runtime_tu = $runtime_xpath
            ->query('/*[local-name()="tmx"]/*[local-name()="body"]/*[local-name()="tu"][@tuid='
                . F_tmx_xpath_literal($key) . ']')
            ->item(0);
        if (!$runtime_tu instanceof DOMElement) {
            $runtime_body->appendChild($runtime->importNode($default_tu, true));
            $changed = true;
            continue;
        }

        $runtime_languages = [];
        foreach ($runtime_xpath->query('./*[local-name()="tuv"]', $runtime_tu) as $runtime_tuv) {
            if ($runtime_tuv instanceof DOMElement) {
                $runtime_languages[strtolower($runtime_tuv->getAttributeNS(
                    'http://www.w3.org/XML/1998/namespace',
                    'lang',
                ))] = true;
            }
        }
        foreach ($default_xpath->query('./*[local-name()="tuv"]', $default_tu) as $default_tuv) {
            if (!$default_tuv instanceof DOMElement) {
                continue;
            }
            $language = strtolower($default_tuv->getAttributeNS(
                'http://www.w3.org/XML/1998/namespace',
                'lang',
            ));
            if ($language !== '' && !isset($runtime_languages[$language])) {
                $runtime_tu->appendChild($runtime->importNode($default_tuv, true));
                $runtime_languages[$language] = true;
                $changed = true;
            }
        }
    }

    if (!$changed) {
        return false;
    }

    $temporary = $runtime_file . '.tmp-' . bin2hex(random_bytes(6));
    if ($runtime->save($temporary) === false || !rename($temporary, $runtime_file)) {
        if (is_file($temporary)) {
            unlink($temporary);
        }
        throw new RuntimeException('Unable to update the runtime TMX file: ' . $runtime_file);
    }

    return true;
}

/**
 * Quote an arbitrary string for use as an XPath literal.
 */
function F_tmx_xpath_literal(string $value): string
{
    if (!str_contains($value, "'")) {
        return "'" . $value . "'";
    }
    if (!str_contains($value, '"')) {
        return '"' . $value . '"';
    }

    $parts = explode("'", $value);
    return 'concat(' . implode(', "\'", ', array_map(static fn(string $part): string => "'" . $part . "'", $parts)) . ')';
}
