<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * ⭐ صفحة هبوط مستقلّة (12.2.3 `landing_pages`) — كيانٌ قائمٌ بذاته مرتبط
 * بـ`Bundle` أو `Product`، **منفصل** عن `BundleLanding` (المبنيّة داخل شاشة
 * البندل نفسها بحقولٍ على جدول `bundles`).
 *
 * والحقول متعمَّدة البساطة: لا محرّك وراثة ولا حسابَ توفيرٍ هنا — تلك خاصّة
 * بتسعير البندل (`BundleLanding`) ولا معنى لها على صفحةٍ تسويقيّة عامّة قد
 * تُربَط بمنتجٍ لا سعر «باقة» له أصلًا.
 */
class LandingPage extends Model
{
    use HasFactory;

    protected $table = 'landing_pages';

    protected $guarded = [];

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_ARCHIVED = 'archived';

    /** الأنواع المسموحة للكيان المرتبط — بندل أو منتج فقط (12.2.3) */
    public const LANDINGABLE_TYPES = [
        'bundle' => Bundle::class,
        'product' => Product::class,
    ];

    protected function casts(): array
    {
        return [
            'outcomes' => 'array',
            'faq' => 'array',
            'published_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function landingable(): MorphTo
    {
        return $this->morphTo();
    }

    public function created_by_user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** ⭐ الحالة الوحيدة التي يفتحها العرض العامّ (state:published — 12.2.2) */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function isArchived(): bool
    {
        return $this->status === self::STATUS_ARCHIVED;
    }

    /** مفتاح النوع القصير («bundle»/«product») المستعمل في الروابط والفورم */
    public function landingableKey(): ?string
    {
        return array_search($this->landingable_type, self::LANDINGABLE_TYPES, true) ?: null;
    }

    /** العنوان: نصّ الصفحة الخاصّ، وإلّا اسم الكيان المرتبط — فلا تظهر الصفحة فارغة */
    public function title(): string
    {
        $own = trim((string) $this->headline);

        if ($own !== '') {
            return $own;
        }

        return (string) ($this->landingable->name_ar ?? '');
    }

    /** الوعد/العنوان الفرعيّ: نصّ الصفحة، وإلّا وصف الكيان المرتبط */
    public function promise(): string
    {
        $own = trim((string) $this->subheadline);

        if ($own !== '') {
            return $own;
        }

        return (string) ($this->landingable->description ?? '');
    }

    public function ctaLabel(): string
    {
        $own = trim((string) $this->cta_label);

        return $own !== '' ? $own : (string) setting('landing_pages.default_cta_label', 'اعرف أكتر');
    }

    /** رابط صفحة المتجر الحقيقيّة (الشراء يبقى هناك — هذه صفحة تسويقيّة قبلها) */
    public function targetStoreUrl(): ?string
    {
        $key = $this->landingableKey();

        if (! $key || ! $this->landingable) {
            return null;
        }

        return route('store.product', ['type' => $key, 'slug' => $this->landingable->slug]);
    }
}
