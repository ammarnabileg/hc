<?php

namespace App\Services\Notifications;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * ⭐ **جدار الإقرار الإلزاميّ «قبل المتابعة»** (13.2).
 *
 * النصّ: «**إقرار إلزاميّ (Acknowledge): «قرأتُ وفهمت» للتوجيهات الحرجة قبل
 * المتابعة**». وكلمة «قبل المتابعة» شرطُ **مرورٍ** لا زينةُ بوب-أب: كان الإقرار
 * مبنيًّا كاملًا — يُطلَب في `/announcements` وحدها، في نافذةٍ **تُغلَق بـ✕**،
 * وصاحب التوجيه الحرج غير المُقَرّ يفتح `/dashboard` و`/library` و`/events`
 * و`/store` و`/profile` كلّها بـ200. فالتوجيه الحرج (سياسة · تحذير قانونيّ)
 * **بلا ضمان وصول**، وهو عين ما بُني الإقرار ليضمنه.
 *
 * الجدار هنا هو الوصلة الناقصة: **لا تصفّح قبل الإقرار** — يُقاس على الخادم لا
 * بإخفاء زرّ ولا بنافذةٍ يقدر المستخدم يغلقها.
 *
 * ⚠️ **والإقرار ليس سجنًا** — ثلاثة أبوابٍ تبقى مفتوحة دائمًا (`exemptPaths`):
 *  1) **الإقرار نفسه** (`/announcements` وأفعالها) وإلّا فالتحويلة حلقةٌ مغلقة.
 *  2) **الخروج** — حقّ المستخدم أن يقفل جلسته مهما كانت حالته (نفس قاعدة
 *     جدار الاحتواء 12.1-متقدّم-1)، ومعه إنهاء الانتحال.
 *  3) **الأمان ودليل المستخدم** — إنهاء الجلسات وتصدير البيانات (10.4) وصفحات
 *     المساعدة (12.6-ج): مسارُ التوجيه نفسه قد يحيل إليها بزرّ CTA، فقفلها
 *     يقفل قراءة ما نطالبه بالإقرار به.
 */
class AcknowledgementGate
{
    public function __construct(private readonly AnnouncementFeed $feed) {}

    /** هل يُمنَع صاحب هذا الطلب حتّى يُقِرّ؟ */
    public function blocks(Request $request): bool
    {
        if (! setting('announcements.acknowledge.enforce', true)) {
            return false;
        }

        $user = $request->user();

        if (! $user instanceof User) {
            return false;
        }

        if ($this->passes($request)) {
            return false;
        }

        return $this->pendingFor($user) !== null;
    }

    /**
     * المسارات المفتوحة رغم الإقرار المعلّق — قائمة إعدادات لا قيمًا محروقة (2.13).
     *
     * @return list<string>
     */
    public function exemptPaths(): array
    {
        $configured = setting('announcements.acknowledge.exempt_paths');

        if (is_array($configured) && $configured !== []) {
            return array_values(array_filter(array_map('strval', $configured)));
        }

        return [
            'announcements', 'announcements/*',   // الإقرار نفسه وفيده
            'logout', 'impersonate/stop',         // الخروج لا يُقفَل أبدًا
            'settings/security', 'settings/security/*',
            'help', 'help/*',                     // دليل المستخدم ووجهة زرّ CTA
            'up', 'webhooks/*',                   // خارج جلسة المستخدم أصلًا
        ];
    }

    /** هل هذا الطلب من الأبواب المفتوحة؟ */
    public function passes(Request $request): bool
    {
        foreach ($this->exemptPaths() as $pattern) {
            if ($request->is($pattern) || $request->is(ltrim($pattern, '/'))) {
                return true;
            }
        }

        return false;
    }

    /**
     * أوّل توجيهٍ حرجٍ لم يُقَرّ — أو `null`.
     *
     * وفحصٌ رخيصٌ أوّلًا: بناء الفيد يقيّم شرائح الاستهداف في PHP، وتشغيله على
     * **كلّ طلب** ثمنٌ لا يُدفَع لأجل حالةٍ نادرة. فسؤال SQL واحد يقول «هل هناك
     * منشورٌ حيٌّ يطلب إقرارًا ولم يُقِرّه هذا المستخدم؟» — وإن كان الجواب لا،
     * وهو الغالب، انتهى الطلب بلا بناء.
     */
    public function pendingFor(User $user): ?Announcement
    {
        if (! $this->hasCandidate($user)) {
            return null;
        }

        return $this->feed->pendingAcknowledge($user);
    }

    /**
     * الجدار: تحويلةٌ إلى صفحة التعليمات حيث البوب-أب غير القابل للإغلاق.
     *
     * ولماذا تحويلة لا صفحة مكان الصفحة (كجدار الاحتواء)؟ لأنّ الاحتواء **حالةُ
     * حسابٍ** لا مخرج منها بفعلٍ من صاحبه، أمّا هذا **بابٌ يفتحه المستخدم بنفسه
     * في ثانية** — فالمقصد أن يصل إلى الزرّ لا أن يُعرَض عليه جدار.
     */
    public function wall(Request $request): Response
    {
        $notice = (string) setting(
            'announcements.acknowledge.wall_notice',
            'في توجيه مهمّ مستنّي إقرارك — اقراه وأقِرّ بيه عشان تكمّل.',
        );

        if ($request->expectsJson()) {
            return response()->json([
                'acknowledge_required' => true,
                'message' => $notice,
                'url' => route('announcements.index'),
            ], 403);
        }

        return redirect()->route('announcements.index')->with('status', $notice);
    }

    /** سؤال SQL واحد: منشورٌ حيٌّ يطلب إقرارًا ولا صفَّ إقرارٍ له من هذا المستخدم */
    private function hasCandidate(User $user): bool
    {
        return Announcement::query()
            ->where('status', (string) setting('announcements.status.published', 'published'))
            ->whereNull('recurrence')
            ->where('show_in_feed', true)
            ->where('requires_acknowledge', true)
            ->where(fn ($q) => $q->whereNull('scheduled_at')->orWhere('scheduled_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                ->from('announcement_reads')
                ->whereColumn('announcement_reads.announcement_id', 'announcements.id')
                ->where('announcement_reads.user_id', $user->id)
                ->whereNotNull('announcement_reads.acknowledged_at'))
            ->exists();
    }
}
