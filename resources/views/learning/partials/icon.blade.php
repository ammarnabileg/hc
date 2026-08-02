{{--
    قاموس الأيقونات مقفول وواحد للمنصّة كلّها (2.16-ج) — فلا نُبقي هنا قائمةً
    ثانية تفترق عن الأصل. هذا الملفّ **واجهة توافق** لنداءات
    `@include('learning.partials.icon', [...])`، والرسم في
    `resources/views/components/icon.blade.php`.
--}}
<x-icon :name="$name ?? 'comment'" :size="$box ?? 16" class="inline-block align-[-0.15em]" />
