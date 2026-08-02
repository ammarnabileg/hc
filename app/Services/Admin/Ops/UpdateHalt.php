<?php

namespace App\Services\Admin\Ops;

use RuntimeException;

/**
 * توقّفٌ مقصود في خطّ التحديث (2.11-ح): «عند أيِّ خطأ ⇒ إيقافٌ فوريّ».
 *
 * تحمل **المرحلة** التي وقفنا عندها لأنّ التقرير الذي لا يقول «أين» لا يفيد
 * المالك بشيء (2.17)، وتحمل اسم الهجرة إن كان التوقّف داخل هجرة بعينها.
 */
class UpdateHalt extends RuntimeException
{
    public function __construct(
        public readonly string $stage,
        string $message,
        public readonly ?string $migration = null,
        public readonly array $details = [],
    ) {
        parent::__construct($message);
    }
}
