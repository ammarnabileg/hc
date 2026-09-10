<?php

namespace Tests\Feature\Admin\Content;

use App\Models\CertificateAccreditation;
use App\Models\CertificateType;

/**
 * الاعتمادات (24.1 سطر 4619-4622): «العرض: **جدول** — الشعار · الاسم ·
 * كم نوع شهادة يستخدمه (الضغط ← الأنواع المفلترة) · عدد الشهادات الصادرة ·
 * الحالة» — كان كروتًا بلا بحثٍ ولا فلاتر ولا زرّ تعديل.
 */
class CertificateAccreditationsTableTest extends AdminContentTestCase
{
    public function test_it_renders_as_a_table_not_cards(): void
    {
        $response = $this->actingAs($this->admin())
            ->get(route('admin.certificates.index', ['tab' => 'accreditations']))
            ->assertOk();

        $response->assertSee('<table', false);
    }

    public function test_search_by_name_filters_the_list(): void
    {
        CertificateAccreditation::create(['name_ar' => 'جهة الاختبار الفريدة', 'name_en' => 'Unique Test Body', 'is_active' => true]);
        CertificateAccreditation::create(['name_ar' => 'جهة أخرى', 'name_en' => 'Other Body', 'is_active' => true]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.certificates.index', ['tab' => 'accreditations', 'q' => 'الفريدة']))
            ->assertOk();

        $response->assertSee('جهة الاختبار الفريدة');
        $response->assertDontSee('جهة أخرى');
    }

    public function test_status_filter_narrows_the_list(): void
    {
        CertificateAccreditation::create(['name_ar' => 'جهة نشطة', 'name_en' => 'Active Body', 'is_active' => true]);
        CertificateAccreditation::create(['name_ar' => 'جهة معطّلة', 'name_en' => 'Inactive Body', 'is_active' => false]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.certificates.index', ['tab' => 'accreditations', 'status' => 'inactive']))
            ->assertOk();

        $response->assertSee('جهة معطّلة');
        $response->assertDontSee('جهة نشطة');
    }

    /** ⭐ 24.2: بحثٌ بلا نتائج يقول كده صراحةً بدل رسالة توهم بعدم وجود اعتمادات أصلًا. */
    public function test_a_search_with_no_matches_shows_a_filtered_empty_message_not_the_start_message(): void
    {
        $this->assertGreaterThan(0, CertificateAccreditation::query()->count(), 'اعتماد المنصّة موجودٌ دائمًا');

        $response = $this->actingAs($this->admin())
            ->get(route('admin.certificates.index', ['tab' => 'accreditations', 'q' => 'zzzznotexist']))
            ->assertOk();

        $response->assertSee(
            setting('ux.empty_state.filtered_message', 'مفيش نتائج تطابق البحث/الفلتر الحاليّ — جرّب فلترًا تانيًا.'),
            false,
        );
        $response->assertDontSee(
            setting('admin.certificates.partials.accreditations.mfysh_aatmadat_msjla_asla', 'مفيش اعتمادات مسجّلة أصلًا.'),
            false,
        );
    }

    /** وشاشةٌ فارغةٌ فعليًّا (بلا فلتر) تفضل تعرض رسالة البداية الأصليّة زيّ ما كانت. */
    public function test_an_actually_empty_screen_without_filters_keeps_the_original_start_message(): void
    {
        CertificateAccreditation::query()->delete();

        $response = $this->actingAs($this->admin())
            ->get(route('admin.certificates.index', ['tab' => 'accreditations']))
            ->assertOk();

        $response->assertSee(
            setting('admin.certificates.partials.accreditations.mfysh_aatmadat_msjla_asla', 'مفيش اعتمادات مسجّلة أصلًا.'),
            false,
        );
        $response->assertDontSee(
            setting('ux.empty_state.filtered_message', 'مفيش نتائج تطابق البحث/الفلتر الحاليّ — جرّب فلترًا تانيًا.'),
            false,
        );
    }

    /** ⭐ «الضغط ← الأنواع المفلترة» — العدّاد رابطٌ حقيقيّ لا نصٌّ ميّت */
    public function test_the_type_count_links_to_a_filtered_types_tab(): void
    {
        $accreditation = CertificateAccreditation::create(['name_ar' => 'جهة عندها نوعان', 'name_en' => 'Two Types Body', 'is_active' => true]);
        CertificateType::query()->where('key', 'course')->update(['accreditation_id' => $accreditation->id]);
        $otherType = CertificateType::query()->where('key', '!=', 'course')->first();

        $response = $this->actingAs($this->admin())
            ->get(route('admin.certificates.index', ['tab' => 'accreditations']))
            ->assertOk();

        $response->assertSee(route('admin.certificates.index', ['tab' => 'types', 'accreditation_id' => $accreditation->id]));

        $filtered = $this->actingAs($this->admin())
            ->get(route('admin.certificates.index', ['tab' => 'types', 'accreditation_id' => $accreditation->id]))
            ->assertOk();

        $filtered->assertSee('جهة عندها نوعان');

        if ($otherType && $otherType->accreditation_id !== $accreditation->id) {
            $filtered->assertDontSee($otherType->name_ar);
        }
    }

    /** ⭐ زرّ «تعديل» موجودٌ فعليًّا — كان بلا مسارٍ في الفيو رغم أنّ الكنترولر جاهز */
    public function test_editing_an_accreditation_actually_saves(): void
    {
        $accreditation = CertificateAccreditation::create(['name_ar' => 'قبل التعديل', 'name_en' => 'Before Edit', 'is_active' => true]);

        $this->actingAs($this->admin())->put(route('admin.certificates.accreditations.update', $accreditation), [
            'name_ar' => 'بعد التعديل',
            'name_en' => 'After Edit',
            'is_active' => '1',
        ])->assertRedirect();

        $this->assertSame('بعد التعديل', $accreditation->fresh()->name_ar);
    }

    public function test_the_quick_toggle_flips_active_status_without_touching_other_fields(): void
    {
        $accreditation = CertificateAccreditation::create(['name_ar' => 'قابلة للتبديل', 'name_en' => 'Toggleable', 'is_active' => true]);

        $this->actingAs($this->admin())->put(route('admin.certificates.accreditations.update', $accreditation), [
            'name_ar' => $accreditation->name_ar,
            'name_en' => $accreditation->name_en,
            'logo_path' => $accreditation->logo_path,
            'verify_note_ar' => $accreditation->verify_note_ar,
            'verify_note_en' => $accreditation->verify_note_en,
            'is_active' => '0',
        ])->assertRedirect();

        $accreditation->refresh();
        $this->assertFalse((bool) $accreditation->is_active);
        $this->assertSame('قابلة للتبديل', $accreditation->name_ar, 'التبديل السريع يجب ألّا يمسّ الاسم.');
    }
}
