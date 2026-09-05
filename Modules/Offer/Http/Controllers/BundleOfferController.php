<?php

declare(strict_types=1);

namespace Modules\Offer\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Listing\Models\ClearOut;
use Modules\Listing\Models\Listing;
use Modules\Listing\Models\VirtualGarage;
use Modules\Notification\Models\UserNotification;
use Modules\Offer\Models\BundleOffer;

class BundleOfferController extends Controller
{
    public function index(Request $request): View
    {
        $userId = (int) $request->user()->getKey();

        $direction = $request->query('direction') === 'sent'
            ? 'sent'
            : 'received';

        $bundleOffers = $direction === 'sent'
            ? BundleOffer::sentByBuyer($userId)
            : BundleOffer::receivedBySeller($userId);

        return view('offer::bundles', [
            'bundleOffers' => $bundleOffers,
            'direction' => $direction,
        ]);
    }

    public function store(
        Request $request,
        ClearOut $clearOut
    ): RedirectResponse {
        if ($clearOut->status !== ClearOut::STATUS_ACTIVE) {
            return back()->with(
                'error',
                'This Clear Out is no longer accepting bundle offers.'
            );
        }

        $sellerId = (int) $clearOut->user_id;
        $buyerId = (int) $request->user()->getKey();

        if ($buyerId === $sellerId) {
            return back()->with(
                'error',
                'You cannot make a bundle offer on your own Clear Out.'
            );
        }

        $validated = $request->validate([
            'listing_ids' => [
                'required',
                'array',
                'min:2',
                'max:50',
            ],
            'listing_ids.*' => [
                'required',
                'integer',
                'distinct',
            ],
            'amount' => [
                'required',
                'numeric',
                'min:1',
                'max:99999999',
            ],
            'message' => [
                'nullable',
                'string',
                'max:500',
            ],
        ], [
            'listing_ids.min' =>
                'Select at least two items for a bundle offer.',
        ]);

        $ids = collect($validated['listing_ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $listings = Listing::query()
            ->where('clear_out_id', $clearOut->getKey())
            ->where('user_id', $sellerId)
            ->whereIn('id', $ids->all())
            ->where('status', 'active')
            ->where('quantity_available', '>', 0)
            ->get();

        if ($listings->count() !== $ids->count()) {
            return back()
                ->withInput()
                ->with(
                    'error',
                    'One or more selected items are no longer available.'
                );
        }

        $currency = (string) (
            $listings->first()?->currency
            ?: config('app.default_currency', 'AUD')
        );

        $bundle = BundleOffer::place(
            $clearOut,
            $buyerId,
            $sellerId,
            $listings->all(),
            (float) $validated['amount'],
            $currency,
            $validated['message'] ?? null,
        );

        UserNotification::publish(
            $sellerId,
            UserNotification::TYPE_OFFER,
            'New bundle offer',
            'You received a '.$bundle->amountLabel()
                .' bundle offer for '
                .$listings->count()
                .' items from '
                .$clearOut->title.'.',
            route('panel.bundle-offers.index'),
        );

        return redirect()
            ->route('panel.bundle-offers.index', [
                'direction' => 'sent',
            ])
            ->with('success', 'Bundle offer sent.');
    }

    public function storeVirtualGarage(
        Request $request,
        VirtualGarage $virtualGarage
    ): RedirectResponse {
        if ($virtualGarage->status !== VirtualGarage::STATUS_ACTIVE) {
            return back()->with(
                'error',
                'This Virtual Garage is no longer accepting bundle offers.'
            );
        }

        $sellerId = (int) $virtualGarage->user_id;
        $buyerId = (int) $request->user()->getKey();

        if ($buyerId === $sellerId) {
            return back()->with(
                'error',
                'You cannot make a bundle offer on your own Virtual Garage.'
            );
        }

        $validated = $request->validate([
            'listing_ids' => [
                'required',
                'array',
                'min:2',
                'max:50',
            ],
            'listing_ids.*' => [
                'required',
                'integer',
                'distinct',
            ],
            'amount' => [
                'required',
                'numeric',
                'min:1',
                'max:99999999',
            ],
            'message' => [
                'nullable',
                'string',
                'max:500',
            ],
        ], [
            'listing_ids.min' =>
                'Select at least two items for a bundle offer.',
        ]);

        $ids = collect($validated['listing_ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $listings = $virtualGarage->listings()
            ->where('listings.user_id', $sellerId)
            ->whereIn('listings.id', $ids->all())
            ->where('listings.status', 'active')
            ->where('listings.quantity_available', '>', 0)
            ->get();

        if ($listings->count() !== $ids->count()) {
            return back()
                ->withInput()
                ->with(
                    'error',
                    'One or more selected items are no longer available.'
                );
        }

        $currency = (string) (
            $listings->first()?->currency
            ?: config('app.default_currency', 'AUD')
        );

        $bundle = BundleOffer::place(
            $virtualGarage,
            $buyerId,
            $sellerId,
            $listings->all(),
            (float) $validated['amount'],
            $currency,
            $validated['message'] ?? null,
        );

        UserNotification::publish(
            $sellerId,
            UserNotification::TYPE_OFFER,
            'New Virtual Garage offer',
            'You received a '.$bundle->amountLabel()
                .' bundle offer for '
                .$listings->count()
                .' items from '
                .$virtualGarage->title.'.',
            route('panel.bundle-offers.index'),
        );

        return redirect()
            ->route('panel.bundle-offers.index', [
                'direction' => 'sent',
            ])
            ->with('success', 'Bundle offer sent.');
    }

    public function accept(
        Request $request,
        BundleOffer $bundleOffer
    ): RedirectResponse {
        $this->authorizeSeller($request, $bundleOffer);

        $bundleOffer->accept();

        UserNotification::publish(
            (int) $bundleOffer->buyer_id,
            UserNotification::TYPE_OFFER,
            'Bundle offer accepted',
            'Your bundle offer of '
                .$bundleOffer->amountLabel()
                .' was accepted.',
            route('panel.bundle-offers.index', [
                'direction' => 'sent',
            ]),
        );

        return back()->with(
            'success',
            'Bundle offer accepted.'
        );
    }

    public function decline(
        Request $request,
        BundleOffer $bundleOffer
    ): RedirectResponse {
        $this->authorizeSeller($request, $bundleOffer);

        $bundleOffer->decline();

        UserNotification::publish(
            (int) $bundleOffer->buyer_id,
            UserNotification::TYPE_OFFER,
            'Bundle offer declined',
            'Your bundle offer was declined.',
            route('panel.bundle-offers.index', [
                'direction' => 'sent',
            ]),
        );

        return back()->with(
            'success',
            'Bundle offer declined.'
        );
    }

    public function fulfill(
        Request $request,
        BundleOffer $bundleOffer
    ): RedirectResponse {
        abort_unless(
            (int) $bundleOffer->seller_id
                === (int) $request->user()->getKey(),
            403
        );

        if (! $bundleOffer->isAccepted()) {
            return back()->with(
                'error',
                'Only accepted bundle offers can be marked collected.'
            );
        }

        if ($bundleOffer->isFulfilled()) {
            return back()->with(
                'success',
                'This bundle has already been marked collected.'
            );
        }

        try {
            $bundleOffer->fulfill();
        } catch (\RuntimeException $exception) {
            return back()->with(
                'error',
                $exception->getMessage()
            );
        }

        UserNotification::publish(
            (int) $bundleOffer->buyer_id,
            UserNotification::TYPE_OFFER,
            'Bundle collected',
            'Your accepted bundle has been marked collected by the seller.',
            route('panel.bundle-offers.index', [
                'direction' => 'sent',
            ]),
        );

        return back()->with(
            'success',
            'Bundle marked collected and stock updated.'
        );
    }

    public function withdraw(
        Request $request,
        BundleOffer $bundleOffer
    ): RedirectResponse {
        abort_unless(
            (int) $bundleOffer->buyer_id
                === (int) $request->user()->getKey(),
            403
        );

        abort_unless($bundleOffer->isPending(), 422);

        $bundleOffer->withdraw();

        return back()->with(
            'success',
            'Bundle offer withdrawn.'
        );
    }

    private function authorizeSeller(
        Request $request,
        BundleOffer $bundleOffer
    ): void {
        abort_unless(
            (int) $bundleOffer->seller_id
                === (int) $request->user()->getKey(),
            403
        );

        abort_unless($bundleOffer->isPending(), 422);
    }
}
