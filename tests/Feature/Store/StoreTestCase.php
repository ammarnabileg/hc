<?php

namespace Tests\Feature\Store;

use App\Models\Bundle;
use App\Models\BundleItem;
use App\Models\Course;
use App\Models\Currency;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Models\WalletBalance;
use Database\Seeders\CoreSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\StoreDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/** أساس اختبارات المتجر: العملات والصلاحيّات والإعدادات جاهزة قبل كلّ اختبار. */
abstract class StoreTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CoreSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        $this->seed(SettingSeeder::class);

        // إعدادات المتجر وحدها — والبيانات التجريبيّة يبنيها كلّ اختبار بنفسه
        (new StoreDemoSeeder)->settings();
    }

    /** تغيير إعداد أثناء الاختبار — مع تفريغ كاش الإعدادات */
    protected function setting(string $key, string $value): void
    {
        Setting::updateOrCreate(['key' => $key], [
            'group' => 'store',
            'label_ar' => $key,
            'type' => 'string',
            'value' => $value,
        ]);

        Cache::forget('settings');
    }

    protected function trainee(float $coins = 0): User
    {
        $user = User::create([
            'name' => 'أحمد سيّد',
            'email' => 'trainee'.uniqid().'@test.local',
            'password' => 'secret-password',
            'code' => 'U'.strtoupper(substr(uniqid(), -7)),
            'status' => 'active',
        ]);

        $user->assignRole('trainee');
        $this->credit($user, $coins);

        return $user;
    }

    protected function credit(User $user, float $coins): void
    {
        WalletBalance::updateOrCreate(
            ['user_id' => $user->id, 'currency_id' => $this->coins()->id],
            ['balance' => $coins],
        );
    }

    protected function coins(): Currency
    {
        return Currency::where('code', 'coins')->firstOrFail();
    }

    protected function balanceOf(User $user): float
    {
        return (float) WalletBalance::where('user_id', $user->id)
            ->where('currency_id', $this->coins()->id)
            ->value('balance');
    }

    protected function product(array $overrides = []): Product
    {
        return Product::create([
            'slug' => 'dalil-mokabalat',
            'name_ar' => 'دليل أسئلة المقابلات',
            'description' => 'أشهر الأسئلة وطريقة الإجابة عليها.',
            'type' => 'digital',
            'teaser_pages' => 3,
            'price_coins' => 100,
            'status' => 'published',
            ...$overrides,
        ]);
    }

    protected function course(array $overrides = []): Course
    {
        return Course::create([
            'slug' => 'excel-lel-shoghl',
            'name_ar' => 'إكسل للشغل',
            'description_ar' => 'تدريب عمليّ على الجداول والمعادلات.',
            'price_coins' => 400,
            'deadline_days' => 30,
            'free_preview_lessons' => 2,
            'status' => 'published',
            'published_at' => now(),
            ...$overrides,
        ]);
    }

    protected function bundle(array $items, array $overrides = []): Bundle
    {
        $bundle = Bundle::create([
            'slug' => 'baqet-elwazifa',
            'name_ar' => 'باقة الاستعداد للوظيفة',
            'description' => 'تدريب ومنتج في عرض واحد.',
            'price_coins' => 420,
            'original_value' => collect($items)->sum(fn ($item) => (float) $item->price_coins),
            'status' => 'published',
            ...$overrides,
        ]);

        foreach ($items as $index => $item) {
            BundleItem::create([
                'bundle_id' => $bundle->id,
                'itemable_type' => $item::class,
                'itemable_id' => $item->id,
                'sort_order' => $index,
            ]);
        }

        return $bundle;
    }
}
