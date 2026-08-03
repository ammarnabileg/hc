@extends('layouts.admin')

@section('title', $article->exists ? 'تعديل مقال' : 'مقال جديد')

@section('content')
    <x-page-header :title="$article->exists ? $article->title : 'مقال جديد'"
                   subtitle="اكتب، ابعت للمراجعة — وحدّ تاني بينشر."
                   :breadcrumbs="[
                       ['label' => 'لوحة الإدارة', 'url' => url('/admin')],
                       ['label' => 'المقالات', 'url' => route('admin.articles.index')],
                       ['label' => $article->exists ? 'تعديل' : 'جديد'],
                   ]" />

    @if ($errors->any())
        <div class="card p-3 mb-4 text-sm" style="border: 1px solid var(--color-state-danger); color: var(--color-state-danger)">
            {{ $errors->first() }}
        </div>
    @endif

    @if ($article->exists && $article->review_notes)
        {{-- ملاحظات مراجعة مكتوبة يعدّل عليها الكاتب ويعيد الإرسال --}}
        <div class="card p-3 mb-4 text-sm" style="border: 1px solid var(--color-state-warn)">
            <strong>ملاحظات المراجعة:</strong> {{ $article->review_notes }}
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-[2fr_1fr]">
        <form method="post"
              action="{{ $article->exists ? route('admin.articles.update', $article) : route('admin.articles.store') }}"
              class="card p-4 space-y-3">
            @csrf
            @if ($article->exists) @method('PUT') @endif

            <x-form.input name="title" label="العنوان" :value="$article->title" required />
            <x-form.input name="slug" label="الرابط (Slug)" :value="$article->slug" hint="فاضي = يتولّد من العنوان." />

            <label class="block text-sm">
                <span class="block mb-1">التصنيف</span>
                <select name="article_category_id" class="w-full rounded-xl px-3 py-2 text-sm"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    <option value="">بلا تصنيف</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected($article->article_category_id == $category->id)>{{ $category->name_ar }}</option>
                    @endforeach
                </select>
            </label>

            <x-form.input name="cover_path" label="مسار صورة الغلاف" :value="$article->cover_path" />

            <label class="block text-sm">
                <span class="block mb-1">المقتطف</span>
                <textarea name="excerpt" rows="2" class="w-full rounded-xl px-3 py-2 text-sm"
                          style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">{{ old('excerpt', $article->excerpt) }}</textarea>
            </label>

            <label class="block text-sm">
                <span class="block mb-1">المحتوى</span>
                <textarea name="body" rows="12" class="w-full rounded-xl px-3 py-2 text-sm font-mono"
                          style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">{{ old('body', $article->body) }}</textarea>
            </label>

            <details>
                <summary class="text-xs cursor-pointer" style="color: var(--text-muted)">بيانات SEO والربط</summary>
                <div class="mt-3 space-y-3">
                    <x-form.input name="meta_title" label="عنوان الميتا" :value="$article->meta_title" />
                    <x-form.input name="meta_description" label="وصف الميتا" :value="$article->meta_description" />
                    <div class="grid grid-cols-2 gap-2">
                        <x-form.input name="related_type" label="نوع العنصر المرتبط" :value="$article->related_type"
                                      hint="مثال: App\Models\Course" />
                        <x-form.input name="related_id" label="معرّفه" type="number" :value="$article->related_id" />
                    </div>
                </div>
            </details>

            <button class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                    style="background: var(--color-brand-500); color: #04201c">حفظ</button>
        </form>

        <div class="space-y-4">
            <div class="card p-4 space-y-2 text-sm">
                <h2 class="font-bold">دورة النشر</h2>
                <x-state-badge :state="match ($article->status) { 'published' => 'ok', 'in_review' => 'warn', default => 'idle' }"
                               :label="\App\Services\Admin\System\ArticleWorkflow::statuses()[$article->status] ?? 'مسودّة'" />

                @if ($article->exists)
                    @if ($article->status === \App\Services\Admin\System\ArticleWorkflow::DRAFT)
                        @can('articles.edit')
                            <form method="post" action="{{ route('admin.articles.submit', $article) }}">
                                @csrf
                                <button class="w-full rounded-xl px-4 py-2 text-sm" style="background: var(--surface-raised)">ابعت للمراجعة</button>
                            </form>
                        @endcan
                    @endif

                    @if ($article->status === \App\Services\Admin\System\ArticleWorkflow::IN_REVIEW)
                        @can('articles.review')
                            <form method="post" action="{{ route('admin.articles.review', $article) }}" class="space-y-2">
                                @csrf
                                <textarea name="notes" rows="3" required minlength="3" placeholder="ملاحظات المراجعة…"
                                          class="w-full rounded-xl px-3 py-2 text-sm"
                                          style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"></textarea>
                                <button class="w-full rounded-xl px-4 py-2 text-sm" style="background: var(--surface-raised)">رجّعه بملاحظات</button>
                            </form>
                        @endcan

                        @if ($workflow->mayPublish($article, auth()->user()))
                            <form method="post" action="{{ route('admin.articles.publish', $article) }}">
                                @csrf
                                <button class="btn w-full rounded-xl px-4 py-2 text-sm font-semibold"
                                        style="background: var(--color-brand-500); color: #04201c">انشر المقال</button>
                            </form>
                        @elseif ($workflow->isAuthor($article, auth()->user()))
                            {{-- ⭐ الكاتب لا ينشر مقاله بنفسه — والقاعدة مُتحقَّقة في الخادم كذلك --}}
                            <p class="text-xs" style="color: var(--text-muted)">
                                إنت كاتب المقال ده — لازم حدّ تاني يراجعه وينشره.
                            </p>
                        @endif
                    @endif

                    @can('articles.archive')
                        @if ($article->status !== \App\Services\Admin\System\ArticleWorkflow::ARCHIVED)
                            <form method="post" action="{{ route('admin.articles.archive', $article) }}">
                                @csrf
                                <button class="w-full rounded-xl px-4 py-2 text-sm" style="background: var(--surface-raised)">أرشفة (مش حذف)</button>
                            </form>
                        @endif
                    @endcan
                @endif
            </div>

            <div class="card p-4 text-xs" style="color: var(--text-muted)">
                <p>الأرشفة بديل الحذف دائمًا — فالرابط المنشور ما يتحوّلش 404 فجأة.</p>
                <p class="mt-1">كلّ تغيير حالة بيتسجّل في سجلّ التدقيق.</p>
            </div>
        </div>
    </div>
@endsection
