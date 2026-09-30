<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

/**
 * رسالة منشور التعليمات على **قناة البريد** (12.6-أ).
 *
 * Mailable لا `Mail::raw` — على غرار `ScheduledReportMail` — لأنّ العنوان
 * والافتتاحيّة والتذييل ونصّ زرّ الرابط كلّها **من الإعدادات** (2.13)، ولأنّ
 * الرسالة بهذا الشكل قابلة للتأكيد في الاختبارات كما تُرسَل فعلًا.
 *
 * والنصّ يصل **مخصَّصًا لكلّ قارئ** (12.6-أ): وسوم «مرحبًا [اسم]» تُركَّب قبل
 * البناء، وإلّا قرأ الناس الوسم حرفيًّا في بريدهم.
 */
class AnnouncementMail extends Mailable
{
    public function __construct(
        public readonly string $subjectLine,
        public readonly string $heading,
        public readonly string $bodyText,
        public readonly ?string $ctaLabel = null,
        public readonly ?string $ctaUrl = null,
        public readonly string $footer = '',
    ) {}

    public function build(): self
    {
        // القالب الموحّد للمنصّة (emails/platform) بدل HTML مبعثر داخل الكلاس
        $data = [
            'subjectLine' => $this->subjectLine,
            'heading' => $this->heading,
            'bodyText' => $this->bodyText,
            'ctaLabel' => $this->ctaLabel,
            'ctaUrl' => $this->ctaUrl !== '' ? $this->ctaUrl : null,
            'footer' => $this->footer,
            'code' => null,
            'textBody' => null,
        ];

        return $this->subject($this->subjectLine)
            ->view('emails.platform', $data)
            ->text('emails.platform-text', $data);
    }

    /**
     * قالب بسيط مبنيّ هنا لا في Blade: رسالة البريد نصٌّ وعنوانٌ وزرّ، ولا تحتمل
     * أصول التصميم (CSS/الخطوط) التي لا يعرضها أغلب عملاء البريد أصلًا.
     */
}
