<?php

declare(strict_types=1);

namespace Modules\Listing\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Modules\Category\Models\Category;
use Modules\User\App\Models\User;

class WantedPost extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_FULFILLED = 'fulfilled';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'user_id',
        'category_id',
        'title',
        'slug',
        'description',
        'max_budget',
        'currency',
        'city',
        'country',
        'status',
        'published_at',
        'fulfilled_at',
    ];

    protected $casts = [
        'max_budget' => 'decimal:2',
        'published_at' => 'datetime',
        'fulfilled_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (WantedPost $wanted): void {
            if (blank($wanted->slug)) {
                $wanted->slug = static::generateUniqueSlug(
                    (string) $wanted->title
                );
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

    public function category()
    {
        return $this->belongsTo(Category::class);
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

    public function assertOwnedBy(User $user): void
    {
        abort_unless(
            (int) $this->user_id === (int) $user->getKey(),
            403
        );
    }

    public function budgetLabel(): string
    {
        if ($this->max_budget === null) {
            return 'Open budget';
        }

        return 'Up to '
            .number_format((float) $this->max_budget, 2)
            .' '
            .$this->currency;
    }

    public function publish(): void
    {
        $this->forceFill([
            'status' => self::STATUS_ACTIVE,
            'published_at' => $this->published_at ?? now(),
            'fulfilled_at' => null,
        ])->save();
    }

    public function markFulfilled(): void
    {
        $this->forceFill([
            'status' => self::STATUS_FULFILLED,
            'fulfilled_at' => now(),
        ])->save();
    }

    private static function generateUniqueSlug(string $title): string
    {
        $base = Str::slug($title);

        if ($base === '') {
            $base = 'wanted';
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
