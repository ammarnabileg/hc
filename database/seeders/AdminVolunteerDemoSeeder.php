<?php

namespace Database\Seeders;

use App\Models\Badge;
use App\Models\Challenge;
use App\Models\Entity;
use App\Models\Event;
use App\Models\Membership;
use App\Models\Position;
use App\Models\Setting;
use App\Models\Track;
use App\Models\User;
use App\Services\Admin\Volunteer\SettingsCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

/**
 * بيانات تجريبيّة لمجال «إدارة التطوّع والتلعيب والمكافآت والفعاليّات»
 * — ولا تُسجَّل في `DatabaseSeeder` (دليل البناء 7).
 *
 * ويحمل معه **كلّ إعدادات المجال** من الكتالوج (2.13) فلا رقم ولا نصّ محروق.
 */
class AdminVolunteerDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->settings();
        $this->entities();
        $this->badges();
        $this->challenges();
        $this->events();
    }

    /** كلّ مفاتيح الكتالوج تُزرَع بقيمها الافتراضيّة المعتمَدة */
    public function settings(): void
    {
        foreach (SettingsCatalog::all() as $key => [$group, $label, $type, $default]) {
            /*
             | بعض المفاتيح تشترك فيها مجالات أخرى (الاحتفالات مثلًا — مصدر واحد
             | لا نسختان)، فلا نلمس صفًّا قائمًا إلّا لملء قيمة فارغة — وإلّا
             | داس سيدر مجالٍ على إعدادات مجالٍ آخر.
             */
            $existing = Setting::query()->where('key', $key)->first();

            if ($existing) {
                if ($existing->value === null) {
                    $existing->forceFill(['value' => $default])->save();
                }

                continue;
            }

            Setting::create([
                'key' => $key,
                'group' => $group,
                'label_ar' => $label,
                'type' => $type,
                'default_value' => $default,
                'value' => $default,
            ]);
        }

        // محتوى صفحة التطوّع التعريفيّة — يُدار بالكامل من لوحة الإدارة (13.4-أ)
        Setting::where('key', 'volunteer_page.blocks')->update([
            'value' => json_encode([
                ['type' => 'impact', 'title' => 'أثرك بيتقاس', 'body' => 'كلّ ساعة بتديها بتوصل لمتدرّب محتاجها — وبنقيس الأثر ده ونعرضه عليك.'],
                ['type' => 'faq', 'title' => 'محتاج أدّي وقت قدّ إيه؟', 'body' => 'الالتزام مرن — بس بنطلب صدق في المواعيد اللي تختارها بنفسك.'],
                ['type' => 'faq', 'title' => 'هل التطوّع بيدّي شهادة؟', 'body' => 'أيوه، وشهادات التطوّع كلّها مجّانيّة بالكامل، وبتفضل سارية بعد ما تخرج.'],
                ['type' => 'story', 'title' => 'قصّة مريم', 'body' => 'بدأت كوردنيتور وبعد سنة بقت تيم ليدر لفريق من خمسة — والرحلة كلّها موثّقة في بروفايلها.'],
            ], JSON_UNESCAPED_UNICODE),
        ]);

        Cache::forget('settings');
    }

    /** شجرة كيانات واقعيّة بعضويّات نشطة — مادّة لمؤشّرات السعة */
    private function entities(): void
    {
        $track = Track::query()->where('key', 'department')->first();

        if (! $track) {
            return;
        }

        $root = Entity::updateOrCreate(
            ['track_id' => $track->id, 'name_ar' => 'قسم المحتوى'],
            ['status' => 'active', 'opened_at' => now()->subMonths(8)],
        );

        foreach (['فريق الكتابة', 'فريق المونتاج', 'فريق التدقيق'] as $name) {
            Entity::updateOrCreate(
                ['track_id' => $track->id, 'name_ar' => $name],
                ['parent_id' => $root->id, 'status' => 'active', 'opened_at' => now()->subMonths(6)],
            );
        }

        $coordinator = Position::query()->where('key', 'coordinator')->first();
        $teamLeader = Position::query()->where('key', 'team_leader')->first();
        $writing = Entity::query()->where('name_ar', 'فريق الكتابة')->first();

        if (! $coordinator || ! $teamLeader || ! $writing) {
            return;
        }

        $lead = $this->user('سلمى عبد الرحمن', 'VOL001');

        $leadMembership = Membership::firstOrCreate(
            ['user_id' => $lead->id, 'entity_id' => $writing->id, 'position_id' => $teamLeader->id],
            ['started_at' => now()->subDays(120), 'status' => 'active', 'is_primary' => true],
        );

        foreach ([['أحمد فتحي', 'VOL002'], ['نور الهدى محمود', 'VOL003'], ['كريم السيّد', 'VOL004']] as [$name, $code]) {
            $member = $this->user($name, $code);

            Membership::firstOrCreate(
                ['user_id' => $member->id, 'entity_id' => $writing->id, 'position_id' => $coordinator->id],
                [
                    'started_at' => now()->subDays(60),
                    'status' => 'active',
                    'upline_id' => $leadMembership->id,
                    'is_primary' => true,
                ],
            );
        }
    }

    /** شارات بشرط فتح مكتوب صراحةً — لا ألغاز (7.4) */
    private function badges(): void
    {
        $rows = [
            ['first_lesson', 'أوّل خطوة', 'أكمل أوّل درس في أيّ تدريب.', 'lesson.completed', 1],
            ['streak_7', 'أسبوع بلا انقطاع', 'حافظ على ستريك 7 أيّام متواصلة.', 'streak.days', 7],
            ['club_5am', 'صاحب الفجر', 'سجّل حضورك في نادي الخامسة 10 مرّات.', 'five_am.count', 10],
            ['volunteer_first', 'أوّل تسكين', 'ابدأ أوّل بوزشن تطوّعيّ لك.', 'membership.count', 1],
        ];

        foreach ($rows as [$key, $name, $condition, $conditionKey, $value]) {
            Badge::updateOrCreate(['key' => $key], [
                'name_ar' => $name,
                'condition_text_ar' => $condition,
                'condition_key' => $conditionKey,
                'condition_value' => $value,
                'is_active' => true,
            ]);
        }
    }

    /** حروب بأيقونة ولون — والتفاصيل تُقرَأ من العامّ ما لم تُعمَل Override */
    private function challenges(): void
    {
        $rows = [
            ['knowledge_war', 'حرب المعلومات', '#00d4b8', 15],
            ['focus_war', 'حرب التركيز', '#7cf7e6', 25],
            ['survival_war', 'حرب البقاء', '#eab308', 10],
            ['estimation_war', 'حرب التقدير', '#22c55e', 10],
        ];

        foreach ($rows as [$key, $name, $color, $minutes]) {
            Challenge::updateOrCreate(['key' => $key], [
                'name_ar' => $name,
                'color' => $color,
                'duration_minutes' => $minutes,
                'is_active' => true,
                'settings_locked' => false,
            ]);
        }

        // حرب التقدير: أسئلة رقميّة فقط — استثناء منصوص (12.10-ب)
        Challenge::where('key', 'estimation_war')->update([
            'question_source' => json_encode(['numeric_only' => 1], JSON_UNESCAPED_UNICODE),
        ]);
    }

    private function events(): void
    {
        Event::updateOrCreate(['slug' => 'volunteer-open-day'], [
            'title_ar' => 'اليوم المفتوح للمتطوّعين',
            'title_en' => 'Volunteer Open Day',
            'description' => 'لقاء مباشر نتعرّف فيه على الفرق ونجاوب على كلّ الأسئلة.',
            'description_en' => 'A live meetup to introduce the teams and answer questions.',
            'mode' => 'hybrid',
            'starts_at' => now()->addDays(10)->setTime(19, 0),
            'ends_at' => now()->addDays(10)->setTime(21, 0),
            'location' => 'القاهرة — مقرّ المنصّة',
            'join_link' => 'https://meet.example.com/open-day',
            'capacity' => 120,
            'attendance_code' => '482913',
            'status' => 'published',
            'xp_reward' => 200,
            'ticket_reward' => 1,
            'reward_tiers' => json_encode([
                ['hours' => 24, 'xp' => 200, 'tickets' => 1],
                ['hours' => 72, 'xp' => 100, 'tickets' => 0],
            ], JSON_UNESCAPED_UNICODE),
        ]);

        Event::updateOrCreate(['slug' => 'content-workshop'], [
            'title_ar' => 'ورشة كتابة المحتوى التعليميّ',
            'title_en' => 'Educational Content Workshop',
            'description' => 'ورشة عمليّة لفريق المحتوى — بأمثلة وتمارين.',
            'mode' => 'online',
            'starts_at' => now()->addDays(3)->setTime(20, 0),
            'join_link' => 'https://meet.example.com/workshop',
            'capacity' => 40,
            'attendance_code' => '307455',
            'status' => 'published',
        ]);
    }

    private function user(string $name, string $code): User
    {
        return User::firstOrCreate(
            ['code' => $code],
            [
                'name' => $name,
                'email' => mb_strtolower($code).'@demo.local',
                'password' => 'demo-password',
                'status' => 'active',
            ],
        );
    }
}
