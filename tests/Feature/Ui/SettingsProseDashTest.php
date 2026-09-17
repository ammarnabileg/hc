<?php

namespace Tests\Feature\Ui;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;

/**
 * الطبقة الثالثة من حارس الشرطة الطويلة: **الجدول نفسه**.
 *
 * ⚠️ لماذا لا يكفي مسحُ الكود: نصوص الإعدادات تُكتَب من ثلاثة أماكن — القوالب
 * والبذور و**المايجريشنز**. والمايجريشن القائم **لا يُعدَّل** (قاعدة البناء §1)،
 * فبقيت 59 صفًّا تحمل «—» بينما الكودُ كلُّه نظيف: الشاشة تقرأ من الجدول لا من
 * الملفّ. فالقياس هنا على **ما يصل المستخدم فعلًا** بعد أن تجري المايجريشنز.
 *
 * وتُستثنى الشرطة اليتيمة `'—'` وحدها: هي «لا قيمة» في خانةٍ فارغة.
 */
class SettingsProseDashTest extends UiTestCase
{
    #[Test]
    public function no_setting_row_carries_a_prose_dash(): void
    {
        $offenders = [];

        $rows = DB::table('settings')
            ->where(fn ($q) => $q->where('value', 'like', '%—%')->orWhere('default_value', 'like', '%—%'))
            ->get(['key', 'value', 'default_value']);

        foreach ($rows as $row) {
            foreach (['value', 'default_value'] as $column) {
                if ($this->isProseDash((string) $row->{$column})) {
                    $offenders[] = $row->key.'.'.$column.'  '.mb_substr((string) $row->{$column}, 0, 80);
                }
            }
        }

        $this->assertSame([], $offenders,
            'صفوف إعدادات فيها شرطة طويلة توصل للشاشة. صحّحها بمايجريشن جديد لا بتعديل القديم: '
            ."\n".implode("\n", $offenders));
    }

    /** شرطةٌ تفصل كلامًا: على جانبَيها حرفٌ أو رقم، وليست الخانة الفارغة. */
    private function isProseDash(string $value): bool
    {
        if (trim($value) === '—') {
            return false;
        }

        return (bool) preg_match('/[\p{L}\p{N}][^—]*—[^—]*[\p{L}\p{N}]/u', $value);
    }
}
