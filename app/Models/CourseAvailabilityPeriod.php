<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * فترة إتاحة واحدة لتدريب (الدستور 5) — والتدريب يجوز أن تكون له فترات كثيرة
 * (1→7 يناير، 1→7 مارس، …) والمتدرّب يصل إليه أثناء إحداها فقط.
 */
class CourseAvailabilityPeriod extends Model
{
    use HasFactory;

    protected $table = 'course_availability_periods';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id');
    }
}
