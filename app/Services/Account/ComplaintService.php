<?php

namespace App\Services\Account;

use App\Models\Complaint;
use App\Models\ComplaintMessage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * الشكاوى والمقترحات (الدستور 11 · 24.5) — تذكرة يفتحها المستخدم ويتابعها.
 * الأسباب قابلة للإدارة من لوحة الأدمن، فهي إعداد لا قائمة محروقة (2.13).
 */
class ComplaintService
{
    /** حالات التذكرة بترتيب عرض العدّادات (2.16 يحكم ألوانها) */
    public const STATUSES = ['open', 'in_review', 'answered', 'closed'];

    /**
     * تسميات الحالات — **واحدة عند المستخدم والأدمن** (11).
     * كانت `open` تُسمّى «مفتوحة» هنا و«جديدة» في لوحة الأدمن، فبدت حالتين.
     *
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            'open' => (string) setting('complaints.status.open_label', 'مفتوحة'),
            'in_review' => (string) setting('complaints.status.in_review_label', 'قيد المراجعة'),
            'answered' => (string) setting('complaints.status.answered_label', 'تمّ الردّ'),
            'closed' => (string) setting('complaints.status.closed_label', 'مغلقة'),
        ];
    }

    /** حالة التذكرة ⟵ حالة قاموس الألوان (2.16) */
    public static function stateOf(string $status): string
    {
        return match ($status) {
            'answered' => 'ok',
            'in_review' => 'warn',
            'closed' => 'idle',
            default => 'warn',
        };
    }

    /** @return array<string, string> */
    public static function typeLabels(): array
    {
        return [
            'complaint' => (string) setting('complaints.type.complaint_label', 'شكوى'),
            'suggestion' => (string) setting('complaints.type.suggestion_label', 'مقترح'),
        ];
    }

    /**
     * الأسباب/التصنيفات — افتراضها قائمة الدستور 11 وتُدار من لوحة الأدمن.
     *
     * ⭐ **مفتاح واحد** `complaints.reasons` يقرؤه الفورم ولوحة الأدمن معًا.
     * كان الفورم يقرأ `account.complaints.categories` والأدمن يقرأ غيره،
     * فكان تحرير الأدمن بلا أثر على القائمة التي يراها المستخدم فعلًا.
     */
    public const REASONS_KEY = 'complaints.reasons';

    /** @return array<int, string> */
    public static function defaultReasons(): array
    {
        return [
            'أحد المشرفين',
            'الهيكل الإداريّ وأسلوب الإدارة',
            'اللقاءات المباشرة',
            'اللوائح والقوانين',
            'المحتوى التدريبيّ',
            'خدمة العملاء',
            'المنصّة',
            'أخرى',
        ];
    }

    /** @return array<int, string> */
    public static function categories(): array
    {
        $stored = setting(self::REASONS_KEY);

        if (is_array($stored) && $stored !== []) {
            return array_values(array_filter(array_map(
                fn ($reason) => trim((string) $reason),
                $stored,
            ), fn ($reason) => $reason !== ''));
        }

        return self::defaultReasons();
    }

    public static function attachmentMaxKb(): int
    {
        return max(1, (int) setting('account.complaints.attachment_max_kb', 4096));
    }

    /** فتح تذكرة جديدة — ونصّها الأوّل يصير أوّل رسالة في السلسلة */
    public function open(User $user, array $data, ?UploadedFile $attachment = null): Complaint
    {
        return DB::transaction(function () use ($user, $data, $attachment) {
            $path = $attachment ? $this->store($attachment) : null;

            $complaint = Complaint::create([
                'number' => $this->nextNumber(),
                'user_id' => $user->id,
                'type' => $data['type'],
                'category' => $data['category'] ?? null,
                'title' => $data['title'],
                'body' => $data['body'],
                'attachment_path' => $path,
                'status' => 'open',
                // ⭐ الحقل رقم 1 في القسم 11: «هل ترغب في التواصل معك؟»
                // — يُكتَب هنا وإلّا رأى الأدمن «لا أحد يطلب التواصل» أبدًا.
                'wants_contact' => (bool) ($data['wants_contact'] ?? false),
            ]);

            ComplaintMessage::create([
                'complaint_id' => $complaint->id,
                'user_id' => $user->id,
                'body' => $data['body'],
                'attachment_path' => $path,
            ]);

            return $complaint;
        });
    }

    /** إضافة ردّ/مرفق — والمغلقة قراءة فقط */
    public function reply(Complaint $complaint, User $user, string $body, ?UploadedFile $attachment = null): ComplaintMessage
    {
        $message = ComplaintMessage::create([
            'complaint_id' => $complaint->id,
            'user_id' => $user->id,
            'body' => $body,
            'attachment_path' => $attachment ? $this->store($attachment) : null,
        ]);

        // ردّ صاحب التذكرة يُعيدها لطابور المراجعة — «تمّ الردّ» حالةُ الأدمن لا حالته
        if (in_array($complaint->status, ['answered', 'open'], true)) {
            $complaint->update(['status' => 'in_review']);
        }

        $complaint->touch();

        return $message;
    }

    public function close(Complaint $complaint): void
    {
        $complaint->update(['status' => 'closed', 'closed_at' => now()]);
    }

    public function isClosed(Complaint $complaint): bool
    {
        return $complaint->status === 'closed';
    }

    private function store(UploadedFile $file): string
    {
        return $file->store('complaints', 'public');
    }

    /** رقم التذكرة: بادئة من الإعدادات + تسلسل — يسهل البحث به (24.5) */
    private function nextNumber(): string
    {
        $prefix = (string) setting('account.complaints.number_prefix', 'TK-');

        do {
            $number = $prefix.now()->format('ym').'-'.Str::upper(Str::random(4));
        } while (Complaint::where('number', $number)->exists());

        return $number;
    }
}
