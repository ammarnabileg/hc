<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

/**
 * رسالة التقرير المجدول (24.3-خامسًا).
 *
 * Mailable لا `Mail::raw` لأنّ العنوان والنصّ والمرفق كلّها **من الإعدادات**،
 * ولأنّ الرسالة بهذا الشكل قابلة للتأكيد في الاختبارات كما تُرسَل فعلًا.
 */
class ScheduledReportMail extends Mailable
{
    /**
     * @param  array{name:string,mime:string,content:string}|null  $file  المرفق — وnull يعني أنّ الحجم تجاوز الحدّ
     */
    public function __construct(
        public readonly string $subjectLine,
        public readonly string $bodyText,
        public readonly ?array $file = null,
    ) {}

    public function build(): self
    {
        $this->subject($this->subjectLine)
            ->html(nl2br(e($this->bodyText)));

        if ($this->file !== null) {
            $this->attachData($this->file['content'], $this->file['name'], ['mime' => $this->file['mime']]);
        }

        return $this;
    }
}
