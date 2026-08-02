<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * بيانات عرض مجال «رحلة التسجيل» — بنك أسئلة الاختبار التمهيديّ (2.5-د-2).
 *
 * ⚠️ محتوى عرضٍ لا إعدادات: **تعريفات إعدادات الرحلة تُزرَع في هجرة الإنتاج**
 * `2026_08_09_120030_onboarding_settings_and_first_run_unification`، فالإنتاج
 * يشغّل الهجرات ولا يشغّل بذور العرض (2.13).
 */
class OnboardingDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('placement_test_questions')->exists()) {
            return;
        }

        // [النصّ, النوع, الخيارات, الصحيح, XP, تذاكر, نوع الوسيط, الرابط]
        $rows = [
            [
                'إيه أقرب حاجة بتوصف مستواك دلوقتي؟', 'choice',
                ['مبتدئ تمامًا', 'عندي أساسيّات', 'شغّال بالفعل في المجال'],
                null, 10, 0, 'none', null,
            ],
            [
                'لو زميلك سلّم شغلك على إنّه شغله، تعمل إيه؟', 'choice',
                ['أسكت وأكمّل', 'أكلّمه وأوثّق الموضوع للمشرف', 'أرفع صوتي في الجروب'],
                'أكلّمه وأوثّق الموضوع للمشرف', 15, 1, 'none', null,
            ],
            [
                'اكتب في سطر: إيه اللي مستنّيه من المنصّة؟', 'text',
                null, null, 5, 0, 'none', null,
            ],
            [
                'شوف الفيديو التعريفيّ وقولنا: القيمة الأساسيّة اللي اتكلّمنا عنها إيه؟', 'choice',
                ['السرعة', 'الاحترام', 'المنافسة'],
                'الاحترام', 20, 1, 'video', 'https://example.com/media/intro.mp4',
            ],
        ];

        foreach ($rows as $index => [$prompt, $type, $options, $correct, $xp, $tickets, $media, $url]) {
            DB::table('placement_test_questions')->insert([
                'prompt' => $prompt,
                'media_kind' => $media,
                'media_url' => $url,
                'embed_html' => null,
                'type' => $type,
                'options' => $options === null ? null : json_encode($options, JSON_UNESCAPED_UNICODE),
                'correct_answer' => $correct,
                'reward_xp' => $xp,
                'reward_tickets' => $tickets,
                'sort_order' => $index + 1,
                'is_active' => true,
                'created_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command?->info('بيانات onboarding جاهزة.');
    }
}
