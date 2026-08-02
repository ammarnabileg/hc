<?php

namespace App\Services\Volunteer\Profile;

use App\Models\EmergencyContact;
use App\Models\User;
use App\Services\Account\PrivacyFields;
use App\Services\Account\ProfileVisibility;
use App\Services\Volunteer\Org\ContactVisibility;
use Illuminate\Support\Carbon;

/**
 * تاب «التواصل» (13.4-م-2).
 *
 * الفلسفة: **Masking افتراضيّ** يرفع إحساس الأمان فيرفع اكتمال البيانات،
 * و**الحقل المقفول لا يُعرَض فراغًا** بل يظهر مكانه زرّ «اطلب إظهار …» —
 * فالطلب على **البيانات** لا على الشخص، أخفّ نفسيًّا على الطرفين.
 */
final class ContactPanel
{
    public function __construct(
        private readonly ContactVisibility $contacts,
        private readonly ConsentFlow $consent,
        private readonly ProfileVisibility $privacy,
        private readonly ViewerLevel $levels,
    ) {}

    public function build(User $owner, ?User $viewer, string $level): array
    {
        $privileged = $this->levels->isPrivileged($level);

        return [
            'level' => $level,
            'fields' => [
                $this->field('phone', (string) $owner->phone, $owner, $viewer, $level),
                $this->field('email', (string) $owner->email, $owner, $viewer, $level),
            ],
            'country' => $owner->country?->name_ar,
            'governorate' => $owner->governorate?->name_ar,
            'flag' => $owner->country?->flag_path,
            'phone_code' => $owner->country?->phone_code,
            // التوقيت المحليّ الحاليّ من قاعدة الدول (2.5-ج) — فلا يُكلَّم أحد في وقت غير مناسب
            'local_time' => $this->localTime($owner),
            'timezone' => $owner->country?->timezone,
            // جهة الطوارئ **ظاهرة دائمًا للأبلاينز** (13.4-م-2)
            'emergency' => $privileged || $level === ViewerLevel::OWNER
                ? EmergencyContact::where('user_id', $owner->id)->get()
                : collect(),
            'shows_emergency' => $privileged || $level === ViewerLevel::OWNER,
            // شفافيّة مسبقة: العلم المسبق يمنع إحساس الخرق
            'transparency_note' => (string) setting(
                'volunteer.profile.contact.transparency',
                'مشرفيك يشوفوا بيانات تواصلك — ده حقّ نظاميّ للتنسيق، مش موافقة تتسحب.',
            ),
            'privacy_options' => PrivacyFields::visibilityLabels(),
            'pending' => $level === ViewerLevel::OWNER ? $this->consent->pendingFor($owner) : collect(),
        ];
    }

    /**
     * حالة حقل واحد: ظاهر · مقنَّع ومعه زرّ الطلب · مستنّي ردًّا · غير متاح.
     *
     * @return array<string, mixed>
     */
    public function field(string $key, string $value, User $owner, ?User $viewer, string $level): array
    {
        $label = ConsentFlow::fieldLabel($key);
        $has = trim($value) !== '';

        // صاحبه والأدمن والأبلاين المخوَّل: بلا موافقة — استثناء نظاميّ (13.4-م-2-أ)
        $byLevel = $level === ViewerLevel::OWNER || $this->levels->isPrivileged($level);
        // إعداد الخصوصيّة استثناء ثانٍ: الحقل المفتوح يُرى بلا طلب (13.4-م-2-ب)
        $byPrivacy = $viewer !== null && $this->contacts->openByPrivacy($viewer, $owner, $key);
        $byConsent = $viewer !== null && $this->consent->hasLiveGrant($viewer, $owner, $key);

        $visible = $has && ($byLevel || $byPrivacy || $byConsent);
        $latest = $viewer && ! $byLevel ? $this->consent->latestFor($viewer, $owner, $key) : null;
        $waiting = $latest?->status === 'pending' && $latest->request_expires_at > now();

        return [
            'key' => $key,
            'label' => $label,
            'has_value' => $has,
            'visible' => $visible,
            'display' => $visible ? $value : $this->mask($key, $value),
            'whatsapp' => $key === 'phone' && $visible ? $this->contacts->whatsapp($value, $owner) : null,
            'mailto' => $key === 'email' && $visible ? 'mailto:'.$value : null,
            // زرّ الإجراء بدل الفراغ الصامت (13.4-م-2)
            'can_request' => $has && ! $visible && $viewer !== null && $viewer->id !== $owner->id && ! $waiting,
            'waiting' => $waiting,
            // نصّ محايد دائمًا — لا يكشف رفضًا أبدًا
            'neutral' => ConsentFlow::neutralLabel(),
            'request_label' => str_replace(':field', $label, (string) setting(
                'volunteer.profile.contact.request_label',
                'اطلب إظهار :field',
            )),
            'privacy' => $this->privacy->visibilityOf($owner, $key),
            'privacy_label' => PrivacyFields::visibilityLabels()[$this->privacy->visibilityOf($owner, $key)] ?? '',
        ];
    }

    /** إخفاء جزئيّ: الرقم من خدمة القسم، والإيميل بأوّل حرفين ونطاقه */
    public function mask(string $key, string $value): string
    {
        if (trim($value) === '') {
            return '';
        }

        if ($key === 'phone') {
            return $this->contacts->mask($value);
        }

        $dot = (string) setting('volunteer.contact.mask_char', '•');
        [$name, $domain] = array_pad(explode('@', $value, 2), 2, '');

        return mb_substr($name, 0, 2).str_repeat($dot, 4).'@'.$domain;
    }

    /** التوقيت المحليّ الحاليّ لصاحب البروفايل — صيغته إعداد لا نصّ محروق (2.13) */
    public function localTime(User $owner): ?string
    {
        $timezone = $owner->country?->timezone;

        if (! $timezone) {
            return null;
        }

        return Carbon::now($timezone)->translatedFormat(
            (string) setting('volunteer.profile.contact.time_format', 'g:i A'),
        );
    }
}
