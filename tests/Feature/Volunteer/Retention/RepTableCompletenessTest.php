<?php

namespace Tests\Feature\Volunteer\Retention;

use App\Models\RepRule;
use Illuminate\Support\Facades\Cache;

/**
 * جدول Rep الموحَّد (13.4-ن-أ) — القيمتان اللتان كانتا ناقصتين:
 * «قرار الحالة 8 **يدويّ +0.25 ↔ −0.5**» و«فرصة لجنة التحقيق **+1**».
 */
class RepTableCompletenessTest extends RetentionTestCase
{
    public function test_state8_manual_and_committee_chance_exist_with_constitutional_values(): void
    {
        // فرصة لجنة التحقيق: +1 لمعدّل الالتزام مع إعادة التفعيل
        $this->assertSame(1.0, rep_rule('task.committee_chance'));

        // قرار الحالة 8 اليدويّ: مداه +0.25 ↔ −0.5 — والقيمة حدّه الأعلى
        $this->assertSame(0.25, rep_rule('task.state8_manual'));
        $this->assertSame(0.25, (float) setting('workflow.repeated_return.rep_max', 0.25));
        $this->assertSame(-0.5, (float) setting('workflow.repeated_return.rep_min', -0.5));

        foreach (['task.state8_manual', 'task.committee_chance'] as $key) {
            $rule = RepRule::query()->where('key', $key)->first();

            $this->assertNotNull($rule, 'القيمة لازم تكون صفًّا في جدول Rep لا رقمًا في الكود: '.$key);
            $this->assertSame('tasks', $rule->group);
            $this->assertTrue($rule->is_active);
            $this->assertNotEmpty($rule->label_ar);
            $this->assertNotEmpty($rule->note, 'المدى المنصوص يُوثَّق مع القيمة');
        }
    }

    /** والقيمتان تُقرآن من `rep_rule()` — فتعديل الأدمن يسري فورًا (2.13). */
    public function test_the_values_follow_the_admin_table_not_the_code(): void
    {
        RepRule::query()->where('key', 'task.committee_chance')->update(['value' => 2.0]);
        Cache::forget('rep_rules');

        $this->assertSame(2.0, rep_rule('task.committee_chance'));
    }
}
