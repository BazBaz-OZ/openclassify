<?php

declare(strict_types=1);

namespace Modules\Listing\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Modules\Category\Models\Category;
use Modules\Listing\Models\Listing;
use Modules\Listing\Support\SampleListingImageCatalog;
use Modules\User\App\Models\User;
use Modules\User\App\Support\DemoUserCatalog;

class ListingSeeder extends Seeder
{
    private const MAX_DEMO_LISTINGS = 120;

    public function run(): void
    {
        $users = $this->resolveSeederUsers();
        $categories = $this->resolveSeedableCategories();

        if ($users->isEmpty() || $categories->isEmpty()) {
            return;
        }

        $plannedSlugs = [];
        $listingIndex = 0;

        foreach ($categories as $category) {
            if ($listingIndex >= self::MAX_DEMO_LISTINGS) {
                break;
            }

            $user = $users->get($listingIndex % $users->count());

            if (! $user instanceof User) {
                continue;
            }

            $listingData = $this->buildListingData(
                $category,
                $listingIndex,
                $user,
                SampleListingImageCatalog::pathFor($category, $listingIndex)
            );

            $listing = $this->upsertListing($listingData, $category, $user);

            $plannedSlugs[] = $listing->slug;

            $this->syncListingImage(
                $listing,
                $listingData['image_path']
            );

            $listingIndex++;
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

    /**
     * Return leaf categories in a balanced order.
     *
     * Instead of exhausting Electronics, Appliances, Furniture, etc. before
     * reaching later categories, take the first child from every parent,
     * then the second child from every parent, and so on.
     */
    private function resolveSeedableCategories(): Collection
    {
        $leafCategories = Category::query()
            ->where('is_active', true)
            ->whereDoesntHave('children', function ($query): void {
                $query->where('is_active', true);
            })
            ->with('parent:id,name,slug,sort_order')
            ->get();

        if ($leafCategories->isEmpty()) {
            return Category::query()
                ->where('is_active', true)
                ->with('parent:id,name,slug,sort_order')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->values();
        }

        $families = $leafCategories
            ->groupBy(fn (Category $category): string => (string) ($category->parent_id ?? $category->id))
            ->sortBy(function (Collection $family): int {
                $first = $family->first();

                if (! $first instanceof Category) {
                    return PHP_INT_MAX;
                }

                return (int) ($first->parent?->sort_order ?? $first->sort_order);
            })
            ->values()
            ->map(
                fn (Collection $family): Collection => $family
                    ->sortBy([
                        ['sort_order', 'asc'],
                        ['name', 'asc'],
                    ])
                    ->values()
            );

        $maxChildren = (int) ($families->map->count()->max() ?? 0);
        $balanced = collect();

        for ($childIndex = 0; $childIndex < $maxChildren; $childIndex++) {
            foreach ($families as $family) {
                if ($family->has($childIndex)) {
                    $balanced->push($family->get($childIndex));
                }
            }
        }

        return $balanced->values();
    }

    private function buildListingData(
        Category $category,
        int $index,
        User $user,
        ?string $imagePath
    ): array {
        $location = $this->resolveLocation($index);
        $title = $this->buildTitle($category, $index);
        $slug = 'demo-'.Str::slug($user->email).'-'.$category->slug;

        return [
            'slug' => $slug,
            'title' => $title,
            'description' => $this->buildDescription(
                $category,
                $location['city'],
                $location['country'],
                $user
            ),
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

    private function buildTitle(Category $category, int $index): string
    {
        $name = strtolower(trim((string) $category->name));

        $titles = match ($name) {
            'computers & laptops' => [
                'Laptop in Great Working Condition',
                'Desktop Computer Ready to Use',
                'Laptop with Charger',
            ],
            'tablets & ipads' => [
                'iPad in Great Condition',
                'Tablet with Case and Charger',
                'Tablet Ready for a New Home',
            ],
            'mobile phones' => [
                'Unlocked Smartphone with Charger',
                'iPhone in Great Condition',
                'Android Phone Ready to Use',
            ],
            'tvs & projectors' => [
                'Smart TV with Remote',
                '4K Television in Great Condition',
                'Projector Ready for Movie Nights',
            ],
            'sound systems & speakers' => [
                'Bluetooth Speaker in Great Condition',
                'Home Sound System',
                'Quality Speakers Ready to Go',
            ],
            'headphones' => [
                'Wireless Headphones',
                'Noise Cancelling Headphones',
                'Headphones in Great Condition',
            ],
            'cameras & photography' => [
                'Digital Camera with Accessories',
                'Camera Gear Bundle',
                'Photography Equipment in Great Condition',
            ],
            'networking & wi-fi equipment' => [
                'Wi-Fi Router in Great Condition',
                'Network Equipment Bundle',
                'Wireless Access Point',
            ],

            'fridges & freezers' => [
                'Fridge in Excellent Working Order',
                'Clean Fridge Freezer',
                'Freezer in Great Working Condition',
            ],
            'washing machines & dryers' => [
                'Front Load Washing Machine',
                'Washing Machine in Great Condition',
                'Clothes Dryer in Working Order',
            ],
            'dishwashers' => [
                'Dishwasher in Great Working Order',
                'Clean Dishwasher Ready for Pickup',
            ],
            'ovens & cooktops' => [
                'Electric Oven in Working Order',
                'Cooktop in Great Condition',
            ],
            'microwaves' => [
                'Microwave in Great Condition',
                'Clean Microwave Ready to Use',
            ],
            'coffee machines' => [
                'Coffee Machine in Great Condition',
                'Coffee Maker Ready to Use',
            ],

            'sofas & lounge furniture' => [
                'Comfortable Lounge Suite',
                'Sofa in Great Condition',
                'Lounge Clearing Out',
            ],
            'beds & bedroom furniture' => [
                'Bedroom Furniture Set',
                'Bed Frame in Great Condition',
                'Bedroom Furniture Clearing Out',
            ],
            'tables & chairs' => [
                'Solid Timber Dining Table',
                'Table and Chairs Set',
                'Dining Setting in Great Condition',
            ],
            'cabinets & storage' => [
                'Storage Cabinet in Great Condition',
                'Cabinet Ready for Pickup',
            ],

            'timber' => [
                'Leftover Timber from Renovation',
                'Building Timber - Surplus to Needs',
            ],
            'tiles' => [
                'Leftover Floor Tiles',
                'Surplus Tiles from Renovation',
                'Unused Tiles - Multiple Boxes',
            ],
            'bricks & blocks' => [
                'Leftover Bricks - Surplus to Needs',
                'Building Blocks Available',
            ],
            'roofing materials' => [
                'Leftover Roofing Materials',
                'Surplus Roofing Sheets',
            ],
            'doors' => [
                'Door in Great Condition',
                'Unused Door from Renovation',
            ],
            'windows' => [
                'Window from Renovation',
                'Unused Window Ready for Pickup',
            ],
            'flooring' => [
                'Leftover Flooring from Renovation',
                'Surplus Flooring Materials',
            ],
            'fencing & gates' => [
                'Leftover Fencing Materials',
                'Gate in Great Condition',
            ],
            'cabinets & benchtops' => [
                'Kitchen Cabinets from Renovation',
                'Benchtop Surplus to Needs',
            ],

            'corded power tools' => [
                'Corded Power Tool in Great Condition',
                'Power Tool Ready for Work',
            ],
            'cordless & battery tools' => [
                'Cordless Power Tool with Battery',
                'Battery Tool in Great Condition',
            ],
            'hand tools' => [
                'Hand Tool Bundle',
                'Quality Hand Tools',
            ],
            'toolboxes & storage' => [
                'Toolbox in Great Condition',
                'Workshop Tool Storage',
            ],
            'workshop equipment' => [
                'Workshop Equipment Clearing Out',
                'Garage Workshop Equipment',
            ],
            'welding equipment' => [
                'Welding Equipment in Great Condition',
                'Welder Ready for Work',
            ],

            'car parts' => [
                'Car Parts Clearing Out',
                'Spare Car Parts',
            ],
            'motorcycle parts' => [
                'Motorcycle Parts Clearing Out',
                'Spare Motorcycle Parts',
            ],
            '4wd parts & accessories' => [
                '4WD Accessories Clearing Out',
                '4WD Parts and Accessories',
            ],
            'wheels & tyres' => [
                'Set of Wheels and Tyres',
                'Wheels in Great Condition',
            ],
            'roof racks & tow bars' => [
                'Roof Rack in Great Condition',
                'Tow Bar Ready for Pickup',
            ],

            'plants & pots' => [
                'Garden Plants and Pots',
                'Potted Plants Clearing Out',
            ],
            'bbqs & outdoor cooking' => [
                'BBQ in Great Condition',
                'Outdoor BBQ Ready to Use',
            ],
            'lawn mowers' => [
                'Lawn Mower in Good Working Order',
                'Mower Ready for the Weekend',
            ],

            'gym & fitness equipment' => [
                'Home Gym Equipment',
                'Fitness Equipment Clearing Out',
            ],
            'bikes & cycling' => [
                'Bike in Great Condition',
                'Quality Bicycle Ready to Ride',
            ],
            'golf' => [
                'Golf Club Set',
                'Golf Gear Clearing Out',
            ],
            'fishing' => [
                'Fishing Gear Bundle',
                'Fishing Equipment Clearing Out',
            ],

            "men's clothing" => [
                "Men's Clothing Bundle",
                "Men's Wardrobe Clearout",
            ],
            "women's clothing" => [
                "Women's Clothing Bundle",
                "Women's Wardrobe Clearout",
            ],
            "kids' clothing" => [
                "Kids' Clothing Bundle",
                "Kids' Wardrobe Clearout",
            ],
            'shoes' => [
                'Shoes in Great Condition',
                'Near New Shoes',
            ],

            'toys' => [
                'Kids Toy Bundle',
                'Toys Clearing Out',
            ],
            'baby equipment' => [
                'Baby Equipment Bundle',
                'Baby Gear in Great Condition',
            ],
            'prams & strollers' => [
                'Pram in Great Condition',
                'Baby Stroller Ready to Use',
            ],

            'gaming consoles' => [
                'Gaming Console with Controller',
                'Console in Great Condition',
            ],
            'video games' => [
                'Video Game Bundle',
                'Games Collection Clearing Out',
            ],
            'board games' => [
                'Board Game Bundle',
                'Family Board Games',
            ],

            'coins & banknotes' => [
                'Coin Collection',
                'Collectable Coins',
            ],
            'trading cards' => [
                'Trading Card Collection',
                'Collectable Cards Bundle',
            ],
            'sports memorabilia' => [
                'Sports Memorabilia Collection',
                'Collectable Sporting Item',
            ],

            'antique furniture' => [
                'Antique Furniture Piece',
                'Vintage Furniture in Great Condition',
            ],
            'ceramics & glassware' => [
                'Vintage Ceramics Collection',
                'Antique Glassware',
            ],

            'books' => [
                'Book Collection Clearing Out',
                'Box of Books',
            ],
            'textbooks' => [
                'Textbook Bundle',
                'Study Books Clearing Out',
            ],
            'vinyl records' => [
                'Vinyl Record Collection',
                'Records Clearing Out',
            ],

            'desks' => [
                'Office Desk in Great Condition',
                'Desk Ready for Pickup',
            ],
            'office chairs' => [
                'Office Chair in Great Condition',
                'Ergonomic Office Chair',
            ],
            'shelving' => [
                'Office Shelving',
                'Storage Shelves Ready for Pickup',
            ],
            'filing cabinets' => [
                'Filing Cabinet in Great Condition',
                'Office Filing Cabinet',
            ],

            'model kits' => [
                'Model Kit Collection',
                'Model Kits Clearing Out',
            ],
            'rc cars, boats & aircraft' => [
                'RC Hobby Gear',
                'Remote Control Collection',
            ],
            'art supplies' => [
                'Art Supplies Bundle',
                'Art Materials Clearing Out',
            ],
            'sewing & fabric' => [
                'Sewing and Fabric Bundle',
                'Craft Fabric Clearing Out',
            ],
            'musical instruments' => [
                'Musical Instrument in Great Condition',
                'Music Gear Clearing Out',
            ],

            'beds & bedding' => [
                'Pet Bed in Great Condition',
                'Pet Bedding and Accessories',
            ],
            'crates, cages & carriers' => [
                'Pet Carrier in Great Condition',
                'Pet Crate Ready to Use',
            ],
            'aquariums & fish tanks' => [
                'Glass Aquarium with Stand',
                'Fish Tank in Great Condition',
            ],
            'pet furniture & scratchers' => [
                'Cat Scratching Tower',
                'Pet Furniture in Great Condition',
            ],
            'enclosures & kennels' => [
                'Pet Kennel in Great Condition',
                'Outdoor Pet Enclosure',
            ],

            'free furniture' => [
                'Free Furniture - Pickup Only',
                'Free Furniture - Must Go',
            ],
            'free building materials' => [
                'Free Leftover Building Materials',
                'Free Renovation Materials - Pickup Only',
            ],
            'free garden items' => [
                'Free Garden Items - Pickup Only',
                'Free Garden Supplies',
            ],
            'free appliances' => [
                'Free Appliance - Pickup Only',
                'Free Appliance - Must Go',
            ],
            'free electronics' => [
                'Free Electronics - Pickup Only',
                'Free Electronic Items',
            ],
            'moving boxes' => [
                'Free Moving Boxes',
                'Moving Boxes - Free Pickup',
            ],
            'scrap & reusable materials' => [
                'Free Reusable Materials',
                'Free Scrap and Leftover Materials',
            ],

            default => [
                trim((string) $category->name).' - Good Condition',
                trim((string) $category->name).' - Clearing Out',
                trim((string) $category->name).' - Ready for Pickup',
                trim((string) $category->name).' - Priced to Sell',
            ],
        };

        return $titles[$index % count($titles)];
    }

    private function buildDescription(
        Category $category,
        string $city,
        string $country,
        User $user
    ): string {
        $categoryName = trim((string) $category->name);
        $location = trim(collect([$city, $country])->filter()->join(', '));
        $sellerName = trim((string) $user->name);

        $isFree = $category->parent?->slug === 'free-stuff';

        if ($isFree) {
            return sprintf(
                '%s available free to a new home. Located in %s. Message %s through Sell My Junk for more details or to arrange pickup.',
                $categoryName !== '' ? $categoryName : 'Item',
                $location !== '' ? $location : 'Australia',
                $sellerName !== '' ? $sellerName : 'the seller'
            );
        }

        return sprintf(
            '%s available in %s. Clearing out some space and would rather see it reused than thrown away. Message %s through Sell My Junk for more details or to arrange pickup.',
            $categoryName !== '' ? $categoryName : 'Item',
            $location !== '' ? $location : 'Australia',
            $sellerName !== '' ? $sellerName : 'the seller'
        );
    }

    private function priceForCategory(Category $category, int $index): int
    {
        $parentSlug = strtolower(trim((string) ($category->parent?->slug ?? $category->slug)));

        $prices = match ($parentSlug) {
            'electronics' => [20, 50, 100, 180, 300, 650, 1200],
            'appliances' => [20, 50, 100, 200, 350, 600, 900],
            'furniture-homewares' => [10, 30, 60, 120, 250, 450, 800],
            'excess-building-materials' => [5, 20, 50, 100, 200, 400, 800],
            'tools-diy' => [10, 30, 60, 120, 250, 450, 900],
            'automotive-parts-accessories' => [10, 40, 100, 250, 500, 1000, 2500],
            'garden-outdoor' => [5, 20, 50, 100, 200, 400, 750],
            'sports' => [10, 30, 70, 150, 300, 600, 1000],
            'fashion' => [5, 15, 30, 60, 120, 250, 500],
            'toys-kids-baby' => [5, 15, 30, 60, 120, 250, 500],
            'games-gaming' => [5, 15, 30, 60, 150, 300, 600],
            'collectables' => [5, 20, 50, 100, 250, 600, 1500],
            'antiques' => [20, 50, 100, 250, 500, 1000, 2500],
            'books-media' => [2, 5, 10, 20, 40, 80, 150],
            'office-business-equipment' => [10, 30, 80, 150, 300, 600, 1200],
            'hobbies-crafts' => [5, 20, 50, 100, 250, 500, 1000],
            'pet-supplies' => [5, 15, 30, 60, 120, 250, 500],
            'free-stuff' => [0],
            default => [5, 20, 50, 100, 250, 500, 1000],
        };

        return $prices[$index % count($prices)];
    }

    private function upsertListing(
        array $data,
        Category $category,
        User $user
    ): Listing {
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

    private function syncListingImage(
        Listing $listing,
        ?string $imageAbsolutePath
    ): void {
        if (! is_string($imageAbsolutePath) || ! is_file($imageAbsolutePath)) {
            // Prevent an old demo image surviving when the new category has
            // no suitable sample image.
            $listing->clearMediaCollection('listing-images');

            return;
        }

        $listing->replacePublicImage(
            $imageAbsolutePath,
            SampleListingImageCatalog::fileNameFor(
                $imageAbsolutePath,
                $listing->slug
            )
        );
    }
}
