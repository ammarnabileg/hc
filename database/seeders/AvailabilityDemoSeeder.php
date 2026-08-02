<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\Course;
use App\Models\CourseAvailabilityPeriod;
use App\Models\Setting;
use App\Services\Admin\Volunteer\SettingsCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * بذرة الإتاحة الزمنيّة (الدستور 5): إعدادات المجال + دول بتوقيتاتها
 * + مثال حيّ لتدريب «نادي الفجر» يفتح 5→7 ص بتوقيت كلّ متدرّب.
 *
 * لا تُسجَّل في DatabaseSeeder (دليل البناء 7).
 */
class AvailabilityDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->settings();
        $this->countries();
        $this->demoCourse();

        Cache::forget('settings');

        $this->command?->info('بذرة الإتاحة: '.CourseAvailabilityPeriod::count().' فترة إتاحة');
    }

    /** الافتراضيّات من الكتالوج نفسه — مرجع واحد لا اثنان (2.13) */
    public function settings(): void
    {
        foreach (SettingsCatalog::group('availability') as $key => [$group, $label, $type, $default]) {
            Setting::updateOrCreate(['key' => $key], [
                'group' => $group,
                'label_ar' => $label,
                'type' => $type,
                'default_value' => $default,
                'value' => $default,
            ]);
        }
    }

    /** دول بتوقيتات مختلفة — بها يُختبَر أنّ «5 ص» تعني خامسة كلّ مستخدم */
    private function countries(): void
    {
        $rows = [
            ['EG', 'مصر', 'Egypt', '+20', 'Africa/Cairo'],
            ['SA', 'السعوديّة', 'Saudi Arabia', '+966', 'Asia/Riyadh'],
            ['AE', 'الإمارات', 'United Arab Emirates', '+971', 'Asia/Dubai'],
            ['MA', 'المغرب', 'Morocco', '+212', 'Africa/Casablanca'],
        ];

        foreach ($rows as $index => [$iso2, $ar, $en, $phone, $timezone]) {
            Country::updateOrCreate(['iso2' => $iso2], [
                'name_ar' => $ar,
                'name_en' => $en,
                'phone_code' => $phone,
                'timezone' => $timezone,
                'sort_order' => $index + 1,
                'is_active' => true,
            ]);
        }
    }

    /** تدريب بنافذة يوميّة صباحيّة وفترتَي إتاحة — المثال الحرفيّ في الدستور 5 */
    private function demoCourse(): void
    {
        $course = Course::query()->where('slug', 'nadi-al-fajr')->first();

        if (! $course) {
            $course = Course::create([
                'slug' => 'nadi-al-fajr',
                'name_ar' => 'نادي الفجر — ابدأ يومك بدرس',
                'name_en' => 'Dawn Club',
                'description_ar' => 'تدريب قصير يفتح ساعتين كلّ صباح: تحضر بدري، تخلّص درس، وتمشي وقد كسبت يومك.',
                'is_free' => true,
                'status' => 'published',
                'published_at' => Carbon::now()->subMonth(),
                'forced_order' => true,
            ]);
        }

        // من 5:00 ص إلى 7:00 ص — بتوقيت كلّ متدرّب المحلّيّ لا بتوقيت الخادم
        $course->forceFill(['daily_open_at' => '05:00', 'daily_close_at' => '07:00'])->save();

        $periods = [
            [Carbon::now()->startOfYear()->addDays(0), Carbon::now()->startOfYear()->addDays(6)],
            [Carbon::now()->startOfMonth(), Carbon::now()->startOfMonth()->addDays(6)],
            [Carbon::now()->addMonths(2)->startOfMonth(), Carbon::now()->addMonths(2)->startOfMonth()->addDays(6)],
        ];

        foreach ($periods as [$from, $to]) {
            CourseAvailabilityPeriod::updateOrCreate([
                'course_id' => $course->id,
                'starts_on' => $from->toDateString(),
            ], [
                'ends_on' => $to->toDateString(),
                'is_active' => true,
            ]);
        }
    }
}
