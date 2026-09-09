<?php

namespace App\Services\Admin\Volunteer;

use App\Models\Currency;
use App\Models\Membership;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Scope\ScopeFilter;
use Illuminate\Support\Collection;

/**
 * إدارة المكافآت (12.9): منح/خصم رصيد لكودٍ واحد أو مئات دفعةً واحدة.
 *
 * قواعد حاكمة:
 *  - ⭐ **الخصم يقدر ينزل تحت الصفر** — مسموح صراحةً ولا يُقَصّ عند الصفر.
 *  - الأكواد الخاطئة/المكرّرة **تُستبعَد بتنبيه** ولا تُفشِل العمليّة صامتةً.
 *  - معاينة قبل التنفيذ (رصيد قبل/بعد) ثمّ تأكيد نهائيّ بملخّص.
 */
class RewardGrantService
{
    /** تفكيك النصّ الملصوق: مسافات · فواصل · أسطر · تبويب */
    public static function parseCodes(string $raw): array
    {
        $parts = preg_split('/[\s,;\r\n\t]+/u', trim($raw)) ?: [];

        return array_values(array_filter(array_map(
            fn ($c) => mb_strtoupper(trim($c)),
            $parts,
        ), fn ($c) => $c !== ''));
    }

    /**
     * تحقّق من الأكواد وبناء جدول المعاينة.
     *
     * @param  array<string,float>  $perCode  قيمة لكلّ كود (تعلو القيمة الموحّدة)
     * @return array{rows:Collection,invalid:array,duplicates:array,total:float}
     */
    public static function preview(string $raw, string $currencyCode, float $amount, string $direction, array $perCode = []): array
    {
        $codes = self::parseCodes($raw);
        $max = (int) setting('rewards.max_codes', 2000);
        $codes = array_slice($codes, 0, $max);

        $seen = [];
        $duplicates = [];
        $unique = [];

        foreach ($codes as $code) {
            if (isset($seen[$code])) {
                $duplicates[] = $code;

                continue;
            }

            $seen[$code] = true;
            $unique[] = $code;
        }

        $users = User::query()
            ->whereIn('code', $unique)
            ->get(['id', 'name', 'code'])
            ->keyBy('code');

        $invalid = array_values(array_diff($unique, $users->keys()->all()));

        $sign = $direction === 'debit' ? -1 : 1;
        $total = 0.0;
        $rows = collect();

        foreach ($unique as $code) {
            $user = $users->get($code);

            if (! $user) {
                continue;
            }

            $value = round($sign * abs((float) ($perCode[$code] ?? $amount)), 2);
            $before = Integrations::balance($user, $currencyCode);
            $total += $value;

            $rows->push([
                'code' => $code,
                'user' => $user,
                'before' => round($before, 2),
                'value' => $value,
                // ⭐ النزول تحت الصفر مسموح — فالمعاينة تعرض الرقم السالب كما هو
                'after' => round($before + $value, 2),
            ]);
        }

        return [
            'rows' => $rows,
            'invalid' => $invalid,
            'duplicates' => array_values(array_unique($duplicates)),
            'total' => round($total, 2),
        ];
    }

    /**
     * تنفيذ المنح/الخصم على دفعات.
     *
     * @return array{granted:int,total:float,cards:array}
     */
    public static function execute(array $preview, string $currencyCode, string $reasonKey, ?string $reference, ?string $notes, User $actor): array
    {
        $currency = Currency::query()->where('code', $currencyCode)->first();
        $reasons = setting('rewards.reasons', []);
        $reasonLabel = is_array($reasons) ? ($reasons[$reasonKey] ?? $reasonKey) : $reasonKey;

        $reasonText = trim($reasonLabel.($reference ? strtr(setting('rewards.reward_grant_service.execute_1', ' · مرجع: :p1'), [':p1' => (string) ($reference)]) : '').($notes ? ' · '.$notes : ''));

        $batchSize = max(1, (int) setting('rewards.batch_size', 500));
        $granted = 0;
        $total = 0.0;
        $cards = [];

        foreach (collect($preview['rows'])->chunk($batchSize) as $chunk) {
            foreach ($chunk as $row) {
                /** @var User $user */
                $user = $row['user'];

                $transaction = Integrations::post(
                    $user, $currencyCode, (float) $row['value'],
                    'admin', $reasonText, $actor, null, 'training',
                );

                if (! $transaction) {
                    continue;
                }

                $granted++;
                $total += (float) $row['value'];

                if ((bool) setting('rewards.notify_recipient', true)) {
                    $template = $row['value'] >= 0
                        ? (string) setting('rewards.grant_message', '')
                        : (string) setting('rewards.deduct_message', '');

                    Integrations::notify($user, 'wallet', strtr($template, [
                        '{amount}' => number_format(abs((float) $row['value']), (int) ($currency?->decimals ?? 0)),
                        '{currency}' => $currency?->name_ar ?? $currencyCode,
                        '{reason}' => $reasonText,
                    ]));
                }

                // بطاقة التهنئة الاحترافيّة: صورته + القيمة — بأسهم تنقّل في الواجهة
                $cards[] = [
                    // ⭐ [2026-09-10] «صورته» (12.9) — الآيدي وحده، فـBoardSnapshot يجلب
                    // الأفاتار الحقيقيّ من القاعدة وقت الرسم لا نسخةً مجمَّدة هنا
                    'user_id' => $user->id,
                    'name' => $user->name,
                    'code' => $user->code,
                    'value' => (float) $row['value'],
                    'after' => round((float) $row['before'] + (float) $row['value'], 2),
                    'currency' => $currency?->name_ar ?? $currencyCode,
                    'title' => strtr((string) setting('rewards.card_title', ''), ['{name}' => $user->name]),
                ];
            }
        }

        AuditTrail::log($actor, 'manual_rewards.execute', null, [], [
            'currency' => $currencyCode,
            'reason' => $reasonKey,
            'reference' => $reference,
            'count' => $granted,
            'total' => round($total, 2),
        ]);

        return ['granted' => $granted, 'total' => round($total, 2), 'cards' => $cards];
    }

    /** سجلّ المنح (تاب ثانٍ) */
    /** سجلّ المنح اليدويّ — ومع `$viewer` يُحصَر بنطاقه (12.2.1-ب) */
    public static function ledger(array $filters = [], ?User $viewer = null)
    {
        return Transaction::query()
            ->when($viewer !== null, fn ($q) => app(ScopeFilter::class)->apply($q, $viewer, 'manual_rewards.list'))
            ->with(['user:id,name,code', 'currency:id,name_ar,code,decimals'])
            ->where('source', 'admin')
            ->when($filters['code'] ?? null, fn ($q, $code) => $q->whereHas('user', fn ($u) => $u->where('code', mb_strtoupper($code))))
            ->when($filters['currency'] ?? null, fn ($q, $c) => $q->whereHas('currency', fn ($cu) => $cu->where('code', $c)))
            ->when(($filters['direction'] ?? null) === 'credit', fn ($q) => $q->where('amount', '>', 0))
            ->when(($filters['direction'] ?? null) === 'debit', fn ($q) => $q->where('amount', '<', 0))
            ->latest('id')
            ->limit((int) setting('rewards.codes_preview_rows', 100))
            ->get();
    }

    /** أكواد شريحة جاهزة — بديل الأكواد اليدويّة في الدفعات الكبيرة */
    public static function segmentCodes(string $segment): string
    {
        $query = User::query()->select('code');

        $query = match ($segment) {
            'volunteers' => $query->whereIn('id', Membership::query()->where('status', 'active')->select('user_id')),
            'zero_balance' => $query->whereDoesntHave('balances', fn ($q) => $q->where('balance', '>', 0)),
            default => $query->where('status', 'active'),
        };

        return $query->limit((int) setting('rewards.max_codes', 2000))->pluck('code')->implode(' ');
    }
}
