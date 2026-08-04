<?php

namespace App\Services\Events;

use App\Models\Event;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * فلاتر شاشة الفعاليّات (24.5): **ثلاثة ظاهرة** (النوع · الفترة · التصنيف) + بحث،
 * والباقي مطويّ خلف «فلاتر متقدّمة» (2.15-أ-4).
 */
class EventQuery
{
    public const MODES = ['online' => 'أونلاين', 'offline' => 'أوفلاين', 'hybrid' => 'هجين'];

    public const PERIODS = [
        'upcoming' => 'قادمة',
        'today' => 'اليوم',
        'week' => 'الأسبوع ده',
        'past' => 'منتهية',
        'all' => 'الكلّ',
    ];

    public function __construct(private readonly EventPresenter $presenter) {}

    /**
     * @param  array<string,mixed>  $filters
     */
    public function build(array $filters, User $user): Builder
    {
        $query = Event::query()
            ->where('status', (string) setting('events.published_status', 'published'))
            ->withCount('registrations')
            ->with(['certificate_type'])
            ->orderBy('starts_at', ($filters['period'] ?? 'upcoming') === 'past' ? 'desc' : 'asc');

        if ($mode = $filters['mode'] ?? null) {
            $query->where('mode', $mode);
        }

        if ($category = $filters['category'] ?? null) {
            $query->where('category', $category);
        }

        if ($search = trim((string) ($filters['q'] ?? ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('title_ar', 'like', "%{$search}%")
                    ->orWhere('title_en', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%");
            });
        }

        // «مجّانيّ / مدفوع» فلتر متقدّم مطويّ — لا يزاحم الثلاثة الظاهرة
        if (($price = $filters['price'] ?? null) === 'free') {
            $query->where('price_coins', '<=', 0)->where('price_tickets', '<=', 0);
        } elseif ($price === 'paid') {
            $query->where(fn ($q) => $q->where('price_coins', '>', 0)->orWhere('price_tickets', '>', 0));
        }

        if (! empty($filters['mine'])) {
            $query->whereHas('registrations', fn ($q) => $q->where('user_id', $user->id));
        }

        $this->applyPeriod($query, (string) ($filters['period'] ?? 'upcoming'), $filters['month'] ?? null);

        return $query;
    }

    private function applyPeriod(Builder $query, string $period, ?string $month): void
    {
        // في عرض التقويم المدى هو الشهر المعروض نفسه لا الفترة
        if ($month) {
            $start = CarbonImmutable::parse($month.'-01')->startOfMonth();
            $query->whereBetween('starts_at', [$start->startOfWeek(CarbonImmutable::SATURDAY), $start->endOfMonth()->endOfWeek(CarbonImmutable::FRIDAY)]);

            return;
        }

        match ($period) {
            'today' => $query->whereBetween('starts_at', [now()->startOfDay(), now()->endOfDay()]),
            'week' => $query->whereBetween('starts_at', [now()->startOfDay(), now()->addWeek()->endOfDay()]),
            'past' => $query->where('starts_at', '<', now()),
            'all' => null,
            default => $query->where('starts_at', '>=', now()->startOfDay()),
        };
    }

    /** التصنيفات المتاحة فعلًا — بلا قائمة محروقة في الكود */
    public function categories(): Collection
    {
        return Event::query()
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');
    }

    /**
     * شبكة التقويم مرسومة بأيدينا: أسابيع تبدأ بالسبت — بلا أيّ مكتبة تقويم.
     *
     * @param  Collection<int,Event>  $events
     * @return array{month: CarbonImmutable, weeks: array<int,array<int,array{date: ?CarbonImmutable, events: Collection}>>}
     */
    public function calendar(string $month, Collection $events, ?User $user = null): array
    {
        $first = CarbonImmutable::parse($month.'-01')->startOfMonth();
        $tz = $this->presenter->timezone($user);

        $byDay = $events->groupBy(
            fn (Event $event) => CarbonImmutable::parse($event->starts_at)->setTimezone($tz)->toDateString()
        );

        // إزاحة أوّل الشهر عن السبت (0 = السبت) — الأسبوع العربيّ يبدأ بالسبت
        $offset = ((int) $first->dayOfWeek + 1) % 7;
        $cursor = $first->subDays($offset);
        $weeks = [];

        for ($week = 0; $week < 6; $week++) {
            $row = [];

            for ($day = 0; $day < 7; $day++) {
                $inMonth = $cursor->month === $first->month;

                $row[] = [
                    'date' => $inMonth ? $cursor : null,
                    'events' => $inMonth ? ($byDay[$cursor->toDateString()] ?? collect()) : collect(),
                ];

                $cursor = $cursor->addDay();
            }

            $weeks[] = $row;

            if ($cursor->month !== $first->month && $week >= 3) {
                break;
            }
        }

        return ['month' => $first, 'weeks' => $weeks];
    }

    /** أسماء أيّام الأسبوع بترتيب الشبكة (السبت ← الجمعة) */
    public function weekdays(): array
    {
        return [setting('events.event_query.weekdays_1', 'السبت'), setting('events.event_query.weekdays_2', 'الأحد'), setting('events.event_query.weekdays_3', 'الاثنين'), setting('events.event_query.weekdays_4', 'الثلاثاء'), setting('events.event_query.weekdays_5', 'الأربعاء'), setting('events.event_query.weekdays_6', 'الخميس'), setting('events.event_query.weekdays_7', 'الجمعة')];
    }
}
