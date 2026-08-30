<?php

declare(strict_types=1);

namespace Modules\Listing\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Modules\Category\Models\Category;
use Modules\Listing\Models\Listing;
use Modules\Listing\Support\SampleListingImageCatalog;
use Modules\Location\Models\City;
use Modules\User\App\Models\User;
use Modules\User\App\Support\DemoUserCatalog;

class ListingSeeder extends Seeder
{
    private const TITLE_PREFIXES = [
        'Clean',
        'Lightly used',
        'Special offer',
        'Well priced',
        'Owner listed',
        'Must-see',
        'Well kept',
    ];

    private const MAX_DEMO_LISTINGS = 120;

    public function run(): void
    {
        $users = $this->resolveSeederUsers();
        $categories = $this->resolveSeedableCategories();
        $imagePool = SampleListingImageCatalog::uniquePaths();

        if ($users->isEmpty() || $categories->isEmpty() || $imagePool->isEmpty()) {
            return;
        }
        $plannedSlugs = [];
        $assignedImageIndex = 0;

        foreach ($categories as $category) {
            foreach ($users as $user) {
                if ($assignedImageIndex >= self::MAX_DEMO_LISTINGS) {
                    break 2;
                }

                $listingData = $this->buildListingData(
                    $category,
                    $assignedImageIndex,
                    $user,
                    $imagePool->get($assignedImageIndex % $imagePool->count())
                );
                $listing = $this->upsertListing($listingData, $category, $user);
                $plannedSlugs[] = $listing->slug;
                $this->syncListingImage($listing, $listingData['image_path']);
                $assignedImageIndex++;
            }
        }

        Listing::query()
            ->whereIn('user_id', $users->pluck('id'))
            ->where('slug', 'like', 'demo-%')
            ->whereNotIn('slug', $plannedSlugs)
            ->get()
            ->each(function (Listing $listing): void {
                $listing->clearMediaCollection('listing-images');
                $listing->delete();
            });
    }

    private function resolveSeederUsers(): Collection
    {
        return User::query()
            ->whereIn('email', DemoUserCatalog::emails())
            ->orderBy('email')
            ->get()
            ->values();
    }

    private function resolveSeedableCategories(): Collection
    {
        $leafCategories = Category::query()
            ->where('is_active', true)
            ->whereDoesntHave('children')
            ->with('parent:id,name')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        if ($leafCategories->isNotEmpty()) {
            return $leafCategories->values();
        }

        return Category::query()
            ->where('is_active', true)
            ->with('parent:id,name')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->values();
    }


    private function buildListingData(
        Category $category,
        int $index,
        User $user,
        ?string $imagePath
    ): array {
        $location = $this->resolveLocation($index);
        $title = $this->buildTitle($category, $index, $user);
        $slug = 'demo-'.Str::slug($user->email).'-'.$category->slug;

        return [
            'slug' => $slug,
            'title' => $title,
            'description' => $this->buildDescription($category, $location['city'], $location['country'], $user),
            'price' => $this->priceForCategory($category, $index),
            'city' => $location['city'],
            'country' => $location['country'],
            'contact_phone' => DemoUserCatalog::phoneFor($user->email),
            'is_featured' => $index % 7 === 0,
            'expires_at' => now()->addDays(21 + ($index % 9)),
            'created_at' => now()->subHours(6 + $index),
            'image_path' => $imagePath,
        ];
    }

    private function resolveLocation(int $index): array
    {
        // 40% Brisbane/Ipswich/Springfield, 35% Canberra region,
        // 25% other Australian locations.
        $locations = [
            ['city' => 'Brisbane', 'country' => 'Australia'],
            ['city' => 'Brisbane', 'country' => 'Australia'],
            ['city' => 'Brisbane', 'country' => 'Australia'],
            ['city' => 'Brisbane', 'country' => 'Australia'],
            ['city' => 'Springfield Lakes', 'country' => 'Australia'],
            ['city' => 'Springfield Lakes', 'country' => 'Australia'],
            ['city' => 'Ipswich', 'country' => 'Australia'],
            ['city' => 'Ipswich', 'country' => 'Australia'],

            ['city' => 'Canberra', 'country' => 'Australia'],
            ['city' => 'Canberra', 'country' => 'Australia'],
            ['city' => 'Canberra', 'country' => 'Australia'],
            ['city' => 'Belconnen', 'country' => 'Australia'],
            ['city' => 'Belconnen', 'country' => 'Australia'],
            ['city' => 'Gungahlin', 'country' => 'Australia'],
            ['city' => 'Woden', 'country' => 'Australia'],

            ['city' => 'Gold Coast', 'country' => 'Australia'],
            ['city' => 'Sydney', 'country' => 'Australia'],
            ['city' => 'Melbourne', 'country' => 'Australia'],
            ['city' => 'Adelaide', 'country' => 'Australia'],
            ['city' => 'Perth', 'country' => 'Australia'],
        ];

        return $locations[$index % count($locations)];
    }

    private function buildTitle(Category $category, int $index, User $user): string
    {
        $prefix = self::TITLE_PREFIXES[$index % count(self::TITLE_PREFIXES)];
        $categoryName = trim((string) $category->name);
        $ownerFragment = trim(Str::before($user->name, ' '));

        return sprintf(
            '%s %s for %s',
            $prefix,
            $categoryName !== '' ? $categoryName : 'item',
            $ownerFragment !== '' ? $ownerFragment : 'demo'
        );
    }

    private function buildDescription(Category $category, string $city, string $country, User $user): string
    {
        $categoryName = trim((string) $category->name);
        $location = trim(collect([$city, $country])->filter()->join(', '));

        return sprintf(
            '%s listed by %s. Clean demo condition, sample product photo assigned from the provided catalog, and ready for browsing, favorites, inbox, and panel testing. Pickup area: %s.',
            $categoryName !== '' ? $categoryName : 'Item',
            trim((string) $user->name) !== '' ? trim((string) $user->name) : 'a marketplace user',
            $location !== '' ? $location : 'Australia'
        );
    }

    private function priceForCategory(Category $category, int $index): int
    {
        $name = strtolower(trim((string) $category->name));

        $prices = match ($name) {
            'phones' => [120, 250, 450, 700, 950, 1200],
            'computers' => [250, 450, 700, 1200, 1800, 2500],
            'tablets' => [120, 250, 400, 650, 900, 1200],
            'tvs' => [100, 250, 450, 700, 1200, 1800],

            'cars' => [3500, 6500, 9500, 14500, 22000, 32000],
            'motorcycles' => [1800, 3500, 5500, 8500, 12000, 18000],
            'trucks' => [9000, 15000, 24000, 35000, 50000, 75000],
            'boats' => [2500, 5000, 9000, 15000, 25000, 35000],

            'for sale' => [450000, 550000, 650000, 750000, 900000, 1200000],
            'for rent' => [350, 450, 550, 650, 750, 900],
            'commercial' => [300000, 450000, 600000, 800000, 1100000, 1500000],

            'furniture' => [20, 80, 150, 300, 600, 1200],
            'garden' => [20, 60, 120, 250, 500, 1000],
            'appliances' => [50, 150, 300, 500, 800, 1400],

            'men', 'women', 'kids' => [10, 30, 60, 120, 200, 350],
            'shoes' => [20, 50, 80, 130, 200, 300],

            'outdoor', 'fitness', 'team sports' => [20, 70, 150, 300, 600, 1200],

            'full time' => [55000, 65000, 75000, 85000, 95000, 110000],
            'part time' => [25000, 35000, 45000, 55000, 65000, 75000],
            'freelance' => [300, 600, 1200, 2500, 5000, 8000],

            'cleaning' => [80, 120, 160, 220, 300, 400],
            'repair' => [100, 180, 250, 350, 500, 700],
            'education' => [40, 60, 80, 100, 130, 160],

            default => [20, 50, 100, 250, 500, 1000],
        };

        return $prices[$index % count($prices)];
    }

    private function upsertListing(array $data, Category $category, User $user): Listing
    {
        $listing = Listing::updateOrCreate(
            ['slug' => $data['slug']],
            [
                'title' => $data['title'],
                'description' => $data['description'],
                'price' => $data['price'],
                'currency' => 'AUD',
                'city' => $data['city'],
                'country' => $data['country'],
                'category_id' => $category->id,
                'contact_email' => $user->email,
                'contact_phone' => $data['contact_phone'],
                'expires_at' => $data['expires_at'],
            ]
        );

        $listing->applyAdminFormData([
            'slug' => $data['slug'],
            'user_id' => $user->id,
            'status' => 'active',
            'is_featured' => $data['is_featured'],
        ]);
        $listing->save();

        $listing->forceFill([
            'created_at' => $data['created_at'],
            'updated_at' => $data['created_at'],
        ])->saveQuietly();

        return $listing;
    }

    private function syncListingImage(Listing $listing, ?string $imageAbsolutePath): void
    {
        if (! is_string($imageAbsolutePath) || ! is_file($imageAbsolutePath)) {
            return;
        }

        $listing->replacePublicImage(
            $imageAbsolutePath,
            SampleListingImageCatalog::fileNameFor($imageAbsolutePath, $listing->slug)
        );
    }
}
