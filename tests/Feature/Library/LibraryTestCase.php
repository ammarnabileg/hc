<?php

namespace Tests\Feature\Library;

use App\Models\Currency;
use App\Models\LibraryEntitlement;
use App\Models\Permission;
use App\Models\Product;
use App\Models\User;
use App\Models\WalletBalance;
use App\Support\Access\AccessEngine;
use Database\Seeders\CoreSeeder;
use Database\Seeders\LibraryDemoSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** أساسٌ مشترك لاختبارات المجال: صلاحيّات المتدرّب + إعدادات المجال جاهزة. */
abstract class LibraryTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CoreSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(SettingSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        $this->seed(LibraryDemoSeeder::class);
    }

    protected function trainee(string $code = 'UTEST001', string $name = 'أحمد سعيد'): User
    {
        $user = User::create([
            'name' => $name,
            'email' => mb_strtolower($code).'@test.local',
            'password' => 'secret-password',
            'code' => $code,
            'status' => 'active',
        ]);

        $user->assignRole('trainee');
        app(AccessEngine::class)->forget();

        return $user;
    }

    protected function grant(User $user, string $permissionKey, string $scope = 'ALL'): void
    {
        $permission = Permission::query()->where('key', $permissionKey)->firstOrFail();

        DB::table('permission_user')->insertOrIgnore([
            'permission_id' => $permission->id,
            'user_id' => $user->id,
            'membership_id' => null,
            'scope' => $scope,
            'effect' => 'allow',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(AccessEngine::class)->forget($user);
    }

    /** منتج PDF محميّ بملفّ حقيقيّ على القرص الخاصّ */
    protected function protectedProduct(int $teaserPages = 2): Product
    {
        Storage::disk('local')->put('library/test/kitab.pdf', $this->pdf(4));

        return Product::create([
            'slug' => 'kitab-test-'.uniqid(),
            'name_ar' => 'كتاب الاختبار',
            'type' => 'protected_pdf',
            'file_path' => 'library/test/kitab.pdf',
            'is_downloadable' => false,
            'teaser_pages' => $teaserPages,
            'status' => 'published',
        ]);
    }

    protected function entitle(User $user, Model $item): LibraryEntitlement
    {
        return LibraryEntitlement::create([
            'user_id' => $user->id,
            'itemable_type' => $item->getMorphClass(),
            'itemable_id' => $item->getKey(),
            'source' => 'purchase',
        ]);
    }

    protected function giveTickets(User $user, float $amount): void
    {
        WalletBalance::updateOrCreate(
            ['user_id' => $user->id, 'currency_id' => Currency::where('code', 'tickets')->value('id')],
            ['balance' => $amount],
        );
    }

    private function pdf(int $pages): string
    {
        $kids = [];

        for ($i = 0; $i < $pages; $i++) {
            $kids[] = (3 + $i).' 0 R';
        }

        $body = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            ."2 0 obj\n<< /Type /Pages /Kids [".implode(' ', $kids)."] /Count {$pages} >>\nendobj\n";

        for ($i = 0; $i < $pages; $i++) {
            $body .= (3 + $i)." 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] >>\nendobj\n";
        }

        return $body."trailer\n<< /Size ".($pages + 3)." /Root 1 0 R >>\n%%EOF";
    }
}
