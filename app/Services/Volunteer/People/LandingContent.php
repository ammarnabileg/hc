<?php

namespace App\Services\Volunteer\People;

use App\Models\Membership;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * محتوى صفحة «تطوّع معنا» التعريفيّة (13.4-أ) — **مصدره لوحة الإدارة وحدها**.
 *
 * **العطل الذي تُصلحه:** كانت الصفحة تقرأ مفاتيح `volunteering.landing.*`
 * والأدمن يكتب في `volunteer_page.*`؛ وكتل المحتوى (`volunteer_page.blocks`)
 * يقرؤها **الأدمن نفسه فقط** ليرسم محرّره — حلقةٌ مغلقة لا تخرج للمستخدم أبدًا.
 * فمن اليوم: **مفتاح واحد لكلّ معنًى** (2.13)، والكتل تُصنَّف بأنواعها
 * فتُرسَم FAQ أكورديون وقصصًا وأثرًا وأقسامًا حرّة.
 *
 * والإحصائيّات **حيّة** لا مكتوبة، ومعها إزاحات يضبطها الأدمن (13.4-أ).
 */
class LandingContent
{
    /** أنواع الكتل كما يكتبها محرّر الأدمن */
    public const TYPES = ['faq', 'story', 'impact', 'section'];

    /**
     * كتل المحتوى مصنَّفة بنوعها — نفس المفتاح الذي يكتبه الأدمن حرفيًّا.
     *
     * @return array<string, Collection<int, array{type:string,title:string,body:string}>>
     */
    public function blocks(): array
    {
        $raw = Setting::query()->where('key', 'volunteer_page.blocks')->value('value');
        $rows = json_decode((string) $raw, true);
        $rows = is_array($rows) ? $rows : [];

        $out = array_fill_keys(self::TYPES, collect());

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $type = (string) ($row['type'] ?? 'section');
            $type = in_array($type, self::TYPES, true) ? $type : 'section';

            $out[$type] = $out[$type]->push([
                'type' => $type,
                'title' => (string) ($row['title'] ?? ''),
                'body' => (string) ($row['body'] ?? ''),
            ]);
        }

        return $out;
    }

    public function hasAnyBlock(array $blocks): bool
    {
        foreach ($blocks as $group) {
            if ($group->isNotEmpty()) {
                return true;
            }
        }

        return false;
    }

    /**
     * ⭐ إحصائيّات حيّة: «X متطوّع · Y متدرّب مستفيد» — أرقام حقيقيّة من الجداول،
     * والإزاحة إعدادٌ يضبطه الأدمن (13.4-أ) لا رقمٌ محروق.
     *
     * @return array{enabled:bool,volunteers:int,trainees:int,volunteers_label:string,trainees_label:string}
     */
    public function stats(): array
    {
        $enabled = (bool) setting('volunteer_page.stats_enabled', true);

        $volunteers = $enabled ? Membership::query()
            ->where('status', 'active')
            ->whereHas('position', fn ($q) => $q->where('is_honorary', false))
            ->distinct()
            ->count('user_id') : 0;

        $trainees = $enabled ? User::query()->where('status', 'active')->count() : 0;

        return [
            'enabled' => $enabled,
            'volunteers' => max(0, $volunteers + (int) setting('volunteer_page.stats_offset_volunteers', 0)),
            'trainees' => max(0, $trainees + (int) setting('volunteer_page.stats_offset_trainees', 0)),
            'volunteers_label' => (string) setting('volunteer_page.stats_volunteers_label', 'متطوّع معنا'),
            'trainees_label' => (string) setting('volunteer_page.stats_trainees_label', 'متدرّب مستفيد'),
        ];
    }

    /** «بتطوّعك هتساعد X متدرّب» — إبراز الأثر التحفيزيّ (13.4-أ) */
    public function impactLine(array $stats): string
    {
        return str_replace(
            ':count',
            number_format($stats['trainees']),
            (string) setting('volunteer_page.impact_line', 'بتطوّعك هتساعد :count متدرّب على إكمال رحلته.'),
        );
    }
}
