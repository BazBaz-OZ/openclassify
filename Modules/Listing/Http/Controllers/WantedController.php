<?php

declare(strict_types=1);

namespace Modules\Listing\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\View\View;
use Modules\Listing\Models\WantedPost;
use Modules\Listing\Support\WantedMatcher;

class WantedController extends Controller
{
    public function index(): View
    {
        $wantedPosts = WantedPost::query()
            ->active()
            ->with([
                'user:id,name',
                'category:id,name',
            ])
            ->latest('published_at')
            ->paginate(24);

        return view('listing::wanted.index', [
            'wantedPosts' => $wantedPosts,
        ]);
    }

    public function show(WantedPost $wanted): View
    {
        abort_unless(
            in_array($wanted->status, [
                WantedPost::STATUS_ACTIVE,
                WantedPost::STATUS_FULFILLED,
            ], true),
            404
        );

        $wanted->load([
            'user:id,name',
            'category:id,name',
        ]);

        return view('listing::wanted.show', [
            'wanted' => $wanted,
            'matches' => WantedMatcher::listingsForWanted($wanted),
        ]);
    }
}
