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

    /** @return array<string, string> */
    public static function statusLabels(): array
    {
        return [
            'open' => 'مفتوحة',
            'in_review' => 'قيد المراجعة',
            'answered' => 'تمّ الردّ',
            'closed' => 'مغلقة',
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
            'complaint' => 'شكوى',
            'suggestion' => 'مقترح',
        ];
    }

    /** الأسباب/التصنيفات — افتراضها قائمة الدستور 11 وتُدار من لوحة الأدمن */
    public static function categories(): array
    {
        $stored = setting('account.complaints.categories');

        if (is_array($stored) && $stored !== []) {
            return $stored;
        }

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

        if ($complaint->status === 'answered') {
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
