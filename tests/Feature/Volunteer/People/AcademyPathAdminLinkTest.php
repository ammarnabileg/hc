<?php

namespace Tests\Feature\Volunteer\People;

use App\Models\Course;
use App\Models\LearningPath;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Access\AccessEngine;
use Illuminate\Support\Facades\DB;

/**
 * الأكاديمية (13.4-ل): «الأدمن ينشئ مسار أكاديمية بنفس إعدادات المسارات … ويربطه
 * بقسم/أكثر/الكل» + «مسار الشهادة المستهدَف (Nullable)».
 *
 * الفجوة المرصودة: عمودا `learning_paths.is_academy`/`target_path_id` وجدول
 * `academy_path_entity` موجودون فعلًا (يستهلكهم `AcademyService`/`AcademyController`
 * بالكامل)، **لكن `PathAdminController::validated()` لا يعرفها إطلاقًا** — فلا حقلٌ
 * في فورم المسار يكتبها، ولا الخدمة تحفظها. فمسارٌ أكاديميّ **لا يمكن إنشاؤه من
 * الواجهة إطلاقًا** رغم أنّ كلّ الجهاز الذي يستهلكه جاهزٌ ينتظر. هذا الملفّ يثبت
 * أنّ الفورم صار يكتبها فعلًا، وأنّ لهما **أثرًا حقيقيًّا** على مَن يرى المسار.
 */
class AcademyPathAdminLinkTest extends PeopleTestCase
{
    /**
     * ⭐ الفورم الحاليّ (لا شاشة جديدة) صار يحفظ العلامة + مسار الشهادة المستهدَف +
     * الأقسام المربوطة، وتُقرأ نفس القيم في فورم التعديل (Round-trip).
     */
    public function test_admin_can_flag_a_path_academic_link_it_to_departments_and_see_it_reflected_on_edit(): void
    {
        $admin = $this->userWith(['paths.list', 'paths.view', 'paths.create', 'paths.edit']);

        $target = LearningPath::create([
            'slug' => 'target-'.str()->random(6),
            'name_ar' => 'مسار الشهادة الطبيعيّ',
            'status' => 'published',
        ]);

        $media = $this->makeEntity('قسم الإعلام');
        $design = $this->makeEntity('قسم التصميم');

        $this->actingAs($admin)->post(route('admin.paths.store'), [
            'name_ar' => 'مسار الأكاديمية للاختبار',
            'status' => 'draft',
            'forced_order' => '0',
            'is_academy' => '1',
            'target_path_id' => (string) $target->id,
            'entity_ids' => [$media->id, $design->id],
        ])->assertRedirect(route('admin.paths.index'));

        $path = LearningPath::query()->where('name_ar', 'مسار الأكاديمية للاختبار')->firstOrFail();

        $this->assertTrue((bool) $path->is_academy, 'العلامة لم تُحفَظ — والمسار لن يظهر أبدًا في تاب الأكاديمية.');
        $this->assertSame($target->id, $path->target_path_id, 'مسار الشهادة المستهدَف لم يُحفَظ.');

        $linkedEntityIds = DB::table('academy_path_entity')->where('learning_path_id', $path->id)->pluck('entity_id')->sort()->values()->all();
        $this->assertSame(collect([$media->id, $design->id])->sort()->values()->all(), $linkedEntityIds,
            'الربط بالأقسام لم يُحفَظ في academy_path_entity.');

        // ⭐ Round-trip: نفس القيم تُقرَأ في فورم التعديل — لا تُكتَب فقط بلا عرض
        $html = $this->actingAs($admin)->get(route('admin.paths.index'))->assertOk()->getContent();
        $payload = $this->pathPayloadFrom($html, $path->id);

        $this->assertTrue($payload['is_academy']);
        $this->assertSame($target->id, $payload['target_path_id']);
        $this->assertSame(collect([$media->id, $design->id])->sort()->values()->all(), collect($payload['entity_ids'])->sort()->values()->all());

        // ⛔ وتعديل المسار لا يستطيع استهداف نفسه بمسار شهادة (منع دائرة بلا معنى)
        $this->actingAs($admin)->put(route('admin.paths.update', $path), [
            'name_ar' => $path->name_ar,
            'status' => 'draft',
            'is_academy' => '1',
            'target_path_id' => (string) $path->id,
        ])->assertSessionHasErrors('target_path_id');
    }

    /**
     * ⭐ الأثر الحقيقيّ (لا مجرّد عمود): العلامة + الربط بالقسم يتحكّمان فعليًّا
     * فيمَن يرى المسار في تاب الأكاديمية بلوحة التطوّع (`AcademyService::paths`).
     */
    public function test_the_flag_and_department_link_actually_gate_who_sees_the_path_in_the_academy_tab(): void
    {
        $admin = $this->userWith(['paths.create']);

        $media = $this->makeEntity('قسم الإعلام');
        $design = $this->makeEntity('قسم التصميم');

        $volunteerInMedia = $this->makeUser('متطوّع الإعلام');
        $this->makeMembership($volunteerInMedia, $media);
        $volunteerInMedia = $this->userWithPermissionsOn($volunteerInMedia, ['academy_paths.list', 'academy_paths.view']);

        $volunteerInDesign = $this->makeUser('متطوّع التصميم');
        $this->makeMembership($volunteerInDesign, $design);
        $volunteerInDesign = $this->userWithPermissionsOn($volunteerInDesign, ['academy_paths.list', 'academy_paths.view']);

        $course = Course::create(['slug' => 'c-'.str()->random(6), 'name_ar' => 'تدريب الاختبار', 'status' => 'published']);

        // مسار أكاديميّ **مربوط بقسم الإعلام فقط** — عبر فورم الأدمن (كودنا الجديد)
        $this->actingAs($admin)->post(route('admin.paths.store'), [
            'name_ar' => 'مسار إعلاميّ أكاديميّ',
            'status' => 'published',
            'is_academy' => '1',
            'entity_ids' => [$media->id],
        ])->assertRedirect();

        $academyPath = LearningPath::query()->where('name_ar', 'مسار إعلاميّ أكاديميّ')->firstOrFail();
        DB::table('course_learning_path')->insert([
            'course_id' => $course->id, 'learning_path_id' => $academyPath->id,
            'sort_order' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);

        // مسار عاديّ (بلا علامة أكاديميّة) — حتّى لو رُبِط بنفس القسم لا يظهر أبدًا هناك
        $this->actingAs($admin)->post(route('admin.paths.store'), [
            'name_ar' => 'مسار عاديّ غير أكاديميّ',
            'status' => 'published',
            'entity_ids' => [$media->id],
        ])->assertRedirect();

        // ⚠️ تصفية بقايا رسالة الـflash («اتحفظ المسار «…» ✓») من طلبات الأدمن —
        // وإلّا تظهر في أوّل طلبٍ تالٍ (لأيّ مستخدم) فتُبطِل assertDontSee زورًا.
        session()->flush();

        // ✅ متطوّع الإعلام يرى المسار الأكاديميّ المربوط بقسمه
        $this->actingAs($volunteerInMedia)->get(route('volunteer.academy'))
            ->assertOk()
            ->assertSee('مسار إعلاميّ أكاديميّ')
            ->assertDontSee('مسار عاديّ غير أكاديميّ'); // ⛔ العلامة وحدها تتحكّم — لا الربط بالقسم فقط

        // ⛔ متطوّع قسمٍ آخر لا يرى مسارًا مربوطًا بقسمٍ غيره
        $this->actingAs($volunteerInDesign)->get(route('volunteer.academy'))
            ->assertOk()
            ->assertDontSee('مسار إعلاميّ أكاديميّ');
    }

    // ------------------------------------------------------------------ أدوات

    /** يمنح مستخدمًا موجودًا صلاحيّاتٍ بنطاق ALL — نسخة من userWith تعمل على مستخدمٍ جاهز لا مستخدمٍ جديد. */
    private function userWithPermissionsOn(User $user, array $permissionKeys): User
    {
        $role = Role::create(['key' => 'r_'.str()->random(8), 'name_ar' => 'دور اختبار', 'layer' => 'volunteer']);

        foreach ($permissionKeys as $key) {
            $permission = Permission::firstOrCreate(['key' => $key], [
                'resource' => explode('.', $key)[0],
                'action' => explode('.', $key)[1] ?? 'view',
                'group' => 'اختبار',
                'label_ar' => $key,
                'allowed_scopes' => ['SELF', 'TEAM', 'SUBTREE', 'ENTITY', 'TRACK', 'ALL'],
            ]);

            DB::table('permission_role')->insert([
                'role_id' => $role->id, 'permission_id' => $permission->id,
                'scope' => 'ALL', 'effect' => 'allow', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $user->roles()->attach($role->id, ['assigned_at' => now()]);
        app(AccessEngine::class)->forget($user);

        return $user->refresh();
    }

    /**
     * يستخرج حمولة `data-path="…"` (JSON) لمسارٍ بعينه من HTML شاشة المسارات —
     * يثبت أنّ القيم المحفوظة تُقرأ فعليًّا في فورم التعديل لا تُكتَب فقط بلا عرض.
     *
     * @return array<string, mixed>
     */
    private function pathPayloadFrom(string $html, int $pathId): array
    {
        preg_match_all('/data-path="([^"]+)"/', $html, $matches);

        foreach ($matches[1] as $raw) {
            $decoded = json_decode(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5), true);

            if (is_array($decoded) && (int) ($decoded['id'] ?? 0) === $pathId) {
                return $decoded;
            }
        }

        $this->fail("لم أجد حمولة data-path للمسار #{$pathId} في شاشة المسارات.");
    }
}
