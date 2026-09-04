<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

final class HtmlSanitizer
{
    /**
     * Conservative subset aligned with OraEditor's HTML export.
     *
     * @var list<string>
     */
    private const ALLOWED_TAGS = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'strike', 'code', 'pre',
        'blockquote', 'ul', 'ol', 'li', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'a', 'img', 'table', 'thead', 'tbody', 'tr', 'th', 'td', 'hr',
        'span', 'sub', 'sup', 'div',
    ];

    /**
     * Tags dropped entirely, including their children.
     *
     * @var list<string>
     */
    private const DROP_TAGS = [
        'script', 'style', 'iframe', 'object', 'embed', 'link', 'meta', 'base',
        'form', 'input', 'button', 'textarea', 'select', 'option', 'svg', 'math',
        'video', 'audio', 'source', 'track', 'canvas', 'applet', 'frame',
        'frameset', 'noscript', 'template',
    ];

    /**
     * @var array<string, list<string>>
     */
    private const ALLOWED_ATTRS = [
        'a' => ['href', 'title', 'target', 'rel'],
        'img' => ['src', 'alt', 'width', 'height'],
        'th' => ['colspan', 'rowspan'],
        'td' => ['colspan', 'rowspan'],
        'ol' => ['start'],
        'code' => [],
        'pre' => [],
    ];

    public static function clean(?string $html): string
    {
        if ($html === null || $html === '') {
            return '';
        }

        $html = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $html) ?? $html;

        $dom = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $wrapped = '<div id="ora-sanitizer-root">'.$html.'</div>';
        $dom->loadHTML(
            '<?xml encoding="UTF-8">'.$wrapped,
            LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $dom->getElementById('ora-sanitizer-root');
        if (! $root instanceof DOMElement) {
            $bodies = $dom->getElementsByTagName('body');
            $root = $bodies->length > 0 ? $bodies->item(0) : $dom->documentElement;
        }

        if (! $root instanceof DOMNode) {
            return '';
        }

        self::sanitizeNode($root);

        $output = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $output .= $dom->saveHTML($child);
        }

        return trim($output);
    }

    public static function isSafeUrl(string $url, bool $forImage = false): bool
    {
        $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $url = preg_replace('/[\x00-\x1F\x7F]/', '', $url) ?? '';
        $url = str_replace(["\r", "\n", "\t"], '', $url);

        if ($url === '' || str_contains($url, '\\')) {
            return false;
        }

        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return ! str_contains($url, '..');
        }

        $parsed = parse_url($url);
        if (! is_array($parsed) || empty($parsed['scheme'])) {
            return false;
        }

        $scheme = strtolower($parsed['scheme']);
        if ($forImage) {
            return in_array($scheme, ['http', 'https'], true);
        }

        return in_array($scheme, ['http', 'https', 'mailto'], true);
    }

    private static function sanitizeNode(DOMNode $node): void
    {
        $children = iterator_to_array($node->childNodes);
        foreach ($children as $child) {
            if ($child instanceof DOMElement) {
                self::sanitizeElement($child);
            } elseif ($child->nodeType === XML_COMMENT_NODE || $child->nodeType === XML_CDATA_SECTION_NODE) {
                $child->parentNode?->removeChild($child);
            }
        }
    }

    private static function sanitizeElement(DOMElement $element): void
    {
        $tag = strtolower($element->tagName);

        if (in_array($tag, self::DROP_TAGS, true)) {
            $element->parentNode?->removeChild($element);

            return;
        }

        if (! in_array($tag, self::ALLOWED_TAGS, true)) {
            self::unwrap($element);

            return;
        }

        self::sanitizeAttributes($element, $tag);
        self::sanitizeNode($element);
    }

    private static function sanitizeAttributes(DOMElement $element, string $tag): void
    {
        $allowed = self::ALLOWED_ATTRS[$tag] ?? [];
        $toRemove = [];

        foreach (iterator_to_array($element->attributes ?? []) as $attribute) {
            $name = strtolower($attribute->name);
            if (! in_array($name, $allowed, true) || str_starts_with($name, 'on')) {
                $toRemove[] = $attribute->name;
            }
        }

        foreach ($toRemove as $name) {
            $element->removeAttribute($name);
        }

        if ($tag === 'a' && $element->hasAttribute('href')) {
            $href = $element->getAttribute('href');
            if (! self::isSafeUrl($href)) {
                $element->removeAttribute('href');
            }
        }

        if ($tag === 'a' && strtolower($element->getAttribute('target')) === '_blank') {
            $element->setAttribute('rel', 'noopener noreferrer');
            $element->setAttribute('target', '_blank');
        } elseif ($tag === 'a') {
            $element->removeAttribute('target');
            $element->removeAttribute('rel');
        }

        if ($tag === 'img' && $element->hasAttribute('src')) {
            $src = $element->getAttribute('src');
            if (! self::isSafeUrl($src, forImage: true)) {
                $element->parentNode?->removeChild($element);
            }
        }

        foreach (['width', 'height', 'colspan', 'rowspan', 'start'] as $numeric) {
            if ($element->hasAttribute($numeric) && ! ctype_digit($element->getAttribute($numeric))) {
                $element->removeAttribute($numeric);
            }
        }
    }

    private static function unwrap(DOMElement $element): void
    {
        self::sanitizeNode($element);
        $parent = $element->parentNode;
        if (! $parent) {
            return;
        }

        while ($element->firstChild) {
            $parent->insertBefore($element->firstChild, $element);
        }
        $parent->removeChild($element);
    }
}
