<?php

namespace App\Services\Growth;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ⭐ **لوحة مصادر الاكتساب** (21.2-ح) بنصّها:
 *
 *   «⭐ لوحة مصادر الاكتساب في الإحصائيّات: **المصدر ⟵ التسجيل ⟵ التفعيل ⟵
 *    الشراء** — فلا يُصرَف على قناةٍ لا نعرف عائدها.»
 *
 * والوصلات الثلاث الأخيرة تُقرأ من **المصدر المثبَّت على المستخدم**
 * (`users.acquisition_utm_*`) لا من الـUTM الملتقط في لحظة الحدث. لماذا؟ لأنّ
 * الحدث يقرأ الـquery الجاري: `POST /register` و«فعّل الحساب» و«أتمّ الشراء»
 * تقع كلّها على مساراتٍ بلا وسم — فكانت الأعمدة الثلاثة **صفرًا دائمًا** مهما
 * كثرت الزيارات الموسومة (المقيس: `visits=2 · registered=0`).
 *
 * ⛔ **وأثر الموافقة ظاهرٌ في الجدول لا مخفيّ** (21.3-د):
 *  - **التسجيل/التفعيل/الشراء** لا تُنسَب إلّا لمن وافق على **القياس الداخليّ**،
 *    لأنّ العمود لا يُكتَب أصلًا بدونه (`AcquisitionSource`).
 *  - **الزيارات** تبقى من `tracking_events` — وهي بموافقة **الإعلان** لأنّها
 *    سجلّ البكسل. فكلّ عمودٍ يتغذّى من غرضه هو، ولا يُقاس أحدٌ تحت غرضٍ رفضه.
 */
class AcquisitionFunnel
{
    /**
     * @return array{rows:array<int,array<string,mixed>>, kpis:array<int,array<string,mixed>>}
     */
    public function report(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = [];

        foreach ($this->sources($from, $to) as $source) {
            $ids = $this->attributedUserIds($source, $from, $to);

            $rows[] = [
                'source' => $source,
                'visits' => $this->visits($source, $from, $to),
                'registered' => count($ids),
                'activated' => $this->activated($ids),
                'purchased' => $this->purchased($ids),
            ];
        }

        usort($rows, fn ($a, $b) => $b['registered'] <=> $a['registered']);

        return [
            'rows' => $rows,
            'kpis' => [
                ['label' => 'مصادر نشطة', 'value' => count($rows), 'icon' => '📣'],
                ['label' => 'زيارات موسومة', 'value' => array_sum(array_column($rows, 'visits')), 'icon' => '🔗'],
                ['label' => 'تسجيلات', 'value' => array_sum(array_column($rows, 'registered')), 'icon' => '👥'],
                ['label' => 'مشترون', 'value' => array_sum(array_column($rows, 'purchased')), 'icon' => '🛒'],
            ],
        ];
    }

    // ------------------------------------------------------------------ داخليّ

    /**
     * المصادر الظاهرة في الفترة: ما نُسِب إليه حساب **وما زار وحده ولم يسجّل بعد**
     * — فالمصدر الذي يجيب زيارات بلا تسجيل هو بالضبط ما يجب أن يراه المالك.
     *
     * @return array<int,string>
     */
    private function sources(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $sources = [];

        if ($this->attributionReady()) {
            $sources = User::query()
                ->whereNotNull('acquisition_utm_source')
                ->whereBetween('created_at', [$from, $to])
                ->distinct()
                ->pluck('acquisition_utm_source')
                ->all();
        }

        if (Schema::hasTable('tracking_events')) {
            $sources = array_merge($sources, DB::table('tracking_events')
                ->whereBetween('created_at', [$from, $to])
                ->whereNotNull('utm_source')
                ->distinct()
                ->pluck('utm_source')
                ->all());
        }

        return array_values(array_unique(array_filter(array_map('strval', $sources), fn ($v) => $v !== '')));
    }

    /**
     * ⭐ **الوصلة التي كانت مقطوعة**: المسجّلون المنسوبون لهذا المصدر.
     *
     * @return array<int,int>
     */
    private function attributedUserIds(string $source, CarbonImmutable $from, CarbonImmutable $to): array
    {
        if (! $this->attributionReady()) {
            return [];
        }

        return User::query()
            ->where('acquisition_utm_source', $source)
            ->whereBetween('created_at', [$from, $to])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** «التفعيل» = الحساب المعتمَد إداريًّا (2.5-د) — وهو الوصلة الثالثة */
    private function activated(array $ids): int
    {
        return $ids === [] ? 0 : (int) User::query()
            ->whereIn('id', $ids)
            ->where('status', 'active')
            ->count();
    }

    /** «الشراء» = طلبٌ مدفوع لصاحب الحساب — وهو الوصلة الرابعة والأخيرة */
    private function purchased(array $ids): int
    {
        if ($ids === [] || ! Schema::hasTable('orders')) {
            return 0;
        }

        return (int) DB::table('orders')
            ->whereIn('user_id', $ids)
            ->where('status', 'paid')
            ->distinct()
            ->count('user_id');
    }

    /** الزيارات الموسومة من سجلّ البكسل (21.3-أ) — بموافقة الإعلان لا القياس */
    private function visits(string $source, CarbonImmutable $from, CarbonImmutable $to): int
    {
        if (! Schema::hasTable('tracking_events')) {
            return 0;
        }

        return (int) DB::table('tracking_events')
            ->where('utm_source', $source)
            ->whereBetween('created_at', [$from, $to])
            ->count();
    }

    private function attributionReady(): bool
    {
        return Schema::hasColumn('users', 'acquisition_utm_source');
    }
}
