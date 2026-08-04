@extends('layouts.app')

@section('title', $lesson->title_ar.' — '.$course->name_ar)
@section('meta_description', \Illuminate\Support\Str::limit(strip_tags((string) $course->description_ar), 155))
@section('og_image', $ogImage)

@if (! $indexable)
    @section('noindex', '1')
@endif

@section('content')
    <div class="max-w-3xl mx-auto">
        <x-page-header :title="$lesson->title_ar"
                       :breadcrumbs="[
                           ['label' => $course->name_ar, 'url' => route('growth.preview.course', $course->slug)],
                           ['label' => $lesson->title_ar],
                       ]" />

        <p class="text-xs mb-4 inline-flex items-center gap-1 rounded-full px-3 py-1"
           style="background: var(--surface-sunken); color: var(--text-muted)">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/>
                <path d="M12 8v5l3 2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            </svg>
            {{ setting('growth.preview.badge', 'درس معاينة مجّانيّ') }}
        </p>

        <div class="card p-4">
            @if ($lesson->type === 'video' && $lesson->video_id)
                <div class="rounded-xl overflow-hidden" style="aspect-ratio: 16/9; background: var(--surface-sunken)">
                    <iframe class="w-full h-full" loading="lazy" title="{{ $lesson->title_ar }}"
                            src="{{ str_replace(['{provider}', '{id}'], [(string) $lesson->video_provider, (string) $lesson->video_id],
                                (string) setting('growth.preview.embed_template', 'https://www.youtube.com/embed/{id}')) }}"
                            allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>
                </div>
            @endif

            @if ($lesson->content)
                <div class="article-body leading-8 mt-4">{!! app(\App\Services\Growth\SafeHtml::class)->clean($lesson->content) !!}</div>
            @endif
        </div>

        <div class="flex flex-wrap items-center gap-2 mt-4">
            @if ($next)
                <a href="{{ route('growth.preview.lesson', ['slug' => $course->slug, 'lesson' => $next->id]) }}"
                   class="rounded-xl px-4 py-2 text-sm motion-standard" style="background: var(--surface-sunken)">
                    {{ setting('growth.preview_lesson.text_1', 'الدرس التالي:') }} {{ \Illuminate\Support\Str::limit($next->title_ar, 30) }}
                </a>
            @endif

            <a href="{{ route('growth.preview.course', $course->slug) }}"
               class="rounded-xl px-4 py-2 text-sm motion-standard" style="background: var(--surface-sunken)">
                {{ setting('growth.preview_lesson.text_2', 'كلّ دروس المعاينة') }}
            </a>
        </div>

        {{-- دعوة صريحة بقيمتها الحقيقيّة مكتوبة — بلا مبالغة (21.1-د) --}}
        <a href="{{ auth()->check() ? $buyUrl : $registerUrl }}"
           class="card p-4 mt-4 flex items-center justify-between gap-3 motion-standard">
            <span class="text-sm">{{ setting('growth.preview.upsell', 'عجبك الدرس؟ باقي التدريب بيتفتح بعد التسجيل — والتسجيل مجّانيّ.') }}</span>
            <span class="rounded-xl px-4 py-2 text-sm font-semibold shrink-0"
                  style="background: var(--color-brand-500); color: #04201c">
                {{ auth()->check() ? setting('growth.preview.cta', 'افتح التدريب كامل') : (string) setting('growth.preview_lesson.expr_1', 'سجّل حسابك') }}
            </span>
        </a>
    </div>
@endsection

@section('mobile_action')
    <a href="{{ auth()->check() ? $buyUrl : $registerUrl }}"
       class="btn w-full inline-flex items-center justify-center rounded-xl px-4 py-3 text-sm font-semibold"
       style="background: var(--color-brand-500); color: #04201c">
        {{ auth()->check() ? setting('growth.preview.cta', 'افتح التدريب كامل') : (string) setting('growth.preview_lesson.expr_2', 'سجّل حسابك') }}
    </a>
@endsection
