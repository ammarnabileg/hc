<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\LandingPage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * ⭐⭐ لوحة صفحات الهبوط **المستقلّة** (12.2.3 `landing_pages`) — سدّ الفجوة:
 * كانت الصلاحيّات الستّ (`view · create · edit · archive · restore · delete`)
 * موجودة في المصفوفة ومسنَدة لـ«مسؤول التسويق والمتجر» **بلا أيّ مسارٍ يحرسه
 * واحدٌ منها**، والمُنجَز الوحيد فعليًّا (`BundleLanding`) مبنيٌّ داخل شاشة
 * تعديل البندل نفسها ومحروسٌ بـ`bundles.edit` — لا بصلاحيّة `landing_pages`
 * المنصوصة، ولا صفحة هبوط مستقلّة لمنتج متجرٍ (`store_products`) إطلاقًا.
 *
 * ⚠️ **`landing_pages.view` ليست صلاحيّة إداريّة كأخواتها الخمس.** نصّها
 * الحرفيّ في 12.2.2: «فتح صفحة هبوط البندل/المنتج **للزائر**» بشرط «الحالة =
 * منشور» ونطاق `ALL` — أي **كلّ زائر**، بلا استثناء تسجيل دخول. وحارس المسارات
 * `EnsurePermission` يردّ 401 فورًا لغير المسجَّل (`if (! $user) abort(401)`)،
 * فتعليقها على مسار الزائر العامّ كان يقفل الصفحة في وجه الزائر نفسه — عكسَ
 * نصّها تمامًا. فالعرض العامّ (`StoreController::landingPage()`) **مسارٌ بلا
 * حارس صلاحيّة**، وشرطه هو الشرط المنصوص حرفًا: `status = published` فقط —
 * تمامًا كما تُفتَح صفحات `store.product`/`store.bundle` العامّة أصلًا
 * (`routes/parts/store.php`) بلا `permission:` على مسارها.
 *
 * وهنا — في لوحة الإدارة — تُستعمَل `landing_pages.view` بمعناها الوحيد
 * القابل للتطبيق على حارسٍ يفرض مستخدمًا مسجَّلًا: **معاينة الأدمن** لسجلّ
 * الصفحة (بما فيها المسوَّدة قبل نشرها) دون فتح فورم التعديل.
 */
class LandingPageController extends Controller
{
    /**
     * نقطة دخول واحدة من شاشتَي البندل/المنتج («صفحة الهبوط المستقلّة»):
     * تحوّل لفورم التعديل إن كانت موجودة، وإلّا لفورم الإنشاء — والصلاحيّة
     * المناسبة تُفرَض هنا لا في المسار (لأنّها تختلف باختلاف الحالة).
     */
    public function manage(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(LandingPage::LANDINGABLE_TYPES))],
            'id' => ['required', 'integer'],
        ]);

        $landingable = $this->resolveLandingable($data['type'], (int) $data['id']);
        abort_unless($landingable, 404);

        $existing = LandingPage::query()
            ->where('landingable_type', $landingable::class)
            ->where('landingable_id', $landingable->id)
            ->first();

        if ($existing) {
            abort_unless($request->user()->allows('landing_pages.edit'), 403);

            return redirect()->route('admin.store.landing-pages.edit', $existing);
        }

        abort_unless($request->user()->allows('landing_pages.create'), 403);

        return redirect()->route('admin.store.landing-pages.create', ['type' => $data['type'], 'id' => $data['id']]);
    }

    /** فورم الإنشاء — مربوطٌ بكيانٍ محدَّد سلفًا (بندل أو منتج) عبر الاستعلام */
    public function create(Request $request): View|RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(LandingPage::LANDINGABLE_TYPES))],
            'id' => ['required', 'integer'],
        ]);

        $landingable = $this->resolveLandingable($data['type'], (int) $data['id']);
        abort_unless($landingable, 404);

        $existing = LandingPage::query()
            ->where('landingable_type', $landingable::class)
            ->where('landingable_id', $landingable->id)
            ->first();

        // صفحة هبوط واحدة لكلّ كيان (unique على الجدول) — فلا نموذج إنشاءٍ ثانٍ صامت
        if ($existing) {
            return redirect()->route('admin.store.landing-pages.edit', $existing);
        }

        return view('admin.store.landing-page-form', [
            'landingPage' => new LandingPage(['status' => LandingPage::STATUS_DRAFT]),
            'landingable' => $landingable,
            'landingableType' => $data['type'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $landingable = $this->resolveLandingable($data['type'], (int) $data['id']);
        abort_unless($landingable, 404);

        // ⭐ الحصر على القاعدة نفسها لا على الفورم وحده — تعارضٌ محتمَل لو فُتح تبويبان معًا
        abort_if(
            LandingPage::query()->where('landingable_type', $landingable::class)->where('landingable_id', $landingable->id)->exists(),
            409,
            (string) setting('landing_pages.admin.duplicate_msg', 'لهذا العنصر صفحة هبوط بالفعل، عدّلها بدل إنشاء أخرى.'),
        );

        $payload = $this->payload($data, $landingable);
        $payload['landingable_type'] = $landingable::class;
        $payload['landingable_id'] = $landingable->id;
        $payload['slug'] = $this->uniqueSlug($data['headline'] !== '' ? $data['headline'] : $landingable->name_ar);
        $payload['created_by'] = $request->user()->id;
        $payload['status'] = LandingPage::STATUS_DRAFT; // النشر فعلٌ صريح لاحقًا من فورم التعديل — لا إنشاءٌ منشور صامت

        $landingPage = LandingPage::create($payload);

        $this->audit($request, $landingPage, 'landing_pages.create', [], $payload);

        return redirect()
            ->route('admin.store.landing-pages.edit', $landingPage)
            ->with('status', (string) setting('landing_pages.admin.store_ok', 'صفحة الهبوط اتحفظت مسوّدة ✓، راجعها وانشرها.'));
    }

    /** ⭐ معاينة الأدمن للسجلّ — `landing_pages.view` (راجع تعليق الصنف) */
    public function show(LandingPage $landingPage): View
    {
        return view('admin.store.landing-page-show', [
            'landingPage' => $landingPage->load('landingable'),
        ]);
    }

    public function edit(LandingPage $landingPage): View
    {
        return view('admin.store.landing-page-form', [
            'landingPage' => $landingPage,
            'landingable' => $landingPage->landingable,
            'landingableType' => $landingPage->landingableKey(),
        ]);
    }

    /** تحرير الأقسام والمحتوى **ومعاينتها قبل النشر** — والنشر فعلٌ صريح بحقل الحالة (12.2.2) */
    public function update(Request $request, LandingPage $landingPage): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in([LandingPage::STATUS_DRAFT, LandingPage::STATUS_PUBLISHED])],
            'headline' => ['nullable', 'string', 'max:190'],
            'subheadline' => ['nullable', 'string', 'max:2000'],
            'hero_image_path' => ['nullable', 'string', 'max:255'],
            'cta_label' => ['nullable', 'string', 'max:60'],
            'body' => ['nullable', 'string', 'max:20000'],
            // ⭐ سطرٌ لكلّ عنصر — نفس تحويلة `StoreAdminController::lines()/faqLines()` لبندل 18
            'outcomes' => ['nullable', 'string', 'max:4000'],
            'faq' => ['nullable', 'string', 'max:8000'],
        ]);

        $old = $landingPage->only(['status', 'headline', 'subheadline', 'hero_image_path', 'cta_label', 'body', 'outcomes', 'faq']);

        $payload = [
            'headline' => trim((string) ($data['headline'] ?? '')) ?: null,
            'subheadline' => trim((string) ($data['subheadline'] ?? '')) ?: null,
            'hero_image_path' => trim((string) ($data['hero_image_path'] ?? '')) ?: null,
            'cta_label' => trim((string) ($data['cta_label'] ?? '')) ?: null,
            'body' => trim((string) ($data['body'] ?? '')) ?: null,
            'outcomes' => $this->lines($data['outcomes'] ?? null),
            'faq' => $this->faqLines($data['faq'] ?? null),
        ];

        $wasPublished = $landingPage->isPublished();
        $payload['status'] = $data['status'];
        $payload['published_at'] = $data['status'] === LandingPage::STATUS_PUBLISHED
            ? ($landingPage->published_at ?? now())
            : $landingPage->published_at;

        // العودة من «مؤرشفة» لا تمرّ من هنا (فعل `restore` وحده) — فلا نسمح بتخطّيه صامتًا
        abort_if($landingPage->isArchived(), 409, (string) setting('landing_pages.admin.archived_edit_msg', 'صفحة مؤرشفة، استعدها أوّلًا قبل التعديل.'));

        $landingPage->update($payload);

        $action = ! $wasPublished && $data['status'] === LandingPage::STATUS_PUBLISHED ? 'landing_pages.publish' : 'landing_pages.edit';
        $this->audit($request, $landingPage, $action, $old, $payload);

        return back()->with('status', (string) setting('landing_pages.admin.update_ok', 'التعديل اتحفظ ✓'));
    }

    /** إلغاء نشر الصفحة وأرشفتها — الرابط العامّ يتحوّل لـ404 لا يبقى منشورًا صامتًا */
    public function archive(Request $request, LandingPage $landingPage): RedirectResponse
    {
        $old = ['status' => $landingPage->status];
        $landingPage->update(['status' => LandingPage::STATUS_ARCHIVED, 'archived_at' => now()]);

        $this->audit($request, $landingPage, 'landing_pages.archive', $old, ['status' => LandingPage::STATUS_ARCHIVED]);

        return back()->with('status', (string) setting('landing_pages.admin.archive_ok', 'صفحة الهبوط اتأرشفت ✓'));
    }

    /**
     * ⭐ استعادة صفحة مؤرشفة — **ترجع مسوّدة لا منشورة مباشرةً**: عودةٌ فوريّة
     * للنشر بلا مراجعة نقضٌ لروح «معاينتها قبل النشر» في نصّ `landing_pages.edit`.
     */
    public function restore(Request $request, LandingPage $landingPage): RedirectResponse
    {
        abort_unless($landingPage->isArchived(), 409, (string) setting('landing_pages.admin.not_archived_msg', 'الصفحة مش مؤرشفة أصلًا.'));

        $old = ['status' => $landingPage->status];
        $landingPage->update(['status' => LandingPage::STATUS_DRAFT, 'archived_at' => null]);

        $this->audit($request, $landingPage, 'landing_pages.restore', $old, ['status' => LandingPage::STATUS_DRAFT]);

        return back()->with('status', (string) setting('landing_pages.admin.restore_ok', 'اتستعادت مسوّدة ✓، راجعها وانشرها.'));
    }

    /** حذف نهائيّ بتأكيد — لا رجوع عنه (بخلاف الأرشفة) */
    public function destroy(Request $request, LandingPage $landingPage): RedirectResponse
    {
        $old = $landingPage->only(['status', 'headline', 'landingable_type', 'landingable_id']);
        $this->audit($request, $landingPage, 'landing_pages.delete', $old, []);

        $landingPage->delete();

        return redirect()
            ->route('admin.store.index')
            ->with('status', (string) setting('landing_pages.admin.destroy_ok', 'صفحة الهبوط اتحذفت نهائيًّا ✓'));
    }

    // ================================================================ مساعدات

    private function resolveLandingable(string $type, int $id): ?Model
    {
        $class = LandingPage::LANDINGABLE_TYPES[$type] ?? null;

        return $class ? $class::query()->find($id) : null;
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'type' => ['required', Rule::in(array_keys(LandingPage::LANDINGABLE_TYPES))],
            'id' => ['required', 'integer'],
            'headline' => ['nullable', 'string', 'max:190'],
        ]) + ['headline' => trim((string) $request->string('headline'))];
    }

    /** @return array<string, mixed> */
    private function payload(array $data, Model $landingable): array
    {
        return [
            'headline' => $data['headline'] !== '' ? $data['headline'] : null,
        ];
    }

    /** @return array<int, string>|null */
    private function lines(?string $text): ?array
    {
        $rows = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $text) ?: [])));

        return $rows === [] ? null : $rows;
    }

    /** أسطر `سؤال | إجابة` ⟵ قائمة أسئلة — @return array<int, array{q: string, a: string}>|null */
    private function faqLines(?string $text): ?array
    {
        $rows = [];

        foreach ($this->lines($text) ?? [] as $line) {
            [$q, $a] = array_pad(explode('|', $line, 2), 2, '');
            $q = trim($q);

            if ($q !== '') {
                $rows[] = ['q' => $q, 'a' => trim($a)];
            }
        }

        return $rows === [] ? null : $rows;
    }

    private function uniqueSlug(string $base): string
    {
        return Str::slug($base ?: 'landing').'-'.Str::lower(Str::random(6));
    }

    private function audit(Request $request, LandingPage $model, string $action, array $old, array $new): void
    {
        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => $action,
            'auditable_type' => $model->getMorphClass(),
            'auditable_id' => $model->getKey(),
            'old_values' => $old,
            'new_values' => $new,
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ]);
    }
}
