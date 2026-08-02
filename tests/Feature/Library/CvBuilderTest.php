<?php

namespace Tests\Feature\Library;

use App\Models\Cv;
use App\Models\CvTemplate;
use App\Models\Transaction;

/** السيرة الذاتيّة (9 · 24.5): الحفظ التلقائيّ بين الخطوات والقوالب بالتذاكر. */
class CvBuilderTest extends LibraryTestCase
{
    public function test_stepper_page_opens_with_steps_and_completion(): void
    {
        $user = $this->trainee('UCVOPEN1');

        $this->actingAs($user)->get(route('cv.index'))
            ->assertOk()
            ->assertSee(setting('cv.step.profile_label', 'البيانات'), false)
            ->assertSee(setting('cv.step.certificates_label', 'الشهادات'), false)
            ->assertSee(setting('cv.download_label', 'تحميل PDF'), false)
            ->assertSee(setting('cv.completion.label', 'اكتمال السيرة'), false);
    }

    public function test_autosave_between_steps_persists_and_reports_saved(): void
    {
        $user = $this->trainee('UCVSAVE1');

        $this->actingAs($user)->postJson(route('cv.autosave'), [
            'step' => 'profile',
            'data' => ['profile' => ['job_title' => 'محلّل بيانات', 'company' => 'نماء']],
        ])
            ->assertOk()
            ->assertJson(['saved' => true, 'label' => setting('cv.autosave.saved_label', 'اتحفظ ✓')]);

        $this->actingAs($user)->postJson(route('cv.autosave'), [
            'step' => 'experience',
            'data' => ['experience' => [['title' => 'مسؤول مبيعات', 'company' => 'فودافون', 'from' => '2022']]],
        ])->assertOk();

        $cv = Cv::where('user_id', $user->id)->firstOrFail();

        // الخطوة الثانية لا تمسح الأولى — الحفظ تراكميّ بين الخطوات
        $this->assertSame('محلّل بيانات', $cv->data['profile']['job_title']);
        $this->assertSame('مسؤول مبيعات', $cv->data['experience'][0]['title']);
        $this->assertGreaterThan(0, $cv->completion_percent);
    }

    public function test_free_template_is_selectable_without_tickets(): void
    {
        $user = $this->trainee('UCVFREE1');
        $free = CvTemplate::where('is_free', true)->firstOrFail();

        $this->actingAs($user)->postJson(route('cv.template', $free), ['confirm' => 0])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertSame($free->id, (int) Cv::where('user_id', $user->id)->value('cv_template_id'));
    }

    /**
     * ⭐ الدستور 9: «معاينة مجّانيّة … **قبل الخصم**»، ثمّ «الاستخراج النهائيّ …
     * **ويُخصَم** عدد تذاكر القالب». فالاختيار لا يخصم شيئًا.
     */
    public function test_choosing_a_paid_template_costs_nothing_until_the_final_export(): void
    {
        $user = $this->trainee('UCVPAID1');
        $paid = CvTemplate::where('is_free', false)->orderBy('sort_order')->firstOrFail();
        $this->giveTickets($user, 5);

        $this->actingAs($user)->postJson(route('cv.template', $paid))
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'owned' => false,
                'balance_before' => 5,
                'balance_after' => 5 - $paid->priceTickets(),
            ]);

        // اختيارٌ بلا خصم: الرصيد كما هو ولا معاملة واحدة
        $this->assertSame(5.0, $user->fresh()->balance('tickets'));
        $this->assertSame(0, Transaction::where('user_id', $user->id)->where('source', 'purchase')->count());
        $this->assertSame($paid->id, (int) Cv::where('user_id', $user->id)->value('cv_template_id'));
    }

    public function test_preview_is_watermarked_before_the_charge_and_clean_after_it(): void
    {
        $user = $this->trainee('UCVMARK1');
        $paid = CvTemplate::where('is_free', false)->orderBy('sort_order')->firstOrFail();
        $this->giveTickets($user, 5);

        $this->actingAs($user)->postJson(route('cv.template', $paid))->assertOk();

        $mark = (string) setting('platform.identity.name', config('app.name'));

        // قبل الخصم: علامة مائيّة = اسم المنصّة (أو لوجوها) + سطر يشرح التكلفة (9)
        $this->actingAs($user)->get(route('cv.download'))
            ->assertOk()
            ->assertSee('data-cv-watermark', false)
            ->assertSee($mark, false);

        // بالتأكيد: يُخصَم القالب **مرّةً واحدة** والنسخة تخرج نظيفة
        $this->actingAs($user)->get(route('cv.download', ['confirm' => 1]))
            ->assertOk()
            ->assertDontSee('data-cv-watermark', false);

        $this->assertSame(5 - $paid->priceTickets(), $user->fresh()->balance('tickets'));
        $this->assertSame(1, Transaction::where('user_id', $user->id)->where('source', 'purchase')->count());

        // ولا خصم ثانيًا بعد التملّك
        $this->actingAs($user)->get(route('cv.download', ['confirm' => 1]))->assertOk();
        $this->assertSame(1, Transaction::where('user_id', $user->id)->where('source', 'purchase')->count());
    }

    public function test_short_tickets_keep_the_watermarked_preview_and_charge_nothing(): void
    {
        $user = $this->trainee('UCVPOOR1');
        $paid = CvTemplate::where('is_free', false)->orderBy('sort_order')->firstOrFail();
        $this->giveTickets($user, 0);

        $this->actingAs($user)->postJson(route('cv.template', $paid))->assertOk();

        $this->actingAs($user)->get(route('cv.download', ['confirm' => 1]))
            ->assertOk()
            ->assertSee('data-cv-watermark', false);

        $this->assertSame(0, Transaction::where('user_id', $user->id)->count());
    }

    /** الخبرة التطوّعيّة والدورات التدريبيّة — بندان صريحان في القسم 9 */
    public function test_volunteering_and_courses_are_saved_and_rendered(): void
    {
        $user = $this->trainee('UCVEXTR1');

        $this->actingAs($user)->postJson(route('cv.autosave'), [
            'step' => 'volunteering',
            'data' => ['volunteering' => [['role' => 'منسّق مبادرة', 'organization' => 'رسالة', 'from' => '2021']]],
        ])->assertOk();

        $this->actingAs($user)->postJson(route('cv.autosave'), [
            'step' => 'courses',
            'data' => ['courses' => [['name' => 'إدارة المشاريع', 'provider' => 'PMI', 'date' => '2023', 'serial' => 'PM-9']]],
        ])->assertOk();

        $cv = Cv::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('منسّق مبادرة', $cv->data['volunteering'][0]['role']);
        $this->assertSame('إدارة المشاريع', $cv->data['courses'][0]['name']);

        $this->actingAs($user)->get(route('cv.preview'))
            ->assertOk()
            ->assertSee('منسّق مبادرة', false)
            ->assertSee('إدارة المشاريع', false);
    }

    public function test_download_renders_a_real_size_printable_sheet(): void
    {
        $user = $this->trainee('UCVPDF01');

        $this->actingAs($user)->postJson(route('cv.autosave'), [
            'step' => 'profile',
            'data' => ['profile' => ['job_title' => 'مصمّم واجهات']],
        ])->assertOk();

        $this->actingAs($user)->get(route('cv.download'))
            ->assertOk()
            ->assertSee('210mm', false)
            ->assertSee('مصمّم واجهات', false);
    }

    public function test_free_builder_works_without_registration_and_download_asks_for_account(): void
    {
        // قالب مجّانيّ واحد كباب دخول (21.2-ج)
        $this->post(route('cv.free.autosave'), [
            'step' => 'profile',
            'data' => ['profile' => ['job_title' => 'مسوّق رقميّ']],
        ])->assertOk()->assertJson(['saved' => true]);

        $this->get(route('cv.free'))
            ->assertOk()
            ->assertSee(setting('cv.guest.download_label', 'أنشئ حساب وحمّل PDF'), false);

        $this->get(route('cv.free.preview'))->assertOk()->assertSee('مسوّق رقميّ', false);
    }
}
