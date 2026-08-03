<?php

namespace App\Services\Admin\System;

use App\Models\Country;
use App\Models\CountrySourceCheck;
use App\Models\CountrySourceSnapshot;
use App\Models\Governorate;
use App\Models\User;
use App\Services\Notifications\Notifier;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * 12.7-د «بيانات الدول»: تحديث مصدر `dr5hn` مع **فحص فروق النسخة قبل الدمج
 * بلا فقد بيانات** — بالقاعدة الحاكمة في 2.11-د (قاعدة عدم الفقد).
 *
 * الترتيب المُلزِم: **نسخة تُثبَّت ⇐ فروق تُعرَض ⇐ قرار المالك ⇐ Dry-run ⇐ دمج
 * ⇐ تحقّق**. والفروق تُعرَض **ليقرّر قبل التنفيذ** لا ليُخبَر بعده.
 *
 * ⛔ ثلاث قواعد لا تُخترَق هنا:
 * 1) **لا حذف إطلاقًا من هذه الشاشة.** 2.11-د تمنع الحذف إلّا بعد **نقلٍ
 *    وتحقّق**، ولا مكان يُنقَل إليه ارتباطُ مستخدمٍ بدولته أو بمحافظته — فالمحذوف
 *    من المصدر **يُخفى** لا يُمحى، ويبقى صفُّه ورقمه كما هو.
 * 2) **المحافظة لا تُخفى أبدًا** (قاعدة أقرّها المالك): مهما اختفت من المصدر
 *    تبقى ظاهرةً مرتبطةً بأهلها — إخفاؤها يُسقطها من كلّ قوائم الاختيار
 *    فيصير ارتباط المستخدم بها ارتباطًا بشبح.
 * 3) **ما له مستخدم لا يُمَسّ**: أيّ دولة مرتبطة بمستخدم تُدرَج «محميّة» ولا
 *    يطالها الإخفاء ولو غابت عن المصدر.
 */
class CountryDataSync
{
    /** أنواع الفروق الثلاثة كما ينصّ عليها 24.3: مضاف / محذوف / معدَّل. */
    public const CHANGES = ['added' => 'مضاف', 'removed' => 'محذوف من المصدر', 'changed' => 'معدَّل'];

    /** حقول الدولة القابلة للتحديث من المصدر — والمعرّف `iso2` ليس منها. */
    private const COUNTRY_FIELDS = ['name_ar', 'name_en', 'phone_code', 'timezone'];

    /** حقول المحافظة القابلة للتحديث — والمعرّف `name_en` ليس منها. */
    private const GOVERNORATE_FIELDS = ['name_ar'];

    // ============================================================== النسخة

    /**
     * تثبيت نسخة من المصدر. الشكل المقبول:
     * `{"version": "...", "countries": [{"iso2","name_ar","name_en","phone_code","timezone","governorates":[{"name_ar","name_en"}]}]}`
     *
     * @param  array<string, mixed>  $payload
     */
    public function import(array $payload, ?User $actor = null): CountrySourceSnapshot
    {
        $countries = $payload['countries'] ?? null;

        if (! is_array($countries) || $countries === []) {
            throw new RuntimeException('الملفّ مالوش قايمة دول — تأكّد إنّه نسخة المصدر المعتمَد وجرّب تاني.');
        }

        foreach ($countries as $country) {
            if (! is_array($country) || trim((string) ($country['iso2'] ?? '')) === '') {
                throw new RuntimeException('في صفّ دولة بلا كود ISO2 — النسخة ناقصة، ما نقدرش نفحصها.');
            }
        }

        return CountrySourceSnapshot::create([
            'source' => (string) setting('countries.source', 'dr5hn'),
            'version' => isset($payload['version']) ? mb_substr((string) $payload['version'], 0, 64) : null,
            'payload' => ['countries' => array_values($countries)],
            'status' => 'pending',
            'created_by' => $actor?->id,
        ]);
    }

    /** آخر نسخة تُعرَض في الشاشة — والفحص والدمج يقعان عليها. */
    public function latest(): ?CountrySourceSnapshot
    {
        return CountrySourceSnapshot::query()->latest('id')->first();
    }

    /** فحص التحديثات: يحسب الفروق ويثبّت ملخّصها على النسخة (24.3). */
    public function check(CountrySourceSnapshot $snapshot): CountrySourceSnapshot
    {
        $diff = $this->diff($snapshot);

        $snapshot->update([
            'summary' => [
                'added' => count($diff['added']),
                'removed' => count($diff['removed']),
                'changed' => count($diff['changed']),
                'protected' => count(array_filter($diff['removed'], fn (array $row) => $row['protected'])),
            ],
            'status' => 'checked',
            'checked_at' => now(),
        ]);

        return $snapshot->refresh();
    }

    // ============================================================== الجلب من الشبكة

    /**
     * ⭐ جلب نسخة المصدر **عبر الشبكة** بعميل الفريمورك (`Http`) — بلا أيّ حزمة
     * خارجيّة (2.16-ج · 2.11-و).
     *
     * ثلاثة أشياء لا تُخترَق هنا:
     * 1) **الجلب يُنتج لقطة `pending` وحدها** — لا يدمج ولا يكتب حرفًا في
     *    `countries`/`governorates`. والقرار يبقى للمالك من الشاشة (12.7-د:
     *    «فحص فروق النسخة الجديدة **قبل** الدمج»).
     * 2) **يمرّ على `import()` نفسه** فيرث كلّ تحقّقاته — فلا بابَ ثانٍ للبيانات
     *    بقواعد أرخى.
     * 3) **كلّ فشلٍ يُقال ولا يُبتلَع**: مهلة · حالة HTTP غير ناجحة · جسمٌ ليس
     *    JSON · JSON بلا `countries` — لكلٍّ سببه مكتوبًا بالعربيّة على سجلّ
     *    الفحص، **ولا لقطة تُنشَأ**؛ فتبقى **اللقطة الأخيرة الناجحة كما هي**
     *    وفروقُها التي لم يقرّر فيها المالك بعد لا تضيع.
     *
     * والعنوان والمهلة وعدد المحاولات **إعدادات** لا أرقامٌ هنا (2.13).
     */
    public function fetch(?User $actor = null, string $trigger = 'manual'): CountrySourceCheck
    {
        $url = trim((string) setting('countries.source.url', ''));
        $timeout = max(1, (int) setting('countries.source.timeout', 20));
        $attempts = max(1, (int) setting('countries.source.retries', 2));
        $delay = max(0, (int) setting('countries.source.retry_delay_ms', 500));

        $base = ['source_url' => $url ?: null, 'attempts' => $attempts, 'trigger' => $trigger, 'created_by' => $actor?->id];

        if ($url === '') {
            return $this->recordFailure('no_url', (string) setting(
                'countries.source.error.no_url',
                'مافيش رابط للمصدر — اضبط «رابط جلب نسخة المصدر» من الإعدادات الأوّل، وبعدها جرّب الفحص تاني.',
            ), $base);
        }

        try {
            $response = Http::timeout($timeout)
                ->retry($attempts, $delay, throw: false)
                ->acceptJson()
                ->get($url);
        } catch (ConnectionException $exception) {
            // المهلة وانقطاع الاتّصال: أشيع فشلٍ وأهمّه — يُقال صريحًا لا يُبتلَع
            return $this->recordFailure('timeout', $this->fill((string) setting(
                'countries.source.error.timeout',
                'المصدر ما ردّش خلال {timeout} ثانية بعد {attempts} محاولة — جرّب تاني بعد شويّة أو زوّد المهلة من الإعدادات.',
            ), ['timeout' => $timeout, 'attempts' => $attempts]), $base);
        } catch (Throwable $exception) {
            // أيّ عطبٍ آخر في الطلب (رابط غير صالح · DNS · شهادة) — يُقال بسببه
            return $this->recordFailure('network', $this->fill((string) setting(
                'countries.source.error.network',
                'ما قدرناش نوصل للمصدر: {reason} — راجع الرابط في الإعدادات وجرّب تاني.',
            ), ['reason' => $exception->getMessage()]), $base);
        }

        if ($response->failed()) {
            return $this->recordFailure('http', $this->fill((string) setting(
                'countries.source.error.http',
                'المصدر ردّ بحالة {status} — راجع الرابط في الإعدادات أو استنّى وجرّب تاني.',
            ), ['status' => $response->status()]), $base + ['http_status' => $response->status()]);
        }

        $payload = json_decode($response->body(), true);

        if (! is_array($payload)) {
            return $this->recordFailure('body', (string) setting(
                'countries.source.error.body',
                'اللي رجع من المصدر مش JSON صالح — اتأكّد إنّ الرابط بيرجّع ملفّ النسخة نفسه مش صفحة.',
            ), $base + ['http_status' => $response->status()]);
        }

        try {
            // ⛔ نفس باب `import()` — نفس التحقّقات، ولقطة `pending` بلا أيّ دمج
            $snapshot = $this->import($payload, $actor);
        } catch (RuntimeException $exception) {
            return $this->recordFailure('shape', $this->fill((string) setting(
                'countries.source.error.shape',
                'النسخة اللي رجعت من المصدر ناقصة: {reason}',
            ), ['reason' => $exception->getMessage()]), $base + ['http_status' => $response->status()]);
        }

        return CountrySourceCheck::create($base + [
            'status' => 'ok',
            'failure' => null,
            'http_status' => $response->status(),
            'message' => (string) setting(
                'countries.source.check.fetched_text',
                'اتجابت نسخة المصدر ✓ — لسّه ما اتدمجتش، الفروق تحت.',
            ),
            'snapshot_id' => $snapshot->id,
        ]);
    }

    /**
     * الفحص الكامل: **جلب ⇐ فروق ⇐ وقوف**. لا دمج آليّ إطلاقًا — القرار للمالك
     * من الشاشة (12.7-د).
     *
     * وحين لا يوجد فرقٌ واحد: **سكوت** — لا لقطة مكرّرة تتراكم في السجلّ ولا
     * إشعار يوقظ أحدًا بلا سبب.
     */
    public function checkSource(?User $actor = null, string $trigger = 'manual'): CountrySourceCheck
    {
        $check = $this->fetch($actor, $trigger);

        if (! $check->succeeded()) {
            return $check;
        }

        $snapshot = $this->check($check->snapshot);
        $summary = (array) $snapshot->summary;

        $counts = [
            'added' => (int) ($summary['added'] ?? 0),
            'removed' => (int) ($summary['removed'] ?? 0),
            'changed' => (int) ($summary['changed'] ?? 0),
        ];

        if (array_sum($counts) === 0) {
            // مطابِقة لبياناتنا ⟵ لقطةٌ بلا فائدة، تُزال فلا يتضخّم السجلّ بنسخٍ
            // متطابقة. وهي لقطةُ هذه اللحظة وحدها (`pending`) — ولا تُمَسّ لقطةٌ
            // سابقة ولا صفٌّ في `countries`/`governorates`.
            $snapshot->delete();

            $check->update($counts + [
                'snapshot_id' => null,
                'message' => (string) setting(
                    'countries.source.check.none_text',
                    'المصدر مطابق لبياناتنا — مافيش فروق.',
                ),
            ]);

            return $check->refresh();
        }

        $check->update($counts + [
            'message' => $this->fill((string) setting(
                'countries.source.check.diff_text',
                'المصدر فيه فروق: مضاف {added} · محذوف {removed} · معدَّل {changed} — راجعها قبل الدمج.',
            ), $counts),
        ]);

        return $check->refresh();
    }

    /** آخر فحص — تعرضه الشاشة بنتيجته (نجح/فشل + السبب). */
    public function lastCheck(): ?CountrySourceCheck
    {
        return CountrySourceCheck::query()->latest('id')->first();
    }

    // ============================================================== الفحص الدوريّ

    /** هل الفحص الدوريّ مفعَّل أصلًا؟ — مفتاحٌ بلا قارئ يعني إيقافًا لا يوقِف. */
    public function checkEnabled(): bool
    {
        return (bool) setting('countries.source.check.enabled', true);
    }

    /** منطقة الفحص: مفتاحه الخاصّ أوّلًا ثمّ توقيت المنصّة (2.13). */
    public function checkTimezone(): string
    {
        $tz = trim((string) setting('countries.source.check.timezone', ''));

        return $tz !== '' ? $tz : (string) setting('system.timezone', 'Africa/Cairo');
    }

    /**
     * ⭐ هل حان موعد الفحص الآن؟ — **القرار كلّه هنا**.
     *
     * الجدولة في `routes/console.php` مسحةٌ كلّ ساعة لا موعدٌ مكتوبٌ فيها، لأنّ
     * تعبير الكرون يُقرأ مرّةً عند تحميل الملفّ فيتجمّد على القيمة القديمة بعد أن
     * يغيّر الأدمن الدوريّة من الإعدادات — فيبقى الموعد الجديد في الشاشة والتنفيذ
     * على القديم، أو لا يقع أبدًا: **فشلٌ صامت**. المسحة تسأل هذه الدالّة، وهي
     * وحدها تقرأ الإعدادات.
     */
    public function isCheckDue(?CarbonImmutable $now = null): bool
    {
        if (! $this->checkEnabled()) {
            return false;
        }

        $tz = $this->checkTimezone();
        $now = ($now ?? CarbonImmutable::now($tz))->setTimezone($tz);

        if ($now->day !== $this->checkDayOfMonth() || $now->hour !== $this->checkHour()) {
            return false;
        }

        $last = $this->lastCheckAt();

        // الدوريّة تمنع تكرار الفحص في نفس النافذة وفي الشهور غير المستحقّة
        return $last === null || ! $last->addMonthsNoOverflow($this->checkEveryMonths())->isAfter($now);
    }

    /** موعد الفحص القادم — يُعرَض في الشاشة وفي مخرجات الأمر بلا تخمين. */
    public function nextCheckAt(): CarbonImmutable
    {
        $tz = $this->checkTimezone();
        $now = CarbonImmutable::now($tz);
        $floor = $this->lastCheckAt()?->addMonthsNoOverflow($this->checkEveryMonths());
        $from = $floor && $floor->isAfter($now) ? $floor : $now;

        $candidate = $from->startOfMonth()->addDays($this->checkDayOfMonth() - 1)->setTime($this->checkHour(), 0);

        return $candidate->isBefore($from) ? $candidate->addMonthNoOverflow() : $candidate;
    }

    /**
     * إشعار داخل المنصّة لأصحاب `countries_data.import` وحدهم — لا لكلّ الأدمنز.
     * الإشعار الذي يصل لمن لا يملك التصرّف فيه ضجيجٌ لا فائدة (12.6-ب).
     *
     * ولا يُرسَل إلّا حين **توجد فروق فعلًا**: صفرُ فروقٍ = سكوت.
     *
     * @return int عدد من وصلهم الإشعار
     */
    public function notifyImporters(CountrySourceCheck $check): int
    {
        if (! $check->succeeded() || $check->differences() === 0) {
            return 0;
        }

        $body = $this->fill((string) setting(
            'countries.source.check.notify_body',
            'الفحص الدوريّ لقى فروق في المصدر: مضاف {added} · محذوف {removed} · معدَّل {changed} — راجعها قبل الدمج.',
        ), ['added' => $check->added, 'removed' => $check->removed, 'changed' => $check->changed]);

        $sent = 0;

        foreach ($this->importAdmins() as $admin) {
            Notifier::send(
                $admin,
                (string) setting('countries.source.check.notify_category', 'system'),
                (string) setting('countries.source.check.notify_title', 'فروق جديدة في بيانات الدول'),
                $body,
                route('admin.settings.index', ['tab' => 'countries']),
            );

            $sent++;
        }

        return $sent;
    }

    // ------------------------------------------------------------------ داخليّ (الجلب)

    /** لحظة آخر فحص — أيًّا كانت نتيجته: المحاولة الفاشلة محاولةٌ أيضًا. */
    private function lastCheckAt(): ?CarbonImmutable
    {
        $last = $this->lastCheck();

        return $last?->created_at
            ? CarbonImmutable::parse($last->created_at)->setTimezone($this->checkTimezone())
            : null;
    }

    private function checkDayOfMonth(): int
    {
        return max(1, (int) setting('countries.source.check.day_of_month', 1));
    }

    private function checkHour(): int
    {
        return (int) setting('countries.source.check.hour', 4);
    }

    private function checkEveryMonths(): int
    {
        return max(1, (int) setting('countries.source.check.every_months', 1));
    }

    /**
     * سجلّ فشلٍ بسببه — **وبلا لقطة**: اللقطة الأخيرة الناجحة تبقى كما هي.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function recordFailure(string $failure, string $message, array $attributes): CountrySourceCheck
    {
        return CountrySourceCheck::create($attributes + [
            'status' => 'failed',
            'failure' => $failure,
            'message' => $message,
            'snapshot_id' => null,
        ]);
    }

    /**
     * ملء متغيّرات نصٍّ **قرأه المتّصل من الإعدادات بمفتاحه الصريح**.
     *
     * ولماذا لا يأخذ المفتاح ويقرأه بنفسه؟ لأنّ `settings:coverage` يمسح
     * `setting('…')` بنصّها؛ فمفتاحٌ يمرّ عبر وسيطٍ يصير **مزروعًا بلا قارئ يراه
     * الحارس** — وحارسٌ لا يرى المفتاح لا يحرسه (2.13-ب).
     *
     * @param  array<string, mixed>  $replace
     */
    private function fill(string $text, array $replace = []): string
    {
        foreach ($replace as $name => $value) {
            $text = str_replace('{'.$name.'}', (string) $value, $text);
        }

        return $text;
    }

    /**
     * أصحاب `countries_data.import` — مباشرةً أو عبر أدوارهم.
     *
     * @return Collection<int, User>
     */
    private function importAdmins(): Collection
    {
        $limit = max(1, (int) setting('countries.source.check.max_recipients', 10));

        $direct = DB::table('permission_user')
            ->join('permissions', 'permissions.id', '=', 'permission_user.permission_id')
            ->where('permissions.key', 'countries_data.import')
            ->where('permission_user.effect', 'allow')
            ->pluck('permission_user.user_id');

        $viaRoles = DB::table('role_user')
            ->join('permission_role', 'permission_role.role_id', '=', 'role_user.role_id')
            ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
            ->where('permissions.key', 'countries_data.import')
            ->where('permission_role.effect', 'allow')
            ->pluck('role_user.user_id');

        $ids = $direct->merge($viaRoles)->unique()->values()->all();

        if ($ids === []) {
            return collect();
        }

        return User::query()->whereIn('id', $ids)->limit($limit)->get();
    }

    // ============================================================== الفروق

    /**
     * جدول فروق النسخة الجديدة: **مضاف / محذوف / معدَّل** — صفٌّ لكلّ تغيير،
     * وكلّ صفّ يحمل مفتاحه ليختاره المالك بمربّع اختيار قبل التنفيذ.
     *
     * @return array{added: array<int, array<string, mixed>>, removed: array<int, array<string, mixed>>, changed: array<int, array<string, mixed>>}
     */
    public function diff(CountrySourceSnapshot $snapshot): array
    {
        $source = $this->indexSource($snapshot);
        $countries = Country::query()->get()->keyBy(fn (Country $c) => mb_strtoupper((string) $c->iso2));
        $governorates = Governorate::query()->get()->groupBy('country_id');
        $userCounts = $this->userCounts();

        $rows = ['added' => [], 'removed' => [], 'changed' => []];

        // ------------------------------------------------ من المصدر إلى عندنا
        foreach ($source as $iso2 => $incoming) {
            $country = $countries->get($iso2);

            if (! $country) {
                $rows['added'][] = [
                    'key' => 'country:'.$iso2,
                    'kind' => 'country',
                    'change' => 'added',
                    'label' => $incoming['name_ar'].' ('.$iso2.')',
                    'after' => $incoming['fields'],
                    'before' => null,
                    'protected' => false,
                    'users' => 0,
                    'note' => 'دولة جديدة بـ'.count($incoming['governorates']).' محافظة.',
                ];

                continue;
            }

            if ($changes = $this->changedFields($country, $incoming['fields'], self::COUNTRY_FIELDS)) {
                $rows['changed'][] = [
                    'key' => 'country:'.$iso2,
                    'kind' => 'country',
                    'change' => 'changed',
                    'label' => $country->name_ar.' ('.$iso2.')',
                    'before' => $changes['before'],
                    'after' => $changes['after'],
                    'protected' => false,
                    'users' => (int) ($userCounts['countries'][$country->id] ?? 0),
                    'note' => 'تحديث بيانات — بلا مساس بالارتباطات.',
                ];
            }

            $mine = ($governorates[$country->id] ?? collect())->keyBy(fn (Governorate $g) => $this->slug($g->name_en ?: $g->name_ar));

            foreach ($incoming['governorates'] as $slug => $incomingGov) {
                $existing = $mine->get($slug);

                if (! $existing) {
                    $rows['added'][] = [
                        'key' => 'gov:'.$iso2.':'.$slug,
                        'kind' => 'governorate',
                        'change' => 'added',
                        'label' => $country->name_ar.' ← '.$incomingGov['name_ar'],
                        'before' => null,
                        'after' => $incomingGov,
                        'protected' => false,
                        'users' => 0,
                        'note' => 'محافظة جديدة.',
                    ];

                    continue;
                }

                if ($changes = $this->changedFields($existing, $incomingGov, self::GOVERNORATE_FIELDS)) {
                    $rows['changed'][] = [
                        'key' => 'gov:'.$iso2.':'.$slug,
                        'kind' => 'governorate',
                        'change' => 'changed',
                        'label' => $country->name_ar.' ← '.$existing->name_ar,
                        'before' => $changes['before'],
                        'after' => $changes['after'],
                        'protected' => false,
                        'users' => (int) ($userCounts['governorates'][$existing->id] ?? 0),
                        'note' => 'إعادة تسمية تحفظ الصفّ وارتباطاته كما هي (2.11-د).',
                    ];
                }
            }

            // ------------------------------------------ عندنا وليس في المصدر
            foreach ($mine as $slug => $existing) {
                if (isset($incoming['governorates'][$slug])) {
                    continue;
                }

                $rows['removed'][] = [
                    'key' => 'gov:'.$iso2.':'.$slug,
                    'kind' => 'governorate',
                    'change' => 'removed',
                    'label' => $country->name_ar.' ← '.$existing->name_ar,
                    'before' => ['name_ar' => $existing->name_ar],
                    'after' => null,
                    // ⛔ المحافظة لا تُخفى أبدًا — محميّة دائمًا مهما كان عدد أهلها
                    'protected' => true,
                    'users' => (int) ($userCounts['governorates'][$existing->id] ?? 0),
                    'note' => 'هتفضل زيّ ما هي — المحافظة لا تُخفى ولا تُحذف أبدًا.',
                ];
            }
        }

        foreach ($countries as $iso2 => $country) {
            if (isset($source[$iso2])) {
                continue;
            }

            $users = (int) ($userCounts['countries'][$country->id] ?? 0)
                + $this->usersUnderCountry($country, $userCounts);

            $rows['removed'][] = [
                'key' => 'country:'.$iso2,
                'kind' => 'country',
                'change' => 'removed',
                'label' => $country->name_ar.' ('.$iso2.')',
                'before' => ['name_ar' => $country->name_ar],
                'after' => null,
                'protected' => $users > 0,
                'users' => $users,
                'note' => $users > 0
                    ? 'محميّة: مرتبطة بـ'.$users.' مستخدم — ما تتغيّرش.'
                    : 'هتتخفي بس (بلا حذف) لو وافقت.',
            ];
        }

        return $rows;
    }

    // ============================================================== الدمج

    /**
     * دمج ما اختاره المالك. `dryRun` يعرض ما سيقع **بلا كتابة** (2.11-ط).
     *
     * والتحقّق بعد الكتابة ليس زينة: نعيد قراءة الصفوف ونقارن، **ونتأكّد أنّ
     * عدد المستخدمين المرتبطين بكلّ دولة ومحافظة لم يتغيّر** — فلو نقص واحد
     * انفجرت المعاملة ورجع كلّ شيء. هذا هو معنى «لا فقد» عمليًّا لا شعارًا.
     *
     * @param  array<int, string>  $keys
     * @return array<string, mixed>
     */
    public function merge(CountrySourceSnapshot $snapshot, array $keys, bool $dryRun = false, ?User $actor = null): array
    {
        $diff = $this->diff($snapshot);
        $selected = array_values(array_unique(array_map('strval', $keys)));

        $rows = collect($diff['added'])
            ->merge($diff['changed'])
            ->merge($diff['removed'])
            ->filter(fn (array $row) => in_array($row['key'], $selected, true))
            ->values();

        $report = [
            'dry_run' => $dryRun,
            'added' => 0,
            'updated' => 0,
            'hidden' => 0,
            'protected' => [],
            'skipped' => 0,
        ];

        if ($rows->isEmpty()) {
            return $report + ['verified' => true, 'message' => 'ما اخترتش أيّ صفّ — مافيش حاجة اتغيّرت.'];
        }

        $before = $this->userCounts();

        $apply = function () use ($rows, $snapshot, &$report) {
            $source = $this->indexSource($snapshot);

            foreach ($rows as $row) {
                match ($row['change']) {
                    'added' => $this->applyAdded($row, $source, $report),
                    'changed' => $this->applyChanged($row, $source, $report),
                    'removed' => $this->applyRemoved($row, $report),
                    default => $report['skipped']++,
                };
            }
        };

        if ($dryRun) {
            // معاينةٌ حقيقيّة: نكتب داخل معاملة ثمّ نتراجع، فنقيس الأثر الفعليّ
            // لا الأثر المتوقَّع — «Dry-run يعرض ما سيُنفَّذ بالضبط» (24.3).
            DB::beginTransaction();

            try {
                $apply();
                $verified = $this->verify($before);
            } finally {
                DB::rollBack();
            }

            return $report + ['verified' => $verified, 'message' => 'دي معاينة — مافيش حاجة اتكتبت.'];
        }

        DB::transaction(function () use ($apply, $before) {
            $apply();

            if (! $this->verify($before)) {
                // ⛔ لو نقص ارتباطُ مستخدمٍ واحد — تُلغى العمليّة كلّها
                throw new RuntimeException('التحقّق بعد الدمج فشل — رجّعنا كلّ حاجة زيّ ما كانت. جرّب تاني أو راجع النسخة.');
            }
        });

        $snapshot->update([
            'status' => 'merged',
            'merged_at' => now(),
            'report' => $report,
        ]);

        return $report + ['verified' => true, 'message' => 'اتدمجت النسخة بلا فقد ✓'];
    }

    // ------------------------------------------------------------------ داخليّ

    /** @param  array<string, mixed>  $row */
    private function applyAdded(array $row, array $source, array &$report): void
    {
        [$kind, $iso2, $slug] = array_pad(explode(':', $row['key']), 3, null);

        if ($kind === 'country') {
            $incoming = $source[$iso2] ?? null;

            if (! $incoming) {
                $report['skipped']++;

                return;
            }

            Country::updateOrCreate(['iso2' => $iso2], $incoming['fields'] + ['is_active' => true]);
            $report['added']++;

            return;
        }

        $country = Country::query()->where('iso2', $iso2)->first();
        $incoming = $source[$iso2]['governorates'][$slug] ?? null;

        if (! $country || ! $incoming) {
            $report['skipped']++;

            return;
        }

        Governorate::updateOrCreate(
            ['country_id' => $country->id, 'name_ar' => $incoming['name_ar']],
            ['name_en' => $incoming['name_en'], 'is_active' => true],
        );

        $report['added']++;
    }

    /** @param  array<string, mixed>  $row */
    private function applyChanged(array $row, array $source, array &$report): void
    {
        [$kind, $iso2, $slug] = array_pad(explode(':', $row['key']), 3, null);

        if ($kind === 'country') {
            $country = Country::query()->where('iso2', $iso2)->first();

            if (! $country) {
                $report['skipped']++;

                return;
            }

            $country->update($row['after']);
            $report['updated']++;

            return;
        }

        $country = Country::query()->where('iso2', $iso2)->first();

        $governorate = $country
            ? Governorate::query()->where('country_id', $country->id)->get()
                ->first(fn (Governorate $g) => $this->slug($g->name_en ?: $g->name_ar) === $slug)
            : null;

        if (! $governorate) {
            $report['skipped']++;

            return;
        }

        // إعادة التسمية تحفظ الصفّ ورقمه — فالارتباط بالمستخدم لا يُمَسّ (2.11-د)
        $governorate->update($row['after']);
        $report['updated']++;
    }

    /** @param  array<string, mixed>  $row */
    private function applyRemoved(array $row, array &$report): void
    {
        // ⛔ المحافظة لا تُخفى أبدًا · وما له مستخدم لا يُمَسّ · ولا حذف إطلاقًا
        if ($row['kind'] === 'governorate' || $row['protected']) {
            $report['protected'][] = $row['label'];

            return;
        }

        if (! setting('countries.no_auto_delete', true)) {
            // السياسة مطفأة: لا نحذف — 2.11-د تمنع الحذف بلا نقلٍ وتحقّق،
            // ولا وجهةَ يُنقَل إليها ارتباطُ مستخدمٍ بدولته. فنتركها لقرارٍ يدويّ.
            $report['skipped']++;

            return;
        }

        [, $iso2] = array_pad(explode(':', $row['key']), 2, null);

        Country::query()->where('iso2', $iso2)->update([
            'is_active' => false,
            'sync_hidden_at' => now(),
        ]);

        $report['hidden']++;
    }

    /**
     * التحقّق بعد الكتابة (2.11-هـ): **لا مستخدم فقد ارتباطه** بدولته ولا
     * بمحافظته، ولا نقص صفٌّ من الجدولين.
     *
     * @param  array{countries: array<int, int>, governorates: array<int, int>}  $before
     */
    private function verify(array $before): bool
    {
        $after = $this->userCounts();

        foreach (['countries', 'governorates'] as $bucket) {
            foreach ($before[$bucket] as $id => $count) {
                if ((int) ($after[$bucket][$id] ?? 0) !== (int) $count) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * كم مستخدمًا مرتبطًا بكلّ دولة وبكلّ محافظة — قياسٌ قبل وبعد.
     *
     * @return array{countries: array<int, int>, governorates: array<int, int>}
     */
    private function userCounts(): array
    {
        return [
            'countries' => User::query()
                ->whereNotNull('country_id')
                ->selectRaw('country_id, count(*) as total')
                ->groupBy('country_id')
                ->pluck('total', 'country_id')
                ->map(fn ($total) => (int) $total)
                ->all(),
            'governorates' => User::query()
                ->whereNotNull('governorate_id')
                ->selectRaw('governorate_id, count(*) as total')
                ->groupBy('governorate_id')
                ->pluck('total', 'governorate_id')
                ->map(fn ($total) => (int) $total)
                ->all(),
        ];
    }

    /** @param  array{countries: array<int, int>, governorates: array<int, int>}  $counts */
    private function usersUnderCountry(Country $country, array $counts): int
    {
        return Governorate::query()
            ->where('country_id', $country->id)
            ->pluck('id')
            ->sum(fn ($id) => (int) ($counts['governorates'][(int) $id] ?? 0));
    }

    /**
     * فهرسة النسخة: الدولة بكود ISO2، والمحافظة بمعرّفٍ مشتقّ من اسمها الإنجليزيّ
     * — فتغيير الاسم العربيّ يبقى «تعديلًا» لا «حذفًا وإضافة».
     *
     * @return array<string, array{name_ar: string, fields: array<string, mixed>, governorates: array<string, array<string, string>>}>
     */
    private function indexSource(CountrySourceSnapshot $snapshot): array
    {
        $indexed = [];

        foreach ((array) ($snapshot->payload['countries'] ?? []) as $country) {
            $iso2 = mb_strtoupper(trim((string) ($country['iso2'] ?? '')));

            if ($iso2 === '') {
                continue;
            }

            $governorates = [];

            foreach ((array) ($country['governorates'] ?? []) as $governorate) {
                $nameAr = trim((string) ($governorate['name_ar'] ?? ''));
                $nameEn = trim((string) ($governorate['name_en'] ?? $nameAr));

                if ($nameAr === '') {
                    continue;
                }

                $governorates[$this->slug($nameEn)] = ['name_ar' => $nameAr, 'name_en' => $nameEn];
            }

            $indexed[$iso2] = [
                'name_ar' => trim((string) ($country['name_ar'] ?? $iso2)),
                'fields' => [
                    'name_ar' => trim((string) ($country['name_ar'] ?? $iso2)),
                    'name_en' => trim((string) ($country['name_en'] ?? $iso2)),
                    'phone_code' => $country['phone_code'] ?? null,
                    'timezone' => (string) ($country['timezone'] ?? setting('countries.default_timezone', 'Africa/Cairo')),
                ],
                'governorates' => $governorates,
            ];
        }

        return $indexed;
    }

    /**
     * @param  array<string, mixed>  $incoming
     * @param  array<int, string>  $fields
     * @return array{before: array<string, mixed>, after: array<string, mixed>}|null
     */
    private function changedFields(Country|Governorate $model, array $incoming, array $fields): ?array
    {
        $before = [];
        $after = [];

        foreach ($fields as $field) {
            if (! array_key_exists($field, $incoming)) {
                continue;
            }

            $new = $incoming[$field];
            $old = $model->{$field};

            if ((string) $old !== (string) $new) {
                $before[$field] = $old;
                $after[$field] = $new;
            }
        }

        return $after === [] ? null : ['before' => $before, 'after' => $after];
    }

    /** معرّفٌ ثابت للمحافظة من اسمها الإنجليزيّ — بلا مكتبات خارجيّة. */
    private function slug(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[^\p{L}\p{N}]+/u', '-', $value) ?? $value;

        return trim((string) $value, '-') ?: 'x';
    }

    // ============================================================== الجدول

    /**
     * جدول الدول للعرض — بثلاثة فلاتر ظاهرة فقط (2.15-أ-4).
     *
     * @param  array<string, mixed>  $filters
     */
    public function table(array $filters = []): Collection
    {
        $counts = $this->userCounts();

        $governorates = Governorate::query()->get()->groupBy('country_id');

        return Country::query()
            ->when(($q = trim((string) ($filters['q'] ?? ''))) !== '', fn ($query) => $query->where(
                fn ($i) => $i->where('name_ar', 'like', '%'.$q.'%')
                    ->orWhere('name_en', 'like', '%'.$q.'%')
                    ->orWhere('iso2', 'like', '%'.$q.'%'),
            ))
            ->when(($status = (string) ($filters['status'] ?? '')) === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'hidden', fn ($query) => $query->where('is_active', false))
            ->orderBy('sort_order')
            ->orderBy('name_ar')
            ->limit((int) setting('countries.admin.per_page', 25))
            ->get()
            ->map(function (Country $country) use ($counts, $governorates) {
                $mine = $governorates[$country->id] ?? collect();

                return [
                    'model' => $country,
                    'governorates' => $mine->count(),
                    'users' => (int) ($counts['countries'][$country->id] ?? 0)
                        + $mine->sum(fn (Governorate $g) => (int) ($counts['governorates'][$g->id] ?? 0)),
                ];
            })
            ->when(! empty($filters['with_users']), fn (Collection $rows) => $rows->filter(fn (array $row) => $row['users'] > 0))
            ->values();
    }
}
