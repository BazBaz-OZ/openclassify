<?php

declare(strict_types=1);

namespace Modules\Panel\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Category\Models\Category;
use Modules\Listing\Models\WantedPost;

class WantedController extends Controller
{
    public function index(Request $request): View
    {
        $wantedPosts = WantedPost::query()
            ->ownedByUser($request->user()->getKey())
            ->with('category:id,name')
            ->latest('id')
            ->paginate(20);

        return view('panel::wanted.index', [
            'wantedPosts' => $wantedPosts,
        ]);
    }

    public function create(): View
    {
        return view('panel::wanted.create', [
            'categories' => Category::activeIdNameOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateWanted($request);

        $wanted = WantedPost::query()->create([
            'user_id' => $request->user()->getKey(),
            'category_id' => $validated['category_id'] ?? null,
            'title' => trim($validated['title']),
            'description' => isset($validated['description'])
                ? trim($validated['description'])
                : null,
            'max_budget' => $validated['max_budget'] ?? null,
            'currency' => 'AUD',
            'city' => isset($validated['city'])
                ? trim($validated['city'])
                : null,
            'country' => isset($validated['country'])
                ? trim($validated['country'])
                : 'Australia',
            'status' => WantedPost::STATUS_DRAFT,
        ]);

        if ($request->input('action') === 'publish') {
            $wanted->publish();

            return redirect()
                ->route('panel.wanted.edit', $wanted)
                ->with('success', 'Wanted post created and published.');
        }

        return redirect()
            ->route('panel.wanted.edit', $wanted)
            ->with('success', 'Wanted post saved as draft.');
    }

    public function edit(
        Request $request,
        WantedPost $wanted
    ): View {
        $wanted->assertOwnedBy($request->user());

        return view('panel::wanted.edit', [
            'wanted' => $wanted,
            'categories' => Category::activeIdNameOptions(),
        ]);
    }

    public function update(
        Request $request,
        WantedPost $wanted
    ): RedirectResponse {
        $wanted->assertOwnedBy($request->user());

        $validated = $this->validateWanted($request);

        $wanted->update([
            'category_id' => $validated['category_id'] ?? null,
            'title' => trim($validated['title']),
            'description' => isset($validated['description'])
                ? trim($validated['description'])
                : null,
            'max_budget' => $validated['max_budget'] ?? null,
            'currency' => 'AUD',
            'city' => isset($validated['city'])
                ? trim($validated['city'])
                : null,
            'country' => isset($validated['country'])
                ? trim($validated['country'])
                : 'Australia',
        ]);

        if ($request->input('action') === 'publish') {
            $wanted->publish();

            return back()->with(
                'success',
                'Wanted post saved and published.'
            );
        }

        return back()->with(
            'success',
            'Wanted post updated.'
        );
    }

    public function fulfill(
        Request $request,
        WantedPost $wanted
    ): RedirectResponse {
        $wanted->assertOwnedBy($request->user());

        $wanted->markFulfilled();

        return back()->with(
            'success',
            'Wanted post marked fulfilled.'
        );
    }

    public function cancel(
        Request $request,
        WantedPost $wanted
    ): RedirectResponse {
        $wanted->assertOwnedBy($request->user());

        $wanted->forceFill([
            'status' => WantedPost::STATUS_CANCELLED,
        ])->save();

        return back()->with(
            'success',
            'Wanted post cancelled.'
        );
    }

    private function validateWanted(Request $request): array
    {
        return $request->validate([
            'title' => [
                'required',
                'string',
                'max:150',
            ],
            'description' => [
                'nullable',
                'string',
                'max:4000',
            ],
            'category_id' => [
                'nullable',
                'integer',
                'exists:categories,id',
            ],
            'max_budget' => [
                'nullable',
                'numeric',
                'min:1',
                'max:99999999',
            ],
            'city' => [
                'nullable',
                'string',
                'max:120',
            ],
            'country' => [
                'nullable',
                'string',
                'max:120',
            ],
        ]);
    }
}
