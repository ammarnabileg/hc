<?php

namespace App\Services\Volunteer\Profile;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\Volunteer\People\AuditTrail;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * تاب «ملاحظات إداريّة» (13.4-م-5): **للمخوَّل فقط · سرّيّة · بـAudit كامل**
 * (مَن كتب / متى / التعديلات) — أداة أساسيّة عند الترقية أو المشكلات.
 *
 * ⛔ ولا يراها **صاحب البروفايل** أبدًا، ولا يعرف أنّها موجودة أصلًا (2.15-أ-7).
 */
final class NotesPanel
{
    public const TABLE = 'volunteer_profile_notes';

    public function __construct(private readonly AuditTrail $audit) {}

    /** @return Collection<int, object> */
    public function list(User $owner): Collection
    {
        return DB::table(self::TABLE.' as n')
            ->leftJoin('users as a', 'a.id', '=', 'n.author_id')
            ->where('n.user_id', $owner->id)
            ->orderByDesc('n.id')
            ->limit((int) setting('volunteer.profile.notes.list_size', 30))
            ->get(['n.id', 'n.body', 'n.created_at', 'a.name as author_name', 'a.code as author_code']);
    }

    /** كتابة ملاحظة — بحدّ أدنى للطول حتى لا تكون الملاحظة كلمةً بلا معنًى وقت القرار */
    public function write(User $author, User $owner, string $body): int
    {
        $body = trim($body);
        $min = (int) setting('volunteer.profile.notes.min_chars', 5);

        if (mb_strlen($body) < $min) {
            throw new RuntimeException(strtr(setting('volunteer.notes_panel.write_1', 'اكتب ملاحظة واضحة — :p1 حروف على الأقلّ.'), [':p1' => (string) ($min)]));
        }

        $id = DB::table(self::TABLE)->insertGetId([
            'user_id' => $owner->id,
            'author_id' => $author->id,
            'body' => mb_substr($body, 0, (int) setting('volunteer.profile.notes.max_chars', 2000)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Audit كامل: الملاحظة السرّيّة لا تُكتَب بلا أثر (13.4-م-5)
        $this->audit->record($author, 'volunteer_profile.note.created', $owner, [], [
            'note_id' => $id,
            'user_id' => $owner->id,
        ]);

        return $id;
    }

    /**
     * سجلّ التدقيق على ملاحظات هذا البروفايل — يراه الأدمن (المستوى الرابع).
     *
     * @return Collection<int, AuditLog>
     */
    public function auditTrail(User $owner): Collection
    {
        return AuditLog::query()
            ->with('user:id,name,code')
            ->where('action', 'like', 'volunteer_profile.note.%')
            ->where('auditable_type', $owner->getMorphClass())
            ->where('auditable_id', $owner->getKey())
            ->latest('id')
            ->limit((int) setting('volunteer.profile.notes.audit_size', 20))
            ->get();
    }
}
