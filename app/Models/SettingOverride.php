<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SettingOverride extends Model
{
    use HasFactory;

    protected $table = 'setting_overrides';

    protected $guarded = [];

    public function setting(): BelongsTo
    {
        return $this->belongsTo(Setting::class, 'setting_id');
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'entity_id');
    }

    public function updated_by(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
