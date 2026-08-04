<?php

namespace App\Services\Images;

use App\Models\Membership;
use App\Models\User;
use RuntimeException;

/**
 * القائمة المقفولة لحقول قوالب الصور (12.14-د).
 *
 * ⛔ **الممنوع غير موجود في القائمة أصلًا — لا معطَّلًا**: الموبايل والبريد
 *    وجهة الطوارئ والملاحظات الإداريّة وأيّ بيانٍ حسّاس. ولذلك لا يوجد هنا
 *    «مفتاح مغلق» يمكن فتحه لاحقًا بالخطأ — الحقل ببساطة غير معرَّف.
 *
 * ⭐ والمحافظة حقل عامّ دائمًا ولا يجوز إخفاؤها (استثناء صريح من خصوصيّة الحقول).
 */
class ImageTemplateFields
{
    /** حقول متاحة لأيّ مستخدم */
    public const PUBLIC_FIELDS = [
        'name' => 'الاسم',
        'short_name' => 'الاسم المختصر',
        'code' => '#الكود',
        'country' => 'الدولة',
        'governorate' => 'المحافظة',
        'level' => 'مستوى الحساب',
        'xp' => 'XP',
        'rank' => 'الترتيب',
        'certificates_count' => 'عدد الشهادات',
        'joined_at' => 'تاريخ الانضمام',
    ];

    /** حقول المتطوّع — وRep مسموحة صراحةً (12.14-د) */
    public const VOLUNTEER_FIELDS = [
        'position' => 'البوزشن',
        'department' => 'القسم',
        'service_duration' => 'مدّة الخدمة',
        'rep' => 'درجة الالتزام (Rep)',
    ];

    /**
     * ⛔ تُذكَر هنا **لترفَض بوضوح** لو حاول أحدٌ حقنها عبر الـAPI أو الاستيراد —
     * لا لتظهر في أيّ قائمة اختيار.
     */
    public const FORBIDDEN = [
        'phone', 'mobile', 'email', 'emergency_contact', 'emergency_phone',
        'admin_notes', 'internal_notes', 'password', 'national_id', 'birthdate',
    ];

    /** مفاتيح غرض القالب — يقرؤها الكود، ومسمّياتها العربيّة في الإعدادات */
    public const PURPOSE_KEYS = ['marketing', 'leaderboard', 'achievement', 'volunteer_card'];

    /** لغتا القالب (13.4-ر) */
    public const LANGUAGE_KEYS = ['ar', 'en'];

    /** @return array<string,string> */
    public function all(): array
    {
        return self::PUBLIC_FIELDS + self::VOLUNTEER_FIELDS;
    }

    public function allows(string $field): bool
    {
        return array_key_exists($field, $this->all());
    }

    /**
     * التحقّق من طبقات القالب قبل الحفظ.
     *
     * @param  array<int, array<string,mixed>>  $layers
     *
     * @throws RuntimeException عند أيّ حقل ممنوع أو غير معروف
     */
    public function validateLayers(array $layers): void
    {
        foreach ($layers as $layer) {
            if (($layer['type'] ?? '') !== 'text') {
                continue;
            }

            $field = $layer['field'] ?? null;

            if ($field === null || $field === '') {
                continue; // نصّ ثابت — مسموح
            }

            if (in_array($field, self::FORBIDDEN, true)) {
                throw new RuntimeException("الحقل «{$field}» ممنوع نهائيًّا في قوالب الصور — بيانات حسّاسة لا تُنشَر.");
            }

            if (! $this->allows($field)) {
                throw new RuntimeException("الحقل «{$field}» مش من القائمة المسموحة.");
            }
        }
    }

    /**
     * قيم الحقول لمستخدم بعينه — للمعاينة ببيانات حقيقيّة وللتوليد.
     *
     * @return array<string,string>
     */
    public function values(User $user): array
    {
        $membership = $user->memberships()->where('status', 'active')->latest('id')->first();

        return [
            'name' => (string) $user->name,
            // ⭐ اسم العرض المختصر الجاهز: أدوات الاسم جزءٌ من الكلمة التالية (12.14-ج)
            'short_name' => $user->shortName((int) setting('images.short_name.units', 2)),
            'code' => '#'.$user->code,
            'country' => (string) ($user->country?->name_ar ?? ''),
            'governorate' => (string) ($user->governorate?->name_ar ?? ''),
            'level' => (string) ($user->level ?? ''),
            'xp' => (string) (int) $user->xp,
            'rank' => (string) $this->rank($user),
            'certificates_count' => (string) $user->certificates()->count(),
            'joined_at' => $user->created_at?->translatedFormat('F Y') ?? '',
            'position' => (string) ($membership?->position?->name_ar ?? ''),
            'department' => (string) ($membership?->entity?->name_ar ?? ''),
            'service_duration' => $this->serviceDuration($membership),
            'rep' => number_format((float) $user->balance('rep'), 2),
        ];
    }

    /** الجمهور المسموح لكلّ قالب (12.14-ز) */
    public function audiences(): array
    {
        return [
            'admin' => 'خاصّ بالإدارة',
            'volunteers' => 'متاح للمتطوّعين',
            'everyone' => 'متاح للجميع',
        ];
    }

    /**
     * أغراض القالب — العمود `image_templates.purpose` كان يُصادَق عليه بلا حقلٍ
     * يملؤه، فتبقى قيمته «marketing» أبدًا مهما صمّم المصمّم. والمسمّيات إعدادٌ
     * لا نصٌّ محروق (2.13)، والمفاتيح وحدها ثابتة لأنّ الكود يقرؤها.
     *
     * @return array<string,string>
     */
    public function purposes(): array
    {
        $configured = setting('images.purposes');

        return is_array($configured) && $configured !== []
            ? $configured
            : array_combine(self::PURPOSE_KEYS, self::PURPOSE_KEYS);
    }

    /**
     * لغة القالب — نسختان (ع/إ) كما في بطاقة المتطوّع (13.4-ر).
     *
     * @return array<string,string>
     */
    public function languages(): array
    {
        $configured = setting('images.languages');

        return is_array($configured) && $configured !== []
            ? $configured
            : array_combine(self::LANGUAGE_KEYS, self::LANGUAGE_KEYS);
    }

    /** الترتيب على XP — يُحسب لحظةَ اللقطة، ولذلك كلّ صورة تحمل تاريخها (12.14-هـ) */
    private function rank(User $user): int
    {
        return User::query()->where('xp', '>', (int) $user->xp)->count() + 1;
    }

    private function serviceDuration(?Membership $membership): string
    {
        if (! $membership || ! $membership->started_at) {
            return '';
        }

        $months = (int) $membership->started_at->diffInMonths(now());

        return $months < 12
            ? $months.' شهر'
            : intdiv($months, 12).' سنة';
    }
}
