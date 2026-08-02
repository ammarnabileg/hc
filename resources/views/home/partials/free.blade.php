@php
    /**
     * التسجيل والتفعيل مجّانيّان (2.5-د) — تُكتَب صراحةً بلا شروط مخفيّة،
     * فكلّ عرضٍ بقيمته الحقيقيّة مكتوبة (21.1-د · 2.9).
     */
    $isFree = (bool) setting('accounts.activation.is_free', true);
    $title = (string) setting('home.free.title', 'التسجيل والتفعيل مجّانيّان');
    $body = (string) setting('home.free.body', 'تفتح حسابك وتفعّله من غير ما تدفع مليم. اللي بفلوس هو التدريبات المدفوعة نفسها — ومكتوب سعرها قدّامك قبل ما تختار.');
    $points = setting('home.free.points', [
        'إنشاء الحساب مجّانيّ',
        'تفعيل الحساب مجّانيّ',
        'تدريبات مجّانيّة متاحة من أوّل يوم',
    ]);
@endphp

@if ($isFree)
    <section class="card p-5 md:p-6 mb-6" aria-labelledby="home-free-title"
             style="border-color: color-mix(in srgb, var(--color-brand-500) 30%, transparent)">
        <div class="flex items-start gap-3">
            <span class="inline-flex items-center justify-center rounded-xl shrink-0"
                  style="width:42px;height:42px;background: color-mix(in srgb, var(--color-brand-500) 14%, transparent); color: var(--color-brand-500)">
                @include('home.partials.icon', ['name' => 'free', 'size' => 22])
            </span>

            <div class="min-w-0">
                <h2 id="home-free-title" class="font-extrabold text-base md:text-lg">{{ $title }}</h2>
                <p class="mt-1 text-sm" style="color: var(--text-muted)">{{ $body }}</p>

                @if (is_array($points) && $points)
                    <ul class="mt-3 grid gap-2 sm:grid-cols-2">
                        @foreach ($points as $point)
                            <li class="flex items-center gap-2 text-sm">
                                <span style="color: var(--color-state-ok)">@include('home.partials.icon', ['name' => 'check', 'size' => 16])</span>
                                <span>{{ $point }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </section>
@endif
