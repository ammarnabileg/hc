<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdAudienceExport extends Model
{
    use HasFactory;

    protected $table = 'ad_audience_exports';

    protected $guarded = [];

    public function ad_audience(): BelongsTo
    {
        return $this->belongsTo(AdAudience::class, 'ad_audience_id');
    }

    public function exported_by(): BelongsTo
    {
        return $this->belongsTo(User::class, 'exported_by');
    }
}
