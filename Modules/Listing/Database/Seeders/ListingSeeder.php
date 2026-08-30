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
        $title = $this->buildTitle($category, $index);
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

    private function buildTitle(Category $category, int $index): string
    {
        $name = strtolower(trim((string) $category->name));

        $titles = match ($name) {
            'phones' => [
                'Unlocked Smartphone in Excellent Condition',
                'iPhone with Charger and Case',
                'Android Phone - Great Everyday Mobile',
                'Near New Smartphone',
                'Budget Smartphone - Works Perfectly',
            ],
            'computers' => [
                'Gaming Desktop PC',
                'Fast Home and Office Computer',
                'Desktop PC with Monitor',
                'Compact Computer - Ready to Use',
                'High Performance Workstation',
            ],
            'tablets' => [
                'Tablet in Excellent Condition',
                'iPad with Protective Case',
                'Android Tablet - Great for Home',
                'Lightweight Tablet with Charger',
                'Family Tablet - Ready to Use',
            ],
            'tvs' => [
                'Smart TV in Excellent Condition',
                'Large Screen 4K Television',
                'Smart TV with Remote',
                'LED Television - Works Perfectly',
                'Quality TV - Great Picture',
            ],

            'cars' => [
                'Reliable Automatic Hatchback',
                'Well Maintained Family Sedan',
                'Low Kilometre SUV',
                'Economical Daily Driver',
                'Late Model Family Car',
            ],
            'motorcycles' => [
                'Learner Approved Motorcycle',
                'Well Maintained Road Bike',
                'Low Kilometre Motorcycle',
                'Weekend Cruiser',
                'Reliable Commuter Motorcycle',
            ],
            'trucks' => [
                'Reliable Work Truck',
                'Well Maintained Light Truck',
                'Commercial Truck Ready for Work',
                'Tipper Truck in Good Condition',
                'Low Kilometre Delivery Truck',
            ],
            'boats' => [
                'Fishing Boat with Trailer',
                'Family Runabout',
                'Weekend Fishing Boat',
                'Boat and Trailer Package',
                'Well Maintained Recreational Boat',
            ],

            'for sale' => [
                'Family Home in Great Location',
                'Modern Home with Plenty of Space',
                'Well Presented Three Bedroom Home',
                'Spacious Family Property',
                'Move-In Ready Home',
            ],
            'for rent' => [
                'Modern Home for Rent',
                'Two Bedroom Unit Available',
                'Family Home Available Now',
                'Well Located Rental Property',
                'Spacious Apartment for Rent',
            ],
            'commercial' => [
                'Commercial Property Opportunity',
                'Office Space in Convenient Location',
                'Retail Premises for Sale',
                'Commercial Investment Property',
                'Warehouse and Office Facility',
            ],

            'furniture' => [
                'Solid Timber Dining Table',
                'Comfortable Lounge Suite',
                'Bedroom Furniture Set',
                'Modern Storage Cabinet',
                'Outdoor Dining Setting',
            ],
            'garden' => [
                'Garden Tools and Equipment',
                'Outdoor Planter and Garden Set',
                'Lawn Mower in Good Condition',
                'Outdoor Garden Furniture',
                'Garden Equipment Bundle',
            ],
            'appliances' => [
                'Fridge in Excellent Working Order',
                'Front Load Washing Machine',
                'Microwave in Great Condition',
                'Quality Dishwasher',
                'Kitchen Appliance Bundle',
            ],

            'men' => [
                'Mens Clothing Bundle',
                'Quality Mens Jacket',
                'Mens Casual Clothing',
                'Near New Mens Clothing',
                'Mens Wardrobe Clearout',
            ],
            'women' => [
                'Womens Clothing Bundle',
                'Quality Womens Jacket',
                'Womens Casual Clothing',
                'Near New Womens Clothing',
                'Womens Wardrobe Clearout',
            ],
            'kids' => [
                'Kids Clothing Bundle',
                'Childrens Clothes - Great Condition',
                'Kids Wardrobe Clearout',
                'Quality Childrens Clothing',
                'Mixed Kids Clothing Bundle',
            ],
            'shoes' => [
                'Quality Shoes in Great Condition',
                'Near New Sneakers',
                'Comfortable Everyday Shoes',
                'Designer Style Shoes',
                'Shoes - Barely Worn',
            ],

            'outdoor' => [
                'Camping Equipment Bundle',
                'Quality Outdoor Gear',
                'Camping Setup - Ready to Go',
                'Outdoor Adventure Equipment',
                'Camping and Hiking Gear',
            ],
            'fitness' => [
                'Home Gym Equipment',
                'Adjustable Dumbbell Set',
                'Fitness Equipment Bundle',
                'Exercise Bike in Great Condition',
                'Home Workout Equipment',
            ],
            'team sports' => [
                'Sports Equipment Bundle',
                'Football Training Gear',
                'Team Sports Equipment',
                'Quality Sporting Equipment',
                'Sports Gear - Great Condition',
            ],

            'full time' => [
                'IT Support Technician - Full Time',
                'Administration Officer - Full Time',
                'Customer Service Representative',
                'Warehouse Team Member - Full Time',
                'Experienced Tradesperson Wanted',
            ],
            'part time' => [
                'Part Time Administration Assistant',
                'Part Time Customer Service Role',
                'Weekend Retail Team Member',
                'Part Time Warehouse Assistant',
                'Flexible Part Time Position',
            ],
            'freelance' => [
                'Freelance Web Designer Required',
                'Contract IT Support Technician',
                'Freelance Graphic Designer',
                'Short Term Administration Contract',
                'Independent Tradesperson Required',
            ],

            'cleaning' => [
                'Experienced House Cleaner',
                'Regular Home Cleaning Service',
                'End of Lease Cleaning',
                'Office Cleaning Service',
                'Reliable Local Cleaner',
            ],
            'repair' => [
                'Home Repair and Maintenance Service',
                'Computer and Technology Repairs',
                'General Handyman Service',
                'Appliance Repair Service',
                'Local Repair and Maintenance',
            ],
            'education' => [
                'Private Tutoring Available',
                'Maths and English Tutoring',
                'Computer Lessons and Support',
                'Experienced Local Tutor',
                'One-on-One Learning Support',
            ],

            default => [
                'Great Item - Ready for a New Home',
                'Quality Item in Good Condition',
                'Well Maintained Item',
                'Great Value Local Listing',
                'Item Available for Pickup',
            ],
        };

        return $titles[$index % count($titles)];
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
