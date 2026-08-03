@extends('layouts.admin')

@section('title', 'معاينة قبل الإصدار')

@section('content')
    {{-- معاينة قبل الإصدار: الشهادات تحت بعضها ببياناتها الحقيقيّة (12.5-ج) --}}
    <x-page-header
        :title="'معاينة قبل الإصدار — '.$type->name_ar"
        :subtitle="$rows->count().' شهادة جاهزة للمراجعة'"
        :breadcrumbs="[
            ['label' => 'الشهادات', 'url' => route('admin.certificates.index')],
            ['label' => 'إصدار', 'url' => route('admin.certificates.index', ['tab' => 'issue'])],
            ['label' => 'المعاينة'],
        ]" />

    @if ($rows->isEmpty())
        <x-empty message="مفيش كود صالح للمعاينة."
                 action="رجوع للإصدار" :href="route('admin.certificates.index', ['tab' => 'issue'])" />
    @else
        <div class="space-y-6">
            @foreach ($rows as $row)
                <div class="card p-4">
                    <div class="flex items-center justify-between mb-3 gap-2">
                        <div class="font-semibold">{{ $row['name'] }} — {{ $row['code'] }}</div>
                        @if ($row['state'] === 'warn')
                            <x-state-badge state="warn" label="صدرت له قبل كده — هنتخطّاها" />
                        @endif
                    </div>

                    <div class="min-w-0 overflow-x-auto">
                        @include('admin.certificates.partials.canvas', [
                            'layers' => $layers,
                            'template' => $template,
                            'sample' => $row['data'],
                            'canvasWidth' => (int) setting('certificates.preview.canvas_width', 700),
                        ])
                    </div>
                </div>
            @endforeach
        </div>

        <form method="post" action="{{ route('admin.certificates.issue') }}" class="mt-4"
              onsubmit="return confirm('{{ setting('certificates.issue.confirm_text', 'هنصدر الشهادات دي دلوقتي — نكمّل؟') }}')">
            @csrf
            <input type="hidden" name="certificate_type_id" value="{{ $type->id }}">
            <input type="hidden" name="language" value="{{ $language }}">
            <input type="hidden" name="codes" value="{{ $raw }}">
            <button class="btn w-full rounded-xl px-4 py-3 text-sm font-semibold"
                    style="background: var(--color-brand-500); color: #04201c">أصدِر الشهادات</button>
        </form>
    @endif

    @include('admin.courses.partials.toast')
@endsection
