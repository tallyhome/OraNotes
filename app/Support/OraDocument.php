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
    private static function sanitizeNode(array $node): array
    {
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
                fn ($child) => is_array($child) ? self::sanitizeNode($child) : $child,
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
    private static function walk(array $node, array &$parts): void
    {
        if (($node['type'] ?? null) === 'text' && isset($node['text'])) {
            $parts[] = (string) $node['text'];
        }

        foreach ($node['content'] ?? [] as $child) {
            if (is_array($child)) {
                self::walk($child, $parts);
            }
        }
    }
}
