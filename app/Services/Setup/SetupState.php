<?php

namespace App\Services\Setup;

/**
 * حالة المعالج بين الخطوات (2.15: الفورم الطويل يتقسّم خطوات **بحفظ بينها**).
 * كلّ ما يكتبه المنصِّب يُحفَظ في الجلسة فورًا، فلو رجع خطوة يلاقي بياناته مكانها.
 */
class SetupState
{
    /** ترتيب الخطوات — وكلّ خطوة لا تُفتَح إلّا بعد ما قبلها */
    public const STEPS = ['requirements', 'database', 'migrate', 'platform', 'owner'];

    public function tokenVerified(): bool
    {
        return (bool) session('setup.token_ok', false);
    }

    public function markTokenVerified(): void
    {
        session()->put('setup.token_ok', true);
    }

    /** @return list<string> */
    public function completedSteps(): array
    {
        return (array) session('setup.completed', []);
    }

    public function completed(string $step): bool
    {
        return in_array($step, $this->completedSteps(), true);
    }

    public function complete(string $step): void
    {
        $steps = $this->completedSteps();

        if (! in_array($step, $steps, true)) {
            $steps[] = $step;
            session()->put('setup.completed', $steps);
        }
    }

    /** أوّل خطوة ناقصة — إليها يُحوَّل مَن حاول القفز للأمام */
    public function nextStep(): string
    {
        foreach (self::STEPS as $step) {
            if (! $this->completed($step)) {
                return $step;
            }
        }

        return 'finish';
    }

    /** هل كلّ ما قبل هذه الخطوة تمّ؟ */
    public function reachable(string $step): bool
    {
        $index = array_search($step, self::STEPS, true);

        // خطوة الإنهاء: لا تُفتَح إلّا بعد كلّ الخطوات بلا استثناء
        $previousSteps = $index === false ? self::STEPS : array_slice(self::STEPS, 0, (int) $index);

        foreach ($previousSteps as $previous) {
            if (! $this->completed($previous)) {
                return false;
            }
        }

        return true;
    }

    // ------------------------------------------------------------ المسوّدة

    public function draft(?string $key = null, mixed $default = null): mixed
    {
        $draft = (array) session('setup.draft', []);

        return $key === null ? $draft : ($draft[$key] ?? $default);
    }

    /** حفظ تلقائيّ بين الخطوات (2.15-ب) */
    public function remember(array $values): void
    {
        session()->put('setup.draft', array_merge((array) session('setup.draft', []), $values));
    }

    // ------------------------------ اختبار الاتّصال قبل الكتابة (شرط أمنيّ)

    /** بصمة بيانات الاتّصال — فأيّ تعديل حرف واحد يُبطل الاختبار السابق */
    public function fingerprint(array $credentials): string
    {
        return hash('sha256', implode('|', [
            $credentials['db_host'] ?? '',
            $credentials['db_port'] ?? '',
            $credentials['db_database'] ?? '',
            $credentials['db_username'] ?? '',
            $credentials['db_password'] ?? '',
        ]));
    }

    public function markConnectionVerified(array $credentials): void
    {
        session()->put('setup.db_fingerprint', $this->fingerprint($credentials));
    }

    public function connectionVerified(array $credentials): bool
    {
        $stored = session('setup.db_fingerprint');

        return is_string($stored) && hash_equals($stored, $this->fingerprint($credentials));
    }

    /** بعد الإنهاء: لا نُبقي كلمة سرّ قاعدة البيانات في الجلسة لحظة زائدة */
    public function flush(): void
    {
        session()->forget('setup');
    }
}
