@extends('layouts.guest')
@section('title', setting('onboarding.instructions.title', 'تعليمات المنصّة'))

@section('content')
    {{--
      2.5-د-1: صفحة «تعليمات» — الأدمن يضيف فيها صورًا و/أو فيديوهات و/أو نصوصًا،
      والمستخدم **ينزل لتحت (زرّ تمرير) ليقرأ كلّ شيء** ثمّ يجد زرّ «موافقة».
      والزرّ لا يعمل قبل الوصول لآخر الصفحة — فالموافقة موافقةٌ حقيقيّة لا نقرة.
    --}}
    <div class="card w-full max-w-2xl flex flex-col" style="max-height: 92vh">

        <header class="px-5 py-4 shrink-0" style="border-bottom: 1px solid var(--border)">
            <h1 class="text-xl font-extrabold">{{ setting('onboarding.instructions.title', 'تعليمات المنصّة') }}</h1>
            <div class="mt-3 h-1 rounded-full overflow-hidden" style="background: var(--surface-sunken)"
                 role="progressbar" aria-label="نسبة القراءة">
                <div data-read-bar class="h-full motion-standard" style="width: 0%; background: var(--color-brand-500)"></div>
            </div>
        </header>

        <div data-instructions-body class="prose-onboarding px-5 py-4 overflow-y-auto text-sm" style="line-height: 1.9">
            {!! setting('onboarding.instructions.html', '') !!}
            <div data-read-end style="height: 1px"></div>
        </div>

        <footer class="px-5 py-4 shrink-0 flex flex-wrap items-center justify-between gap-3"
                style="border-top: 1px solid var(--border)">

            {{-- زرّ التمرير المنصوص عليه: يوصّله لآخر الصفحة بضغطة على الموبايل --}}
            <button type="button" data-scroll-more class="text-xs underline"
                    style="color: var(--text-muted); min-height: 44px">
                {{ setting('onboarding.instructions.scroll_label', 'كمّل قراية ↓') }}
            </button>

            <form method="post" action="{{ route('onboarding.instructions.agree') }}" class="flex items-center gap-2">
                @csrf
                <input type="hidden" name="agreed" value="1">
                <span class="text-xs" data-agree-hint style="color: var(--text-muted)">
                    {{ setting('onboarding.instructions.agree_hint', 'الزرّ بيشتغل لمّا توصل لآخر الصفحة.') }}
                </span>
                {{--
                  الزرّ يخرج **مفعَّلًا** من الخادم ثمّ يقفله الجافاسكربت حتى نهاية
                  القراءة: تحسينٌ تدريجيّ (2.1) — فمن لا جافاسكربت عنده لا يُحبَس
                  خارج حسابه، وهو يرى الصفحة كاملةً على أيّ حال.
                --}}
                <button type="submit" data-agree-button
                        class="btn rounded-xl px-5 py-2 font-semibold motion-standard"
                        style="min-height: 44px; background: var(--color-brand-500); color: #04201c">
                    {{ setting('onboarding.instructions.agree_label', 'موافق') }}
                </button>
            </form>
        </footer>
    </div>

    <style>
        .prose-onboarding h2 { font-size: 1.05rem; font-weight: 800; margin-block: 1rem .35rem; }
        .prose-onboarding h3 { font-size: .95rem; font-weight: 700; margin-block: .9rem .3rem; }
        .prose-onboarding p, .prose-onboarding li { margin-block-end: .5rem; }
        .prose-onboarding ul, .prose-onboarding ol { padding-inline-start: 1.25rem; }
        .prose-onboarding img, .prose-onboarding video, .prose-onboarding iframe {
            max-width: 100%; height: auto; border-radius: .75rem; margin-block: .75rem;
        }
    </style>

    <script>
        (() => {
            const body = document.querySelector('[data-instructions-body]');
            const button = document.querySelector('[data-agree-button]');
            const hint = document.querySelector('[data-agree-hint]');
            const bar = document.querySelector('[data-read-bar]');
            const more = document.querySelector('[data-scroll-more]');
            if (!body || !button) return;

            const lock = () => {
                button.disabled = true;
                button.style.opacity = '.45';
            };

            const unlock = () => {
                button.disabled = false;
                button.style.opacity = '1';
                if (hint) hint.textContent = '';
            };

            lock();

            const track = () => {
                const max = body.scrollHeight - body.clientHeight;
                const ratio = max <= 1 ? 1 : Math.min(1, body.scrollTop / max);
                if (bar) bar.style.width = Math.round(ratio * 100) + '%';
                // هامش صغير: بعض المتصفّحات لا تصل للقيمة القصوى بالضبط
                if (ratio >= 0.98) unlock();
            };

            body.addEventListener('scroll', track);
            more?.addEventListener('click', () => body.scrollBy({ top: body.clientHeight * 0.9, behavior: 'smooth' }));
            track();
        })();
    </script>
@endsection
