{{--
    صفحة «بلا صلاحيّة» (403) — بديلٌ عن صفحة Laravel الافتراضيّة الخام.

    كلّ `abort(403, ...)` في المشروع (حارس الصلاحيّة · حارس لوحة الإدارة ·
    حارس القسم · إلخ) يمرّ من هنا تلقائيًّا — Laravel يحلّ
    `resources/views/errors/403.blade.php` لأيّ رفضٍ 403 بلا سباكةٍ إضافيّة.

    تمتدّ من القالب المشترك `errors.minimal` (نفس البطاقة لكلّ الأخطاء).
    والرسالة عبر `setting()` (2.13) — والافتراضيّ نفسه نصّ حارس الصلاحيّة
    الحاليّ (`admin_roles.permission_guard.handle_msg`) حتى لا يتيه المستخدم
    برسالتين مختلفتين لنفس الرفض.
--}}
@extends('errors.minimal', ['icon' => 'lock'])

@section('title', setting('admin_roles.permission_guard.forbidden_page_title', 'بلا صلاحيّة'))
@section('message', setting('admin_roles.permission_guard.forbidden_page_message', 'ليس لديك صلاحيّة الوصول لهذه الصفحة.'))
@section('action_label', setting('admin_roles.permission_guard.forbidden_page_action', 'الرجوع للوحة الرئيسيّة'))
@section('action_url'){{ Route::has('admin.dashboard') && request()->is('admin', 'admin/*') ? route('admin.dashboard') : (Route::has('dashboard') ? route('dashboard') : url('/')) }}@endsection
