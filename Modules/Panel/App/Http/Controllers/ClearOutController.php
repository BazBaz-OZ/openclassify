<?php

declare(strict_types=1);

namespace Modules\Panel\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Listing\Models\ClearOut;
use Modules\Listing\Models\Listing;

class ClearOutController extends Controller
{
    public function index(Request $request): View
    {
        $clearOuts = ClearOut::query()
            ->ownedByUser($request->user()->getKey())
            ->withCount([
                'listings',
                'listings as available_listings_count' => fn ($query) => $query
                    ->where('status', 'active')
                    ->where('quantity_available', '>', 0),
                'listings as sold_listings_count' => fn ($query) => $query
                    ->where('status', 'sold'),
            ])
            ->latest('id')
            ->paginate(20);

        return view('panel::clear-outs.index', [
            'clearOuts' => $clearOuts,
        ]);
    }

    public function create(): View
    {
        return view('panel::clear-outs.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:4000'],
            'city' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:120'],
        ]);

        $clearOut = ClearOut::query()->create([
            'user_id' => $request->user()->getKey(),
            'title' => trim($validated['title']),
            'description' => isset($validated['description'])
                ? trim($validated['description'])
                : null,
            'city' => isset($validated['city'])
                ? trim($validated['city'])
                : null,
            'country' => isset($validated['country'])
                ? trim($validated['country'])
                : null,
            'status' => ClearOut::STATUS_DRAFT,
        ]);

        return redirect()
            ->route('panel.clear-outs.edit', $clearOut)
            ->with('success', 'Clear Out created. Now add some listings.');
    }

    public function edit(Request $request, ClearOut $clearOut): View
    {
        $clearOut->assertOwnedBy($request->user());

        $listings = Listing::query()
            ->ownedByUser($request->user()->getKey())
            ->where(function ($query) use ($clearOut): void {
                $query
                    ->whereNull('clear_out_id')
                    ->orWhere('clear_out_id', $clearOut->getKey());
            })
            ->latest('id')
            ->get();

        return view('panel::clear-outs.edit', [
            'clearOut' => $clearOut->load('listings'),
            'listings' => $listings,
        ]);
    }

    public function update(Request $request, ClearOut $clearOut): RedirectResponse
    {
        $clearOut->assertOwnedBy($request->user());

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:4000'],
            'city' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:120'],
            'listing_ids' => ['nullable', 'array'],
            'listing_ids.*' => ['integer'],
        ]);

        $clearOut->update([
            'title' => trim($validated['title']),
            'description' => isset($validated['description'])
                ? trim($validated['description'])
                : null,
            'city' => isset($validated['city'])
                ? trim($validated['city'])
                : null,
            'country' => isset($validated['country'])
                ? trim($validated['country'])
                : null,
        ]);

        $selectedIds = collect($validated['listing_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        // Remove listings no longer selected from this Clear Out.
        Listing::query()
            ->ownedByUser($request->user()->getKey())
            ->where('clear_out_id', $clearOut->getKey())
            ->when(
                $selectedIds->isNotEmpty(),
                fn ($query) => $query->whereNotIn('id', $selectedIds->all())
            )
            ->update(['clear_out_id' => null]);

        if ($selectedIds->isNotEmpty()) {
            // Only allow the seller's own listings to be attached.
            Listing::query()
                ->ownedByUser($request->user()->getKey())
                ->whereIn('id', $selectedIds->all())
                ->where(function ($query) use ($clearOut): void {
                    $query
                        ->whereNull('clear_out_id')
                        ->orWhere('clear_out_id', $clearOut->getKey());
                })
                ->update(['clear_out_id' => $clearOut->getKey()]);
        }

        if ($request->input('action') === 'publish') {
            if (! $clearOut->listings()->exists()) {
                return back()
                    ->withInput()
                    ->withErrors([
                        'clear_out' => 'Add at least one listing before publishing this Clear Out.',
                    ]);
            }

            $clearOut->update([
                'status' => ClearOut::STATUS_ACTIVE,
                'starts_at' => $clearOut->starts_at ?? now(),
            ]);

            return back()->with('success', 'Clear Out saved and published.');
        }

        return back()->with('success', 'Clear Out updated.');
    }

    public function publish(Request $request, ClearOut $clearOut): RedirectResponse
    {
        $clearOut->assertOwnedBy($request->user());

        if (! $clearOut->listings()->exists()) {
            return back()->withErrors([
                'clear_out' => 'Add at least one listing before publishing this Clear Out.',
            ]);
        }

        $clearOut->update([
            'status' => ClearOut::STATUS_ACTIVE,
            'starts_at' => $clearOut->starts_at ?? now(),
        ]);

        return back()->with('success', 'Clear Out published.');
    }

    public function complete(Request $request, ClearOut $clearOut): RedirectResponse
    {
        $clearOut->assertOwnedBy($request->user());

        $clearOut->update([
            'status' => ClearOut::STATUS_COMPLETED,
            'ends_at' => now(),
        ]);

        return back()->with('success', 'Clear Out completed.');
    }
}
