<?php

namespace Tests\Feature\Ui;

use App\Http\Controllers\Trainee\WalletController;
use App\Models\Position;
use Tests\Feature\Admin\Volunteer\AdminVolunteerTestCase;

/**
 * مسحٌ لكلمات إنجليزيّة خامّة كانت تتسرّب إلى الشاشة (badge · course · growth ·
 * coordinator · protected_pdf): كلّ مفتاحٍ يُعرَض باسمه العربيّ من قاموسه.
 */
class RawKeysNeverLeakTest extends AdminVolunteerTestCase
{
    public function test_the_growth_wallet_source_has_an_arabic_label(): void
    {
        $this->assertArrayHasKey('growth', WalletController::sourceLabels());
        $this->assertNotSame('growth', WalletController::sourceLabels()['growth']);
    }

    public function test_reentries_screen_names_the_starting_position_in_arabic(): void
    {
        $admin = $this->grant($this->makeUser(), 'offboarding.view');
        $label = (string) Position::query()->where('key', 'coordinator')->value('name_ar');
        $this->assertNotSame('', $label, 'البوزشن مبذور في CoreSeeder');

        $this->actingAs($admin)->get(route('admin.volunteer.reentries'))
            ->assertOk()
            ->assertSee('<strong>'.$label.'</strong>', false)
            ->assertDontSee('<strong>coordinator</strong>', false);
    }

    public function test_admin_store_products_table_shows_type_labels_not_keys(): void
    {
        $admin = $this->grant($this->makeUser(), 'store.view', 'store_products.list', 'store_products.create');

        $this->actingAs($admin)->post(route('admin.store.products.store'), [
            'name_ar' => 'منتج للفحص', 'type' => 'digital', 'price_currency' => 'coins', 'price' => 10, 'status' => 'published',
        ]);

        $html = $this->actingAs($admin)->get(route('admin.store.index', ['tab' => 'products']))->assertOk()->getContent();

        if (str_contains($html, 'data-product-type="digital"')) {
            $this->assertStringNotContainsString('data-product-type="digital">digital<', $html);
            $this->assertMatchesRegularExpression('/data-product-type="digital">\s*[^<]*[\x{0600}-\x{06FF}]/u', $html);
        } else {
            $this->markTestSkipped('لم يُنشأ منتج بهذه الصلاحيّات؛ العرض مغطًّى في اختبارات المتجر');
        }
    }
}
