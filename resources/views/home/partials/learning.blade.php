@php
    /**
     * أحدث التدريبات والمسارات المنشورة (21.1-أ) — أرقام ومحتوى **حقيقيّان**
     * بلا ندرة مزيّفة ولا عدّاد وهميّ (2.9). والزائر يعرف قبل التسجيل:
     * السعر مكتوب، وعدد دروس المعاينة المجّانيّة مكتوب.
     */
    $coursesTitle = (string) setting('home.courses.title', 'أحدث التدريبات');
    $pathsTitle = (string) setting('home.paths.title', 'المسارات');
    $emptyText = (string) setting('home.courses.empty', 'التدريبات الأولى في الطريق — سجّل دلوقتي وتوصلك أوّل ما تنزل.');
    $freeLabel = (string) setting('home.courses.free_label', 'مجّانيّ');
@endphp

<section id="home-learning" class="mb-6 scroll-mt-24" aria-labelledby="home-courses-title">
    <h2 id="home-courses-title" class="text-lg md:text-xl font-extrabold mb-3">{{ $coursesTitle }}</h2>

    @if ($courses->isEmpty())
        {{-- الحالة الفارغة سطر واحد + زرّ واحد، وتشجّع ولا تعاتب (2.15-د · 2.17-ج) --}}
        <x-empty :message="$emptyText" :action="setting('home.courses.empty_cta', 'أنشئ حسابك')" :href="route('register')" />
    @else
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($courses as $course)
                <article class="card p-4 flex flex-col animate-fadeup" style="animation-delay: {{ $loop->index * 40 }}ms">
                    <div class="flex items-center gap-2 mb-2" style="color: var(--color-brand-500)">
                        @include('home.partials.icon', ['name' => 'course', 'size' => 18])
                        @if ($course->is_free)
                            <x-state-badge state="ok" :label="$freeLabel" />
                        @endif
                    </div>

                    <h3 class="font-bold text-sm break-words">{{ $course->name_ar }}</h3>

                    @if ($course->description_ar)
                        <p class="mt-1 text-xs line-clamp-3" style="color: var(--text-muted)">{{ $course->description_ar }}</p>
                    @endif

                    <div class="mt-3 pt-3 flex items-center justify-between gap-2 text-xs"
                         style="border-top: 1px solid var(--border); color: var(--text-muted)">
                        {{-- معاينة مجّانيّة قبل التسجيل (21.1-أ) — بعددها الصادق --}}
                        <span>
                            @if ((int) $course->free_preview_lessons > 0)
                                أوّل {{ (int) $course->free_preview_lessons }} درس معاينة مجّانيّة
                            @else
                                {{ $course->is_free ? 'متاح مجّانًا' : 'تدريب مدفوع' }}
                            @endif
                        </span>
                    </div>

                    <a href="{{ route('register') }}"
                       class="btn inline-flex items-center justify-center mt-3 rounded-xl px-3 py-2 text-xs font-bold motion-standard"
                       style="background: color-mix(in srgb, var(--color-brand-500) 16%, transparent); color: var(--color-brand-500)">
                        {{ setting('home.courses.cta', 'ابدأ التدريب') }}
                    </a>
                </article>
            @endforeach
        </div>
    @endif

    @if ($paths->isNotEmpty())
        <h3 class="text-base font-extrabold mt-6 mb-3">{{ $pathsTitle }}</h3>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($paths as $path)
                <article class="card p-4 flex items-start gap-3">
                    <span class="inline-flex items-center justify-center rounded-xl shrink-0"
                          style="width:38px;height:38px;background: var(--surface-sunken); color: var(--color-brand-500)">
                        @include('home.partials.icon', ['name' => 'path', 'size' => 20])
                    </span>
                    <div class="min-w-0">
                        <h4 class="font-bold text-sm break-words">{{ $path->name_ar }}</h4>
                        @if ($path->description_ar)
                            <p class="mt-1 text-xs line-clamp-2" style="color: var(--text-muted)">{{ $path->description_ar }}</p>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</section>
