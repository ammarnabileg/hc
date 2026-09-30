<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

/**
 * البريد الموحّد للمنصّة (2.13 · الهويّة 2.10.1-3): قالبٌ واحد RTL بألوان النظام
 * (اسم المنصّة بالنقطة الحمراء · عنوان · نصّ · صندوق رمز اختياريّ · زرّ واحد · تذييل)
 * بدل `Mail::raw` النصّيّ الخام وHTML مبعثر في كلّ خدمة. ومع كلّ رسالة بديلٌ نصّيّ
 * لعملاء البريد النصّيّة (`textBody` أو ما يُشتقّ من الحقول نفسها).
 */
class PlatformMail extends Mailable
{
    public function __construct(
        public readonly string $subjectLine,
        public readonly string $heading,
        public readonly string $bodyText,
        public readonly ?string $ctaLabel = null,
        public readonly ?string $ctaUrl = null,
        public readonly string $footer = '',
        public readonly ?string $code = null,
        public readonly ?array $file = null,
        public readonly ?string $textBody = null,
    ) {}

    public function build(): self
    {
        $data = [
            'subjectLine' => $this->subjectLine,
            'heading' => $this->heading,
            'bodyText' => $this->bodyText,
            'ctaLabel' => $this->ctaLabel,
            'ctaUrl' => $this->ctaUrl,
            'footer' => $this->footer,
            'code' => $this->code,
            'textBody' => $this->textBody,
        ];

        $this->subject($this->subjectLine)
            ->view('emails.platform', $data)
            ->text('emails.platform-text', $data);

        if ($this->file !== null) {
            $this->attachData($this->file['content'], $this->file['name'], ['mime' => $this->file['mime']]);
        }

        return $this;
    }
}
