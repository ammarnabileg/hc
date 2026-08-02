<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GatewayWebhookLog extends Model
{
    use HasFactory;

    protected $table = 'gateway_webhook_logs';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'hash_valid' => 'boolean',
            'headers' => 'array',
        ];
    }
}
