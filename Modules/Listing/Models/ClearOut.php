<?php

declare(strict_types=1);

namespace Modules\Listing\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Modules\User\App\Models\User;

class ClearOut extends Model
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
        'starts_at',
        'ends_at',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (ClearOut $clearOut): void {
            if (blank($clearOut->slug)) {
                $clearOut->slug = static::generateUniqueSlug($clearOut->title);
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

    public function listings()
    {
        return $this->hasMany(Listing::class);
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
            ->where('status', 'active')
            ->where('quantity_available', '>', 0);
    }

    public function availableListingCount(): int
    {
        return $this->availableListings()->count();
    }

    public function soldListingCount(): int
    {
        return $this->listings()
            ->where('status', 'sold')
            ->count();
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
            $base = 'clear-out';
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
