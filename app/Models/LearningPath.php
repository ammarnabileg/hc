<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LearningPath extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'learning_paths';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'forced_order' => 'boolean',
            'is_indexable' => 'boolean',
            'is_academy' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /** «مسار الشهادة المستهدَف» (13.4-ل) — الفراغ يعني مسارًا تعليميًّا صِرفًا بلا CTA شهادة. */
    public function targetPath(): BelongsTo
    {
        return $this->belongsTo(self::class, 'target_path_id');
    }

    /** الأقسام المربوط بها المسار الأكاديميّ (13.4-ل) — فراغ الربط يعني «الكلّ» (AcademyService::paths). */
    public function entities(): BelongsToMany
    {
        return $this->belongsToMany(Entity::class, 'academy_path_entity', 'learning_path_id', 'entity_id');
    }
}
