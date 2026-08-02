<?php

namespace App\Services\Volunteer\Profile;

use App\Models\Membership;
use App\Models\User;
use App\Services\Account\ConsentDirectory;
use App\Services\Volunteer\Org\RepBadge;
use Illuminate\View\View;

/**
 * حاقن طبقة التطوّع في **البروفايل الواحد** (10.0 · 13.4-م).
 *
 * لماذا حاقن لا صفحة ثانية؟ لأنّ الدستور قاطع: «صفحة بروفايل واحدة للجميع —
 * لا صفحتان ولا نسختان»، وعليها طبقتان. فمجال التطوّع يدفع تاباته في
 * `volunteer_profile_tabs` ومحتواها في `volunteer_profile_content`
 * **بلا أن يمسّ ملفًّا واحدًا من مجال الحساب**.
 *
 * وتابات التطوّع **لا تظهر ولا يُعرَف بوجودها** لغير المتطوّع المُسكَّن (10.0-د).
 */
final class ProfileTabInjector
{
    public function __construct(
        private readonly ViewerLevel $levels,
        private readonly OverviewPanel $overview,
        private readonly ContactPanel $contact,
        private readonly OrganizationPanel $organization,
        private readonly PerformancePanel $performance,
        private readonly NotesPanel $notes,
        private readonly ConsentDirectory $directory,
    ) {}

    public function compose(View $view): void
    {
        $data = $view->getData();
        $owner = $data['owner'] ?? null;

        if (! $owner instanceof User) {
            return;
        }

        $membership = $this->activeMembership($owner);

        // ليس متطوّعًا مُسكَّنًا ⇒ لا طبقة تطوّع أصلًا، ولا أثر لها في الصفحة
        if (! $membership) {
            return;
        }

        $viewer = auth()->user();
        $level = $this->levels->for($viewer, $owner);
        $tabs = VolunteerProfileTabs::definitions($level, $viewer);
        $allowed = array_column($tabs, 'key');

        $requested = VolunteerProfileTabs::normalize(request()->query('tab'));
        $active = $requested !== null && in_array($requested, $allowed, true) ? $requested : null;

        $factory = $view->getFactory();

        $factory->startPush('volunteer_profile_tabs', view('volunteer.profile.tabs', [
            'owner' => $owner,
            'tabs' => $tabs,
            'active' => $active,
            'isOwner' => $level === ViewerLevel::OWNER,
        ])->render());

        $factory->startPush('volunteer_profile_content', view('volunteer.profile.content', [
            'owner' => $owner,
            'viewer' => $viewer,
            'membership' => $membership,
            'level' => $level,
            'levelLabel' => $this->levels->label($level),
            'active' => $active,
            'header' => $this->headerActions($owner, $viewer, $level, $membership),
            'panel' => $active ? $this->panel($active, $owner, $viewer, $level, $membership) : [],
        ])->render());

        /*
         | التاب المفتوح واحد لا اثنان: حين يُفتَح تاب تطوّع يُخفى محتوى تاب المتدرّب
         | الافتراضيّ — بقاعدة CSS واحدة تُدفَع في الهيدر، بلا لمس ملفّ البروفايل
         | وبلا وميض على الشاشة (2.17-د).
         */
        if ($active !== null) {
            $factory->startPush(
                'head',
                '<style>main > .sticky-bar ~ *:not([data-volunteer-profile]){display:none}</style>',
            );
        }
    }

    /** العضويّة النشطة — بها تظهر طبقة التطوّع كلّها */
    private function activeMembership(User $owner): ?Membership
    {
        return Membership::query()
            ->with(['entity:id,name_ar,parent_id', 'entity.parent:id,name_ar', 'position:id,name_ar,rank', 'upline.user:id,name,code', 'upline.position:id,name_ar'])
            ->where('user_id', $owner->id)
            ->where('status', 'active')
            ->orderByDesc('is_primary')
            ->first();
    }

    /**
     * أزرار الهيدر (13.4-م): شكر · واتساب · **سجلّ المشرف** · دروب-داون «إجراءات».
     * و«إجراءات» هو **المدخل المنصوص عليه لمعاملة السلوك** (13.4-ن-هـ).
     */
    private function headerActions(User $owner, ?User $viewer, string $level, Membership $membership): array
    {
        $score = (float) ($owner->repScore?->score ?? 0);
        $phone = (string) ($owner->phone ?? '');
        $canSeePhone = $this->contact->field('phone', $phone, $owner, $viewer, $level);

        return [
            'rep_state' => RepBadge::state($score),
            'rep_label' => RepBadge::label($score),
            'is_club' => RepBadge::isClubMember($score),
            'can_kudos' => $viewer !== null && $viewer->id !== $owner->id && $viewer->allows('kudos.create'),
            'whatsapp' => $canSeePhone['whatsapp'],
            'whatsapp_locked' => $phone !== '' && $canSeePhone['whatsapp'] === null,
            // «سجلّ المشرف» — سجلّ التدقيق على هذا الشخص، للمخوَّل به وحده
            'can_see_audit' => $viewer?->allows('audit_logs.view', $owner) === true,
            'actions' => $this->actionLinks($owner, $viewer, $membership),
            'level_label' => $this->levels->label($level),
            'active_consents' => $level === ViewerLevel::OWNER ? $this->directory->activeFor($owner)->count() : 0,
        ];
    }

    /**
     * قائمة «إجراءات» — والعنصر الذي لا يملكه المستخدم **يُخفى فعلًا** (2.15-أ-7).
     *
     * @return array<int, array{key:string,label:string,url:string}>
     */
    private function actionLinks(User $owner, ?User $viewer, Membership $membership): array
    {
        if (! $viewer) {
            return [];
        }

        $links = [];

        // معاملة سلوك: المنفذ الوحيد لتقدير بشريّ على Rep (13.4-ن-هـ)
        if ($viewer->allows('rep_manual.create', $owner)) {
            $links[] = [
                'key' => 'behavior',
                'label' => (string) setting('volunteer.profile.actions.behavior', 'معاملة سلوك (Rep)'),
                'url' => route('admin.volunteer.rep', ['user' => $owner->code]),
            ];
        }

        if ($viewer->allows('memberships.edit', $owner)) {
            $links[] = [
                'key' => 'org',
                'label' => (string) setting('volunteer.profile.actions.org', 'نقل / ترقية / تغيير أبلاين'),
                'url' => route('admin.volunteer.org', ['entity' => $membership->entity_id]),
            ];
        }

        if ($viewer->allows('offboarding.create', $owner)) {
            $links[] = [
                'key' => 'offboarding',
                'label' => (string) setting('volunteer.profile.actions.offboarding', 'إنهاء خدمة / تعليق'),
                'url' => route('admin.volunteer.offboarding'),
            ];
        }

        return $links;
    }

    /** تحميل كسول: بيانات التاب المفتوح وحده تُحسَب (2.15-د · 2.7) */
    private function panel(string $tab, User $owner, ?User $viewer, string $level, Membership $membership): array
    {
        return match ($tab) {
            VolunteerProfileTabs::CONTACT => $this->contact->build($owner, $viewer, $level),
            VolunteerProfileTabs::ORGANIZATION => $this->organization->build($owner, $viewer, $level, $membership),
            VolunteerProfileTabs::PERFORMANCE => $this->performance->build($owner, $viewer, $level),
            VolunteerProfileTabs::NOTES => [
                'notes' => $this->notes->list($owner),
                'audit' => $level === ViewerLevel::ADMIN ? $this->notes->auditTrail($owner) : collect(),
                'can_write' => $viewer?->allows('admin_notes.create', $owner) === true,
            ],
            default => $this->overview->build($owner, $viewer, $level, $membership),
        };
    }
}
