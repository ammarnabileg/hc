<?php

namespace App\Http\Controllers\Trainee;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Account\ProfileTabs;
use App\Services\Account\ProfileVisibility;
use App\Services\Gamification\LevelResolver;
use App\Services\Volunteer\Profile\ViewerLevel;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * بروفايل واحد بطبقتين (الدستور 10 · 10.0 · 13.4-م):
 * صفحة واحدة للجميع، عليها طبقة المتدرّب دائمًا وطبقة التطوّع لمن هو متطوّع.
 *
 * والهيدر **أفاتار بلا أيّ هالة** (2.10.1-16)، والتابات Sticky بترتيب نهائيّ:
 * نظرة عامّة · الإنجازات · الشهادات · خبراتي — **ثمّ تابات التطوّع** التي
 * يحقنها مجال التطوّع في `volunteer_profile_tabs`.
 */
class ProfileController extends Controller
{
    public function __construct(
        private readonly ProfileTabs $tabs,
        private readonly ProfileVisibility $visibility,
        // إضافات هيدر المتطوّع طبقةُ تطوّعٍ لا طبقةُ متدرّب — فمَن يحكمها هو
        // صاحب المستويات الأربعة عليها نفسه، بلا منطقٍ موازٍ هنا (10.0-ب · 13.4-م)
        private readonly ViewerLevel $volunteerLevels,
        // ⭐ المصدر الواحد لمستوى الحساب — والعمود المخبَّأ يُزامَن منه لا يُقرَأ بديلًا
        private readonly LevelResolver $levels,
    ) {}

    /** بروفايلي — نفس الصفحة تمامًا، بمستوى مشاهدة «صاحب البروفايل» */
    public function me(Request $request): View
    {
        return $this->render($request, $request->user());
    }

    /** البروفايل العامّ `/u/CODE` — يعمل من كلّ مكان (13.4-م) */
    public function show(Request $request, string $code): View
    {
        $owner = User::query()
            ->with(['country:id,name_ar', 'governorate:id,name_ar', 'streak'])
            ->where('code', $code)
            ->firstOrFail();

        return $this->render($request, $owner);
    }

    private function render(Request $request, User $owner): View
    {
        $viewer = $request->user();
        $level = $this->visibility->levelFor($viewer, $owner);
        $tab = ProfileTabs::normalize($request->string('tab')->toString());

        /*
         | ⭐ **مزامنة العمود المخبَّأ قبل الرسم** (ن-2). هيدر البروفايل ووثيقة
         | الإفادة يقرآن `users.level` مباشرةً، وكان السيدر يكتبه رقمًا اعتباطيًّا
         | (`3 + $index % 4`) لا صلة له بـXP — فيظهر «مستوى الحساب 5» فوق صفحةٍ
         | يقول رادارها «مستوى 3». والمزامنة تكتب فقط حين يختلف الرقم، فتُبقي
         | العمود **نسخةً مطابقة** للمصدر الواحد لا مصدرًا خامسًا.
         */
        $this->levels->sync($owner);

        /*
         | ⛔ لقب «مشرف» والبوزشن والكيان وشارة Rep ونادي التميّز (10.0-ب) كلّها
         | **من طبقة التطوّع**، و«لا يراها ولا يعرف بوجودها غيره» (10.0). فمن هو
         | خارج مستوياتها الأربعة لا تُحسَب له ولا تصل قالبَه أصلًا — لا عنصرٌ
         | يُرسَل ثمّ يُخفى.
         */
        $seesVolunteerLayer = $this->volunteerLevels->for($viewer, $owner) !== null;
        $membership = $seesVolunteerLayer ? $this->tabs->activeMembership($owner) : null;

        // تحميل كسول: التاب لا يُحمَّل إلّا عند فتحه (2.15-د · 2.7)
        $payload = match ($tab) {
            'achievements' => ['achievements' => $this->tabs->achievements($owner)],
            'certificates' => ['certificates' => $this->tabs->certificates($owner)],
            'experience' => ['experience' => $this->tabs->experience($owner)],
            default => ['overview' => $this->tabs->overview($owner, $viewer, $level)],
        };

        return view('profile.show', [
            'owner' => $owner,
            'viewer' => $viewer,
            'level' => $level,
            'levelLabel' => $this->visibility->levelLabel($level),
            'isOwner' => $level === ProfileVisibility::OWNER,
            'tab' => $tab,
            'tabs' => ProfileTabs::definitions(),
            'visibility' => $this->visibility,
            // إضافات هيدر المتطوّع تظهر فقط بعضويّة نشطة (10.0-ب)
            'membership' => $membership,
            'rep' => $membership ? $this->tabs->rep($owner) : null,
            'profileUrl' => $owner->profileUrl(),
            ...$payload,
        ]);
    }
}
