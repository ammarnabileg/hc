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
        return $this->subject($this->subjectLine)->html($this->markup());
    }

    /**
     * قالب بسيط مبنيّ هنا لا في Blade: رسالة البريد نصٌّ وعنوانٌ وزرّ، ولا تحتمل
     * أصول التصميم (CSS/الخطوط) التي لا يعرضها أغلب عملاء البريد أصلًا.
     */
    private function markup(): string
    {
        $html = '<div dir="rtl" style="font-family: sans-serif; line-height: 1.8; text-align: right">';
        $html .= '<h2 style="margin:0 0 12px">'.e($this->heading).'</h2>';
        $html .= '<div>'.nl2br(e($this->bodyText)).'</div>';

        if ($this->ctaLabel !== null && $this->ctaUrl !== null && $this->ctaUrl !== '') {
            $html .= '<p style="margin:20px 0"><a href="'.e($this->ctaUrl).'">'.e($this->ctaLabel).'</a></p>';
        }

        if ($this->footer !== '') {
            $html .= '<hr><p style="font-size:12px; color:#666">'.e($this->footer).'</p>';
        }

        return $html.'</div>';
    }
}
