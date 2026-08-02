<?php

namespace App\Services\Setup;

/**
 * مسارات التنصيب في مكان واحد (الدستور 2.2).
 * المعالج يكتب ملفّات حسّاسة (‎.env‎ · قفل التنصيب · توكن التنصيب)، فوضعناها
 * خلف كائن واحد قابل للاستبدال — تُوجَّه في الاختبارات لجذر مؤقّت فلا نلمس
 * ملفّات المشروع الحقيقيّة أبدًا.
 */
class SetupPaths
{
    public function __construct(private readonly ?string $root = null) {}

    public function root(): string
    {
        return rtrim($this->root ?? base_path(), '/');
    }

    public function env(): string
    {
        return $this->root().'/.env';
    }

    public function envExample(): string
    {
        return $this->root().'/.env.example';
    }

    /** علامة اكتمال التنصيب — وجودها يقفل كلّ مسارات ‎/setup‎ نهائيًّا */
    public function lock(): string
    {
        return $this->root().'/storage/installed.lock';
    }

    /** توكن التنصيب: يمنع أن يسبقك أحد للتنصيب على خادم عامّ */
    public function token(): string
    {
        return $this->root().'/storage/setup-token.txt';
    }

    public function storage(): string
    {
        return $this->root().'/storage';
    }

    public function isInstalled(): bool
    {
        return is_file($this->lock());
    }

    /** المسار كما نعرضه للمستخدم في الرسائل — نسبيًّا لجذر المشروع */
    public function relative(string $absolute): string
    {
        return ltrim(str_replace($this->root(), '', $absolute), '/');
    }
}
