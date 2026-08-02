<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            CoreSeeder::class,
            PermissionSeeder::class,
            RoleSeeder::class,
            RolePermissionSeeder::class,
            SettingSeeder::class,
            // ⭐ تعريفات إعدادات كلّ المجالات — بدونها يفتح المالك اللوحة فيجد
            // عشرات الحقول بينما الكود يقرأ ألوفًا، فيأخذ الافتراضيّ المحروق (2.13)
            SettingDefinitionsSeeder::class,
        ]);
    }
}
