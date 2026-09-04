<?php

namespace App\Support;

final class HtmlSanitizer
{
    /**
     * Conservative subset aligned with OraEditor's HTML export.
     */
    public static function clean(?string $html): string
    {
        if ($html === null || $html === '') {
            return '';
        }

        $allowed = '<p><br><strong><b><em><i><u><s><strike><code><pre><blockquote><ul><ol><li><h1><h2><h3><h4><h5><h6><a><img><table><thead><tbody><tr><th><td><hr><span><sub><sup><div>';
        $clean = strip_tags($html, $allowed);
        $clean = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $clean) ?? $clean;
        $clean = preg_replace('/javascript\s*:/i', '', $clean) ?? $clean;
        $clean = preg_replace('/data\s*:/i', '', $clean) ?? $clean;

        return $clean;
    }
}
