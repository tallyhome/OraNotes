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
     * OraEditor 0.1.3 Document Model node types.
     *
     * @var list<string>
     */
    public const NODE_TYPES = [
        'doc',
        'paragraph',
        'heading',
        'blockquote',
        'codeBlock',
        'listItem',
        'text',
        'image',
        'video',
        'audio',
        'embed',
        'file',
        'table',
        'tableRow',
        'tableCell',
        'hardBreak',
        'horizontalRule',
    ];

    /**
     * @var list<string>
     */
    public const MARK_TYPES = [
        'bold',
        'italic',
        'underline',
        'strike',
        'code',
        'subscript',
        'superscript',
        'link',
        'mention',
        'textColor',
        'highlight',
        'fontFamily',
        'fontSize',
    ];

    /**
     * @param  array<string, mixed>|null  $document
     */
    public static function isValid(?array $document): bool
    {
        if (! is_array($document)) {
            return false;
        }

        $version = $document['version'] ?? null;
        if (! is_int($version) && ! (is_numeric($version) && (int) $version == $version)) {
            return false;
        }

        $version = (int) $version;
        if ($version < 1 || $version > 2) {
            return false;
        }

        $content = $document['content'] ?? null;

        return ($document['type'] ?? null) === 'doc'
            && is_array($content)
            && array_is_list($content);
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

        return self::schemaError($document);
    }

    /**
     * @param  array<string, mixed>  $document
     */
    public static function schemaError(array $document): ?string
    {
        if (! self::isValid($document)) {
            return 'Document OraEditor invalide.';
        }

        return self::nodeSchemaError($document, 0);
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
            $node['marks'] = array_values(array_filter(array_map(function ($mark) {
                if (! is_array($mark) || ! is_string($mark['type'] ?? null)) {
                    return null;
                }
                if (! in_array($mark['type'], self::MARK_TYPES, true)) {
                    return null;
                }
                if (isset($mark['attrs']) && is_array($mark['attrs'])) {
                    $mark['attrs'] = self::sanitizeAttrs($mark['attrs']);
                } elseif (isset($mark['attrs'])) {
                    unset($mark['attrs']);
                }

                return $mark;
            }, $node['marks'])));
        }

        if (($node['type'] ?? null) === 'text') {
            $node['text'] = isset($node['text']) && is_string($node['text']) ? $node['text'] : '';
            unset($node['content']);
        }

        if (isset($node['content']) && is_array($node['content'])) {
            $node['content'] = array_values(array_filter(
                array_map(
                    function ($child) use ($depth) {
                        if (! is_array($child)) {
                            return null;
                        }

                        $type = $child['type'] ?? null;
                        if (! is_string($type) || ! in_array($type, self::NODE_TYPES, true)) {
                            return null;
                        }

                        return self::sanitizeNode($child, $depth + 1);
                    },
                    $node['content']
                )
            ));
        }

        return $node;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private static function nodeSchemaError(array $node, int $depth): ?string
    {
        if ($depth > self::MAX_DEPTH) {
            return 'Document trop profondément imbriqué.';
        }

        $type = $node['type'] ?? null;
        if (! is_string($type) || $type === '' || strlen($type) > 40) {
            return 'Nœud OraEditor sans type valide.';
        }

        if (! in_array($type, self::NODE_TYPES, true)) {
            return 'Type de nœud OraEditor inattendu.';
        }

        if ($type === 'text') {
            $text = $node['text'] ?? '';
            if (! is_string($text) && $text !== null) {
                return 'Nœud texte malformé.';
            }
            if (array_key_exists('content', $node)) {
                return 'Nœud texte malformé.';
            }
        }

        if (isset($node['marks'])) {
            if (! is_array($node['marks']) || ! array_is_list($node['marks'])) {
                return 'Marques OraEditor malformées.';
            }

            foreach ($node['marks'] as $mark) {
                if (! is_array($mark) || ! is_string($mark['type'] ?? null)) {
                    return 'Marques OraEditor malformées.';
                }
                if (! in_array($mark['type'], self::MARK_TYPES, true)) {
                    return 'Marque OraEditor inattendue.';
                }
                if (isset($mark['attrs']) && ! is_array($mark['attrs'])) {
                    return 'Marques OraEditor malformées.';
                }
            }
        }

        if (isset($node['attrs']) && ! is_array($node['attrs'])) {
            return 'Attributs de nœud malformés.';
        }

        if (! array_key_exists('content', $node)) {
            return null;
        }

        if (! is_array($node['content']) || ! array_is_list($node['content'])) {
            return 'Structure de document malformée.';
        }

        foreach ($node['content'] as $child) {
            if (! is_array($child)) {
                return 'Structure de document malformée.';
            }

            $error = self::nodeSchemaError($child, $depth + 1);
            if ($error !== null) {
                return $error;
            }
        }

        return null;
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
