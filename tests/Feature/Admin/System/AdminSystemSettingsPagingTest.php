<?php

namespace Tests\Feature\Admin\System;

use App\Models\Setting;
use App\Services\Admin\System\SettingsRegistry;
use Illuminate\Support\Facades\Cache;

/**
 * ⭐ شاشة الإعدادات تحت وزنها: **التحميل الكسول للمجموعات** (2.15-ب) مع بقاء
 * **كلّ** مفتاح قابلًا للتعديل (2.13).
 *
 * الشاشة كانت تُصيّر مفاتيح التاب كلّها دفعةً — 3,353 حقلًا في تابّ التطوّع —
 * فتسقط بـTimeout. والحرّاس هنا يمنعون رجوعَها:
 *
 * 1. الصفحة لا تُصيّر أكثر من **دفعةٍ واحدة** مهما كبر التاب.
 * 2. ولا مفتاح يضيع: الدفعة التالية تصل من نقطتها، والبحث يمسح **التاب كلّه**
 *    لا المعروض — وإلّا صار الترقيمُ حجابًا.
 * 3. و**الحفظ لا يمسّ الصفحات الأخرى**: الشاشة تحفظ حقلًا حقلًا، وحفظُ مفتاحٍ
 *    في الدفعة الأولى يترك بقيّة مفاتيح التاب كما هي بلا تفريغ. وهذا أخطر ما
 *    يمكن أن يكسره التقسيم، فله حارسٌ يقرأ من القاعدة قبل وبعد.
 * 4. وعزل الماليّ يبقى قائمًا على **نقطة الدفعة** كما هو على الصفحة.
 */
class AdminSystemSettingsPagingTest extends SystemTestCase
{
    private const ADMIN = ['settings_general.view', 'settings_general.edit'];

    /** مجموعةُ الحمل الثقيل ومقاسها — بذرةُ الاختبار لا بذرةُ العرض */
    private const FAT_GROUP = 'volunteer_org';

    private const FAT_COUNT = 120;

    /**
     * ⭐ بذرةُ الوزن: بذرة العرض تزرع عشرين مفتاحًا في المجموعة، والعطب الذي
     * نحرسه لا يظهر إلّا فوق مئات. فنزرع هنا **120 مفتاحًا** في مجموعةٍ حقيقيّة
     * من تابّ التطوّع — أكثر من أربع دفعات — فيقيس الحارسُ ما وقع فعلًا.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $rows = [];

        for ($i = 1; $i <= self::FAT_COUNT; $i++) {
            $n = str_pad((string) $i, 4, '0', STR_PAD_LEFT);
            $rows[] = [
                'key' => 'volunteer.org.load_guard_'.$n,
                'group' => self::FAT_GROUP,
                'label_ar' => 'مفتاح حمل '.$n,
                'value' => 'قيمة '.$n,
                'default_value' => 'قيمة '.$n,
                'type' => 'string',
                'hint' => '',
                'is_owner_only' => false,
                'is_sensitive' => false,
            ];
        }

        Setting::query()->insert($rows);
        Cache::forget('settings');
    }

    /** أكبر مجموعة في تابٍ ما — مادّةُ الاختبار مهما تغيّر عدد المفاتيح */
    private function fattestGroup(string $tab): array
    {
        $registry = app(SettingsRegistry::class);
        $counts = $registry->countsForTab($tab, $this->owner());
        $group = null;
        $max = 0;

        foreach ($counts as $key => $meta) {
            if ($meta['count'] > $max) {
                $max = $meta['count'];
                $group = $key;
            }
        }

        return [$group, $max];
    }

    /**
     * الحارس الأوّل: الصفحة تُصيّر **دفعةً واحدة** لا مفاتيح التاب كلّها.
     *
     * الطفرة: أعِد `pageOfGroup()` بلا `limit` (أو صيّر `groupedForTab()` كما
     * كان) ⟵ يسقط هذا الحارس لأنّ عدد الحقول يساوي عدد مفاتيح التاب.
     */
    public function test_the_screen_renders_one_batch_not_the_whole_tab(): void
    {
        $registry = app(SettingsRegistry::class);
        $owner = $this->owner();
        $batch = $registry->batchSize();

        foreach (array_keys($registry->tabsFor($owner)) as $tab) {
            $html = $this->actingAs($owner)->get(route('admin.settings.index', ['tab' => $tab]))
                ->assertOk()
                ->getContent();

            $rendered = substr_count($html, 'class="setting-row"');

            $this->assertLessThanOrEqual(
                $batch + 1, // +1: تابّ الصيانة يضمّ حقلًا واحدًا خارج كروت المجموعات
                $rendered,
                "التاب «{$tab}» صيّر {$rendered} حقلًا — والدفعة {$batch}.",
            );
        }
    }

    /** ورأس الكارت يعلن العدد الكامل — فالمخفيّ معلوم لا مفقود (2.13) */
    public function test_group_header_announces_the_full_count_even_when_collapsed(): void
    {
        [$group, $count] = $this->fattestGroup('volunteer');

        $this->assertSame(self::FAT_GROUP, $group);
        $this->assertGreaterThanOrEqual(self::FAT_COUNT, $count);

        $this->actingAs($this->owner())->get(route('admin.settings.index', ['tab' => 'volunteer']))
            ->assertOk()
            ->assertSee('data-group-card="'.$group.'"', false)
            ->assertSee('data-total="'.$count.'"', false);
    }

    /** الدفعة التالية تصل من نقطتها بمفاتيح **جديدة** لا مكرَّرة */
    public function test_the_next_batch_brings_new_keys(): void
    {
        $registry = app(SettingsRegistry::class);
        $owner = $this->owner();
        [$group, $count] = $this->fattestGroup('volunteer');
        $batch = $registry->batchSize();

        $this->assertGreaterThan($batch, $count, 'المجموعة أصغر من دفعةٍ — لا معنى للاختبار.');

        $first = $this->actingAs($owner)->getJson(route('admin.settings.batch', [
            'tab' => 'volunteer', 'group' => $group, 'offset' => 0,
        ]))->assertOk()->json();

        $second = $this->actingAs($owner)->getJson(route('admin.settings.batch', [
            'tab' => 'volunteer', 'group' => $group, 'offset' => $first['next'],
        ]))->assertOk()->json();

        $keysOf = function (string $html): array {
            preg_match_all('/data-setting="([^"]+)"/', $html, $matches);

            return $matches[1];
        };

        $a = $keysOf($first['html']);
        $b = $keysOf($second['html']);

        $this->assertCount($batch, $a);
        $this->assertNotEmpty($b);
        $this->assertSame([], array_intersect($a, $b), 'الدفعتان تتقاطعان — مفتاحٌ يُصيَّر مرّتين.');
    }

    /**
     * ⭐ **البحث يجد مفتاحًا خارج الدفعة المعروضة** — وإلّا صار التحميل الكسول
     * حجابًا: مفتاحٌ في الدفعة الأربعين لا يجده أحد.
     *
     * الطفرة: أعِد البحث ليصفّي `forTab()` المحدودة بدل مسح القاعدة ⟵ يسقط.
     */
    public function test_search_finds_a_key_that_is_not_on_the_rendered_page(): void
    {
        $registry = app(SettingsRegistry::class);
        $owner = $this->owner();
        [$group] = $this->fattestGroup('volunteer');

        // مفتاحٌ بعد الدفعة الأولى بمسافةٍ مؤكَّدة
        $far = $registry->pageOfGroup('volunteer', $group, $owner, '', $registry->batchSize() * 2, 1)->first();
        $this->assertNotNull($far, 'المجموعة أقصر من دفعتين — لا معنى للاختبار.');

        // ليس على الصفحة أصلًا
        $this->actingAs($owner)->get(route('admin.settings.index', ['tab' => 'volunteer']))
            ->assertOk()
            ->assertDontSee('data-setting="'.$far->key.'"', false);

        // والبحث يجده بمساره الكامل
        $results = $this->actingAs($owner)
            ->getJson(route('admin.settings.search', ['q' => $far->key]))
            ->assertOk()
            ->json('results');

        $this->assertContains($far->key, array_column($results, 'key'));
        $this->assertSame('volunteer', $results[array_search($far->key, array_column($results, 'key'), true)]['tab']);
    }

    /** ونتيجةُ البحث تفتح **دفعة المفتاح نفسه** لا الدفعة الأولى دائمًا */
    public function test_following_a_search_result_renders_the_batch_that_holds_the_key(): void
    {
        $registry = app(SettingsRegistry::class);
        $owner = $this->owner();
        [$group] = $this->fattestGroup('volunteer');

        $far = $registry->pageOfGroup('volunteer', $group, $owner, '', $registry->batchSize() * 3, 1)->first();
        $this->assertNotNull($far);

        $this->actingAs($owner)->get(route('admin.settings.index', ['tab' => 'volunteer', 'key' => $far->key]))
            ->assertOk()
            ->assertSee('data-setting="'.$far->key.'"', false);
    }

    /**
     * ⭐⭐ **أخطر ما في التقسيم: الحفظ.**
     *
     * نحفظ مفتاحًا في الدفعة الأولى، ثمّ نقرأ من القاعدة **كلّ** مفاتيح التاب:
     * لا واحد تغيّر ولا واحد فُرِّغ. والحفظ حقلٌ حقل عبر `settings.field`،
     * فلا فورم يرسل الحقول الظاهرة وحدها فيمسح الغائبة.
     */
    public function test_saving_one_field_leaves_every_other_key_in_the_tab_untouched(): void
    {
        $registry = app(SettingsRegistry::class);
        $owner = $this->owner();
        $groups = $registry->groupsOfTab('volunteer', $owner);

        $before = Setting::query()->whereIn('group', $groups)->orderBy('key')->pluck('value', 'key')->all();
        $this->assertGreaterThan($registry->batchSize(), count($before));

        $target = $registry->pageOfGroup('volunteer', self::FAT_GROUP, $owner, 'load_guard_', 0, 1)->first();
        $this->assertNotNull($target);

        $this->actingAs($owner)
            ->postJson(route('admin.settings.field'), ['key' => $target->key, 'value' => 'قيمة الحارس'])
            ->assertOk()
            ->assertJson(['saved' => true]);

        $after = Setting::query()->whereIn('group', $groups)->orderBy('key')->pluck('value', 'key')->all();

        // لا مفتاح اختفى ولا مفتاح ظهر
        $this->assertSame(array_keys($before), array_keys($after));

        foreach ($before as $key => $value) {
            if ($key === $target->key) {
                $this->assertSame('قيمة الحارس', $after[$key]);

                continue;
            }

            $this->assertSame($value, $after[$key], "المفتاح «{$key}» اتغيّر بحفظ مفتاحٍ آخر.");
        }

        // ولا واحد اتفرّغ
        $emptied = array_filter(
            $after,
            fn ($value, $key) => $key !== $target->key && $value === '' && ($before[$key] ?? '') !== '',
            ARRAY_FILTER_USE_BOTH,
        );

        $this->assertSame([], $emptied, 'مفاتيح اتفرّغت بحفظ مفتاحٍ في دفعةٍ أخرى.');
    }

    /** والقيمة المحفوظة تعود في دفعتها هي عند إعادة تحميلها — لا القديمة */
    public function test_a_saved_value_comes_back_in_its_own_batch(): void
    {
        $registry = app(SettingsRegistry::class);
        $owner = $this->owner();
        [$group] = $this->fattestGroup('volunteer');

        $offset = $registry->batchSize() * 2;
        $far = $registry->pageOfGroup('volunteer', $group, $owner, '', $offset, 1)->first();
        $this->assertNotNull($far);

        $this->actingAs($owner)
            ->postJson(route('admin.settings.field'), ['key' => $far->key, 'value' => 'قيمة بعيدة'])
            ->assertOk();

        $html = $this->actingAs($owner)->getJson(route('admin.settings.batch', [
            'tab' => 'volunteer', 'group' => $group, 'offset' => $offset,
        ]))->assertOk()->json('html');

        $this->assertStringContainsString('data-setting="'.$far->key.'"', $html);
        $this->assertStringContainsString('قيمة بعيدة', $html);
    }

    /** 🔒 عزل الماليّ قائمٌ على نقطة الدفعة كما هو على الصفحة (2.13-و) */
    public function test_the_batch_endpoint_never_hands_finance_keys_to_a_general_admin(): void
    {
        $admin = $this->admin(self::ADMIN);

        $this->actingAs($admin)->getJson(route('admin.settings.batch', [
            'tab' => 'finance', 'group' => 'finance', 'offset' => 0,
        ]))->assertNotFound();
    }

    /** والتاب أو المجموعة المجهولة ترجع رسالةً تقول ماذا يفعل — لا 404 صامتة (2.17-ب) */
    public function test_an_unknown_group_answers_with_a_message(): void
    {
        $this->actingAs($this->owner())->getJson(route('admin.settings.batch', [
            'tab' => 'volunteer', 'group' => 'no_such_group', 'offset' => 0,
        ]))->assertNotFound()->assertJsonStructure(['message']);
    }

    /**
     * ⭐ ولا يُصيَّر مفتاحٌ محميّ لأدمن عامّ **من نقطة الدفعة** — لا في HTML
     * ولا في العدّ. فالتحميل الكسول لم يفتح بابًا خلفيًّا على المجموعة المحميّة.
     */
    public function test_the_batch_never_hands_an_owner_only_key_to_a_general_admin(): void
    {
        Setting::query()->create([
            'key' => 'volunteer.org.load_guard_secret',
            'group' => self::FAT_GROUP,
            'label_ar' => 'مفتاح محميّ',
            'value' => 'سرّ',
            'default_value' => 'سرّ',
            'type' => 'string',
            'hint' => '',
            'is_owner_only' => true,
            'is_sensitive' => false,
        ]);

        $admin = $this->admin(self::ADMIN);
        $registry = app(SettingsRegistry::class);

        // العدّ لا يحسبه
        $counts = $registry->countsForTab('volunteer', $admin);
        $owner = $registry->countsForTab('volunteer', $this->owner());
        $this->assertSame($owner[self::FAT_GROUP]['count'] - 1, $counts[self::FAT_GROUP]['count']);

        // والدفعات لا تُخرجه مهما مشى فيها
        for ($offset = 0; $offset <= self::FAT_COUNT + 25; $offset += 25) {
            $html = $this->actingAs($admin)->getJson(route('admin.settings.batch', [
                'tab' => 'volunteer', 'group' => self::FAT_GROUP, 'offset' => $offset,
            ]))->assertOk()->json('html');

            $this->assertStringNotContainsString('volunteer.org.load_guard_secret', $html);
        }
    }
}
