<?php

namespace Database\Seeders;

use App\Models\CandidateEntityFit;
use App\Models\Course;
use App\Models\Entity;
use App\Models\InternalLibraryItem;
use App\Models\InterviewCriterion;
use App\Models\LearningPath;
use App\Models\Membership;
use App\Models\Position;
use App\Models\RecruitmentCandidate;
use App\Models\RepScore;
use App\Models\Setting;
use App\Models\Track;
use App\Models\User;
use App\Models\VolunteerRecording;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * بيانات تجريبيّة لمجال «التوظيف والتسكين والأكاديمية والمكتبة والتقدير».
 * ومعها **إعدادات المجال** — فلا رقم ولا نصّ محروق في الكود (2.13).
 */
class VolunteerPeopleDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->settings();
        $entities = $this->entities();
        $this->criteria();
        $candidates = $this->candidates($entities);
        $this->academy($entities);
        $this->library($entities);
        $this->recognition();

        $this->command?->info('بيانات المجال: '.count($candidates).' مرشّحين · '
            .VolunteerRecording::count().' تسجيلات · '.InternalLibraryItem::count().' مُدخَل مكتبة.');
    }

    /** إعدادات المجال بنمط «المجال.الميزة.المفتاح» مع قيمة افتراضيّة قابلة للاسترجاع */
    public function settings(): void
    {
        $rows = [
            // ---------------- التوظيف (13.4-د)
            ['recruitment.stage.applied.label', 'recruitment', 'اسم مرحلة التقديم', 'string', 'تقديم'],
            ['recruitment.stage.screening.label', 'recruitment', 'اسم مرحلة الفرز', 'string', 'فرز'],
            ['recruitment.stage.interview.label', 'recruitment', 'اسم مرحلة المقابلة', 'string', 'مقابلة'],
            ['recruitment.stage.final_list.label', 'recruitment', 'اسم مرحلة القائمة النهائيّة', 'string', 'قائمة نهائيّة'],
            ['recruitment.stage.placed.label', 'recruitment', 'اسم مرحلة التسكين', 'string', 'تسكين'],
            ['recruitment.score.min', 'recruitment', 'أدنى قيمة في منزلق الدرجات', 'number', '0'],
            ['recruitment.score.max', 'recruitment', 'أقصى قيمة في منزلق الدرجات', 'number', '100'],
            ['recruitment.waiting.warn_days', 'recruitment', 'أيّام الانتظار قبل التنبيه', 'number', '30'],
            ['recruitment.waiting.danger_days', 'recruitment', 'أيّام الانتظار قبل الإنذار', 'number', '60'],
            ['recruitment.returning.line', 'recruitment', 'سطر شارة «عائد»', 'string', 'كان معنا من :from إلى :to — مدّة الخدمة :duration'],

            // ---------------- المقابلات (13.4-د)
            ['interviews.slot_minutes', 'recruitment', 'مدّة سلوت المقابلة (دقائق) — بها يُقاس التعارض', 'number', '45'],
            ['interviews.status.scheduled.label', 'recruitment', 'حالة: مجدولة', 'string', 'مجدولة'],
            ['interviews.status.done.label', 'recruitment', 'حالة: تمّت', 'string', 'تمّت'],
            ['interviews.status.no_show.label', 'recruitment', 'حالة: لم يحضر', 'string', 'لم يحضر'],
            ['interviews.status.cancelled.label', 'recruitment', 'حالة: مُلغاة', 'string', 'مُلغاة'],
            ['scorecards.scale_max', 'recruitment', 'مقياس المعايير', 'number', '10'],
            ['scorecards.skills.required', 'recruitment', 'المهارات حقل إجباريّ', 'bool', '1'],
            ['scorecards.skills.label', 'recruitment', 'تسمية حقل المهارات', 'string', 'المهارات'],
            ['scorecards.skills.placeholder', 'recruitment', 'تلميح حقل المهارات', 'string', 'اكتب أمثلة ملموسة شفتها في المقابلة — مش صفات عامّة.'],
            ['scorecards.personality.required', 'recruitment', 'تحليل الشخصيّة حقل إجباريّ', 'bool', '1'],
            ['scorecards.personality.label', 'recruitment', 'تسمية حقل الشخصيّة', 'string', 'تحليل الشخصيّة'],
            ['scorecards.personality.placeholder', 'recruitment', 'تلميح حقل الشخصيّة', 'string', 'إزاي بيتعامل مع الضغط والاختلاف؟ اذكر موقفًا.'],

            // ---------------- التسكين (13.4-هـ)
            ['placement.response_hours', 'recruitment', 'مهلة ردّ المرشّح (ساعات)', 'number', '48'],
            ['placement.default_member_cap', 'recruitment', 'السعة الافتراضيّة للقسم الفرعيّ', 'number', '12'],
            ['placement.occupancy.warn_percent', 'recruitment', 'نسبة الإشغال التي تُنبِّه', 'number', '80'],
            ['placement.sort.newest.label', 'recruitment', 'ترتيب: الأحدث أوّلًا', 'string', 'الأحدث أوّلًا'],
            ['placement.sort.longest.label', 'recruitment', 'ترتيب: الأقدم انتظارًا', 'string', 'الأقدم انتظارًا أوّلًا'],
            ['placement.status.sent.label', 'recruitment', 'حالة: مُرسَل', 'string', 'مُرسَل'],
            ['placement.status.awaiting.label', 'recruitment', 'حالة: بانتظار موافقة المرشّح', 'string', 'بانتظار موافقة المرشّح'],
            ['placement.status.accepted.label', 'recruitment', 'حالة: مقبول', 'string', 'مقبول'],
            ['placement.status.rejected.label', 'recruitment', 'حالة: مرفوض', 'string', 'مرفوض'],
            ['placement.status.withdrawn.label', 'recruitment', 'حالة: مسحوب', 'string', 'مسحوب'],
            ['placement.status.expired.label', 'recruitment', 'حالة: فاتت المهلة', 'string', 'فاتت المهلة'],
            ['placement.whatsapp.template', 'recruitment', 'قالب رسالة واتساب للتسكين', 'text',
                'أهلًا :name 👋 عندنا مكان مناسب ليك في :entity. تقدر تدخل المنصّة وتوافق خلال :hours ساعة.'],

            // ---------------- الأكاديمية (13.4-ل)
            ['academy.recording.vxp_value', 'academy', 'VXP تسجيل بـOTP', 'number', '10'],
            ['academy.recording.badge', 'academy', 'نصّ شارة مكافأة التسجيل', 'string', '+:vxp VXP و+:rep Rep — مرّة واحدة'],
            ['academy.recording.granted.message', 'academy', 'رسالة منح نقاط التسجيل', 'string', 'تمام ✓ اتضاف لك :vxp VXP و:rep Rep.'],
            ['academy.recording.wrong_otp.message', 'academy', 'رسالة الرمز الخطأ', 'string', 'الرمز غير صحيح — راجعه في آخر التسجيل وجرّب تاني.'],
            ['academy.recording.already.message', 'academy', 'رسالة الكسب المستنفَد', 'string', 'حصلت على نقاط التسجيل ده بالفعل.'],
            ['academy.recording.no_otp.message', 'academy', 'رسالة تسجيل بلا رمز', 'string', 'التسجيل ده مالوش رمز — اتفرّج واستفيد وبس.'],
            ['academy.recording.report_sla_hours', 'academy', 'مهلة الردّ على بلاغ رابط معطّل (ساعات)', 'number', '24'],
            ['academy.complete.linked_message', 'academy', 'رسالة إكمال مسار مربوط بشهادة', 'string', 'أنت جاهز للامتحان — كلّ المذاكرة خلصت.'],
            ['academy.complete.pure_message', 'academy', 'رسالة إكمال مسار تعليميّ صِرف', 'string', 'أتممت المسار 🎉'],

            // ---------------- المكتبة الداخليّة (23-3.3)
            ['internal_library.snippet_chars', 'internal_library', 'طول المقتطف حول موضع المطابقة', 'number', '80'],
            ['internal_library.type.design', 'internal_library', 'نوع: تصميم', 'string', 'تصميم'],
            ['internal_library.type.content', 'internal_library', 'نوع: محتوى', 'string', 'محتوى'],
            ['internal_library.type.plan', 'internal_library', 'نوع: خطّة', 'string', 'خطّة'],
            ['internal_library.type.form', 'internal_library', 'نوع: نموذج', 'string', 'نموذج'],
            ['internal_library.type.document', 'internal_library', 'نوع: مستند', 'string', 'مستند'],
            ['internal_library.type.research', 'internal_library', 'نوع: بحث', 'string', 'بحث'],
            ['internal_library.access.entity', 'internal_library', 'مستوى: كياني فقط', 'string', 'كياني فقط'],
            ['internal_library.access.all', 'internal_library', 'مستوى: كلّ المتطوّعين', 'string', 'كلّ المتطوّعين'],
            ['internal_library.access.restricted', 'internal_library', 'مستوى: مقيَّد ببوزشن فأعلى', 'string', 'مقيَّد ببوزشن فأعلى'],

            // ---------------- التقدير (13.4-ي)
            ['kudos.reason.placeholder', 'kudos', 'تلميح سبب الشكر', 'string', 'احكِ الموقف نفسه — الحكاية هي اللي بتفضل.'],
            ['kudos.reason_required.message', 'kudos', 'رسالة السبب الإلزاميّ', 'string', 'اكتب سبب الشكر — القصّة هي اللي بتفرق مش الرقم.'],
            ['kudos.daily_limit.message', 'kudos', 'رسالة بلوغ حدّ اليوم', 'string', 'وصلت لحدّ اليوم — بكرة تقدر تشكر تاني.'],
            ['kudos.weekly_limit.message', 'kudos', 'رسالة بلوغ حدّ الأسبوع', 'string', 'وصلت لحدّ الأسبوع — الأسبوع الجاي مفتوح.'],
            ['kudos.duplicate.message', 'kudos', 'رسالة تكرار نفس الشخص', 'string', 'شكرت الشخص ده الأسبوع ده بالفعل — دوّر على حد تاني يستاهل.'],
            ['kudos.self.message', 'kudos', 'رسالة شكر النفس', 'string', 'الشكر بيروح لغيرك — اختر زميلًا 🙂'],
            ['thanks_wall.approaching_gap', 'kudos', 'فجوة بلوك «اقتربت» تحت العتبة', 'number', '1.5'],
        ];

        foreach ($rows as [$key, $group, $label, $type, $default]) {
            Setting::updateOrCreate(['key' => $key], [
                'group' => $group,
                'label_ar' => $label,
                'type' => $type,
                'default_value' => $default,
                'value' => $default,
            ]);
        }

        // الإعدادات مكاشة إلى الأبد — فالسيدر ينظّف الكاش وإلّا قرأ الكود قيمًا قديمة
        Cache::forget('settings');
    }

    /** @return array<string, Entity> */
    private function entities(): array
    {
        $track = Track::firstWhere('key', 'department') ?? Track::first();

        $media = Entity::firstOrCreate(
            ['name_ar' => 'الإعلام', 'parent_id' => null],
            ['track_id' => $track->id, 'icon' => '🎬', 'status' => 'active', 'member_cap' => 20],
        );

        $design = Entity::firstOrCreate(
            ['name_ar' => 'التصميم', 'parent_id' => $media->id],
            ['track_id' => $track->id, 'icon' => '🎨', 'status' => 'active', 'member_cap' => 8],
        );

        $editing = Entity::firstOrCreate(
            ['name_ar' => 'المونتاج', 'parent_id' => $media->id],
            ['track_id' => $track->id, 'icon' => '🎞', 'status' => 'active', 'member_cap' => 6],
        );

        return ['media' => $media, 'design' => $design, 'editing' => $editing];
    }

    /** معايير المقابلة — ومنها **معيار مؤرشف** ليظهر بدرجته بوسمه */
    private function criteria(): void
    {
        $rows = [
            ['الالتزام بالمواعيد', 3, false, 1],
            ['وضوح التواصل', 2, false, 2],
            ['الخبرة العمليّة', 2, false, 3],
            ['التعامل مع الضغط', 1, false, 4],
            ['اختبار قديم (مؤرشف)', 1, true, 5],
        ];

        foreach ($rows as [$label, $weight, $archived, $order]) {
            InterviewCriterion::updateOrCreate(['label_ar' => $label], [
                'weight' => $weight,
                'is_archived' => $archived,
                'sort_order' => $order,
            ]);
        }
    }

    /** @return array<int, RecruitmentCandidate> */
    private function candidates(array $entities): array
    {
        $rows = [
            ['سلمى عبد الرحمن', 'CND001', '01000000001', 92.5, 12, false, null],
            ['محمود السيّد', 'CND002', '01000000002', 78.0, 45, false, null],
            ['نورهان أحمد', 'CND003', '01000000003', 85.5, 70, false, null],
            // ⭐ عائد: يظهر بشارته وسطر خدمته السابقة (13.4-ق)
            ['كريم مصطفى', 'CND004', '01000000004', 88.0, 20, true, 'استقالة طوعيّة'],
        ];

        $stages = ['applied', 'screening', 'interview', 'final_list'];
        $created = [];

        foreach ($rows as $index => [$name, $code, $phone, $score, $waitDays, $returning, $exit]) {
            $user = User::firstOrCreate(['code' => $code], [
                'name' => $name,
                'email' => strtolower($code).'@demo.local',
                'password' => 'demo-password',
                'phone' => $phone,
                'status' => 'active',
            ]);

            $candidate = RecruitmentCandidate::updateOrCreate(['user_id' => $user->id], [
                'stage' => $stages[$index % count($stages)],
                'qualifying_score' => $score,
                'course_scores' => ['أساسيّات التطوّع' => 95, 'مهارات التواصل' => 88],
                'cv_summary' => 'خبرة سنتين في العمل التطوّعيّ — تنظيم فعاليّات وإدارة فرق صغيرة.',
                'applied_at' => now()->subDays($waitDays),
                'stage_changed_at' => now()->subDays(max(0, $waitDays - 3)),
                'is_returning' => $returning,
                'previous_service_from' => $returning ? now()->subYears(2) : null,
                'previous_service_to' => $returning ? now()->subYear() : null,
                'previous_exit_type' => $exit,
            ]);

            CandidateEntityFit::firstOrCreate([
                'recruitment_candidate_id' => $candidate->id,
                'entity_id' => $index % 2 === 0 ? $entities['design']->id : $entities['editing']->id,
            ]);

            $created[] = $candidate;
        }

        return $created;
    }

    private function academy(array $entities): void
    {
        $courses = Course::query()->limit(3)->get();

        $path = LearningPath::updateOrCreate(['slug' => 'academy-media-basics'], [
            'name_ar' => 'أساسيّات الإعلام للمتطوّعين',
            'description_ar' => 'مسار أكاديميّ يجمع تدريبات موجودة بترتيب يخدم شغل قسم الإعلام.',
            'status' => 'published',
            'is_academy' => true,
            'sort_order' => 1,
        ]);

        foreach ($courses as $order => $course) {
            DB::table('course_learning_path')->updateOrInsert(
                ['course_id' => $course->id, 'learning_path_id' => $path->id],
                ['sort_order' => $order, 'created_at' => now(), 'updated_at' => now()],
            );
        }

        DB::table('academy_path_entity')->updateOrInsert(
            ['learning_path_id' => $path->id, 'entity_id' => $entities['media']->id],
            ['created_at' => now(), 'updated_at' => now()],
        );

        $host = User::query()->first();

        VolunteerRecording::updateOrCreate(['title' => 'شرح دورة عمل المهامّ'], [
            'entity_id' => $entities['media']->id,
            'url' => 'https://drive.google.com/file/d/demo-1/view',
            'source' => 'drive',
            'host_name' => 'فريق الإعلام',
            'otp' => '4821', // له OTP ⟵ يمنح النقاط مرّة واحدة
            'status' => 'published',
            'created_by' => $host?->id,
        ]);

        VolunteerRecording::updateOrCreate(['title' => 'جلسة أسئلة مفتوحة — بلا رمز'], [
            'entity_id' => $entities['media']->id,
            'url' => 'https://youtu.be/demo-2',
            'source' => 'youtube',
            'host_name' => 'مشرف القسم',
            'otp' => null,
            'status' => 'published',
            'created_by' => $host?->id,
        ]);
    }

    private function library(array $entities): void
    {
        $owner = User::query()->first();
        $director = Position::firstWhere('key', 'director');

        InternalLibraryItem::updateOrCreate(['title' => 'هويّة حملة رمضان'], [
            'entity_id' => $entities['design']->id,
            'owner_id' => $owner?->id,
            'type' => 'design',
            'content_text' => 'ملفّ الهويّة البصريّة لحملة رمضان: الألوان والخطوط ومقاسات المنشورات لكلّ منصّة، '
                .'مع أمثلة تطبيقيّة على ستوري وبوست ومربّع.',
            'tags' => ['تصميم', 'حملات'],
            'access_level' => 'all_volunteers',
            'approved_at' => now()->subDays(10),
        ]);

        InternalLibraryItem::updateOrCreate(['title' => 'خطّة تغطية الفعاليّة السنويّة'], [
            'entity_id' => $entities['media']->id,
            'owner_id' => $owner?->id,
            'type' => 'plan',
            'content_text' => 'خطّة التغطية الإعلاميّة: جدول التصوير وتوزيع الفرق ونقاط البثّ المباشر '
                .'وقائمة المعدّات المطلوبة، مع خطّة بديلة للطقس السيّئ.',
            'tags' => ['خطط', 'فعاليّات'],
            'access_level' => 'entity',
            'approved_at' => now()->subDays(4),
        ]);

        // مقيَّد ببوزشن فأعلى — يظهر بعنوانه وقفله ولا يُخفى (23-3.3)
        InternalLibraryItem::updateOrCreate(['title' => 'تقييم أداء الفرق — مسودّة داخليّة'], [
            'entity_id' => $entities['media']->id,
            'owner_id' => $owner?->id,
            'type' => 'document',
            'content_text' => 'ملاحظات إداريّة عن أداء الفرق خلال الربع الأخير وتوصيات إعادة التوزيع.',
            'tags' => ['تقارير'],
            'access_level' => 'restricted',
            'min_position_id' => $director?->id,
            'approved_at' => now()->subDays(2),
        ]);
    }

    private function recognition(): void
    {
        $threshold = rep_rule('limit.club_threshold', 9.5);
        $users = User::query()->limit(3)->get();

        foreach ($users as $index => $user) {
            RepScore::updateOrCreate(['user_id' => $user->id], [
                // أوّل مستخدم داخل النادي، والثاني «اقترب»، والثالث في أوّل الطريق
                'score' => match ($index) {
                    0 => $threshold + 0.2,
                    1 => $threshold - 0.7,
                    default => 4.0,
                },
            ]);
        }

        // عضويّة تجريبيّة حتى تعمل شاشات «حسب قسمي» و«نطاق المكتبة»
        $entity = Entity::query()->whereNotNull('parent_id')->first();
        $position = Position::firstWhere('key', 'coordinator');

        if ($users->isNotEmpty() && $entity && $position) {
            Membership::firstOrCreate(
                ['user_id' => $users->first()->id, 'entity_id' => $entity->id],
                ['position_id' => $position->id, 'started_at' => now()->subMonths(3), 'status' => 'active', 'is_primary' => true],
            );
        }
    }
}
