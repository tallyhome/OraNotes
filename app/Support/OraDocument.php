<?php

namespace App\Support;

final class OraDocument
{
    /**
     * @return array{version: int, type: string, content: list<array<string, mixed>>}
     */
    public static function empty(): array
    {
        return [
            'version' => 1,
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'paragraph',
                    'content' => [
                        ['type' => 'text', 'text' => ''],
                    ],
                ],
            ],
        ];
    }

    public const MAX_JSON_BYTES = 262_144;

    public const MAX_NODES = 4_000;

    public const MAX_DEPTH = 24;

    public const MAX_TEXT_CHARS = 200_000;

    /**
     * @param  array<string, mixed>|null  $document
     */
    public static function isValid(?array $document): bool
    {
        if (! is_array($document)) {
            return false;
        }

        return ($document['type'] ?? null) === 'doc'
            && isset($document['version'])
            && is_array($document['content'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $document
     */
    public static function limitError(array $document): ?string
    {
        $encoded = json_encode($document);
        if ($encoded === false || strlen($encoded) > self::MAX_JSON_BYTES) {
            return 'Document trop volumineux.';
        }

        $nodes = 0;
        $textLen = 0;
        $tooDeep = false;
        self::measure($document, 0, $nodes, $textLen, $tooDeep);

        if ($tooDeep) {
            return 'Document trop profondément imbriqué.';
        }

        if ($nodes > self::MAX_NODES) {
            return 'Document trop complexe.';
        }

        if ($textLen > self::MAX_TEXT_CHARS) {
            return 'Document trop volumineux.';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $document
     */
    public static function extractText(?array $document): string
    {
        if (! is_array($document)) {
            return '';
        }

        $parts = [];
        self::walk($document, $parts);

        return trim(preg_replace('/\s+/u', ' ', implode(' ', $parts)) ?? '');
    }

    /**
     * Strip unsafe URLs from document attrs and marks before persistence.
     *
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    public static function sanitize(array $document): array
    {
        return self::sanitizeNode($document);
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private static function sanitizeNode(array $node, int $depth = 0): array
    {
        if ($depth > self::MAX_DEPTH) {
            unset($node['content']);

            return $node;
        }

        if (isset($node['attrs']) && is_array($node['attrs'])) {
            $node['attrs'] = self::sanitizeAttrs($node['attrs']);
        }

        if (isset($node['marks']) && is_array($node['marks'])) {
            $node['marks'] = array_values(array_map(function ($mark) {
                if (! is_array($mark)) {
                    return $mark;
                }
                if (isset($mark['attrs']) && is_array($mark['attrs'])) {
                    $mark['attrs'] = self::sanitizeAttrs($mark['attrs']);
                }

                return $mark;
            }, $node['marks']));
        }

        if (isset($node['content']) && is_array($node['content'])) {
            $node['content'] = array_map(
                fn ($child) => is_array($child) ? self::sanitizeNode($child, $depth + 1) : $child,
                $node['content']
            );
        }

        return $node;
    }

    /**
     * @param  array<string, mixed>  $attrs
     * @return array<string, mixed>
     */
    private static function sanitizeAttrs(array $attrs): array
    {
        foreach (['href', 'src', 'srcset'] as $key) {
            if (! array_key_exists($key, $attrs)) {
                continue;
            }

            $value = is_string($attrs[$key]) ? $attrs[$key] : '';
            $forImage = $key !== 'href';
            if ($value === '' || ! HtmlSanitizer::isSafeUrl($value, $forImage)) {
                unset($attrs[$key]);
            }
        }

        foreach (array_keys($attrs) as $name) {
            if (str_starts_with(strtolower((string) $name), 'on')) {
                unset($attrs[$name]);
            }
        }

        return $attrs;
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  list<string>  $parts
     */
    private static function walk(array $node, array &$parts, int $depth = 0): void
    {
        if ($depth > self::MAX_DEPTH) {
            return;
        }

        if (($node['type'] ?? null) === 'text' && isset($node['text'])) {
            $parts[] = (string) $node['text'];
        }

        foreach ($node['content'] ?? [] as $child) {
            if (is_array($child)) {
                self::walk($child, $parts, $depth + 1);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private static function measure(array $node, int $depth, int &$nodes, int &$textLen, bool &$tooDeep): void
    {
        if ($tooDeep || $nodes > self::MAX_NODES) {
            return;
        }

        if ($depth > self::MAX_DEPTH) {
            $tooDeep = true;

            return;
        }

        $nodes++;

        if (isset($node['text']) && is_string($node['text'])) {
            $textLen += strlen($node['text']);
        }

        if (! isset($node['content']) || ! is_array($node['content'])) {
            return;
        }

        foreach ($node['content'] as $child) {
            if (is_array($child)) {
                self::measure($child, $depth + 1, $nodes, $textLen, $tooDeep);
                if ($tooDeep || $nodes > self::MAX_NODES) {
                    return;
                }
            }
        }
    }
}
