<?php

declare(strict_types=1);

namespace Modules\Offer\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Modules\Listing\Models\ClearOut;
use Modules\Listing\Models\Listing;
use Modules\User\App\Models\User;
use RuntimeException;

class BundleOffer extends Model
{
    use SoftDeletes;

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_DECLINED = 'declined';
    public const STATUS_WITHDRAWN = 'withdrawn';

    protected $fillable = [
        'clear_out_id',
        'buyer_id',
        'seller_id',
        'amount',
        'currency',
        'message',
        'status',
        'responded_at',
        'fulfilled_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'responded_at' => 'datetime',
        'fulfilled_at' => 'datetime',
    ];

    public function clearOut()
    {
        return $this->belongsTo(ClearOut::class);
    }

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function items()
    {
        return $this->hasMany(BundleOfferItem::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public static function place(
        ClearOut $clearOut,
        int $buyerId,
        int $sellerId,
        array $listings,
        float $amount,
        string $currency,
        ?string $message
    ): self {
        return DB::transaction(function () use (
            $clearOut,
            $buyerId,
            $sellerId,
            $listings,
            $amount,
            $currency,
            $message
        ): self {
            // A new bundle replaces any previous pending bundle from
            // the same buyer for this Clear Out.
            static::query()
                ->where('clear_out_id', $clearOut->getKey())
                ->where('buyer_id', $buyerId)
                ->pending()
                ->update([
                    'status' => self::STATUS_WITHDRAWN,
                    'responded_at' => now(),
                ]);

            $bundle = static::query()->create([
                'clear_out_id' => $clearOut->getKey(),
                'buyer_id' => $buyerId,
                'seller_id' => $sellerId,
                'amount' => $amount,
                'currency' => $currency,
                'message' => $message,
                'status' => self::STATUS_PENDING,
            ]);

            foreach ($listings as $listing) {
                $bundle->items()->create([
                    'listing_id' => $listing->getKey(),
                    'quantity' => 1,
                    'listed_price' => $listing->price,
                ]);
            }

            return $bundle;
        });
    }

    public static function receivedBySeller(
        int $sellerId,
        int $perPage = 12
    ): LengthAwarePaginator {
        return static::query()
            ->where('seller_id', $sellerId)
            ->with([
                'clearOut:id,title,slug',
                'buyer:id,name',
                'items.listing:id,title,slug,price,currency',
            ])
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public static function sentByBuyer(
        int $buyerId,
        int $perPage = 12
    ): LengthAwarePaginator {
        return static::query()
            ->where('buyer_id', $buyerId)
            ->with([
                'clearOut:id,title,slug',
                'seller:id,name',
                'items.listing:id,title,slug,price,currency',
            ])
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function accept(): void
    {
        $this->forceFill([
            'status' => self::STATUS_ACCEPTED,
            'responded_at' => now(),
        ])->save();
    }

    public function decline(): void
    {
        $this->forceFill([
            'status' => self::STATUS_DECLINED,
            'responded_at' => now(),
        ])->save();
    }

    public function withdraw(): void
    {
        $this->forceFill([
            'status' => self::STATUS_WITHDRAWN,
            'responded_at' => now(),
        ])->save();
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isAccepted(): bool
    {
        return $this->status === self::STATUS_ACCEPTED;
    }

    public function isFulfilled(): bool
    {
        return $this->fulfilled_at !== null;
    }

    public function fulfill(): void
    {
        DB::transaction(function (): void {
            $bundle = static::query()
                ->whereKey($this->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($bundle->fulfilled_at !== null) {
                return;
            }

            if ($bundle->status !== self::STATUS_ACCEPTED) {
                throw new RuntimeException(
                    'Only an accepted bundle offer can be marked collected.'
                );
            }

            $items = BundleOfferItem::query()
                ->where('bundle_offer_id', $bundle->getKey())
                ->orderBy('listing_id')
                ->get();

            if ($items->isEmpty()) {
                throw new RuntimeException(
                    'This bundle does not contain any items.'
                );
            }

            $listingIds = $items
                ->pluck('listing_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $listings = Listing::query()
                ->whereIn('id', $listingIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (Listing $listing) => (int) $listing->getKey());

            foreach ($items as $item) {
                $listing = $listings->get((int) $item->listing_id);

                if (! $listing) {
                    throw new RuntimeException(
                        'One of the bundle listings no longer exists.'
                    );
                }

                $quantity = max(1, (int) $item->quantity);
                $available = max(0, (int) $listing->quantity_available);

                if ($available < $quantity) {
                    throw new RuntimeException(
                        'Not enough stock remains for "'
                        .$listing->title
                        .'".'
                    );
                }
            }

            foreach ($items as $item) {
                /** @var Listing $listing */
                $listing = $listings->get((int) $item->listing_id);

                $quantity = max(1, (int) $item->quantity);
                $available = max(
                    0,
                    (int) $listing->quantity_available - $quantity
                );

                $listing->forceFill([
                    'quantity_available' => $available,
                    'status' => $available === 0 ? 'sold' : 'active',
                ])->save();
            }

            $bundle->forceFill([
                'fulfilled_at' => now(),
            ])->save();

            $this->refresh();
        });
    }

    public function amountLabel(): string
    {
        return number_format((float) $this->amount, 2)
            .' '
            .$this->currency;
    }

    public function listedTotal(): float
    {
        return (float) $this->items->sum(
            fn (BundleOfferItem $item) =>
                (float) $item->listed_price * (int) $item->quantity
        );
    }

    public function statusTone(): string
    {
        return match ($this->status) {
            self::STATUS_ACCEPTED => 'positive',
            self::STATUS_DECLINED => 'critical',
            self::STATUS_WITHDRAWN => 'default',
            default => 'caution',
        };
    }
}
