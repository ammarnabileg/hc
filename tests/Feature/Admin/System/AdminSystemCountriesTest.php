<?php

namespace Tests\Feature\Admin\System;

use App\Models\Country;
use App\Models\CountrySourceSnapshot;
use App\Models\Governorate;
use App\Models\Setting;
use App\Models\User;
use App\Services\Admin\System\CountryDataSync;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;

/**
 * 12.7-د «بيانات الدول»: مصدر + **فحص فروق (مضاف/محذوف/معدَّل) قبل الدمج** + دمج
 * **بلا فقد بيانات** — بقاعدة 2.11-د الحاكمة.
 *
 * الاختبار الحاكم هنا: **لا يفقد مستخدمٌ ارتباطه بمحافظته** مهما قال المصدر.
 */
class AdminSystemCountriesTest extends SystemTestCase
{
    /** أدمن بيانات الدول — بصلاحيّاته الخمس المنصوصة في المصفوفة (12.2.2). */
    private function countriesAdmin(): User
    {
        return $this->admin([
            'countries_data.view', 'countries_data.list', 'countries_data.edit',
            'countries_data.import', 'countries_data.export', 'settings_general.view',
        ], 'أدمن بيانات الدول');
    }

    /** مصر بمحافظتين، وواحدة منهما لها مستخدم — هذا هو محلّ الخطر. */
    private function seedGeography(): array
    {
        $egypt = Country::create([
            'iso2' => 'EG', 'name_ar' => 'مصر', 'name_en' => 'Egypt',
            'phone_code' => '+20', 'timezone' => 'Africa/Cairo', 'is_active' => true,
        ]);

        $cairo = Governorate::create(['country_id' => $egypt->id, 'name_ar' => 'القاهرة', 'name_en' => 'Cairo', 'is_active' => true]);
        $luxor = Governorate::create(['country_id' => $egypt->id, 'name_ar' => 'الأقصر', 'name_en' => 'Luxor', 'is_active' => true]);

        $resident = $this->makeUser('ساكن الأقصر');
        $resident->forceFill(['country_id' => $egypt->id, 'governorate_id' => $luxor->id])->save();

        return compact('egypt', 'cairo', 'luxor', 'resident');
    }

    /** نسخة مصدر بلا «الأقصر» وبدولة جديدة وباسمٍ معدَّل — الحالات الثلاث معًا. */
    private function sourcePayload(): array
    {
        return [
            'version' => '2026-08-01',
            'countries' => [
                [
                    'iso2' => 'EG',
                    'name_ar' => 'جمهوريّة مصر العربيّة', // معدَّل
                    'name_en' => 'Egypt',
                    'phone_code' => '+20',
                    'timezone' => 'Africa/Cairo',
                    'governorates' => [
                        ['name_ar' => 'القاهرة', 'name_en' => 'Cairo'],
                        ['name_ar' => 'الجيزة', 'name_en' => 'Giza'], // مضاف
                        // «الأقصر» غائبة ⟵ محذوفة من المصدر
                    ],
                ],
                [
                    'iso2' => 'JO', 'name_ar' => 'الأردن', 'name_en' => 'Jordan', // مضاف
                    'phone_code' => '+962', 'timezone' => 'Asia/Amman',
                    'governorates' => [['name_ar' => 'عمّان', 'name_en' => 'Amman']],
                ],
            ],
        ];
    }

    private function snapshot(): CountrySourceSnapshot
    {
        $sync = app(CountryDataSync::class);

        return $sync->check($sync->import($this->sourcePayload()));
    }

    /** الشاشة تفتح لمن يملك الصلاحيّة وحده — والتاب نفسه لا يظهر لغيره (12.2.1). */
    public function test_countries_screen_is_permission_gated(): void
    {
        $this->seedGeography();

        $this->actingAs($this->countriesAdmin())
            ->get(route('admin.settings.index', ['tab' => 'countries']))
            ->assertOk()
            ->assertSee('بيانات الدول');

        $this->actingAs($this->makeUser('غريب'))
            ->post(route('admin.countries.import'))
            ->assertForbidden();
    }

    /** الشاشة تعرض جدول الفروق بأعمدته الثلاثة وشارة «سيُدمج بلا فقد» (24.3). */
    public function test_the_screen_shows_the_three_diff_columns_before_merging(): void
    {
        $this->seedGeography();
        $this->snapshot();

        $this->actingAs($this->countriesAdmin())
            ->get(route('admin.settings.index', ['tab' => 'countries']))
            ->assertOk()
            ->assertSee('مضاف')
            ->assertSee('محذوف من المصدر')
            ->assertSee('معدَّل')
            ->assertSee('سيُدمج بلا فقد')
            ->assertSee('Dry-run')
            // والقاعدة معروضة قبل الفعل لا بعده
            ->assertSee('المحافظة لا تُخفى أبدًا', false)
            ->assertSee('ODbL', false);
    }

    /** ⭐ الفحص يفرز الفروق الثلاثة: مضاف · محذوف · معدَّل — قبل أيّ تنفيذ. */
    public function test_check_lists_added_removed_and_changed_before_merging(): void
    {
        $geo = $this->seedGeography();
        $snapshot = $this->snapshot();

        $diff = app(CountryDataSync::class)->diff($snapshot);

        $this->assertEqualsCanonicalizing(
            ['country:JO', 'gov:EG:giza'],
            array_column($diff['added'], 'key'),
        );
        $this->assertSame(['country:EG'], array_column($diff['changed'], 'key'));
        $this->assertSame(['gov:EG:luxor'], array_column($diff['removed'], 'key'));

        // المحافظة المحذوفة من المصدر تُعرَض **محميّة** قبل أن يقرّر المالك
        $this->assertTrue($diff['removed'][0]['protected']);
        $this->assertSame(1, $diff['removed'][0]['users']);

        // ولا شيء تغيّر بمجرّد الفحص — الفروق تُعرَض ليقرّر لا لتُنفَّذ (2.11)
        $this->assertDatabaseHas('governorates', ['id' => $geo['luxor']->id, 'is_active' => true]);
        $this->assertDatabaseHas('countries', ['id' => $geo['egypt']->id, 'name_ar' => 'مصر']);
        $this->assertDatabaseMissing('countries', ['iso2' => 'JO']);
    }

    /**
     * ⭐⭐ القاعدة الحاكمة: **الدمج لا يفقد ارتباط مستخدمٍ بمحافظته** — حتّى لو
     * اختارها المالك ضمن المحذوف. المحافظة تبقى موجودة **وظاهرة** ومربوطة به.
     */
    public function test_merge_never_loses_a_users_link_to_their_governorate(): void
    {
        $geo = $this->seedGeography();
        $snapshot = $this->snapshot();

        $report = $this->actingAs($this->countriesAdmin())
            ->post(route('admin.countries.merge'), [
                'snapshot_id' => $snapshot->id,
                // المالك اختار **كلّ** الفروق بما فيها حذف «الأقصر»
                'keys' => ['country:JO', 'gov:EG:giza', 'country:EG', 'gov:EG:luxor'],
            ])
            ->assertRedirect()
            // لو فشل التحقّق بعد الكتابة تُلغى العمليّة كلّها وتظهر رسالة خطأ —
            // فنجاح الدمج نفسه جزءٌ من الادّعاء لا مقدّمةٌ مسلَّمة.
            ->assertSessionMissing('error')
            ->getSession()->get('countries_report');

        // 1) المستخدم ما زال مرتبطًا بمحافظته — الاختبار كلّه من أجل هذا السطر
        $this->assertDatabaseHas('users', [
            'id' => $geo['resident']->id,
            'governorate_id' => $geo['luxor']->id,
        ]);

        // 2) والمحافظة **لم تُخفَ أبدًا** — إخفاؤها يسقطها من كلّ قوائم الاختيار
        $this->assertDatabaseHas('governorates', [
            'id' => $geo['luxor']->id,
            'is_active' => true,
            'sync_hidden_at' => null,
        ]);

        // 3) والتقرير يقولها صراحةً: سجلّات محميّة من الحذف
        $this->assertContains('مصر ← الأقصر', $report['protected']);
        $this->assertSame(0, $report['hidden']);

        // 4) وباقي الفروق طُبِّقت فعلًا — الحماية ليست شللًا
        $this->assertDatabaseHas('countries', ['iso2' => 'JO', 'name_ar' => 'الأردن']);
        $this->assertDatabaseHas('countries', ['iso2' => 'EG', 'name_ar' => 'جمهوريّة مصر العربيّة']);
        $this->assertDatabaseHas('governorates', ['name_ar' => 'الجيزة', 'country_id' => $geo['egypt']->id]);
    }

    /** لا يُدمَج إلّا ما اختاره المالك — الصفّ غير المختار يبقى كما هو. */
    public function test_only_the_rows_the_owner_picked_are_merged(): void
    {
        $geo = $this->seedGeography();
        $snapshot = $this->snapshot();

        $this->actingAs($this->countriesAdmin())->post(route('admin.countries.merge'), [
            'snapshot_id' => $snapshot->id,
            'keys' => ['country:JO'],
        ])->assertRedirect();

        $this->assertDatabaseHas('countries', ['iso2' => 'JO']);
        // الاسم المعدَّل لم يُختَر ⟵ لم يُطبَّق
        $this->assertDatabaseHas('countries', ['iso2' => 'EG', 'name_ar' => 'مصر']);
        $this->assertDatabaseMissing('governorates', ['name_ar' => 'الجيزة', 'country_id' => $geo['egypt']->id]);
    }

    /** Dry-run يعرض ما سيُنفَّذ **بالضبط** — ولا يكتب حرفًا (24.3 · 2.11-ط). */
    public function test_dry_run_reports_the_effect_without_writing_anything(): void
    {
        $this->seedGeography();
        $snapshot = $this->snapshot();

        $report = $this->actingAs($this->countriesAdmin())->post(route('admin.countries.merge'), [
            'snapshot_id' => $snapshot->id,
            'keys' => ['country:JO', 'country:EG'],
            'dry_run' => 1,
        ])->assertRedirect()->getSession()->get('countries_report');

        $this->assertTrue($report['dry_run']);

        /*
         | ⭐ **الدولة الجديدة تُحسَب بمحافظاتها**: صفّها في جدول الفروق مكتوبٌ
         | عليه «دولة جديدة بـN محافظة»، و`diff()` لا تُدرِج محافظاتها صفوفًا
         | مستقلّة — فلو أضافها الدمج وحدها لخرج الوعد كذبًا وخرجت 246 دولة
         | بصفر محافظة. فالأردن هنا = **الدولة + عمّان** = صفّان.
         */
        $this->assertSame(2, $report['added']);
        $this->assertSame(1, $report['updated']);

        // ولا أثر في القاعدة
        $this->assertDatabaseMissing('countries', ['iso2' => 'JO']);
        $this->assertDatabaseHas('countries', ['iso2' => 'EG', 'name_ar' => 'مصر']);
        $this->assertSame('checked', $snapshot->refresh()->status);
    }

    /**
     * دولة غابت عن المصدر ولا مستخدم لها: **تُخفى ولا تُحذف** — صفّها باقٍ
     * بأرقامه (2.11-د: لا حذف أعمى).
     */
    public function test_a_country_absent_from_the_source_is_hidden_never_deleted(): void
    {
        $this->seedGeography();

        $orphan = Country::create([
            'iso2' => 'ZZ', 'name_ar' => 'دولة قديمة', 'name_en' => 'Legacy',
            'timezone' => 'UTC', 'is_active' => true,
        ]);

        $snapshot = $this->snapshot();

        $this->actingAs($this->countriesAdmin())->post(route('admin.countries.merge'), [
            'snapshot_id' => $snapshot->id,
            'keys' => ['country:ZZ'],
        ])->assertRedirect();

        $this->assertDatabaseHas('countries', ['id' => $orphan->id, 'is_active' => false]);
        $this->assertNotNull($orphan->refresh()->sync_hidden_at);
    }

    /** ودولة غابت عن المصدر ولها مستخدم: **لا تُمَسّ** ولو اختارها المالك. */
    public function test_a_country_with_users_is_protected_even_if_selected(): void
    {
        $geo = $this->seedGeography();

        // نسخة بلا مصر أصلًا — أقسى حالة ممكنة
        $sync = app(CountryDataSync::class);
        $snapshot = $sync->check($sync->import([
            'version' => 'x',
            'countries' => [['iso2' => 'JO', 'name_ar' => 'الأردن', 'name_en' => 'Jordan', 'governorates' => []]],
        ]));

        $report = $this->actingAs($this->countriesAdmin())->post(route('admin.countries.merge'), [
            'snapshot_id' => $snapshot->id,
            'keys' => ['country:EG'],
        ])->assertRedirect()->getSession()->get('countries_report');

        $this->assertDatabaseHas('countries', ['id' => $geo['egypt']->id, 'is_active' => true]);
        $this->assertDatabaseHas('users', ['id' => $geo['resident']->id, 'country_id' => $geo['egypt']->id]);
        $this->assertSame(0, $report['hidden']);
        $this->assertNotEmpty($report['protected']);
    }

    /** سياسة «لا حذف تلقائيّ» مطفأة: لا حذف أيضًا — تُترَك لقرارٍ يدويّ لا تُمحى. */
    public function test_turning_the_policy_off_still_never_deletes(): void
    {
        $this->seedGeography();

        $orphan = Country::create(['iso2' => 'ZZ', 'name_ar' => 'قديمة', 'name_en' => 'Legacy', 'timezone' => 'UTC']);

        Setting::query()->where('key', 'countries.no_auto_delete')->update(['value' => '0']);
        Cache::forget('settings');

        $snapshot = $this->snapshot();

        $this->actingAs($this->countriesAdmin())->post(route('admin.countries.merge'), [
            'snapshot_id' => $snapshot->id,
            'keys' => ['country:ZZ'],
        ])->assertRedirect();

        $this->assertDatabaseHas('countries', ['id' => $orphan->id]);
    }

    /** الاستيراد يرفض ملفًّا ليس نسخة مصدر — برسالة تقول ماذا يفعل (2.17). */
    public function test_import_rejects_a_file_that_is_not_a_source_snapshot(): void
    {
        $this->actingAs($this->countriesAdmin())
            ->post(route('admin.countries.import'), [
                'file' => UploadedFile::fake()->createWithContent('bad.json', '{"nothing":true}'),
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseCount('country_source_snapshots', 0);
    }

    /** التصدير يخرج بنفس شكل النسخة — فيصلح مدخلًا للفحص القادم. */
    public function test_export_matches_the_snapshot_shape(): void
    {
        $this->seedGeography();

        $payload = json_decode(
            $this->actingAs($this->countriesAdmin())->get(route('admin.countries.export'))
                ->assertOk()->streamedContent(),
            true,
        );

        $this->assertSame('EG', $payload['countries'][0]['iso2']);
        $this->assertContains('الأقصر', array_column($payload['countries'][0]['governorates'], 'name_ar'));
    }
}
