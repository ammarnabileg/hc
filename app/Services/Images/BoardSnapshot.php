<?php

namespace App\Services\Images;

use App\Models\User;
use Illuminate\Support\Collection;

/**
 * لقطة لوحة قابلة للاستخراج كصورة (12.14-هـ).
 *
 * **لماذا نُمرّر اللقطة موقَّعةً في الرابط بدل إعادة حساب كلّ لوحة على الخادم؟**
 * لأنّ اللوحات في المنصّة عشرات (ليدر بوردات · إحصاءات · بطاقات إنجاز) ويملكها
 * مجالات مختلفة؛ فلو بنى الاستخراجُ نسخةً ثانيةً من كلّ حساب لصار **ازدواجًا**
 * وهو ما يمنعه الدستور صراحةً («محرّك واحد — صفر ازدواج»). فالصفحة تُرسِل ما
 * عرضَته فعلًا، **والتوقيع يمنع التزوير**، والصورة تحمل تاريخ اللقطة.
 *
 * ⭐ وقاعدتان لا تُخترقان مهما كانت الحمولة:
 *   1. **المحافظة لا تُخفى أبدًا** — تُقرأ من قاعدة البيانات وتُكتب فوق أيّ قيمة واردة.
 *   2. **الاسم يُختصَر** بقاعدة أدوات الاسم (12.14-ج).
 */
class BoardSnapshot
{
    /** أنواع اللوحات المسموحة — قائمة مقفولة فلا يمرّ نوعٌ غير معروف للرسّام */
    public const KINDS = ['leaderboard', 'stats', 'card'];

    public function __construct(
        public string $kind,
        public string $title,
        public string $subtitle,
        /** @var array<int, array<string,mixed>> */
        public array $rows,
    ) {}

    /** ترميز مضغوط في الرابط — والتوقيع هو ما يحمي المحتوى لا الترميز */
    public function encode(): string
    {
        $json = json_encode([
            'k' => $this->kind,
            't' => $this->title,
            's' => $this->subtitle,
            'r' => $this->rows,
        ], JSON_UNESCAPED_UNICODE);

        $packed = function_exists('gzdeflate') ? gzdeflate((string) $json, 9) : false;

        return $packed === false
            ? 'p'.self::base64url((string) $json)
            : 'z'.self::base64url($packed);
    }

    public static function decode(string $payload): self
    {
        $body = substr($payload, 1);
        $raw = self::base64urlDecode($body);

        if (str_starts_with($payload, 'z') && function_exists('gzinflate')) {
            $raw = @gzinflate($raw) ?: '';
        }

        $data = json_decode($raw, true);
        $data = is_array($data) ? $data : [];

        $kind = (string) ($data['k'] ?? 'leaderboard');

        return new self(
            in_array($kind, self::KINDS, true) ? $kind : 'leaderboard',
            (string) ($data['t'] ?? ''),
            (string) ($data['s'] ?? ''),
            array_values(array_filter((array) ($data['r'] ?? []), 'is_array')),
        );
    }

    /**
     * تطبيع الصفوف قبل الرسم:
     * الاسم يُختصَر · **والمحافظة تُقرأ من المصدر ولا يجوز إخفاؤها** (12.14-د).
     *
     * @return array<int, array<string,mixed>>
     */
    public function normalizedRows(int $limit): array
    {
        $rows = array_slice($this->rows, 0, max(1, $limit));

        $userIds = collect($rows)->pluck('u')->filter()->map('intval')->unique()->values();

        /** @var Collection<int,User> $users */
        $users = $userIds->isEmpty()
            ? collect()
            : User::query()
                ->with(['governorate:id,name_ar'])
                ->whereIn('id', $userIds)
                ->get(['id', 'name', 'avatar_path', 'avatar_sizes', 'governorate_id'])
                ->keyBy('id');

        foreach ($rows as $i => $row) {
            $user = isset($row['u']) ? $users->get((int) $row['u']) : null;

            $rows[$i]['name'] = ShortName::of((string) ($user?->name ?? ($row['name'] ?? '')));

            // ⭐ المحافظة حقل عامّ دائمًا — تُكتب من المصدر فوق أيّ قيمة واردة
            $rows[$i]['gov'] = (string) ($user?->governorate?->name_ar ?? ($row['gov'] ?? ''));

            $rows[$i]['value'] = (string) ($row['value'] ?? '');
            $rows[$i]['rank'] = isset($row['rank']) ? (int) $row['rank'] : ($i + 1);
            $rows[$i]['me'] = (bool) ($row['me'] ?? false);
            $rows[$i]['user'] = $user;
        }

        return $rows;
    }

    /** صفّ المستخدم نفسه — لخيار «صفّي أنا» (12.14-هـ) */
    public function myRows(?int $userId): array
    {
        $mine = array_values(array_filter(
            $this->rows,
            fn ($row) => ($row['me'] ?? false) || (isset($row['u']) && (int) $row['u'] === (int) $userId),
        ));

        return $mine !== [] ? $mine : array_slice($this->rows, 0, 1);
    }

    public static function base64url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    public static function base64urlDecode(string $value): string
    {
        return (string) base64_decode(strtr($value, '-_', '+/'), true);
    }
}
