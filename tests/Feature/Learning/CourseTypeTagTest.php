<?php

namespace Tests\Feature\Learning;

/**
 * لغة أغلفة موحّدة (الفكرة #7): كلّ كارت تدريب يحمل علامة نوع صغيرة في نفس
 * الموضع، مجّاني أو بسعره بالكوينز، من بيانات التدريب لا من تخمين.
 */
class CourseTypeTagTest extends LearningTestCase
{
    public function test_each_course_row_carries_a_free_or_priced_type_tag(): void
    {
        $user = $this->trainee();
        $free = $this->makeCourse(1, true, ['is_free' => true, 'price_coins' => 0]);
        $paid = $this->makeCourse(1, true, ['is_free' => false, 'price_coins' => 250]);
        $this->enroll($user, $free);
        $this->enroll($user, $paid);

        $html = $this->actingAs($user)->get(route('learning.courses'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'data-course-type="free"'));
        $this->assertSame(1, substr_count($html, 'data-course-type="paid"'));
        $this->assertStringContainsString(setting('learning.card.type_free', 'مجّاني'), $html);
        $this->assertStringContainsString(str_replace(':price', \App\Services\Store\Coins::label(250), (string) setting('learning.card.type_paid', 'بـ:price')), $html);
    }
}
