<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'tasks';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            // نافذة التفكيك (23-3.9-١) — عمودٌ زمنيّ كإخوته، وكان بلا كاست
            'breakdown_due_at' => 'datetime',
            'deadline_at' => 'datetime',
            'delivered_at' => 'datetime',
            'late_due_to_child' => 'boolean',
            'merge_window_at' => 'datetime',
            'vxp_value' => 'decimal:2',
        ];
    }

    public function task_type(): BelongsTo
    {
        return $this->belongsTo(TaskType::class, 'task_type_id');
    }

    public function work_item(): BelongsTo
    {
        return $this->belongsTo(WorkItem::class, 'work_item_id');
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'entity_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function created_by(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function parent_task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'parent_task_id');
    }

    public function blocked_by_task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'blocked_by_task_id');
    }

    /** الاجتماع الذي تولّدت من بند محضره — فارغٌ لكلّ مهمّة أخرى (23-0.3) */
    public function source_meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class, 'source_meeting_id');
    }
}
