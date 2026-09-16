<?php

namespace Tests\Feature\Announcements;

use App\Models\Announcement;
use App\Models\AnnouncementRead;
use App\Models\User;
use Database\Seeders\AnnouncementDemoSeeder;
use Database\Seeders\CoreSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * عقد واجهة التفاعل على طراز فيسبوك (13.2): زرّ رئيسيّ واحد + منتقي إيموجي + ملخّص.
 *
 * كلّ اختبار هنا يثبّت جزءًا من الـmarkup الذي تعتمد عليه الواجهة الأماميّة،
 * فلا تنكسر بصمت لو تغيّرت البطاقة.
 */
class AnnouncementReactionsUiTest extends TestCase
{
    use RefreshDatabase;

    /** الإيموجي الافتراضيّ المسموح به (2.13) */
    private const DEFAULT_REACTIONS = ['👍', '❤️', '🎉', '👏', '🙏'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CoreSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(AnnouncementDemoSeeder::class);

        // نبدأ من فيد فارغ حتّى لا تزاحم منشورات العرض التجريبيّ بطاقاتنا
        Announcement::query()->delete();
    }

    // ------------------------------------------------------------------ مساعدات

    private function makeUser(): User
    {
        return User::create([
            'name' => 'متدرّب اختبار',
            'email' => str()->random(8).'@test.local',
            'password' => 'secret-password',
            'code' => str()->upper(str()->random(6)),
            'status' => 'active',
        ]);
    }

    private function makeAnnouncement(array $attributes = []): Announcement
    {
        return Announcement::create($attributes + [
            'title' => 'عنوان '.str()->random(5),
            'body' => 'نصّ المنشور',
            'status' => 'published',
            'audience' => ['type' => 'all'],
        ]);
    }

    /** يسجّل تفاعل مستخدم آخر على المنشور مباشرةً في القاعدة. */
    private function reactAs(User $user, Announcement $announcement, string $emoji): void
    {
        AnnouncementRead::create([
            'announcement_id' => $announcement->id,
            'user_id' => $user->id,
            'reaction' => $emoji,
            'read_at' => now(),
        ]);
    }

    /** يجلب صفحة الفيد للمستخدم ويرجع الـHTML الكامل. */
    private function feedHtml(User $user): string
    {
        $response = $this->actingAs($user)->get(route('announcements.index'));
        $response->assertOk();

        return $response->getContent();
    }

    /** يقتطع بطاقة منشور بعينه من الصفحة (من <article> إلى </article>). */
    private function cardHtml(string $html, Announcement $announcement): string
    {
        $pattern = '/<article\b[^>]*data-announcement="'.$announcement->id.'"[^>]*>.*?<\/article>/su';

        $this->assertMatchesRegularExpression($pattern, $html, 'بطاقة المنشور لازم تظهر في الفيد');

        preg_match($pattern, $html, $matches);

        return $matches[0];
    }

    /** يرجع وسم HTML الأوّل (من < إلى >) الذي يحمل الـdata-attribute المطلوب. */
    private function tagWith(string $html, string $dataAttribute): string
    {
        $pattern = '/<[a-z]+\b[^>]*\b'.preg_quote($dataAttribute, '/').'(?=[\s=>])[^>]*>/iu';

        $this->assertMatchesRegularExpression($pattern, $html, "لازم يكون فيه عنصر يحمل {$dataAttribute}");

        preg_match($pattern, $html, $matches);

        return $matches[0];
    }

    /** يرجع كلّ الوسوم التي تحمل الـdata-attribute المطلوب. */
    private function tagsWith(string $html, string $dataAttribute): array
    {
        preg_match_all('/<[a-z]+\b[^>]*\b'.preg_quote($dataAttribute, '/').'(?=[\s=>])[^>]*>/iu', $html, $matches);

        return $matches[0];
    }

    /** يقرأ قيمة attribute من وسم واحد (بدون افتراض ترتيب الخصائص). */
    private function attr(string $tag, string $name): ?string
    {
        if (! preg_match('/\b'.preg_quote($name, '/').'="([^"]*)"/u', $tag, $matches)) {
            return null;
        }

        return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /** نصّ العنصر الذي يحمل الـdata-attribute المطلوب، بعد نزع الوسوم الداخليّة. */
    private function textOf(string $html, string $dataAttribute): string
    {
        // ⚠️ السمة لمّا تكون آخر حاجة في الوسم: لو الـRegex «أكل» علامة `>` بتاعة
        // الوسم نفسه هيكمّل لحدّ أوّل `</span>` بعده ويرجّع نصًّا غلط، فالفحص بـLookahead لا يستهلك.
        $pattern = '/<([a-z]+)\b[^>]*\b'.preg_quote($dataAttribute, '/').'(?=[\s=>])[^>]*>(.*?)<\/\1>/isu';

        $this->assertMatchesRegularExpression($pattern, $html, "لازم يكون فيه عنصر يحمل {$dataAttribute}");

        preg_match($pattern, $html, $matches);

        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($matches[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    /** الفورم الوحيد للتفاعل داخل البطاقة. */
    private function reactForm(string $card): string
    {
        return $this->tagWith($card, 'data-react-form');
    }

    // ------------------------------------------------------------------ الاختبارات

    /** (1) الغلاف data-reactions + فورم واحد data-react-form يوجّه لمسار التفاعل. */
    #[Test]
    public function card_has_reactions_wrapper_and_single_react_form(): void
    {
        $user = $this->makeUser();
        $announcement = $this->makeAnnouncement(['reactions_enabled' => true]);

        $card = $this->cardHtml($this->feedHtml($user), $announcement);

        $wrapper = $this->tagWith($card, 'data-reactions');
        $this->assertSame('', $this->attr($wrapper, 'data-mine'), 'data-mine لازم تكون فاضية لمّا المستخدم ما تفاعلش');

        $forms = $this->tagsWith($card, 'data-react-form');
        $this->assertCount(1, $forms, 'لازم يكون فيه فورم تفاعل واحد بس في البطاقة');

        $this->assertSame(
            route('announcements.react', $announcement),
            $this->attr($forms[0], 'action'),
            'فورم التفاعل لازم يوجّه لمسار announcements.react',
        );
        $this->assertSame('post', strtolower((string) $this->attr($forms[0], 'method')), 'فورم التفاعل لازم يكون POST');
    }

    /** (1) data-mine تحمل إيموجي المستخدم لمّا يكون متفاعلًا. */
    #[Test]
    public function wrapper_carries_users_current_reaction_in_data_mine(): void
    {
        $user = $this->makeUser();
        $announcement = $this->makeAnnouncement(['reactions_enabled' => true]);
        $this->reactAs($user, $announcement, '🎉');

        $card = $this->cardHtml($this->feedHtml($user), $announcement);

        $this->assertSame('🎉', $this->attr($this->tagWith($card, 'data-reactions'), 'data-mine'), 'data-mine لازم تساوي إيموجي المستخدم');
    }

    /** (2) الزرّ الرئيسيّ بلا تفاعل: submit باسم reaction وقيمته أوّل إيموجي مسموح، وغير مفعّل. */
    #[Test]
    public function main_button_defaults_to_first_allowed_emoji_when_user_has_not_reacted(): void
    {
        $user = $this->makeUser();
        $announcement = $this->makeAnnouncement(['reactions_enabled' => true]);

        $card = $this->cardHtml($this->feedHtml($user), $announcement);

        $mains = $this->tagsWith($card, 'data-react-main');
        $this->assertCount(1, $mains, 'لازم يكون فيه زرّ رئيسيّ واحد بس');

        $main = $mains[0];
        $this->assertStringStartsWith('<button', strtolower($main), 'الزرّ الرئيسيّ لازم يكون <button>');
        $this->assertSame('submit', $this->attr($main, 'type'), 'الزرّ الرئيسيّ لازم يكون type=submit');
        $this->assertSame('reaction', $this->attr($main, 'name'), 'الزرّ الرئيسيّ لازم يكون اسمه reaction');
        $this->assertSame('👍', $this->attr($main, 'value'), 'قيمة الزرّ الرئيسيّ لازم تكون أوّل إيموجي مسموح (👍)');
        $this->assertSame('0', $this->attr($main, 'data-active'), 'data-active لازم تكون 0 لمّا المستخدم ما تفاعلش');
        $this->assertSame('false', $this->attr($main, 'aria-pressed'), 'aria-pressed لازم تكون false لمّا المستخدم ما تفاعلش');
    }

    /** (2) الزرّ الرئيسيّ بعد التفاعل: قيمته إيموجي المستخدم ومفعّل. */
    #[Test]
    public function main_button_reflects_users_current_reaction_when_reacted(): void
    {
        $user = $this->makeUser();
        $announcement = $this->makeAnnouncement(['reactions_enabled' => true]);
        $this->reactAs($user, $announcement, '❤️');

        $card = $this->cardHtml($this->feedHtml($user), $announcement);
        $main = $this->tagWith($card, 'data-react-main');

        $this->assertSame('submit', $this->attr($main, 'type'), 'الزرّ الرئيسيّ لازم يكون type=submit');
        $this->assertSame('reaction', $this->attr($main, 'name'), 'الزرّ الرئيسيّ لازم يكون اسمه reaction');
        $this->assertSame('❤️', $this->attr($main, 'value'), 'قيمة الزرّ الرئيسيّ لازم تكون إيموجي المستخدم الحاليّ');
        $this->assertSame('1', $this->attr($main, 'data-active'), 'data-active لازم تكون 1 لمّا المستخدم متفاعل');
        $this->assertSame('true', $this->attr($main, 'aria-pressed'), 'aria-pressed لازم تكون true لمّا المستخدم متفاعل');
    }

    /** (3) المنتقي: زرّ submit لكلّ إيموجي مسموح باسم reaction وقيمته الإيموجي. */
    #[Test]
    public function picker_lists_one_submit_button_per_allowed_emoji(): void
    {
        $user = $this->makeUser();
        $announcement = $this->makeAnnouncement(['reactions_enabled' => true]);

        $card = $this->cardHtml($this->feedHtml($user), $announcement);

        $this->tagWith($card, 'data-react-picker');

        $picks = $this->tagsWith($card, 'data-react-pick');
        $this->assertCount(count(self::DEFAULT_REACTIONS), $picks, 'المنتقي لازم يحتوي زرًّا لكلّ إيموجي مسموح');

        $values = [];
        foreach ($picks as $pick) {
            $this->assertStringStartsWith('<button', strtolower($pick), 'كلّ عنصر في المنتقي لازم يكون <button>');
            $this->assertSame('submit', $this->attr($pick, 'type'), 'زرّ المنتقي لازم يكون type=submit');
            $this->assertSame('reaction', $this->attr($pick, 'name'), 'زرّ المنتقي لازم يكون اسمه reaction');
            $values[] = $this->attr($pick, 'value');
        }

        $this->assertSame(self::DEFAULT_REACTIONS, $values, 'قيم أزرار المنتقي لازم تطابق الإيموجي المسموح بالترتيب');
    }

    /** (4) الملخّص لا يُرسَم إطلاقًا لو ما حدّش تفاعل. */
    #[Test]
    public function summary_is_absent_when_nobody_reacted(): void
    {
        $user = $this->makeUser();
        $announcement = $this->makeAnnouncement(['reactions_enabled' => true]);

        $card = $this->cardHtml($this->feedHtml($user), $announcement);

        $this->assertStringNotContainsString('data-reactions-summary', $card, 'الملخّص ما يتعرضش لو ما فيش تفاعلات');
        $this->assertStringNotContainsString('data-reactions-count', $card, 'عدّاد التفاعلات ما يتعرضش لو ما فيش تفاعلات');
    }

    /** (4) المستخدم تفاعل لوحده: النصّ «إنت». */
    #[Test]
    public function summary_says_you_when_user_reacted_alone(): void
    {
        $user = $this->makeUser();
        $announcement = $this->makeAnnouncement(['reactions_enabled' => true]);
        $this->reactAs($user, $announcement, '👍');

        $card = $this->cardHtml($this->feedHtml($user), $announcement);

        $this->tagWith($card, 'data-reactions-summary');
        $this->assertSame('إنت', $this->textOf($card, 'data-reactions-count'), 'لمّا المستخدم يتفاعل لوحده النصّ لازم يكون «إنت»');
    }

    /** (4) المستخدم تفاعل ومعه آخرون: «إنت و:n كمان». */
    #[Test]
    public function summary_says_you_and_n_others_when_user_reacted_with_others(): void
    {
        $user = $this->makeUser();
        $announcement = $this->makeAnnouncement(['reactions_enabled' => true]);

        $this->reactAs($user, $announcement, '👍');
        $this->reactAs($this->makeUser(), $announcement, '❤️');
        $this->reactAs($this->makeUser(), $announcement, '👍');

        $card = $this->cardHtml($this->feedHtml($user), $announcement);

        $this->tagWith($card, 'data-reactions-summary');
        $this->assertSame('إنت و2 كمان', $this->textOf($card, 'data-reactions-count'), 'لمّا المستخدم يتفاعل ومعه اثنان النصّ لازم يكون «إنت و2 كمان»');
    }

    /** (4) المستخدم لم يتفاعل: الرقم فقط. */
    #[Test]
    public function summary_shows_plain_number_when_user_did_not_react(): void
    {
        $user = $this->makeUser();
        $announcement = $this->makeAnnouncement(['reactions_enabled' => true]);

        $this->reactAs($this->makeUser(), $announcement, '🎉');
        $this->reactAs($this->makeUser(), $announcement, '🎉');
        $this->reactAs($this->makeUser(), $announcement, '👏');

        $card = $this->cardHtml($this->feedHtml($user), $announcement);

        $this->tagWith($card, 'data-reactions-summary');
        $this->assertSame('3', $this->textOf($card, 'data-reactions-count'), 'لمّا المستخدم ما تفاعلش النصّ لازم يكون الرقم فقط');
    }

    /** (5) صفّ الأزرار القديم اختفى: فورم واحد فقط يوجّه لمسار التفاعل في الصفحة كلّها. */
    #[Test]
    public function old_per_emoji_forms_are_gone(): void
    {
        $user = $this->makeUser();
        $announcement = $this->makeAnnouncement(['reactions_enabled' => true]);

        $html = $this->feedHtml($user);

        $this->assertSame(
            1,
            substr_count($html, 'action="'.route('announcements.react', $announcement).'"'),
            'لازم يكون فيه فورم واحد بس يوجّه لمسار التفاعل، لا خمسة',
        );

        $card = $this->cardHtml($html, $announcement);
        $this->assertCount(1, $this->tagsWith($card, 'data-react-form'), 'البطاقة فيها فورم تفاعل واحد بس');
        $this->assertCount(1, $this->tagsWith($card, 'data-react-main'), 'البطاقة فيها زرّ رئيسيّ واحد بس');
    }

    /** (6) التفاعل مقفول من الأدمن: لا data-reactions في البطاقة. */
    #[Test]
    public function no_reactions_markup_when_reactions_are_disabled(): void
    {
        $user = $this->makeUser();
        $closed = $this->makeAnnouncement(['title' => 'منشور بلا تفاعل', 'reactions_enabled' => false]);
        $open = $this->makeAnnouncement(['title' => 'منشور بتفاعل', 'reactions_enabled' => true]);

        $html = $this->feedHtml($user);

        $closedCard = $this->cardHtml($html, $closed);
        $this->assertStringNotContainsString('data-reactions', $closedCard, 'لا يُرسَم أيّ markup للتفاعل لو الأدمن قفله');
        $this->assertStringNotContainsString('data-react-form', $closedCard, 'لا فورم تفاعل لو الأدمن قفله');
        $this->assertStringNotContainsString(route('announcements.react', $closed), $closedCard, 'لا رابط لمسار التفاعل لو الأدمن قفله');

        // والمنشور المفتوح في نفس الصفحة ما زال يحمل الغلاف (تأكيد أنّ الفحص يخصّ البطاقة لا الصفحة)
        $this->assertStringContainsString('data-reactions', $this->cardHtml($html, $open), 'المنشور المفتوح لازم يحمل غلاف التفاعل');
    }

    /** (7) الردّ JSON: التفاعل يُسجَّل ثمّ يُلغى بالضغط مرّة أخرى (تبديل). */
    #[Test]
    public function json_response_toggles_reaction_and_returns_counts(): void
    {
        $user = $this->makeUser();
        $announcement = $this->makeAnnouncement(['reactions_enabled' => true]);

        $first = $this->actingAs($user)
            ->postJson(route('announcements.react', $announcement), ['reaction' => '❤️']);

        $first->assertOk()
            ->assertJsonStructure(['reaction', 'counts'])
            ->assertJsonPath('reaction', '❤️')
            ->assertJsonPath('counts.❤️', 1);

        $this->assertSame(['❤️' => 1], $first->json('counts'), 'العدّادات بعد أوّل تفاعل لازم تكون ❤️ => 1');

        $second = $this->actingAs($user)
            ->postJson(route('announcements.react', $announcement), ['reaction' => '❤️']);

        $second->assertOk()
            ->assertJsonPath('reaction', null);

        $this->assertArrayHasKey('counts', $second->json(), 'الردّ الثاني لازم يحمل counts');
        $this->assertEmpty($second->json('counts'), 'بعد إلغاء التفاعل ما يبقاش أيّ عدّاد');

        $this->assertNull(AnnouncementRead::where('user_id', $user->id)
            ->where('announcement_id', $announcement->id)
            ->value('reaction'), 'التفاعل لازم يتشال من القاعدة بعد الضغط الثاني');
    }
}
