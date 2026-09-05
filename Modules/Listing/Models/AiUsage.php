<?php

declare(strict_types=1);

namespace Modules\Listing\Models;

use Illuminate\Database\Eloquent\Model;

class AiUsage extends Model
{
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'user_id',
        'feature',
        'provider',
        'model',
        'status',
        'source_id',
        'input_tokens',
        'output_tokens',
        'estimated_cost_usd',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'estimated_cost_usd' => 'decimal:6',
        ];
    }
}
