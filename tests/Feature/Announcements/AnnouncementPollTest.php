<?php

namespace Tests\Feature\Announcements;

use App\Models\Announcement;
use App\Models\AnnouncementPollVote;
use App\Models\User;
use Database\Seeders\AnnouncementDemoSeeder;
use Database\Seeders\CoreSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * استطلاع داخل المنشور (12.6-أ) — بنوعيه: **عامّ النتيجة** و**مخفيّ النتيجة**.
 *
 * ⛔ الاختبار الحاكم هنا يقابل قاعدة «ممنوع Dark Patterns» (2.9): المخفيّ يبقى
 * مخفيًّا **فعلًا** — لا رقم يصل المتصفّح قبل الإغلاق، لا في الـHTML ولا في
 * الـJSON. ولو صار الإخفاء بـCSS أو بـ`data-` سقط الاختبار فورًا.
 */
class AnnouncementPollTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CoreSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(AnnouncementDemoSeeder::class);
    }

    private function makeUser(string $name = 'متدرّب استطلاع'): User
    {
        return User::create([
            'name' => $name,
            'email' => str()->random(8).'@test.local',
            'password' => 'secret-password',
            'code' => str()->upper(str()->random(6)),
            'status' => 'active',
        ]);
    }

    private function poll(array $attributes = []): Announcement
    {
        return Announcement::create($attributes + [
            'title' => 'رأيك يهمّنا',
            'body' => 'اختر ميعاد اللقاء المباشر.',
            'status' => 'published',
            'audience' => ['type' => 'all'],
            'poll_question' => 'أنسب ميعاد للقاء؟',
            'poll_options' => ['بعد المغرب', 'بعد العشاء'],
            'poll_results_public' => false,
        ]);
    }

    /** أصوات بعدد مميَّز حتّى لو ظهر الرقم عرضًا في الصفحة انكشف الأمر. */
    private function stuffVotes(Announcement $announcement, int $first, int $second): void
    {
        foreach ([0 => $first, 1 => $second] as $option => $count) {
            for ($i = 0; $i < $count; $i++) {
                AnnouncementPollVote::create([
                    'announcement_id' => $announcement->id,
                    'user_id' => $this->makeUser('مصوّت '.$option.'-'.$i)->id,
                    'option_index' => $option,
                ]);
            }
        }
    }

    /**
     * ⭐ القاعدة الحاكمة: **النتيجة المخفيّة لا تصل المتصفّح إطلاقًا** قبل الإغلاق.
     * فلو سُرّبت في بيانات العرض أو في نصّ الصفحة أو في ردّ التصويت — يسقط الاختبار.
     */
    public function test_hidden_poll_results_never_reach_the_browser(): void
    {
        $announcement = $this->poll(['poll_results_public' => false]);
        $this->stuffVotes($announcement, 7, 3);

        $viewer = $this->makeUser('قارئ');

        $response = $this->actingAs($viewer)->get(route('announcements.index'))->assertOk();

        // 1) بيانات العرض نفسها لا تحمل أعدادًا — لا حسابَ أصلًا لا إخفاءَ بعد حساب
        $polls = $response->viewData('polls');
        $this->assertNotNull($polls[$announcement->id] ?? null, 'الاستطلاع لازم يظهر للمستهدَف.');
        $this->assertNull($polls[$announcement->id]['results'], 'النتيجة المخفيّة اتحسبت واتبعتت للعرض.');

        // 2) ولا في الـHTML: لا إجماليّ ولا عدد أيّ خيار
        $response->assertDontSee('إجماليّ الأصوات');
        $response->assertSee('النتيجة مخفيّة');

        // 3) ولا في ردّ التصويت (JSON) — أشهر باب تسريب
        $vote = $this->actingAs($viewer)
            ->postJson(route('announcements.poll', $announcement), ['option_index' => 1])
            ->assertOk();

        $vote->assertJsonMissingPath('results');
        $this->assertSame(1, $vote->json('choice'));
    }

    /** «عامّ النتيجة»: الأرقام تصل فعلًا — وإلّا كان النوعان نوعًا واحدًا. */
    public function test_public_poll_results_reach_the_reader(): void
    {
        $announcement = $this->poll(['poll_results_public' => true]);
        $this->stuffVotes($announcement, 7, 3);

        $viewer = $this->makeUser('قارئ عامّ');

        $response = $this->actingAs($viewer)->get(route('announcements.index'))->assertOk();

        $polls = $response->viewData('polls');
        $this->assertSame([7, 3], $polls[$announcement->id]['results']['counts']);
        $this->assertSame(10, $polls[$announcement->id]['results']['total']);
        $response->assertSee('إجماليّ الأصوات');
    }

    /** الوعد يُوفى: بعد الإغلاق تنكشف نتيجة الاستطلاع المخفيّ. */
    public function test_hidden_results_are_revealed_after_the_poll_closes(): void
    {
        $announcement = $this->poll([
            'poll_results_public' => false,
            'poll_closes_at' => now()->subHour(),
        ]);
        $this->stuffVotes($announcement, 2, 5);

        $response = $this->actingAs($this->makeUser('قارئ بعد الإغلاق'))
            ->get(route('announcements.index'))->assertOk();

        $this->assertSame([2, 5], $response->viewData('polls')[$announcement->id]['results']['counts']);
    }

    /** صوتٌ واحد لكلّ مستخدم — والتبديل تعديلٌ لا صفٌّ ثانٍ. */
    public function test_vote_is_single_per_user_and_switchable_before_closing(): void
    {
        $announcement = $this->poll(['poll_results_public' => true]);
        $voter = $this->makeUser('مصوّت متردّد');

        $this->actingAs($voter)->post(route('announcements.poll', $announcement), ['option_index' => 0]);
        $this->actingAs($voter)->post(route('announcements.poll', $announcement), ['option_index' => 1]);

        $this->assertSame(1, AnnouncementPollVote::query()->where('announcement_id', $announcement->id)->count());
        $this->assertDatabaseHas('announcement_poll_votes', [
            'announcement_id' => $announcement->id,
            'user_id' => $voter->id,
            'option_index' => 1,
        ]);
    }

    /** بعد الإغلاق لا يُقبَل صوت جديد — ولا يُكسَر الطلب في وجه المستخدم. */
    public function test_closed_poll_refuses_new_votes(): void
    {
        $announcement = $this->poll(['poll_closes_at' => now()->subMinute()]);
        $voter = $this->makeUser('متأخّر');

        $this->actingAs($voter)->post(route('announcements.poll', $announcement), ['option_index' => 0]);

        $this->assertDatabaseCount('announcement_poll_votes', 0);
    }

    /** خيارٌ غير موجود يُرفَض — فلا يدخل رقمٌ عشوائيّ في الأرقام. */
    public function test_out_of_range_option_is_rejected(): void
    {
        $announcement = $this->poll();

        $this->actingAs($this->makeUser('مجرّب'))
            ->post(route('announcements.poll', $announcement), ['option_index' => 9])
            ->assertSessionHasErrors('option_index');

        $this->assertDatabaseCount('announcement_poll_votes', 0);
    }

    /** منشورٌ لا استطلاع فيه: المسار يردّ 404 لا صفحةً بيضاء. */
    public function test_voting_on_a_pollless_announcement_is_not_found(): void
    {
        $announcement = Announcement::create([
            'title' => 'منشور بلا استطلاع',
            'status' => 'published',
            'audience' => ['type' => 'all'],
        ]);

        $this->actingAs($this->makeUser('فضوليّ'))
            ->post(route('announcements.poll', $announcement), ['option_index' => 0])
            ->assertNotFound();
    }
}
