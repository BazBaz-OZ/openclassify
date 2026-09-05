<?php

declare(strict_types=1);

namespace Modules\Listing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class VirtualGaragePhoto extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSED = 'processed';
    public const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'virtual_garage_id',
        'listing_id',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
        'status',
        'sort_order',
    ];

    protected $casts = [
        'size' => 'integer',
        'sort_order' => 'integer',
    ];

    public function virtualGarage()
    {
        return $this->belongsTo(VirtualGarage::class);
    }

    public function items()
    {
        return $this->hasMany(
            VirtualGarageItem::class,
            'virtual_garage_photo_id'
        )->orderBy('sort_order')->orderBy('id');
    }

    public function listing()
    {
        return $this->belongsTo(Listing::class);
    }

    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    protected static function booted(): void
    {
        static::deleting(function (VirtualGaragePhoto $photo): void {
            if (
                filled($photo->disk)
                && filled($photo->path)
            ) {
                Storage::disk($photo->disk)
                    ->delete($photo->path);
            }
        });
    }
}
