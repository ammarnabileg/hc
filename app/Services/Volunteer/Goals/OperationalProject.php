<?php

namespace App\Services\Volunteer\Goals;

use App\Models\Entity;
use App\Models\Project;
use App\Models\WorkPackage;

/**
 * ⭐ «المشروع التشغيليّ لقسم [الاسم]» (23 — 1.8): «واحد لكلّ كيان رئيسي بأيّ
 * مسار … يُنشأ تلقائيًّا مع إنشاء الكيان وموجود دائمًا لا يُغلَق، وعاؤه بنود
 * الإيقاع المتكرّر — وهو ما يحقّق شرط «كلّ مهمّة مربوطة ببند» دون خنق العمل
 * اليوميّ».
 *
 * وكانت هذه الجملة الأولى وحدها بلا منفِّذ: القالب/التكرار/الموازن/التوليد
 * الآليّ/مسار عدم التسليم للفائتة (`RecurringGenerator` · `NoDeliverySweeper`
 * · `CaseCatalog::NO_DELIVERY`) مبنيّةٌ ومختبَرة بالكامل — لكنّ لا مسارَ إنشاء
 * كيانٍ حقيقيّ في المنصّة كان يفتح لها وعاءها أصلًا، فتبقى شاشة «المشروع
 * التشغيليّ» فارغةً للأبد على أيّ تنصيبٍ حقيقيّ (السطور التي تبنيه موجودة في
 * السيدرات التجريبيّة ومساعدات الاختبار وحدها).
 *
 * ⛔ **«رئيسي» تعني `parent_id === null`** — نفس الاصطلاح المستعمَل حرفيًّا في
 * `EntityScope::rootsFor()`/`CandidatePipeline`/`ThanksWall`: القسم الفرعيّ
 * لا مشروع تشغيليّ له، بل يشارك مشروع قسمه الرئيسي (23-0.2: يُعامَل معاملة
 * قسمٍ لكنّه ليس كيانًا رئيسيًّا مستقلًّا).
 */
class OperationalProject
{
    /**
     * يضمن وجود المشروع التشغيليّ لكيانٍ رئيسي — Idempotent: لا يُنشئ صفًّا
     * ثانيًا لو نودِيَ مرّتين على نفس الكيان.
     */
    public function ensureFor(Entity $entity): ?Project
    {
        if ($entity->parent_id !== null) {
            return null;
        }

        $project = Project::query()
            ->where('entity_id', $entity->id)
            ->where('type', 'operational')
            ->first();

        if ($project) {
            return $project;
        }

        $project = Project::create([
            'entity_id' => $entity->id,
            'name' => strtr(setting('goals.operational_project.name', 'المشروع التشغيليّ لـ:p1'), [':p1' => (string) $entity->name_ar]),
            'type' => 'operational',
            'is_permanent' => true,
            'status' => 'active',
        ]);

        WorkPackage::create([
            'project_id' => $project->id,
            'entity_id' => $entity->id,
            'name' => (string) setting('goals.operational_project.default_package', 'الإيقاع اليوميّ'),
        ]);

        return $project;
    }
}
