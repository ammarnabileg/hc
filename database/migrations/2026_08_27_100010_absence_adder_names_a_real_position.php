<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * إصلاح بيانات: إعداد «مَن يضيف وضع غائب» كان يسمّي بوزشنًا وهميًّا.
 *
 * النصّ (23 — القسم 6): «**مَن يضيفه:** **مشرف عام التطوّع** أو **مشرف المسار**
 * أو **دايركتور الكيان** — لا الشخص نفسه (منعًا للتهرّب)».
 *
 * والمزروع كان `["volunteer_gm","track_gm","director"]` — و`track_gm` **اسم لا
 * وجود له** في جدول `positions` (المفتاح المزروع هناك `track_supervisor`).
 * فكانت `whereIn('key', …)` تُرجِع مُعرِّفَين بدل ثلاثة، و**مشرف المسار يُرَدّ**
 * وهو صاحب حقٍّ منصوص. والعطب صامت: لا استثناء ولا سطر سجلّ — قائمةٌ تنكمش فقط.
 *
 * ولماذا مايجريشن لا سيدر وحده؟ لأنّ السيدر يُصلح **التنصيب الجديد**، أمّا
 * القواعد القائمة فقيمتها محفوظة في `settings.value` ولن يمسّها شيء. والاستبدال
 * هنا **موضعيّ**: نبدّل المفتاح الخاطئ وحده ونترك ما عدّله الأدمن كما هو.
 */
return new class extends Migration
{
    private const KEY = 'volunteer.absence.adder_positions';

    private const WRONG = 'track_gm';

    private const RIGHT = 'track_supervisor';

    public function up(): void
    {
        $this->swap(self::WRONG, self::RIGHT);
    }

    public function down(): void
    {
        $this->swap(self::RIGHT, self::WRONG);
    }

    /** تبديل مفتاح بوزشن داخل قيمة الإعداد وافتراضيّه معًا، بلا مساس بالباقي */
    private function swap(string $from, string $to): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $row = DB::table('settings')->where('key', self::KEY)->first();

        if (! $row) {
            return;
        }

        DB::table('settings')->where('key', self::KEY)->update([
            'value' => $this->replaceKey($row->value ?? null, $from, $to),
            'default_value' => $this->replaceKey($row->default_value ?? null, $from, $to),
            'updated_at' => now(),
        ]);
    }

    private function replaceKey(?string $raw, string $from, string $to): ?string
    {
        if ($raw === null || $raw === '') {
            return $raw;
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            return $raw === $from ? $to : $raw;
        }

        $swapped = array_map(fn ($value) => $value === $from ? $to : $value, $decoded);

        return json_encode(array_values($swapped), JSON_UNESCAPED_UNICODE);
    }
};
