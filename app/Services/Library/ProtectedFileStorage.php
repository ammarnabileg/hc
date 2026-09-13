<?php

namespace App\Services\Library;

use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * رفع/استبدال الملفّ المحميّ لمنتجات المكتبة الرقميّة (24.3 · 20.5).
 *
 * ⭐ **لا يمرّ على مكتبة الوسائط العامّة عمدًا** — القرص `local` لا `public`،
 * تطبيقًا لنصّ 20.5 الحرفيّ «بلا رفع علنيّ · بلا رابط ملفّ مباشر». وهو نفس
 * القرص الذي يقرأ منه `PdfPageRenderer::absolutePath()` أصلًا — فمصدرٌ واحد
 * للكتابة والقراءة لا اثنان قد ينحرفان.
 */
class ProtectedFileStorage
{
    /** هل الملفّ مسموح نوعًا وحجمًا؟ القيم من الإعدادات لا من الكود (2.13). */
    public function rules(bool $required = false): array
    {
        $extensions = (array) setting('library.upload.allowed_extensions', ['pdf', 'mp3', 'wav', 'mp4', 'html']);
        $maxKb = (int) setting('library.upload.max_kb', 51200);

        return [$required ? 'required' : 'nullable', 'file', 'mimes:'.implode(',', $extensions), 'max:'.$maxKb];
    }

    /** رفع أوّل نسخة عند إنشاء منتج المكتبة — يعيد المسار النسبيّ داخل القرص المحلّيّ. */
    public function store(UploadedFile $file): string
    {
        return $file->store((string) setting('library.storage.directory', 'library'), 'local');
    }

    /**
     * استبدال الملفّ (صفّ الجدول «استبدال الملفّ»): يرفع النسخة الجديدة، يمسح
     * القديمة، ويُبطل كاش صفحات القارئ — فتصل النسخة الجديدة للمالكين فورًا
     * (النصّ الحرفيّ في هيدر شاشة 24.3: «النسخة الجديدة تصل المالكين تلقائيًّا»).
     */
    public function replace(Product $product, UploadedFile $file): string
    {
        $old = $product->file_path;
        $new = $this->store($file);

        if ($old && $old !== $new && Storage::disk('local')->exists($old)) {
            Storage::disk('local')->delete($old);
        }

        app(PdfPageRenderer::class)->forget($product);

        return $new;
    }
}
