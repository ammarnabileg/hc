<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * 🧩 صفّ البحث في السايد بار كان يقول الشيء مرّتين: الحقل يكتب «ابحث…» والزرّ
 * بجواره «إبحث» — كلمةٌ واحدة مكرّرة، وثانيتُها مكتوبةٌ بهمزة قطعٍ خاطئة
 * («إبحث» والصواب «ابحث» بهمزة وصل).
 *
 * فصار الحقل يحمل وصف ما يفعله («بحث سريع» كما يسمّيه المرجع) والزرّ يحمل
 * الفعل وحده مصحَّحًا. والصفوف قديمةٌ من مايجريشن سابق فلا يُعدَّل في مكانه
 * (قاعدة البناء §1)، والتصحيح مشروطٌ بالنصّ القديم بعينه حتى لا يُمحى تحرير
 * المالك من شاشة الإعدادات (2.13-ب).
 */
return new class extends Migration
{
    /** المفتاح ⟵ [القديم، الجديد] */
    private function texts(): array
    {
        return [
            'nav.trainee.search_placeholder' => ['ابحث…', 'بحث سريع'],
            'nav.trainee.search_submit' => ['إبحث', 'ابحث'],
        ];
    }

    public function up(): void
    {
        foreach ($this->texts() as $key => [$old, $new]) {
            $this->swap($key, $old, $new);
        }
    }

    public function down(): void
    {
        foreach ($this->texts() as $key => [$old, $new]) {
            $this->swap($key, $new, $old);
        }
    }

    private function swap(string $key, string $from, string $to): void
    {
        DB::table('settings')->where('key', $key)->where('value', $from)->update(['value' => $to]);
        DB::table('settings')->where('key', $key)->where('default_value', $from)->update(['default_value' => $to]);

        Cache::forget('settings');
    }
};
