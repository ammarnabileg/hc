@inject('deletion', 'App\Services\Security\AccountDeletion')

{{--
    منطقة الخطر (2.3) — آخر قسم في «الخصوصيّة والأمان» عن قصد: مايتشافش بالغلط.

    ثلاث قواعد: **شرح ما يحدث للبيانات قبل الضغط لا بعده** · **تأكيد برمز رباعيّ**
    يوصل بريد صاحب الحساب · و**Soft-delete** بمهلة تراجع لا محو فوريّ.
--}}
<section class="card p-4 mt-6" style="border-color: var(--color-state-danger)" id="danger-zone">
    <div class="flex items-center gap-2 mb-3">
        <x-state-badge state="danger" :label="setting('account.delete.badge', 'منطقة الخطر')" />
        <h2 class="font-bold text-sm">{{ setting('account.delete.title', 'حذف الحساب') }}</h2>
    </div>

    <p class="text-sm mb-3" style="color: var(--text-muted)">
        {{ setting('account.delete.intro', 'ده قرار كبير — اقرا الأوّل بيحصل إيه لبياناتك:') }}
    </p>

    <ul class="text-sm space-y-1 mb-4 list-disc list-inside" style="color: var(--text-muted)">
        @foreach ($deletion->dataNotes() as $note)
            <li>{{ $note }}</li>
        @endforeach
    </ul>

    <p class="text-sm mb-4 rounded-xl px-3 py-2" style="background: var(--surface-sunken)">
        {{ str_replace(
            '{days}',
            (string) $deletion->graceDays(),
            setting('account.delete.grace_text', 'عندك {days} يوم تقدر ترجع فيهم: كلّم الدعم وهنرجّع حسابك زيّ ما هو.'),
        ) }}
    </p>

    <div class="grid gap-3 sm:grid-cols-2">
        {{-- خطوة 1: اطلب الرمز --}}
        <form method="post" action="{{ route('settings.danger.code') }}">
            @csrf
            <button type="submit"
                    class="btn w-full rounded-xl py-2 text-sm font-semibold motion-standard"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                {{ setting('account.delete.code_action', 'ابعتلي رمز التأكيد') }}
            </button>
        </form>

        {{-- خطوة 2: أكّد بالرمز الرباعيّ --}}
        <form method="post" action="{{ route('settings.danger.destroy') }}" class="flex gap-2"
              onsubmit="return confirm('{{ setting('account.delete.confirm_text', 'متأكّد؟ الحساب هيتقفل دلوقتي.') }}')">
            @csrf
            @method('DELETE')
            <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code"
                   maxlength="{{ (int) setting('auth.otp.length', 4) }}"
                   placeholder="{{ setting('account.delete.code_placeholder', 'الرمز') }}"
                   class="w-full rounded-xl px-3 py-2 text-center tabular-nums"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"
                   dir="ltr">
            <button type="submit"
                    class="btn shrink-0 rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                    style="background: var(--color-state-danger); color: #fff">
                {{ setting('account.delete.action', 'احذف حسابي') }}
            </button>
        </form>
    </div>

    @error('code')
        <p class="text-xs mt-2" style="color: var(--color-state-danger)">{{ $message }}</p>
    @enderror
</section>
