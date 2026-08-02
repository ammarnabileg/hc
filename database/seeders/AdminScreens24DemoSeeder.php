<?php

namespace Database\Seeders;

use App\Models\LessonQuestion;
use App\Models\Referral;
use App\Models\ReportSchedule;
use App\Models\ReportScheduleRun;
use App\Models\Setting;
use App\Models\User;
use App\Services\AdminScreens\ScreenSettings;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * بيانات شاشات القسم 24 الناقصة (بنك الأسئلة · الريفيرال والسفراء ·
 * التقارير المجدولة · مرآة الاجتماعات) ومعها **إعداداتها كاملة**.
 *
 * 🏆 القاعدة الذهبيّة (2.13): كلّ مفتاح في كتالوج `ScreenSettings` يُزرَع هنا
 * بقيمته الافتراضيّة — فلا يوجد في كود هذه الشاشات رقمٌ ولا نصٌّ محروق.
 *
 * ⚠️ ولا يُسجَّل هذا السيدر في `DatabaseSeeder` (قاعدة البناء §7).
 */
class AdminScreens24DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->settings();
        $this->questionBank();
        $this->referrals();
        $this->reportSchedules();

        Cache::forget('settings');
        $this->command?->info('بيانات شاشات القسم 24 جاهزة.');
    }

    /** كلّ مفاتيح الشاشات الأربع بقيمها الافتراضيّة القابلة للـReset */
    public function settings(): void
    {
        foreach (ScreenSettings::catalog() as $key => [$screen, $group, $label, $type, $default, $hint, $ownerOnly]) {
            Setting::updateOrCreate(['key' => $key], [
                'group' => $group,
                'label_ar' => $label,
                // النوع «أسطر» يُخزَّن JSON ويُعرَض أسطرًا في الشاشة
                'type' => $type === 'lines' ? 'json' : $type,
                'default_value' => $default,
                'value' => $default,
                'hint' => $hint ?: null,
                'is_sensitive' => $ownerOnly,
                'is_owner_only' => $ownerOnly,
            ]);
        }
    }

    /**
     * صعوبات واقعيّة للأسئلة القائمة + سؤال معطّل واحد كي تظهر الحالة الثانية
     * في الشاشة بلا اختراع بيانات لا معنى لها.
     */
    private function questionBank(): void
    {
        $levels = ['easy', 'medium', 'hard'];
        $index = 0;

        foreach (LessonQuestion::query()->orderBy('id')->get() as $question) {
            $question->forceFill([
                'difficulty' => $levels[$index % 3],
                'is_active' => true,
            ])->save();

            $index++;
        }

        LessonQuestion::query()->orderByDesc('id')->first()?->forceFill(['is_active' => false])->save();
    }

    /** حالات مراجعة متنوّعة لسطور الدعوات القائمة */
    private function referrals(): void
    {
        $states = ['pending', 'paid', 'held'];
        $index = 0;

        foreach (Referral::query()->orderBy('id')->get() as $referral) {
            $state = $states[$index % 3];

            $referral->forceFill([
                'payout_status' => $state,
                'payout_note' => $state === 'held' ? 'نمط دعوات متقارب — محتاج تدقيق قبل الصرف.' : null,
                'is_flagged' => $state === 'held',
            ])->save();

            $index++;
        }
    }

    /** جدولتان: واحدة نشطة وصلت، وواحدة موقوفة — كي تُختبَر الحالتان */
    private function reportSchedules(): void
    {
        $owner = User::query()->orderBy('id')->first();

        if (! $owner) {
            return;
        }

        $weekly = ReportSchedule::updateOrCreate(['name' => 'تقرير المستخدمين الأسبوعيّ'], [
            'report_tab' => 'users',
            'is_financial' => false,
            'format' => 'csv',
            'frequency' => 'weekly',
            'day_of_week' => 0,
            'hour' => (int) setting('report_schedules.default_hour', 7),
            'timezone' => (string) setting('report_schedules.default_timezone', 'Africa/Cairo'),
            'period_days' => 7,
            'include_comparison' => true,
            'skip_when_empty' => true,
            'recipient_emails' => [$owner->email],
            'recipient_role_ids' => [],
            'recipient_user_ids' => [],
            'status' => 'active',
            'last_run_at' => now()->subWeek(),
            'last_result' => 'sent',
            'next_run_at' => now()->addDay(),
            'created_by' => $owner->id,
        ]);

        ReportScheduleRun::updateOrCreate(
            ['report_schedule_id' => $weekly->id, 'ran_at' => $weekly->last_run_at],
            [
                'result' => 'sent',
                'rows_count' => 7,
                'recipients_count' => 1,
                'attempt' => 1,
                'was_manual' => false,
                'message' => 'اتبعت لـ1 مستقبِل ✓',
            ],
        );

        ReportSchedule::updateOrCreate(['name' => 'تقرير التدريبات الشهريّ'], [
            'report_tab' => 'training',
            'is_financial' => false,
            'format' => 'csv',
            'frequency' => 'monthly',
            'day_of_month' => 1,
            'hour' => 8,
            'timezone' => (string) setting('report_schedules.default_timezone', 'Africa/Cairo'),
            'period_days' => 30,
            'include_comparison' => false,
            'skip_when_empty' => true,
            'recipient_emails' => [$owner->email],
            'recipient_role_ids' => [],
            'recipient_user_ids' => [],
            'status' => 'paused',
            'created_by' => $owner->id,
        ]);

        // تنظيف أيّ سطور يتيمة لو أُعيد تشغيل السيدر بعد حذف جدولة
        DB::table('report_schedule_runs')
            ->whereNotIn('report_schedule_id', ReportSchedule::query()->pluck('id'))
            ->delete();
    }
}
