<?php

namespace Database\Seeders;

use App\Models\Badge;
use App\Models\CelebrationEvent;
use App\Models\Challenge;
use App\Models\Currency;
use App\Models\Role;
use App\Models\Setting;
use App\Support\Access\PermissionExpander;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

/**
 * بيانات مجال التحديات وإنجازاتي التجريبيّة + إعداداته وصلاحيّاته.
 * لا يُسجَّل في DatabaseSeeder (قاعدة البناء 7).
 */
class ChallengeDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->settings();
        $this->celebrations();
        $this->badges();
        $this->challenges();
        $this->traineePermissions();

        // الإعدادات تُقرأ من كاش دائم — نُبطله بعد الكتابة (2.13)
        Cache::forget('settings');
    }

    /** كلّ رقم ونصّ في المجال من الإعدادات — ممنوع الحرق (2.13) */
    private function settings(): void
    {
        $rows = [
            // ---------------- التحديات (15)
            ['challenges.pass.percent', 'challenges', 'نسبة النجاح في التحدّي (%)', 'number', '60'],
            ['challenges.entry.default_currency', 'challenges', 'عملة الدخول الافتراضيّة', 'string', 'tickets'],
            ['challenges.filter.low_cost_max', 'challenges', 'أقصى تكلفة تُعتبَر «خفيفة»', 'number', '5'],
            ['challenges.filter.short_minutes', 'challenges', 'أقصى دقائق تُعتبَر «سريعة»', 'number', '10'],
            ['challenges.filter.medium_minutes', 'challenges', 'أقصى دقائق تُعتبَر «متوسّطة»', 'number', '30'],
            ['challenges.types', 'challenges', 'أنواع الحروب المتاحة', 'json', json_encode([
                'knowledge' => 'حرب المعلومات',
                'focus' => 'حرب التركيز',
                'survival' => 'حرب البقاء',
                'estimation' => 'حرب التقدير',
            ], JSON_UNESCAPED_UNICODE)],

            // ---------------- الاحتفالات (2.14)
            ['celebrations.auto_dismiss_seconds', 'celebrations', 'ثوانٍ قبل إغلاق الاحتفال تلقائيًّا', 'number', '6'],
            ['celebrations.default_message', 'celebrations', 'صيغة التهنئة الافتراضيّة', 'string', 'مبروك يا :name — :label 🎉'],

            // ---------------- الليدر بورد (7.3)
            ['leaderboard.rows_per_page', 'leaderboard', 'عدد الصفوف المعروضة', 'number', '50'],

            // ---------------- الستريك ونادي الخامسة (7.2)
            ['streaks.club_5am.window_start', 'streaks', 'بداية نافذة نادي الخامسة', 'string', '04:50'],
            ['streaks.club_5am.window_end', 'streaks', 'نهاية نافذة نادي الخامسة', 'string', '05:20'],
            ['streaks.reward.every_days', 'streaks', 'كلّ كم يوم متواصل تُصرَف مكافأة الستريك', 'number', '7'],
            ['streaks.heatmap.months', 'streaks', 'عدد شهور الخريطة الحراريّة', 'number', '3'],

            // ---------------- الألعاب (7.5)
            ['games.catalog', 'games', 'كتالوج الألعاب المتاحة', 'json', '[]'],
            ['games.ticket_cost', 'games', 'تكلفة اللعبة بالتذاكر', 'number', '1'],
        ];

        foreach ($rows as [$key, $group, $label, $type, $default]) {
            Setting::updateOrCreate(['key' => $key], [
                'group' => $group,
                'label_ar' => $label,
                'type' => $type,
                'default_value' => $default,
                'value' => $default,
            ]);
        }
    }

    /** أحداث احتفال المجال — ولكلّ حدث مستواه (2.14-أ) */
    private function celebrations(): void
    {
        $events = [
            ['challenge.finished', 'إتمام تحدّي', 1, 'خلّصت التحدّي يا :name — تمام كده.'],
            ['challenge.won', 'الفوز بتحدٍّ', 2, 'كسبت يا :name! 🎉'],
            ['challenge.first_win', 'أوّل فوز في التحديات', 3, 'مبروك يا :name — أوّل فوز ليك في ساحة التحدّي!'],
            ['badge.unlocked', 'فتح شارة جديدة', 2, 'شارة جديدة يا :name 🥇'],
        ];

        foreach ($events as [$key, $label, $tier, $message]) {
            CelebrationEvent::updateOrCreate(['key' => $key], [
                'label_ar' => $label,
                'tier' => $tier,
                'message_ar' => $message,
            ]);
        }
    }

    /** الشارات — وشرط الفتح مكتوب صراحةً لا لغزًا (7.4 · 24.5) */
    private function badges(): void
    {
        $rows = [
            ['first_step', 'الخطوة الأولى', 'خلّص أوّل تحدّي واحد.', 'challenges.finished', 1],
            ['warrior', 'محارب', 'خلّص 10 تحدّيات.', 'challenges.finished', 10],
            ['champion', 'بطل الساحة', 'اكسب 5 تحدّيات.', 'challenges.wins', 5],
            ['week_streak', 'أسبوع كامل', 'حافظ على ستريك 7 أيّام متواصلة.', 'streak.best_days', 7],
            ['month_streak', 'شهر بلا انقطاع', 'حافظ على ستريك 30 يوم متواصلة.', 'streak.best_days', 30],
            ['club_5am_10', 'صاحب الفجر', 'سجّل حضورك 10 أيّام في نادي الخامسة صباحًا.', 'club_5am.days', 10],
            ['xp_1000', 'ألف نقطة', 'اجمع 1000 XP.', 'xp.total', 1000],
            ['xp_10000', 'عشرة آلاف', 'اجمع 10000 XP.', 'xp.total', 10000],
        ];

        foreach ($rows as [$key, $name, $condition, $conditionKey, $value]) {
            Badge::updateOrCreate(['key' => $key], [
                'name_ar' => $name,
                'condition_text_ar' => $condition,
                'condition_key' => $conditionKey,
                'condition_value' => $value,
                'is_active' => true,
            ]);
        }
    }

    /** أربع حروب معتمَدة في الدستور (15.1 · 15.3 · 15.5 · 15.6) */
    private function challenges(): void
    {
        $tickets = Currency::query()->where('code', 'tickets')->value('id');

        $rows = [
            [
                'key' => 'knowledge_war',
                'name_ar' => 'حرب المعلومات',
                'description' => 'اختبر معلوماتك وسرعتك — جاوب صحّ وتقدّم.',
                'type' => 'knowledge',
                'color' => '#00d4b8',
                'entry_cost' => 2,
                'duration_minutes' => 10,
                'rewards' => ['xp' => 150, 'tickets' => 2],
                'items' => [
                    ['kind' => 'mcq', 'text' => 'إيه أهمّ خطوة قبل ما تبدأ أيّ مهمّة؟', 'options' => ['تحدّد المطلوب بالظبط', 'تبدأ على طول', 'تستنّى حدّ يفكّرك'], 'answer' => '0'],
                    ['kind' => 'mcq', 'text' => 'التغذية الراجعة المفيدة بتبقى…', 'options' => ['عامّة ومختصرة', 'محدّدة وقابلة للتنفيذ', 'متأخّرة'], 'answer' => '1'],
                    ['kind' => 'mcq', 'text' => 'أفضل طريقة تثبّت بيها معلومة اتعلّمتها؟', 'options' => ['تقراها تاني', 'تشرحها لحدّ', 'تحفظها'], 'answer' => '1'],
                    ['kind' => 'mcq', 'text' => 'لو الديدلاين قرب والشغل مش خالص، أوّل حاجة تعملها؟', 'options' => ['تسكت وتكمّل', 'تبلّغ بدري وتقترح خطّة', 'تطلب تأجيل بعد الميعاد'], 'answer' => '1'],
                    ['kind' => 'mcq', 'text' => 'الهدف الذكيّ (SMART) لازم يكون…', 'options' => ['طموح ومبهم', 'محدّد وقابل للقياس', 'طويل ومفصّل'], 'answer' => '1'],
                ],
            ],
            [
                'key' => 'focus_war',
                'name_ar' => 'حرب التركيز',
                'description' => 'عمل عميق بلا مقاطعة — والعدّ مبنيّ على أمانتك.',
                'type' => 'focus',
                'color' => '#45ecd7',
                'entry_cost' => 5,
                'duration_minutes' => 25,
                'rewards' => ['xp' => 200],
                'items' => [
                    ['kind' => 'task', 'text' => 'اقفل الإشعارات وحدّد نيّتك للجلسة دي.'],
                    ['kind' => 'task', 'text' => 'اشتغل 25 دقيقة على حاجة واحدة بس.'],
                    ['kind' => 'task', 'text' => 'اكتب في سطر إيه اللي خلّصته فعلًا.'],
                ],
            ],
            [
                'key' => 'survival_war',
                'name_ar' => 'حرب البقاء',
                'description' => 'جاوب صحّ وابقى… أوّل غلطة تخرجك.',
                'type' => 'survival',
                'color' => '#eab308',
                'entry_cost' => 3,
                'duration_minutes' => 8,
                'rewards' => ['xp' => 180, 'tickets' => 1],
                'items' => [
                    ['kind' => 'mcq', 'text' => 'أوّل علامة إنّك مش مركّز؟', 'options' => ['بتفتح التليفون كلّ شويّة', 'بتخلّص بدري', 'بتاخد نفس عميق'], 'answer' => '0'],
                    ['kind' => 'mcq', 'text' => 'المهمّة المتعثّرة الصحّ فيها إنّك…', 'options' => ['تسيبها', 'ترفع علم التعثّر بدري', 'تعيد كتابتها'], 'answer' => '1'],
                    ['kind' => 'mcq', 'text' => 'أحسن وقت تراجع فيه شغلك؟', 'options' => ['قبل التسليم بشويّة', 'بعد راحة قصيرة', 'وانت تعبان'], 'answer' => '1'],
                    ['kind' => 'mcq', 'text' => 'الاعتذار المقبول بيبقى…', 'options' => ['بعد الميعاد', 'قبل الميعاد وبسبب واضح', 'بلا سبب'], 'answer' => '1'],
                ],
            ],
            [
                'key' => 'estimation_war',
                'name_ar' => 'حرب التقدير',
                'description' => 'قدّر الرقم الأقرب للصحّ واكسب.',
                'type' => 'estimation',
                'color' => '#d4af37',
                'entry_cost' => 2,
                'duration_minutes' => 6,
                'rewards' => ['xp' => 120],
                'items' => [
                    ['kind' => 'number', 'text' => 'كم دقيقة في اليوم الواحد؟', 'answer' => 1440, 'tolerance' => 60, 'unit' => 'دقيقة'],
                    ['kind' => 'number', 'text' => 'كم يوم في السنة الميلاديّة العاديّة؟', 'answer' => 365, 'tolerance' => 2, 'unit' => 'يوم'],
                    ['kind' => 'number', 'text' => 'قدّر عدد ساعات النوم المفضَّلة أسبوعيًّا لشخص بالغ.', 'answer' => 56, 'tolerance' => 7, 'unit' => 'ساعة'],
                ],
            ],
        ];

        foreach ($rows as $row) {
            Challenge::updateOrCreate(['key' => $row['key']], [
                'name_ar' => $row['name_ar'],
                'description' => $row['description'],
                'color' => $row['color'],
                'entry_cost' => $row['entry_cost'],
                'entry_currency_id' => $tickets,
                'rewards' => $row['rewards'],
                'duration_minutes' => $row['duration_minutes'],
                'question_source' => ['items' => $row['items']],
                // نوع الحرب داخل limits ليُفلتَر منه بلا تعديل المخطّط
                'limits' => ['type' => $row['type'], 'max_active' => 5],
                'is_active' => true,
                'settings_locked' => false,
            ]);
        }
    }

    /**
     * صلاحيّات المتدرّب في شاشات هذا المجال — بنطاق SELF (12.2.1).
     * تُتخطّى بهدوء إن لم تكن مصفوفة الصلاحيّات مزروعة بعد.
     */
    private function traineePermissions(): void
    {
        $role = Role::query()->where('key', 'trainee')->first();

        if (! $role) {
            return;
        }

        $expander = app(PermissionExpander::class);

        foreach ([
            'war_participation.view',
            'war_participation.create',
            'wars_matches.view',
            'wars_matches.edit',
            'leaderboards.view',
            'achievements.view',
            'badges.view',
            'streaks.view',
            'streaks.create',
            'games.view',
        ] as $permission) {
            $expander->attachToRole($role, $permission, 'SELF');
        }
    }
}
