<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * البيانات التجريبيّة لكلّ المجالات — تُشغَّل بعد `DatabaseSeeder` وحدها:
 *
 *   php artisan db:seed --class=DemoSeeder
 *
 * وهي منفصلة عن `DatabaseSeeder` عمدًا: الأخير يزرع **ما لا تقوم المنصّة بدونه**
 * (الصلاحيّات · الأدوار · العملات · البوزشنز · قيم Rep · الإعدادات)،
 * أمّا هذه فمحتوًى للعرض والتجربة لا يُرفَع على الإنتاج.
 *
 * الترتيب يتبع التبعيّة: المحتوى ⟵ المتجر ⟵ التطوّع ⟵ الإدارة.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $seeders = [
            // الطبقة الأساسيّة
            DashboardDemoSeeder::class,
            LearningDemoSeeder::class,
            ExamDemoSeeder::class,
            StoreDemoSeeder::class,
            WalletDemoSeeder::class,
            LibraryDemoSeeder::class,
            ChallengeDemoSeeder::class,
            EventDemoSeeder::class,
            AnnouncementDemoSeeder::class,
            AccountDemoSeeder::class,

            // طبقة التطوّع
            VolunteerCoreDemoSeeder::class,
            VolunteerGoalsDemoSeeder::class,
            VolunteerFlowDemoSeeder::class,
            VolunteerMeetingsDemoSeeder::class,
            VolunteerOrgDemoSeeder::class,
            VolunteerPeopleDemoSeeder::class,

            // الطبقة العامّة (تعمل للزائر)
            HomeDemoSeeder::class,
            SetupDemoSeeder::class,

            // لوحة الإدارة
            AdminCoreDemoSeeder::class,
            AdminOpsDemoSeeder::class,
            AdminContentDemoSeeder::class,
            AdminVolunteerDemoSeeder::class,
            AdminSystemDemoSeeder::class,
        ];

        foreach ($seeders as $seeder) {
            $this->command?->line("  <fg=gray>›</> {$seeder}");

            try {
                $this->call($seeder);
            } catch (\Throwable $e) {
                // بذرةٌ واحدة متعثّرة لا توقف الباقي — والسبب يُطبَع ليُصلَح
                $this->command?->warn('    تعثّرت: '.$e->getMessage());
            }
        }
    }
}
