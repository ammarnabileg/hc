@extends('layouts.admin')

@section('title', setting('admin_content.help_guide.page_title', 'إعدادات دليل المستخدم'))

@section('content')
    {{-- بلوك إعدادات دليل المستخدم (12.6-ج سطر 5087 — فجوة مسدودة): البحث و«هل كان مفيدًا؟» والتصنيفات والوسوم ونصّ الحالة الفارغة --}}
    <x-page-header
        :title="setting('admin_content.help_guide.page_title', 'إعدادات دليل المستخدم')"
        :subtitle="setting('admin_content.help_guide.page_subtitle', 'البحث و«هل كان مفيدًا؟» والتصنيفات والوسوم ونصّ الحالة الفارغة.')"
        :breadcrumbs="[
            ['label' => setting('admin.guidance.help.altwjyh_waldam', 'التوجيه والدعم'), 'url' => route('admin.guidance.index')],
            ['label' => setting('admin.guidance.help.dlyl_almstkhdm', 'دليل المستخدم'), 'url' => route('admin.guidance.help')],
            ['label' => setting('admin_content.help_guide.page_title', 'إعدادات دليل المستخدم')],
        ]" />

    <x-tabs :tabs="$tabs" current="help" />

    @include('admin.volunteer.partials.settings-card', [
        'title' => setting('admin_content.help_guide.toggles_title', 'البحث والتقييم والتصنيفات'),
        'rows' => $settings,
        'action' => route('admin.guidance.help.settings.update'),
        'resetAction' => route('admin.guidance.help.settings.reset'),
        'open' => true,
    ])

    <div class="grid md:grid-cols-2 gap-4 mt-4">
        <div>
            <h2 class="font-bold mb-2">{{ setting('admin_content.help_guide.categories_heading', 'تصنيفات الدليل') }}</h2>
            @include('admin.guidance.partials.list-editor', [
                'action' => route('admin.guidance.help.settings.categories'),
                'fieldName' => 'categories',
                'items' => $categories,
                'addLabel' => setting('admin_content.help_guide.categories_add', 'إضافة تصنيف'),
                'newPlaceholder' => setting('admin_content.help_guide.categories_placeholder', 'تصنيف جديد'),
                'removeLabel' => setting('admin_content.help_guide.categories_remove', 'حذف التصنيف'),
                'saveLabel' => setting('admin_content.help_guide.categories_save', 'حفظ التصنيفات'),
                'emptyRequired' => true,
                'defaultsHint' => setting('admin_content.help_guide.categories_defaults_hint', 'الافتراضيّ:'),
                'defaults' => $defaultCategories,
            ])
        </div>

        <div>
            <h2 class="font-bold mb-2">{{ setting('admin_content.help_guide.tags_heading', 'وسوم الدليل') }}</h2>
            @include('admin.guidance.partials.list-editor', [
                'action' => route('admin.guidance.help.settings.tags'),
                'fieldName' => 'tags',
                'items' => $tags,
                'addLabel' => setting('admin_content.help_guide.tags_add', 'إضافة وسم'),
                'newPlaceholder' => setting('admin_content.help_guide.tags_placeholder', 'وسم جديد'),
                'removeLabel' => setting('admin_content.help_guide.tags_remove', 'حذف الوسم'),
                'saveLabel' => setting('admin_content.help_guide.tags_save', 'حفظ الوسوم'),
                'emptyRequired' => false,
                'defaultsHint' => setting('admin_content.help_guide.tags_defaults_hint', 'الافتراضيّ:'),
                'defaults' => [],
                'maxLength' => 32,
            ])
        </div>
    </div>

    @include('admin.courses.partials.toast')
@endsection
