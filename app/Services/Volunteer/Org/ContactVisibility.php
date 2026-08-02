<?php

namespace App\Services\Volunteer\Org;

use App\Models\ConsentRequest;
use App\Models\User;
use App\Services\Account\ProfileVisibility;
use Illuminate\Support\Collection;

/**
 * موافقة إظهار التواصل (13.4-م-2).
 *
 * القاعدة: الحقل الحسّاس **مخفيّ افتراضيًّا**، ويظهر باستثناءين فقط:
 *   (أ) الطالب **أبلاين** لصاحب البيانات — حقٌّ نظاميّ لا موافقة تُسحَب.
 *   (ب) **موافقة سارية** طلبها وأذِن بها صاحبها.
 * والمقنَّع **لا يُعرَض فراغًا** — يظهر معه زرّ «اطلب إظهار الرقم» (أخفّ نفسيًّا من الفراغ الصامت).
 */
final class ContactVisibility
{
    public function __construct(private readonly ProfileVisibility $privacy) {}

    /** الموافقات السارية لهذا الطالب — استعلام واحد للصفحة كلّها */
    public function grantedOwnerIds(User $viewer, string $field = 'phone'): Collection
    {
        return ConsentRequest::query()
            ->where('requester_id', $viewer->id)
            ->where('field', $field)
            ->where('status', 'granted')
            ->whereNull('revoked_at')
            ->where(fn ($q) => $q->whereNull('consent_expires_at')->orWhere('consent_expires_at', '>', now()))
            ->pluck('owner_id')
            ->unique();
    }

    /**
     * الاستثناء (ب) في 13.4-م-2: **إعداد الخصوصيّة** يسمح بالإظهار بلا طلب —
     * فالحقل الذي فتحه صاحبه «لكلّ المتطوّعين» أو «لكلّ المستخدمين» يُرى مباشرةً.
     * وكان يُتجاهَل تمامًا فيُقنَّع رقمٌ فتحه صاحبه بيده.
     */
    public function openByPrivacy(?User $viewer, User $owner, string $field = 'phone'): bool
    {
        return match ($this->privacy->visibilityOf($owner, $field)) {
            'all_users' => true,
            'all_volunteers' => (bool) $viewer?->isVolunteer(),
            default => false, // «مشرفيني فقط» — والزميل ليس مشرفًا
        };
    }

    /**
     * حالة رقم عضو بعينه.
     *
     * @param  list<int>  $uplineUserIds  سلسلة أبلاين صاحب الرقم
     * @return array{visible: bool, display: string, whatsapp: ?string, has_phone: bool}
     */
    public function forMember(User $viewer, User $owner, array $uplineUserIds, Collection $grantedOwnerIds): array
    {
        $phone = (string) ($owner->phone ?? '');
        $isSelf = $viewer->id === $owner->id;
        $isUpline = in_array($viewer->id, $uplineUserIds, true);
        $visible = $phone !== '' && ($isSelf || $isUpline
            || $grantedOwnerIds->contains($owner->id)
            || $this->openByPrivacy($viewer, $owner));

        return [
            'visible' => $visible,
            'display' => $phone === '' ? '' : ($visible ? $phone : $this->mask($phone)),
            'whatsapp' => $visible ? $this->whatsapp($phone, $owner) : null,
            'has_phone' => $phone !== '',
        ];
    }

    /** إخفاء جزئيّ افتراضيّ: `+20 10•• ••• 45` — يرفع إحساس الأمان فيرفع اكتمال البيانات (13.4-م) */
    public function mask(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        if (mb_strlen($digits) < 6) {
            return (string) setting('volunteer.contact.mask_fallback', '•••• ••• ••');
        }

        $dot = (string) setting('volunteer.contact.mask_char', '•');

        return '+'.substr($digits, 0, 2).' '.substr($digits, 2, 2)
            .str_repeat($dot, 2).' '.str_repeat($dot, 3).' '.substr($digits, -2);
    }

    /** رابط واتساب مباشر بقالب رسالة من الإعدادات — لا قناة شات داخل المنصّة (13.4-م) */
    public function whatsapp(string $phone, User $owner): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';
        $template = (string) setting('volunteer.contact.whatsapp_template', 'السلام عليكم :name، معاك زميلك من فريق التطوّع.');
        $text = str_replace(':name', $owner->shortName(), $template);

        return 'https://wa.me/'.$digits.'?text='.rawurlencode($text);
    }
}
