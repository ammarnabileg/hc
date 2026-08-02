@extends('layouts.app')

@section('title', $course->name_ar.' — '.setting('growth.preview.title', 'معاينة مجّانيّة'))
@section('meta_description', \Illuminate\Support\Str::limit(strip_tags((string) $course->description_ar), 155))
@section('og_image', $ogImage)

@if (! $indexable)
    @section('noindex', '1')
@endif

@section('content')
    <div class="max-w-3xl mx-auto">
        <x-page-header :title="$course->name_ar"
                       :subtitle="setting('growth.preview.subtitle', 'جرّب قبل ما تسجّل — الدروس المفتوحة تحت متاحة بلا حساب.')"
                       :breadcrumbs="[['label' => 'التدريبات', 'url' => route('store.index')], ['label' => $course->name_ar]]">
            <x-slot:action>
                <a href="{{ $buyUrl }}"
                   class="btn hidden md:inline-flex items-center justify-center rounded-xl px-5 py-2.5 text-sm font-semibold motion-standard"
                   style="background: var(--color-brand-500); color: #04201c">
                    {{ setting('growth.preview.cta', 'افتح التدريب كامل') }}
                </a>
            </x-slot:action>
        </x-page-header>

        {{-- بلا ندرة مزيّفة ولا عدّاد وهميّ: نقول العدد الحقيقيّ وخلاص (2.9) --}}
        <p class="card p-4 text-sm mb-4" style="color: var(--text-muted)">
            {{ str_replace('{count}', $openCount, (string) setting('growth.preview.note', 'مفتوح لك {count} درس مجّانًا كمعاينة. الباقي بيتفتح بعد ما تسجّل.')) }}
        </p>

        @if ($outline->isEmpty())
            <x-empty :message="setting('growth.preview.empty', 'المنهج لسّه بيتجهّز.')"
                     :action="setting('growth.preview.cta', 'افتح التدريب كامل')" :href="$buyUrl" />
        @else
            <ol class="card divide-y" style="border-color: var(--border)">
                @php $section = null; @endphp
                @foreach ($outline as $row)
                    @if ($section !== $row['section'])
                        @php $section = $row['section']; @endphp
                        <li class="px-4 py-2 text-xs font-bold" style="background: var(--surface-sunken); color: var(--text-muted)">
                            {{ $section }}
                        </li>
                    @endif

                    <li class="px-4 py-3 flex items-center justify-between gap-3">
                        <span class="text-sm min-w-0 truncate">{{ $row['lesson']->title_ar }}</span>

                        @if ($row['open'])
                            {{-- المفتوح رابطٌ فعلًا — لا زرٌّ معطَّل يوهم (2.15-أ-7) --}}
                            <a href="{{ route('growth.preview.lesson', ['slug' => $course->slug, 'lesson' => $row['lesson']->id]) }}"
                               class="shrink-0 rounded-xl px-3 py-1.5 text-xs font-semibold motion-standard"
                               style="background: var(--color-brand-500); color: #04201c">
                                {{ setting('growth.preview.open_label', 'شوف الدرس') }}
                            </a>
                        @else
                            {{-- اللون لا يحمل المعنى وحده: قفلٌ مرسوم ونصّ (2.16-ب) --}}
                            <span class="shrink-0 inline-flex items-center gap-1 text-xs" style="color: var(--text-muted)">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <rect x="5" y="10" width="14" height="10" rx="2" stroke="currentColor" stroke-width="1.8"/>
                                    <path d="M8 10V7a4 4 0 0 1 8 0v3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                </svg>
                                {{ setting('growth.preview.locked_label', 'بعد التسجيل') }}
                            </span>
                        @endif
                    </li>
                @endforeach
            </ol>
        @endif

        @guest
            <a href="{{ $registerUrl }}" class="card p-4 mt-4 flex items-center justify-between gap-3 motion-standard">
                <span class="text-sm">{{ setting('growth.preview.register_note', 'التسجيل مجّانيّ والتفعيل باعتماد إداريّ — بلا أيّ رسوم.') }}</span>
                <span class="rounded-xl px-4 py-2 text-sm font-semibold shrink-0"
                      style="background: var(--color-brand-500); color: #04201c">سجّل حسابك</span>
            </a>
        @endguest
    </div>
@endsection

@section('mobile_action')
    <a href="{{ $buyUrl }}" class="btn w-full inline-flex items-center justify-center rounded-xl px-4 py-3 text-sm font-semibold"
       style="background: var(--color-brand-500); color: #04201c">
        {{ setting('growth.preview.cta', 'افتح التدريب كامل') }}
    </a>
@endsection
