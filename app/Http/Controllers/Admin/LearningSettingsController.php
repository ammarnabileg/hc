<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\Admin\System\LearningUxSettings;
use App\Services\Admin\System\SettingsRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 🖥️ إعدادات التعلّم (24.4 · 12.2.2 `learning_ux.*`) — الضبط العامّ لتجربة
 * التعلّم والتعليقات والملاحظات ومؤثّراتها، بخمس مجموعات جانبيّة.
 *
 * والصلاحيّة `learning_ux.*` من مصفوفة 12.2.2 حرفيًّا — لا `learning_settings.manage`
 * المذكورة في نثر 24.4 وحدها بلا مقابل في المصفوفة (15 · 6077: القسم الوظيفيّ
 * يحكم على بلوبرنت الشاشة عند التعارض).
 */
class LearningSettingsController extends Controller
{
    public function __construct(
        private readonly LearningUxSettings $learningUx,
        private readonly SettingsRegistry $registry,
    ) {}

    public function index(Request $request): View
    {
        $groups = $this->learningUx->groups();
        $group = $request->string('group')->toString();

        if (! array_key_exists($group, $groups)) {
            $group = (string) array_key_first($groups);
        }

        return view('admin.content.learning-settings.index', [
            'groups' => $groups,
            'group' => $group,
            'settings' => $this->learningUx->settingsOf($group),
            'registry' => $this->registry,
            'canEdit' => $request->user()->allows('learning_ux.edit') || $request->user()->allows('learning_ux.manage'),
        ]);
    }

    public function save(Request $request): JsonResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string'],
            'value' => ['nullable'],
        ]);

        // ⭐ لا يُكتَب إلّا مفتاحٌ من مجموعات هذه الشاشة نفسها — منعًا لتلويث جدول الإعدادات (2.13)
        abort_unless(in_array($data['key'], $this->learningUx->allKeys(), true), 404);

        $setting = Setting::query()->where('key', $data['key'])->firstOrFail();
        $result = $this->registry->save($setting, $data['value'], $request->user());

        return response()->json($result, $result['saved'] ? 200 : 422);
    }

    public function resetField(Request $request): JsonResponse
    {
        $data = $request->validate(['key' => ['required', 'string']]);

        abort_unless(in_array($data['key'], $this->learningUx->allKeys(), true), 404);

        $setting = Setting::query()->where('key', $data['key'])->firstOrFail();
        $result = $this->registry->reset($setting, $request->user());

        return response()->json($result, $result['saved'] ? 200 : 422);
    }

    /** ↺ «إعادة الكلّ للافتراضيّ» — لمجموعة واحدة، بتأكيد من الواجهة قبل الطلب */
    public function resetGroup(Request $request): JsonResponse
    {
        $data = $request->validate(['group' => ['required', 'string']]);

        abort_unless(array_key_exists($data['group'], $this->learningUx->groups()), 404);

        $count = $this->learningUx->resetGroup($data['group'], $request->user());

        return response()->json([
            'reset' => true,
            'message' => strtr((string) setting('admin.content.learning_settings.reset_group_ok', 'رجعت :count إعداد للافتراضيّ ✓'), [':count' => (string) $count]),
        ]);
    }
}
