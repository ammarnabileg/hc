<?php

namespace App\Services\Admin\Volunteer;

use App\Models\User;
use Illuminate\View\View;

/**
 * حاقن تاب «التطوّع» في صفحة المستخدم بالأدمن (12.1 · 1103) — على نمط
 * `ProfileTabInjector` (`app/Services/Volunteer/Profile/ProfileTabInjector.php`)
 * لكن على شاشة **الأدمن** `admin.users.show` لا البروفايل الموحَّد.
 *
 * والتاب معرَّفٌ فعلًا في `UserDirectory::tabsFor()` (بصلاحيّة `memberships.view`
 * القابلة للتعديل) وفي `show.blade.php` (`case 'volunteer'` بستاك
 * `admin_user_volunteer_tab`) — وهذا الحاقن وحده يملأ الستاك، بنفس صلاحيّة
 * ظهور التاب حتى لا يتناقض الاثنان (تاب ظاهر بمحتوًى مخفيّ أو العكس).
 */
final class AdminVolunteerTabInjector
{
    public function __construct(private readonly AdminVolunteerRecord $record) {}

    public function compose(View $view): void
    {
        $data = $view->getData();

        // كسل حقيقيّ: لا استعلام ولا رقاقة تُدفَع إلّا وقت فتح هذا التاب بعينه (2.15-ب)
        if (($data['tab'] ?? null) !== 'volunteer') {
            return;
        }

        $subject = $data['user'] ?? null;

        if (! $subject instanceof User) {
            return;
        }

        $viewer = auth()->user();
        $permission = (string) setting('admin.user_tabs.volunteer_permission', 'memberships.view');

        // نفس صلاحيّة ظهور التاب في `UserDirectory::tabsFor()` — لا صلاحيّة ثانية توقعه في تناقض
        if (! $viewer || ! $viewer->allows($permission)) {
            return;
        }

        $view->getFactory()->startPush(
            'admin_user_volunteer_tab',
            view('admin.users.partials.tab-volunteer', [
                'subject' => $subject,
                'record' => $this->record->build($subject),
            ])->render(),
        );
    }
}
