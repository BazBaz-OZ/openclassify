<?php

declare(strict_types=1);

namespace Modules\Listing\Support;

use Modules\Listing\Models\AiUsage;
use Modules\User\App\Models\User;

class AiEntitlement
{
    public function plan(User $user): string
    {
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

    public function remaining(User $user): int
    {
        return max(
            0,
            $this->allowance($user)
                - $this->used($user)
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
                'You have used your 3 free AI photo scans. '
                .'View Membership & Pricing for more AI scans.';
        }

        return
            'You have used your AI scan allowance for this month. '
            .'View Membership & Pricing for available options.';
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
