<?php

namespace App\Services\Admin\System;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * 🖥️ إعدادات التعلّم (24.4 · 12.2.2 `learning_ux.*`) — خمس مجموعات: عرض
 * الدرس · التعليقات · الملاحظات · التوقيت والقفل · الاختبارات.
 *
 * لماذا قوائم مفاتيح صريحة لا Prefix واحد (خلافًا لـ`FinanceSettings`)؟ لأنّ
 * إعدادات كلّ مجموعة هنا موزَّعة أصلًا على أكثر من مجال (`learning.*` ·
 * `engagement.*` · `availability.*` · `system.*` · `exams.*`) بحكم أنّها بُنيت
 * قبل هذه الشاشة لخدمة شاشات المتدرّب مباشرةً — فلا Prefix واحد يجمعها.
 *
 * وكلّ مفتاح هنا **حقيقيّ وله قارئٌ في الكود** (2.13): حيث لا إعداد بعد
 * (Override التعليقات لكلّ تدريب · مراجعة قبل النشر · حدّ المحاولات · مصدر
 * منطقة زمنيّة واحد قابل للاختيار) لا يُخترَع حقلٌ فارغ — يبقى غائبًا حتى
 * يُبنى قارئه أوّلًا، تمامًا كتاب «النصوص والمحتوى» الفارغ في هَب التطوّع.
 */
class LearningUxSettings
{
    public function __construct(private readonly SettingsRegistry $registry) {}

    /** @return array<string, array{label:string, hint:string, keys:list<string>}> */
    public function groups(): array
    {
        return [
            'lesson_ux' => [
                'label' => setting('system.learning_ux_settings.groups_1', 'عرض الدرس'),
                'hint' => setting('system.learning_ux_settings.groups_2', 'التبديلات التي تظهر للمتدرّب أثناء الدرس والتدريب.'),
                'keys' => [
                    'learning.ux.resume_enabled',
                    'learning.cta.resume_where_left',
                    'learning.resume.scan_limit',
                    'learning.ux.half_banner_enabled',
                    'learning.ux.bookmark_enabled',
                    'learning.bookmark.add',
                    'learning.ux.share_enabled',
                    'engagement.social_proof.enabled',
                ],
            ],
            'comments' => [
                'label' => setting('system.learning_ux_settings.groups_3', 'التعليقات'),
                'hint' => setting('system.learning_ux_settings.groups_4', 'تعليقات الفيديو تحت الدرس — تفعيل · لايك وردّ · تحميل تدريجيّ · حدّ الطول.'),
                'keys' => [
                    'learning.comments.enabled',
                    'learning.comments.like_reply_enabled',
                    'learning.comments.per_page',
                    'learning.comments.max_length',
                ],
            ],
            'notes' => [
                'label' => setting('system.learning_ux_settings.groups_5', 'الملاحظات'),
                'hint' => setting('system.learning_ux_settings.groups_6', 'مساحة ملاحظات التدريب المشتركة بين دروسه.'),
                'keys' => [
                    'learning.notes.enabled',
                    'learning.notes.max_length',
                    'learning.notes.autosave_delay_ms',
                ],
            ],
            'timing_lock' => [
                'label' => setting('system.learning_ux_settings.groups_7', 'التوقيت والقفل'),
                'hint' => setting('system.learning_ux_settings.groups_8', 'كشف المنطقة الزمنيّة · منطقة الفشل الافتراضيّة · قفل خارج الأوقات · نصّ القفل.'),
                'keys' => [
                    'availability.detect.enabled',
                    'system.timezone',
                    'learning.lock.outside_hours_enabled',
                    'learning.lock.badge',
                ],
            ],
            'quiz' => [
                'label' => setting('system.learning_ux_settings.groups_9', 'الاختبارات'),
                'hint' => setting('system.learning_ux_settings.groups_10', 'القيم الافتراضيّة العامّة التي يرثها كلّ تدريب جديد.'),
                'keys' => [
                    'learning.quiz.retry_wait_seconds',
                    'learning.quiz.shuffle_questions',
                    'learning.quiz.shuffle_options',
                    'exams.pass_score.default',
                    'exams.questions.default_count',
                ],
            ],
        ];
    }

    public function allKeys(): array
    {
        return array_merge(...array_values(array_map(fn (array $group) => $group['keys'], $this->groups())));
    }

    /** @return Collection<int, Setting> */
    public function settingsOf(string $group): Collection
    {
        $keys = $this->groups()[$group]['keys'] ?? [];

        if (! $keys) {
            return collect();
        }

        $stored = Setting::query()->whereIn('key', $keys)->get()->keyBy('key');

        // ⭐ الترتيب بترتيب المجموعة المعلَن لا أبجديًّا — القراءة تتبع منطق الشاشة
        return collect($keys)->map(fn (string $key) => $stored->get($key))->filter()->values();
    }

    public function resetGroup(string $group, User $actor): int
    {
        $count = 0;

        foreach ($this->settingsOf($group) as $setting) {
            $this->registry->reset($setting, $actor);
            $count++;
        }

        return $count;
    }
}
