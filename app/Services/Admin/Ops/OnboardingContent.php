<?php

namespace App\Services\Admin\Ops;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * محتوى الـOnboarding (12.7-أ) و«شاشة أوّل مرّة» (2.15-د).
 *
 * القاعدة الحاكمة: **الأدمن يختار الشاشات وما يظهر فيها** من قوالب جاهزة قابلة
 * للتعديل، وتظهر للمستخدم **بوب-أب بمراحل** — فالمحتوى بيانات لا كود،
 * ولا يحتاج أيّ تعديل برمجيّ لتغيير كلمة واحدة فيه (2.13).
 */
class OnboardingContent
{
    /** مفتاح سلسلة الترحيب الأولى — أوّل ما يراه المستخدم الجديد */
    public const WELCOME = 'welcome';

    public function __construct(
        private readonly OpsAudit $audit,
        private readonly OpsSettings $settings,
    ) {}

    // ------------------------------------------------------------------ قراءة

    /**
     * الشاشات المتاحة لـ«أوّل مرّة» — إعداد لا قائمة محروقة (2.13).
     *
     * @return array<string, string>
     */
    public function screens(): array
    {
        $screens = setting('onboarding.first_time.screens', []);
        $screens = is_array($screens) ? $screens : [];

        // سلسلة الترحيب أوّلًا دائمًا — هي رحلة المستخدم الجديد لا شاشةً عابرة
        return [self::WELCOME => (string) setting('onboarding.welcome.label', 'سلسلة الترحيب الأولى')] + $screens;
    }

    /** الشاشات المفعَّلة فعلًا — على أهمّ الشاشات فقط منعًا للزحام (2.15-د) */
    public function enabledScreens(): array
    {
        $enabled = setting('ux.first_time.enabled_screens', []);

        return is_array($enabled) ? array_values(array_filter(array_map('strval', $enabled))) : [];
    }

    /**
     * القوالب الجاهزة لكلّ صفحة — قابلة للتعديل بعد تطبيقها (2.15-د).
     *
     * @return array<string, array<int, array<string, string>>>
     */
    public function templates(): array
    {
        $templates = setting('onboarding.first_time.templates', []);

        return is_array($templates) ? $templates : [];
    }

    /**
     * قالب شاشةٍ بعينها، وإلّا **القالب العامّ** — فلا تبقى شاشةٌ بلا نقطة بداية
     * جاهزة. (وهذا القالب العامّ هو وارث `ux.first_time.default_template` القديم
     * بعد توحيد مصدر «أوّل مرّة» على الجدول — انظر هجرة التوحيد.)
     *
     * @return array<int, array<string, string>>
     */
    public function templateFor(string $screen): array
    {
        $own = $this->templates()[$screen] ?? null;

        if (is_array($own) && $own !== []) {
            return $own;
        }

        $default = setting('onboarding.first_time.default_template', []);

        return is_array($default) ? $default : [];
    }

    /** شرائح شاشة بعينها مرتّبةً — والترتيب هو ما يراه المستخدم بالضبط */
    public function slides(string $screen, bool $activeOnly = false): Collection
    {
        return DB::table('onboarding_slides')
            ->where('screen', $screen)
            ->when($activeOnly, fn ($q) => $q->where('is_active', true))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /** عدد الشرائح لكلّ شاشة — لعرضه بجوار اسم الشاشة بلا استعلام لكلّ صفّ */
    public function countsByScreen(): array
    {
        return DB::table('onboarding_slides')
            ->selectRaw('screen, count(*) as total, sum(case when is_active = 1 then 1 else 0 end) as active')
            ->groupBy('screen')
            ->get()
            ->keyBy('screen')
            ->map(fn ($row) => ['total' => (int) $row->total, 'active' => (int) $row->active])
            ->all();
    }

    /**
     * الرحلة كما يراها المستخدم: المراحل المفعَّلة فقط بترتيبها،
     * ومعها نصوص الأزرار من الإعدادات (2.13) — فالمعاينة هي الحقيقة لا محاكاةً لها.
     */
    public function journey(string $screen): array
    {
        return [
            'screen' => $screen,
            'label' => $this->screens()[$screen] ?? $screen,
            'stages' => $this->slides($screen, activeOnly: true)->values()->all(),
            'next_label' => (string) setting('onboarding.first_time.next_label', 'التالي'),
            'back_label' => (string) setting('onboarding.first_time.back_label', 'السابق'),
            'skip_label' => (string) setting('onboarding.first_time.skip_label', 'تخطّي'),
            'done_label' => (string) setting('onboarding.first_time.done_label', 'يلا نبدأ'),
            'replay_hint' => (string) setting('onboarding.first_time.replay_hint', 'زرّ «؟» يعيد الشرح وقت ما تحبّ.'),
            'is_enabled' => $screen === self::WELCOME
                ? (bool) setting('onboarding.welcome.enabled', true)
                : in_array($screen, $this->enabledScreens(), true),
        ];
    }

    /** ثلاثة أرقام تكفي رأس الصفحة — والحدّ أربعة (2.15-أ-3) */
    public function kpis(): array
    {
        $total = DB::table('onboarding_slides')->count();
        $active = DB::table('onboarding_slides')->where('is_active', true)->count();

        return [
            ['label' => 'كلّ الشرائح', 'value' => (string) $total, 'icon' => '🧭'],
            ['label' => 'المفعَّلة', 'value' => (string) $active, 'icon' => '✅'],
            ['label' => 'شاشات «أوّل مرّة»', 'value' => (string) count($this->enabledScreens()), 'icon' => '💡'],
        ];
    }

    // ------------------------------------------------------------------ كتابة

    public function create(array $data, ?User $actor): int
    {
        $screen = $this->safeScreen($data['screen'] ?? self::WELCOME);

        $id = (int) DB::table('onboarding_slides')->insertGetId([
            'screen' => $screen,
            'title_ar' => $data['title_ar'],
            'body_ar' => $data['body_ar'] ?? null,
            'image_path' => $data['image_path'] ?? null,
            'action_label' => $data['action_label'] ?? null,
            'action_url' => $data['action_url'] ?? null,
            'sort_order' => $data['sort_order'] ?? $this->nextOrder($screen),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'from_template' => (bool) ($data['from_template'] ?? false),
            'created_by' => $actor?->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->audit->record($actor, 'ops.onboarding.slide.created', [
            'screen' => $screen,
            'title' => $data['title_ar'],
        ], 'onboarding_slides', $id);

        return $id;
    }

    public function update(int $id, array $data, ?User $actor): bool
    {
        $slide = $this->find($id);

        if (! $slide) {
            return false;
        }

        $payload = array_filter([
            'title_ar' => $data['title_ar'] ?? null,
            'body_ar' => $data['body_ar'] ?? null,
            'image_path' => $data['image_path'] ?? null,
            'action_label' => $data['action_label'] ?? null,
            'action_url' => $data['action_url'] ?? null,
        ], fn ($v) => $v !== null);

        if (array_key_exists('is_active', $data)) {
            $payload['is_active'] = (bool) $data['is_active'];
        }

        $payload['updated_at'] = now();

        DB::table('onboarding_slides')->where('id', $id)->update($payload);

        $this->audit->record($actor, 'ops.onboarding.slide.updated', [
            'screen' => $slide->screen,
            'title' => $payload['title_ar'] ?? $slide->title_ar,
        ], 'onboarding_slides', $id, ['title' => $slide->title_ar]);

        return true;
    }

    public function toggle(int $id, ?User $actor): bool
    {
        $slide = $this->find($id);

        if (! $slide) {
            return false;
        }

        DB::table('onboarding_slides')->where('id', $id)->update([
            'is_active' => ! $slide->is_active,
            'updated_at' => now(),
        ]);

        $this->audit->record($actor, 'ops.onboarding.slide.toggled', [
            'screen' => $slide->screen,
            'is_active' => $slide->is_active ? 'off' : 'on',
        ], 'onboarding_slides', $id);

        return true;
    }

    public function delete(int $id, ?User $actor): bool
    {
        $slide = $this->find($id);

        if (! $slide) {
            return false;
        }

        DB::table('onboarding_slides')->where('id', $id)->delete();

        $this->audit->record($actor, 'ops.onboarding.slide.deleted', [
            'screen' => $slide->screen,
            'title' => $slide->title_ar,
        ], 'onboarding_slides', $id);

        return true;
    }

    /**
     * إعادة الترتيب بالسحب — والترتيب هو تسلسل المراحل الذي سيراه المستخدم.
     *
     * @param  array<int, int|string>  $ids
     */
    public function reorder(string $screen, array $ids, ?User $actor): int
    {
        $screen = $this->safeScreen($screen);
        $moved = 0;

        foreach (array_values($ids) as $index => $id) {
            $moved += DB::table('onboarding_slides')
                ->where('id', (int) $id)
                ->where('screen', $screen)
                ->update(['sort_order' => $index + 1, 'updated_at' => now()]);
        }

        if ($moved > 0) {
            $this->audit->record($actor, 'ops.onboarding.reordered', [
                'screen' => $screen,
                'slides' => $moved,
            ], 'onboarding_slides');
        }

        return $moved;
    }

    /**
     * تطبيق القالب الجاهز لشاشة — **يضيف ولا يمسح** ما كتبه الأدمن بيده،
     * فالقالب نقطةُ بداية قابلة للتعديل لا استبدالٌ لمجهود سابق.
     */
    public function applyTemplate(string $screen, ?User $actor): int
    {
        $screen = $this->safeScreen($screen);
        $stages = $this->templateFor($screen);

        if ($stages === []) {
            return 0;
        }

        $added = 0;

        foreach ($stages as $stage) {
            if (! is_array($stage) || ! isset($stage['title'])) {
                continue;
            }

            $this->create([
                'screen' => $screen,
                'title_ar' => (string) $stage['title'],
                'body_ar' => (string) ($stage['body'] ?? ''),
                'action_label' => $stage['action_label'] ?? null,
                'action_url' => $stage['action_url'] ?? null,
                'from_template' => true,
            ], $actor);

            $added++;
        }

        $this->audit->record($actor, 'ops.onboarding.template.applied', [
            'screen' => $screen,
            'stages' => $added,
        ], 'onboarding_slides');

        return $added;
    }

    /** حفظ الشاشات المفعَّلة لـ«أوّل مرّة» — الشاشات المجهولة تُتجاهَل بصمت */
    public function saveEnabledScreens(array $screens, User $actor): array
    {
        $known = array_keys($this->screens());
        $clean = array_values(array_intersect($known, array_map('strval', $screens)));

        $result = $this->settings->save('ux.first_time.enabled_screens', $clean, $actor);

        $this->audit->record($actor, 'ops.onboarding.first_time.updated', [
            'screens' => $clean,
        ], 'settings');

        return $result + ['screens' => $clean];
    }

    public function find(int $id): ?object
    {
        return DB::table('onboarding_slides')->where('id', $id)->first();
    }

    /** أقصى عدد شرائح لشاشة — إعداد يمنع سلسلةً مرهقة (2.15) */
    public function isFull(string $screen): bool
    {
        return $this->slides($screen)->count() >= (int) setting('onboarding.slides.max', 6);
    }

    // ------------------------------------------------------------------ داخليّ

    private function nextOrder(string $screen): int
    {
        return (int) DB::table('onboarding_slides')->where('screen', $screen)->max('sort_order') + 1;
    }

    private function safeScreen(string $screen): string
    {
        return array_key_exists($screen, $this->screens()) ? $screen : self::WELCOME;
    }
}
