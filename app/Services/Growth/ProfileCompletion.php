<?php

namespace App\Services\Growth;

use App\Models\User;
use App\Services\Events\LedgerBridge;
use App\Services\Events\Tracker;
use Illuminate\Support\Facades\DB;

/**
 * بار «أكمل ملفك» ومكافأته (21.1-ب).
 *
 * لماذا؟ لأنّ الصورة والدولة والمحافظة هي ما يجعل القوالب البصريّة (12.14) تخرج
 * كاملةً — فالمكافأة هنا تشتري **جودة بيانات** لا مجرّد نقرة.
 *
 * والمكافأة **مرّة واحدة مهما تكرّر النداء**: الحارس تحديثٌ مشروط على
 * `profile_completion_rewarded_at` فأوّل نداء وحده هو الذي يمنح.
 */
class ProfileCompletion
{
    public function __construct(
        private readonly LedgerBridge $ledger,
        private readonly Tracker $tracker,
    ) {}

    /**
     * الحقول المحسوبة — إعداد لا قائمة محروقة (2.13).
     * كلّ عنصر: [المفتاح => التسمية].
     *
     * @return array<string,string>
     */
    public function fields(): array
    {
        $configured = setting('growth.profile_completion.fields');

        if (is_array($configured) && $configured !== []) {
            return array_map(fn ($v) => (string) $v, $configured);
        }

        return [
            'avatar_path' => setting('growth.profile_completion.fields_1', 'صورة الملفّ'),
            'phone' => setting('growth.profile_completion.fields_2', 'رقم الموبايل'),
            'country_id' => setting('growth.profile_completion.fields_3', 'الدولة'),
            'governorate_id' => setting('growth.profile_completion.fields_4', 'المحافظة'),
            'birthdate' => setting('growth.profile_completion.fields_5', 'تاريخ الميلاد'),
            'gender' => setting('growth.profile_completion.fields_6', 'النوع'),
        ];
    }

    /** الحقول الناقصة — نعرضها للمستخدم ليعرف **ماذا يفعل** لا أن نعاتبه (2.17) */
    public function missing(User $user): array
    {
        $missing = [];

        foreach ($this->fields() as $key => $label) {
            if ($this->isBlank($user->{$key} ?? null)) {
                $missing[$key] = $label;
            }
        }

        return $missing;
    }

    /**
     * ⭐ **تقدّم مُهدى (Endowed Progress — 2.9-2)**: المؤشّر يبدأ من نقطة
     * **مُنجَزة** لا من صفر.
     *
     * لماذا؟ لأنّ الصفر يقول للمستخدم «لسّه مبدأتش»، بينما هو **بدأ فعلًا**:
     * سجّل واختار اسمه وفعّل حسابه. والبداية المُهداة تجعل الشريط يبدو
     * «شبه مبدوء» فيرتفع احتمال إكماله — وهي ليست تجميلًا كاذبًا (2.9 تمنع
     * الأرقام الوهميّة): الرصيد المُهدى **معلَن ومضبوط من الإعدادات**،
     * و**100% لا تُبلَغ إلّا بامتلاء كلّ الحقول فعلًا** فلا تُمنَح مكافأة بلا عمل.
     */
    public function endowedPercent(): int
    {
        return max(0, min(90, (int) setting('growth.profile_completion.endowed_percent', 20)));
    }

    public function percent(User $user): int
    {
        $fields = $this->fields();
        $total = count($fields);

        if ($total === 0) {
            return 100;
        }

        $filled = $total - count($this->missing($user));

        if ($filled >= $total) {
            return 100;
        }

        $base = $this->endowedPercent();

        return (int) round($base + ($filled / $total) * (100 - $base));
    }

    public function rewardTickets(): int
    {
        return (int) setting('growth.profile_completion.reward_tickets', 3);
    }

    public function enabled(): bool
    {
        return (bool) setting('growth.profile_completion.enabled', true);
    }

    /** هل مُنحت المكافأة من قبل؟ */
    public function rewarded(User $user): bool
    {
        return $user->profile_completion_rewarded_at !== null;
    }

    /**
     * ⭐ **قراءةٌ بلا مِنح** — الحساب وتحديث الكاش فقط. `sync()` كانت تُستدعى من
     * `GET /profile/completion` نفسه ومن بار التذكير على شاشاتٍ عاديّة (dashboard
     * · profile.me · settings.index)، وكانت تمنح المكافأة **داخل طلب GET** —
     * فعلٌ ماليٌّ (12.9-جهة) على فعلٍ يُفترَض به ألّا يغيّر شيئًا (طلبٌ مموَّه
     * بـ`<img>`/Prefetch يقدر يُطلقها بلا أن يضغط المستخدم شيئًا). المِنح
     * الفعليّ صار محصورًا في نقطتين غير-GET فقط: `SettleGrowthOnLogin` (حدث
     * دخولٍ حقيقيّ) و`ProfileCompletionController::claim()` (POST محميٌّ
     * بـCSRF) — فلا يبقى مسارٌ يمنح على طلب قراءة.
     *
     * @return array{percent:int, missing:array<string,string>, granted:bool, eligible:bool, tickets:int, rewarded:bool}
     */
    public function sync(User $user): array
    {
        $percent = $this->percent($user);

        if ((int) $user->profile_completion_percent !== $percent) {
            $user->forceFill(['profile_completion_percent' => $percent])->saveQuietly();
        }

        $rewarded = $this->rewarded($user);

        return [
            'percent' => $percent,
            'missing' => $this->missing($user),
            'granted' => false,
            'eligible' => $percent >= 100 && ! $rewarded,
            'tickets' => $this->rewardTickets(),
            'rewarded' => $rewarded,
        ];
    }

    /**
     * المنح الفعليّ — **idempotent**: تحديثٌ مشروط يضمن أنّ أوّل نداء وحده ينجح،
     * فلا تتضاعف التذاكر بتحديث الصفحة ولا بنداءين متزامنين.
     */
    public function grant(User $user): bool
    {
        $tickets = $this->rewardTickets();

        if (! $this->enabled() || $tickets <= 0 || ! $user->isActive() || $this->rewarded($user)) {
            return false;
        }

        $claimed = DB::table('users')
            ->where('id', $user->id)
            ->whereNull('profile_completion_rewarded_at')
            ->update(['profile_completion_rewarded_at' => now(), 'updated_at' => now()]);

        if ($claimed !== 1) {
            return false;
        }

        $this->ledger->credit(
            $user,
            (string) setting('growth.profile_completion.reward_currency', 'tickets'),
            $tickets,
            'growth',
            (string) setting('growth.profile_completion.reward_reason', 'مكافأة إكمال الملفّ الشخصيّ'),
            $user,
        );

        $this->tracker->record('profile_completed', $user, $user->id);

        return true;
    }

    /** الحقل «فاضي» لو null أو نصّ فارغ أو صفر مرجعيّ */
    private function isBlank(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        if (is_numeric($value)) {
            return (float) $value === 0.0;
        }

        return false;
    }
}
