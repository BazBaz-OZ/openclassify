<?php

declare(strict_types=1);

namespace Modules\Listing\Support;

use Illuminate\Support\Facades\DB;
use Modules\Listing\Models\AiUsage;
use Modules\User\App\Models\User;
use RuntimeException;

class AiEntitlement
{
    /*
     * If a PHP process dies while an AI request is running,
     * its reservation automatically stops consuming allowance
     * after this period.
     */
    private const RESERVATION_TTL_MINUTES = 30;

    public function plan(User $user): string
    {
        $override = $user->getAttribute('membership_override');

        if (in_array($override, ['member', 'pro'], true)) {
            return $override;
        }

        $proPrice = config(
            'membership.plans.pro.stripe_price_id'
        );

        if (
            filled($proPrice)
            && $user->subscribedToPrice(
                $proPrice,
                'default'
            )
        ) {
            return 'pro';
        }

        $memberPrice = config(
            'membership.plans.member.stripe_price_id'
        );

        if (
            filled($memberPrice)
            && $user->subscribedToPrice(
                $memberPrice,
                'default'
            )
        ) {
            return 'member';
        }

        return 'free';
    }

    public function allowance(User $user): int
    {
        return (int) config(
            'membership.plans.'
                .$this->plan($user)
                .'.ai_scans',
            3
        );
    }

    public function used(User $user): int
    {
        $plan = $this->plan($user);

        $query = AiUsage::query()
            ->where('user_id', $user->getKey())
            ->where(
                'status',
                AiUsage::STATUS_SUCCESS
            );

        $period = config(
            "membership.plans.{$plan}.ai_period",
            'lifetime'
        );

        if ($period === 'monthly') {
            $query->where(
                'created_at',
                '>=',
                now()->startOfMonth()
            );
        }

        return $query->count();
    }

    public function reserved(User $user): int
    {
        $plan = $this->plan($user);

        $query = AiUsage::query()
            ->where('user_id', $user->getKey())
            ->where(
                'status',
                AiUsage::STATUS_PENDING
            )
            ->where(
                'created_at',
                '>=',
                $this->reservationCutoff()
            );

        $period = config(
            "membership.plans.{$plan}.ai_period",
            'lifetime'
        );

        if ($period === 'monthly') {
            $query->where(
                'created_at',
                '>=',
                now()->startOfMonth()
            );
        }

        return $query->count();
    }

    public function remaining(User $user): int
    {
        return max(
            0,
            $this->allowance($user)
                - $this->used($user)
                - $this->reserved($user)
        );
    }

    public function canScan(User $user): bool
    {
        return $this->remaining($user) > 0;
    }

    public function exhaustedMessage(
        User $user
    ): string {
        $plan = $this->plan($user);

        if ($plan === 'free') {
            return
                'You have used your '
                .$this->allowance($user)
                .' free AI photo scans. '
                .'View Membership & Pricing for more AI scans.';
        }

        return
            'You have used your AI scan allowance for this month. '
            .'View Membership & Pricing for available options.';
    }

    public function reserveScan(
        User $user,
        string $feature,
        ?int $sourceId = null,
        array $metadata = []
    ): ?AiUsage {
        $reservations = $this->reserveScans(
            $user,
            1,
            $feature,
            $sourceId,
            $metadata
        );

        return $reservations[0] ?? null;
    }

    /**
     * @return array<int, AiUsage>|null
     */
    public function reserveScans(
        User $user,
        int $count,
        string $feature,
        ?int $sourceId = null,
        array $metadata = []
    ): ?array {
        if ($count < 1) {
            throw new RuntimeException(
                'AI reservation count must be at least one.'
            );
        }

        return DB::transaction(function () use (
            $user,
            $count,
            $feature,
            $sourceId,
            $metadata
        ): ?array {
            /*
             * Lock one stable row per user so two simultaneous
             * requests cannot both reserve the same allowance.
             */
            $lockedUser = User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            /*
             * Recover automatically from crashed/abandoned
             * requests.
             */
            AiUsage::query()
                ->where(
                    'user_id',
                    $lockedUser->getKey()
                )
                ->where(
                    'status',
                    AiUsage::STATUS_PENDING
                )
                ->where(
                    'created_at',
                    '<',
                    $this->reservationCutoff()
                )
                ->update([
                    'status' =>
                        AiUsage::STATUS_FAILED,
                    'updated_at' => now(),
                ]);

            if ($count > $this->remaining($lockedUser)) {
                return null;
            }

            $reservations = [];

            for ($index = 0; $index < $count; $index++) {
                $reservations[] =
                    AiUsage::query()->create([
                        'user_id' =>
                            $lockedUser->getKey(),
                        'feature' => $feature,
                        'provider' => config(
                            'quick-listing.ai_provider',
                            'openai'
                        ),
                        'model' => config(
                            'quick-listing.ai_model'
                        ),
                        'status' =>
                            AiUsage::STATUS_PENDING,
                        'source_id' => $sourceId,
                        'metadata' => $metadata,
                    ]);
            }

            return $reservations;
        });
    }

    public function completeSuccess(
        AiUsage $reservation,
        ?int $sourceId = null,
        array $metadata = []
    ): AiUsage {
        return $this->completeReservation(
            $reservation,
            AiUsage::STATUS_SUCCESS,
            $sourceId,
            $metadata
        );
    }

    public function completeFailure(
        AiUsage $reservation,
        ?int $sourceId = null,
        array $metadata = []
    ): AiUsage {
        return $this->completeReservation(
            $reservation,
            AiUsage::STATUS_FAILED,
            $sourceId,
            $metadata
        );
    }

    private function completeReservation(
        AiUsage $reservation,
        string $status,
        ?int $sourceId,
        array $metadata
    ): AiUsage {
        $reservation->refresh();

        if (
            $reservation->status
                !== AiUsage::STATUS_PENDING
        ) {
            throw new RuntimeException(
                'AI reservation has already been completed.'
            );
        }

        $existingMetadata =
            is_array($reservation->metadata)
                ? $reservation->metadata
                : [];

        $reservation->forceFill([
            'status' => $status,
            'source_id' =>
                $sourceId
                    ?? $reservation->source_id,
            'metadata' => array_merge(
                $existingMetadata,
                $metadata
            ),
        ])->save();

        return $reservation->refresh();
    }

    private function reservationCutoff()
    {
        return now()->subMinutes(
            self::RESERVATION_TTL_MINUTES
        );
    }

    /**
     * Mark any still-pending reservations as failed.
     *
     * Used when a request aborts unexpectedly so allowance
     * becomes available again immediately.
     *
     * @param iterable<AiUsage> $reservations
     */
    public function failPendingReservations(
        iterable $reservations,
        array $metadata = []
    ): void {
        foreach ($reservations as $reservation) {
            if (! $reservation instanceof AiUsage) {
                continue;
            }

            $fresh = $reservation->fresh();

            if (
                ! $fresh
                || $fresh->status
                    !== AiUsage::STATUS_PENDING
            ) {
                continue;
            }

            $this->completeFailure(
                $fresh,
                null,
                $metadata
            );
        }
    }

    public function recordSuccess(
        User $user,
        string $feature,
        ?int $sourceId = null,
        array $metadata = []
    ): AiUsage {
        return AiUsage::query()->create([
            'user_id' => $user->getKey(),
            'feature' => $feature,
            'provider' => config(
                'quick-listing.ai_provider',
                'openai'
            ),
            'model' => config(
                'quick-listing.ai_model'
            ),
            'status' => AiUsage::STATUS_SUCCESS,
            'source_id' => $sourceId,
            'metadata' => $metadata,
        ]);
    }

    public function recordFailure(
        User $user,
        string $feature,
        ?int $sourceId = null,
        array $metadata = []
    ): AiUsage {
        return AiUsage::query()->create([
            'user_id' => $user->getKey(),
            'feature' => $feature,
            'provider' => config(
                'quick-listing.ai_provider',
                'openai'
            ),
            'model' => config(
                'quick-listing.ai_model'
            ),
            'status' => AiUsage::STATUS_FAILED,
            'source_id' => $sourceId,
            'metadata' => $metadata,
        ]);
    }
}
