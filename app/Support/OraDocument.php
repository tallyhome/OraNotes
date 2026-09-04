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
