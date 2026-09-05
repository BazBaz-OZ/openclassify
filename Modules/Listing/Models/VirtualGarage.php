<?php

declare(strict_types=1);

namespace Modules\Listing\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Modules\User\App\Models\User;

class VirtualGarage extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'user_id',
        'title',
        'slug',
        'description',
        'status',
        'country',
        'city',
        'bulk_price',
        'allow_bulk_offers',
        'starts_at',
        'ends_at',
    ];

    protected $casts = [
        'bulk_price' => 'decimal:2',
        'allow_bulk_offers' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (VirtualGarage $garage): void {
            if (blank($garage->slug)) {
                $garage->slug = static::generateUniqueSlug($garage->title);
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(VirtualGarageItem::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function photos()
    {
        return $this->hasMany(VirtualGaragePhoto::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function listings()
    {
        return $this->belongsToMany(
            Listing::class,
            'virtual_garage_listing'
        )
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function scopeOwnedByUser(
        Builder $query,
        int|string|null $userId
    ): Builder {
        return $query->where('user_id', $userId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function availableListings()
    {
        return $this->listings()
            ->where('listings.status', 'active')
            ->where('listings.quantity_available', '>', 0);
    }

    public function availableListingCount(): int
    {
        return $this->availableListings()->count();
    }

    public function soldListingCount(): int
    {
        return $this->listings()
            ->where('listings.status', 'sold')
            ->count();
    }

    public function remainingListedTotal(): float
    {
        return (float) $this->availableListings()
            ->sum('listings.price');
    }

    public function assertOwnedBy(User $user): void
    {
        abort_unless(
            (int) $this->user_id === (int) $user->getKey(),
            403
        );
    }

    private static function generateUniqueSlug(string $title): string
    {
        $base = Str::slug($title);

        if ($base === '') {
            $base = 'virtual-garage';
        }

        do {
            $slug = $base.'-'.Str::random(6);
        } while (
            static::query()
                ->withTrashed()
                ->where('slug', $slug)
                ->exists()
        );

        return $slug;
    }
}
