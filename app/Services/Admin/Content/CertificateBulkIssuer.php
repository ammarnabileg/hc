<?php

namespace App\Services\Admin\Content;

use App\Models\Certificate;
use App\Models\CertificateType;
use App\Models\User;
use App\Services\Certificates\CertificateIssuer;
use App\Services\Certificates\CertificateRenderer;
use App\Services\Notifications\Notifier;
use App\Support\Scope\ScopeFilter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * إصدار الشهادات من لوحة الإدارة (12.5-ج · 12.5-د · 24.1).
 *
 * الإصدار الجماعيّ يمرّ بثلاث محطّات لا يجوز اختصارها:
 *   1) **تحقّق من الأكواد** — صالح / غير موجود / **صدرت له قبل كده**.
 *   2) **معاينة قبل الإصدار** ببيانات كلٍّ الحقيقيّة (بوب-أب، الشهادات تحت بعضها).
 *   3) **الإصدار** — وفيه كود فريد + QR + Hash + **تجميد نسخة التصميم**.
 *
 * ⭐ التجميد والترقيم بلا فجوات ومنع التكرار كلّها في `CertificateIssuer` المشترك،
 * فلا نكرّرها هنا حتى لا تفترق قواعد الإصدار بين شاشة وأخرى.
 */
class CertificateBulkIssuer
{
    public function __construct(
        private readonly CertificateIssuer $issuer,
        private readonly CertificateRenderer $renderer,
        private readonly TemplateDesigner $designer,
        private readonly ContentAudit $audit,
    ) {}

    /** الحالات الثلاث المعتمَدة: سارية · منتهية · ملغاة (13.4-ق) — مفاتيح داخليّة لا نصّ (2.13-ب) */
    public const STATUS_KEYS = ['valid', 'expired', 'revoked'];

    /**
     * حالات الشهادة بعناوينها — من `setting()` لا محروقة (2.13).
     *
     * @return array<string, string>
     */
    public static function statuses(): array
    {
        return [
            'valid' => (string) setting('certificates.bulk.status.valid', 'سارية'),
            'expired' => (string) setting('certificates.bulk.status.expired', 'منتهية'),
            'revoked' => (string) setting('certificates.bulk.status.revoked', 'ملغاة'),
        ];
    }

    /**
     * تفكيك الأكواد الملصوقة — الفواصل المقبولة من الإعدادات لا من الكود (2.13).
     *
     * @return array<int, string>
     */
    public function parseCodes(string $raw): array
    {
        $separators = (string) setting('certificates.issue.code_separators', " \n\r\t,;،");

        return collect(preg_split('/['.preg_quote($separators, '/').']+/u', $raw) ?: [])
            ->map(fn ($code) => mb_strtoupper(trim((string) $code)))
            ->filter()
            ->unique()
            ->take((int) setting('certificates.issue.batch_limit', 200))
            ->values()
            ->all();
    }

    /**
     * تحقّق من الأكواد قبل الإصدار.
     *
     * @param  array<int, string>  $codes
     * @return Collection<int, array{code: string, user: User|null, state: string, message: string}>
     */
    public function verify(array $codes, CertificateType $type): Collection
    {
        $users = User::query()->whereIn('code', $codes)->get()->keyBy(fn (User $u) => mb_strtoupper((string) $u->code));

        return collect($codes)->map(function (string $code) use ($users, $type) {
            $user = $users->get($code);

            if (! $user) {
                return [
                    'code' => $code, 'user' => null, 'state' => 'danger',
                    'message' => (string) setting('certificates.issue.error_not_found', 'الكود غير موجود'),
                ];
            }

            // ⭐ منع التكرار: تحذير واضح لو صدرت له قبل كده (12.5-ج)
            if ($this->alreadyIssued($user, $type)) {
                return [
                    'code' => $code, 'user' => $user, 'state' => 'warn',
                    'message' => (string) setting('certificates.issue.error_duplicate', 'صدرت له من قبل'),
                ];
            }

            return ['code' => $code, 'user' => $user, 'state' => 'ok', 'message' => setting('admin_content.certificate_bulk_issuer.verify_1', 'صالح')];
        });
    }

    public function alreadyIssued(User $user, CertificateType $type): bool
    {
        return Certificate::query()
            ->where('user_id', $user->id)
            ->where('certificate_type_id', $type->id)
            ->where('status', 'valid')
            ->exists();
    }

    /**
     * معاينة قبل الإصدار ببيانات كلّ واحد الحقيقيّة (12.5-ج).
     *
     * @param  Collection<int, array{code: string, user: User|null, state: string}>  $verified
     * @return Collection<int, array{code: string, name: string, data: array<string, mixed>}>
     */
    public function preview(Collection $verified, CertificateType $type, string $language, array $extra = []): Collection
    {
        return $verified
            ->filter(fn ($row) => $row['user'] instanceof User)
            ->map(function (array $row) use ($type, $language, $extra) {
                $user = $row['user'];

                return [
                    'code' => $row['code'],
                    'state' => $row['state'],
                    'name' => $user->name,
                    'data' => array_merge(
                        $this->designer->sampleData($type, $language),
                        [
                            'holder_name' => $user->name,
                            'holder_code' => $user->code,
                            'code' => (string) setting('certificates.issue.preview_code_placeholder', '— يُولَّد عند الإصدار —'),
                        ],
                        $this->bindings($type, $user),
                        $extra,
                    ),
                ];
            })
            ->values();
    }

    /**
     * الإصدار الفعليّ — الفشل الجزئيّ لا يُسقط الدفعة (24.1).
     *
     * @param  array<int, string>  $codes
     * @return array{issued: int, skipped: int, failed: array<int, string>, certificates: Collection<int, Certificate>}
     */
    public function issueBatch(array $codes, CertificateType $type, string $language, ?User $actor = null, array $extra = []): array
    {
        $verified = $this->verify($codes, $type);
        $issued = collect();
        $skipped = 0;
        $failed = [];

        foreach ($verified as $row) {
            if (! $row['user'] instanceof User) {
                $failed[] = $row['code'];

                continue;
            }

            if ($row['state'] === 'warn') {
                $skipped++;

                continue;
            }

            $certificate = $this->issuer->issue(
                user: $row['user'],
                typeKey: $type->key,
                subject: null,
                data: array_merge($this->bindings($type, $row['user']), $extra),
                source: 'manual',
                language: $language,
                issuedBy: $actor,
            );

            if (! $certificate) {
                $failed[] = $row['code'];

                continue;
            }

            $this->issuer->claimCelebration($certificate);
            $this->audit->record($certificate, 'certificate.issued', [], [
                'type' => $type->key, 'language' => $language, 'code' => $certificate->code,
            ], $actor);

            $this->notifyIssued($certificate);
            $issued->push($certificate);
        }

        return ['issued' => $issued->count(), 'skipped' => $skipped, 'failed' => $failed, 'certificates' => $issued];
    }

    /**
     * الإلغاء: **بسبب موثّق إلزاميّ** + إشعار المستخدم بلباقة (12.5-د).
     * والإلغاء غير الانتهاء: الملغاة تزوير مثبَت، والمنتهية انتهى العمل بها فقط.
     */
    public function revoke(Certificate $certificate, string $reason, bool $notify = true, ?User $actor = null): Certificate
    {
        $certificate->update([
            'status' => 'revoked',
            'revoked_at' => now(),
            'revoked_reason' => mb_substr($reason, 0, 255),
        ]);

        $this->renderer->forget($certificate);
        $this->audit->record($certificate, 'certificate.revoked', ['status' => 'valid'], ['reason' => $reason], $actor);

        if ($notify) {
            Notifier::send(
                user: $certificate->user,
                category: 'certificate',
                title: (string) setting('certificates.revoke.notice_title', 'تحديث على إحدى شهاداتك'),
                body: (string) setting('certificates.revoke.notice_body', 'راجعنا شهادتك وأوقفنا العمل بها — تواصل معنا لو محتاج توضيحًا.'),
            );
        }

        return $certificate->refresh();
    }

    /**
     * إعادة الإصدار/التصحيح: يُبطل القديمة ويصدر مصحّحةً، وينعكس في التحقّق (12.5-د).
     */
    public function reissue(Certificate $certificate, array $extra = [], ?User $actor = null): ?Certificate
    {
        return DB::transaction(function () use ($certificate, $extra, $actor) {
            $this->revoke(
                $certificate,
                (string) setting('certificates.reissue.reason', 'أُعيد إصدارها مصحَّحةً'),
                notify: false,
                actor: $actor,
            );

            $type = $certificate->certificate_type;

            $fresh = $this->issuer->issue(
                user: $certificate->user,
                typeKey: $type->key,
                subject: null,
                data: array_merge((array) $certificate->data_snapshot, $extra),
                source: 'manual',
                language: $certificate->language,
                issuedBy: $actor,
            );

            if ($fresh) {
                $this->audit->record($fresh, 'certificate.reissued', ['from' => $certificate->code], [], $actor);
            }

            return $fresh;
        });
    }

    /**
     * سجلّ الصادر مع فلاتره (12.5-د).
     *
     * @param  array<string, mixed>  $filters
     */
    /**
     * سجلّ الصادر — ومع `$viewer` يُحصَر **بنطاقه** (12.2.1-ب)،
     * فسجلّ الشهادات دليلُ أشخاص لا جدولُ أكواد.
     */
    public function ledger(array $filters = [], ?User $viewer = null): LengthAwarePaginator
    {
        $query = Certificate::query()->with(['user', 'certificate_type'])->latest('issued_at');

        if ($viewer !== null) {
            app(ScopeFilter::class)->apply($query, $viewer, 'certificate_ledger.list');
        }

        if (($q = trim((string) ($filters['q'] ?? ''))) !== '') {
            $query->where(function ($inner) use ($q) {
                $inner->where('code', 'like', '%'.$q.'%')
                    ->orWhereIn('user_id', User::query()
                        ->where('name', 'like', '%'.$q.'%')
                        ->orWhere('code', 'like', '%'.$q.'%')
                        ->pluck('id'));
            });
        }

        if (($type = (int) ($filters['type'] ?? 0)) > 0) {
            $query->where('certificate_type_id', $type);
        }

        if (($status = (string) ($filters['status'] ?? '')) !== '' && in_array($status, self::STATUS_KEYS, true)) {
            $query->where('status', $status);
        }

        if (($source = (string) ($filters['source'] ?? '')) !== '') {
            $query->where('source', $source);
        }

        if (($from = (string) ($filters['from'] ?? '')) !== '') {
            $query->whereDate('issued_at', '>=', $from);
        }

        return $query->paginate((int) setting('certificates.ledger.per_page', 20))->withQueryString();
    }

    /**
     * قيم الحقول المربوطة بأعمدة قاعدة البيانات لهذا المستخدم (12.5-ب).
     *
     * @return array<string, string>
     */
    public function bindings(CertificateType $type, User $user): array
    {
        $bindings = $type->bindings;
        $bindings = is_array($bindings) ? $bindings : (json_decode((string) $bindings, true) ?: []);
        $values = [];

        foreach ($bindings as $binding) {
            $table = (string) ($binding['table'] ?? '');
            $column = (string) ($binding['column'] ?? '');

            if (! $this->designer->bindingAllowed($table, $column)) {
                continue;
            }

            $value = $table === 'users'
                ? DB::table('users')->where('id', $user->id)->value($column)
                : DB::table($table)->orderBy('id')->value($column);

            $values[$this->designer->bindingKey($table, $column)] = (string) ($value ?? '');
        }

        return $values;
    }

    /** لحظة الذروة للمستخدم: تظهر تلقائيًّا في «مكتبتي» والبروفايل (12.5-ج). */
    private function notifyIssued(Certificate $certificate): void
    {
        if (! setting('certificates.issue.notify_user', true)) {
            return;
        }

        Notifier::send(
            user: $certificate->user,
            category: 'certificate',
            title: (string) setting('certificates.issue.notice_title', 'مبروك — صدرت شهادتك 🎓'),
            body: (string) ($certificate->data_snapshot['certificate_name'] ?? null),
            url: Route::has('verify.certificate')
                ? route('verify.certificate', ['code' => $certificate->code])
                : null,
        );
    }
}
