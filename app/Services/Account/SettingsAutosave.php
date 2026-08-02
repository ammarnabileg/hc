<?php

namespace App\Services\Account;

use App\Models\ConsentRequest;
use App\Models\User;
use App\Services\Images\AvatarProcessor;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * الحفظ التلقائيّ لحقل واحد مع «اتحفظ ✓» بجواره (الدستور 2.17-ب · 24.5).
 * قائمة الحقول مقفولة: ما ليس فيها لا يُحفَظ — فلا يُعدَّل عمودٌ بالخطأ.
 */
class SettingsAutosave
{
    /** الحقول المسموح حفظها تلقائيًّا ⟵ قواعد التحقّق */
    public function rules(): array
    {
        return [
            // مجموعة الحساب
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'country_id' => ['nullable', 'exists:countries,id'],
            'governorate_id' => ['nullable', 'exists:governorates,id'],
            'locale' => ['required', 'in:ar,en'],

            // مجموعة المظهر
            'theme' => ['required', 'in:dark,light'],
            'simple_mode' => ['required', 'boolean'],
            'advanced_mode' => ['required', 'boolean'],

            // مجموعة الصوت والحركة
            'sound_enabled' => ['required', 'boolean'],
            /*
             | ⭐ الحركة تُضبَط من **داخل المنصّة** لا من تفضيل نظام التشغيل
             | (2.3 · 2.14-ب): `prefers-reduced-motion` مرفوض نصًّا لأنّه كان
             | يُخفي الكونفيتي فتُلغى ذروة 2.9-6 بصمت. والافتراضيّ **مفعَّل**.
             */
            'motion_enabled' => ['required', 'boolean'],

            // بيانات التواصل: تغييرها يُبطِل الموافقات السارية (13.4-م)
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:32'],
        ];
    }

    public function isAllowed(string $field): bool
    {
        return array_key_exists($field, $this->rules());
    }

    /** الحقول التي يترتّب على تغييرها إبطال موافقات إظهار التواصل */
    public function invalidatesConsents(string $field): bool
    {
        return in_array($field, ['email', 'phone'], true);
    }

    /**
     * حفظ حقل واحد وإرجاع ما يحتاجه الردّ الفوريّ (2.17-ب).
     *
     * @return array{value:mixed, revoked:int}
     *
     * @throws ValidationException
     */
    public function save(User $user, string $field, mixed $value): array
    {
        if (! $this->isAllowed($field)) {
            throw ValidationException::withMessages([
                'field' => 'الحقل ده مش قابل للتعديل من هنا.',
            ]);
        }

        $rules = [$field => $this->rules()[$field]];

        if ($field === 'email') {
            $rules['email'][] = 'unique:users,email,'.$user->id;
        }

        if ($field === 'phone') {
            $rules['phone'][] = 'unique:users,phone,'.$user->id;
        }

        $value = $this->cast($field, $value);

        Validator::make([$field => $value], $rules, $this->messages(), $this->attributes())->validate();

        $revoked = 0;

        if ($this->invalidatesConsents($field) && $user->{$field} !== $value) {
            // البيانات الجديدة لا ترث موافقة قديمة (13.4-م) — والقفل في صمت
            $revoked = $this->closeConsents($user, $field === 'phone' ? 'phone' : 'email');
        }

        // المحافظة تتبع الدولة: تغيير الدولة يُفرّغ محافظةً لا تنتمي لها
        if ($field === 'country_id' && $user->governorate_id) {
            $user->forceFill(['governorate_id' => null]);
        }

        $user->forceFill([$field => $value])->save();

        return ['value' => $value, 'revoked' => $revoked];
    }

    /** عدد مَن سيتوقّف عرض بياناتك لهم لو غيّرت الرقم/البريد (13.4-م) */
    public function activeConsentCount(User $user): int
    {
        return $this->activeConsentsQuery($user)->count();
    }

    private function closeConsents(User $user, string $field): int
    {
        return $this->activeConsentsQuery($user)
            ->where('field', $field)
            ->update(['status' => 'expired', 'consent_expires_at' => now()]);
    }

    private function activeConsentsQuery(User $user)
    {
        return ConsentRequest::query()
            ->where('owner_id', $user->id)
            ->where('status', 'granted')
            ->whereNull('revoked_at')
            ->where(fn ($q) => $q->whereNull('consent_expires_at')->orWhere('consent_expires_at', '>', now()));
    }

    private function cast(string $field, mixed $value): mixed
    {
        return match ($field) {
            'simple_mode', 'advanced_mode', 'sound_enabled', 'motion_enabled' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'country_id', 'governorate_id' => $value === '' || $value === null ? null : (int) $value,
            'phone' => $value === '' ? null : trim((string) $value),
            default => is_string($value) ? trim($value) : $value,
        };
    }

    /** أسماء الحقول بالعربيّة — الواجهة كلّها عربيّة بنبرة واحدة (BUILD-5) */
    private function attributes(): array
    {
        return [
            'name' => 'الاسم',
            'email' => 'البريد الإلكترونيّ',
            'phone' => 'رقم الموبايل',
            'country_id' => 'الدولة',
            'governorate_id' => 'المحافظة',
            'locale' => 'اللغة',
            'theme' => 'المظهر',
            'simple_mode' => 'الوضع المبسّط',
            'advanced_mode' => 'الوضع المتقدّم',
            'sound_enabled' => 'صوت المنصّة',
            'motion_enabled' => 'حركة الواجهة',
        ];
    }

    /** رسالة الخطأ = ماذا حدث + ماذا تفعل، بلا أكواد تقنيّة (2.17-ب) */
    private function messages(): array
    {
        return [
            'required' => 'الحقل ده مطلوب — اكتب قيمة وجرّب تاني.',
            'name.min' => 'الاسم قصيّر شوية — اكتب اسمك كامل.',
            'name.max' => 'الاسم طويل أوي — اختصره شوية.',
            'email.email' => 'البريد ده شكله مش مظبوط — راجعه وجرّب تاني.',
            'email.unique' => 'البريد ده مستخدَم في حساب تاني.',
            'phone.unique' => 'رقم الموبايل ده مستخدَم في حساب تاني.',
            'exists' => 'الاختيار ده مش متاح — اختار من القائمة.',
            'in' => 'الاختيار ده مش من الخيارات المتاحة.',
            'boolean' => 'الاختيار ده لازم يبقى مفعَّل أو متوقّف.',
            'max' => 'القيمة دي أطول من المسموح.',
        ];
    }

    /**
     * الأفاتار بقصّ ومعاينة (24.5): الواجهة تقصّ مربّعًا وترسله كـData URL،
     * وبلا JS يُرفَع الملفّ كما هو — فالصفحة تعمل في الحالتين (2.1).
     */
    public function storeAvatar(User $user, ?string $dataUrl, mixed $file): ?string
    {
        $old = $user->avatar_path;
        $path = null;

        if ($dataUrl && preg_match('/^data:image\/(png|jpeg|webp);base64,/', $dataUrl, $m)) {
            $binary = base64_decode(substr($dataUrl, strpos($dataUrl, ',') + 1), true);

            if ($binary === false || strlen($binary) > $this->avatarMaxKb() * 1024) {
                throw ValidationException::withMessages([
                    'avatar' => 'الصورة كبيرة شوية. اختار صورة أصغر وجرّب تاني.',
                ]);
            }

            $path = 'avatars/'.$user->id.'-'.now()->timestamp.'.'.($m[1] === 'jpeg' ? 'jpg' : $m[1]);
            Storage::disk('public')->put($path, $binary);
        } elseif ($file) {
            $path = $file->store('avatars', 'public');
        }

        if (! $path) {
            return null;
        }

        $user->forceFill(['avatar_path' => $path])->save();

        // ⭐ قصّ مربّع + ثلاث نسخ (500 · 150 · 50) عند الرفع مباشرةً (2.7)
        app(AvatarProcessor::class)->apply($user, $path);

        if ($old && $old !== $path) {
            Storage::disk('public')->delete($old);
        }

        return $path;
    }

    public function avatarMaxKb(): int
    {
        return max(1, (int) setting('account.avatar.max_kb', 2048));
    }
}
