<?php

namespace App\Services\Growth;

/**
 * تنقية محتوى المقال قبل عرضه على **صفحة عامّة** (21.2-أ).
 *
 * لماذا؟ لأنّ المحرّر غنيّ ويقبل HTML، والصفحة تُفتَح بلا تسجيل — فأيّ وسمٍ
 * تنفيذيّ يصير ثغرةً على كلّ زائر. والقائمة **مسموحٌ بها لا ممنوعة**، لأنّ
 * الحصر الإيجابيّ وحده هو الآمن. وبلا أيّ مكتبة خارجيّة (2.16-ج).
 */
class SafeHtml
{
    /** الوسوم المسموحة — إعداد لا قائمة محروقة (2.13) */
    public function allowedTags(): string
    {
        $configured = setting('growth.articles.allowed_tags');

        if (is_string($configured) && trim($configured) !== '') {
            return $configured;
        }

        return '<p><br><strong><b><em><i><u><ul><ol><li><h2><h3><h4>'
            .'<blockquote><a><img><figure><figcaption><code><pre><hr><table><thead><tbody><tr><th><td>';
    }

    public function clean(?string $html): string
    {
        $html = (string) $html;

        if (trim($html) === '') {
            return '';
        }

        $html = strip_tags($html, $this->allowedTags());

        // معالجات الأحداث (onclick…) تُزال — فالوسم المسموح لا يعني السماح بسلوكه
        $html = preg_replace('/\s on[a-z-]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? '';

        // مخطّطات تنفيذيّة داخل href/src
        $html = preg_replace('/(href|src)\s*=\s*("|\')\s*(javascript|data|vbscript):[^"\']*\2/i', '$1=$2#$2', $html) ?? '';

        return $html;
    }
}
