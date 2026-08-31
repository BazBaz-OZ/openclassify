<?php

declare(strict_types=1);

namespace Modules\Category\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Category\Models\Category;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Electronics',
                'slug' => 'electronics',
                'icon' => 'img/category/electronics.png',
                'children' => [
                    'Computers & Laptops',
                    'Tablets & iPads',
                    'Mobile Phones',
                    'TVs & Projectors',
                    'Sound Systems & Speakers',
                    'Headphones',
                    'Cameras & Photography',
                    'Networking & Wi-Fi Equipment',
                    'Smart Home & Security',
                    'Computer Parts & Accessories',
                    'Cables, Chargers & Accessories',
                    'Other Electronics',
                ],
            ],
            [
                'name' => 'Appliances',
                'slug' => 'appliances',
                'icon' => 'img/category/home_tools.png',
                'children' => [
                    'Fridges & Freezers',
                    'Washing Machines & Dryers',
                    'Dishwashers',
                    'Ovens & Cooktops',
                    'Microwaves',
                    'Air Conditioners & Fans',
                    'Vacuum Cleaners',
                    'Coffee Machines',
                    'Small Kitchen Appliances',
                    'Other Appliances',
                ],
            ],
            [
                'name' => 'Furniture & Homewares',
                'slug' => 'furniture-homewares',
                'icon' => 'img/category/home_garden.png',
                'children' => [
                    'Sofas & Lounge Furniture',
                    'Beds & Bedroom Furniture',
                    'Tables & Chairs',
                    'Cabinets & Storage',
                    'Outdoor Furniture',
                    'Lighting & Lamps',
                    'Kitchenware & Dining',
                    'Home Decor',
                    'Rugs & Curtains',
                    'Bedding & Linen',
                    'Other Homewares',
                ],
            ],
            [
                'name' => 'Excess Building Materials',
                'slug' => 'excess-building-materials',
                'icon' => 'img/category/home_tools.png',
                'children' => [
                    'Timber',
                    'Tiles',
                    'Bricks & Blocks',
                    'Roofing Materials',
                    'Doors',
                    'Windows',
                    'Flooring',
                    'Plasterboard & Sheeting',
                    'Insulation',
                    'Plumbing Materials',
                    'Electrical Materials',
                    'Fencing & Gates',
                    'Concrete & Landscaping Materials',
                    'Paint & Finishes',
                    'Fixtures & Fittings',
                    'Cabinets & Benchtops',
                    'Other Building Materials',
                ],
            ],
            [
                'name' => 'Tools & DIY',
                'slug' => 'tools-diy',
                'icon' => 'img/category/home_tools.png',
                'children' => [
                    'Corded Power Tools',
                    'Cordless & Battery Tools',
                    'Hand Tools',
                    'Toolboxes & Storage',
                    'Workshop Equipment',
                    'Ladders & Access Equipment',
                    'Compressors & Air Tools',
                    'Welding Equipment',
                    'Measuring & Testing Tools',
                    'Garden Tools',
                    'Other Tools',
                ],
            ],
            [
                'name' => 'Automotive Parts & Accessories',
                'slug' => 'automotive-parts-accessories',
                'icon' => 'img/category/car.png',
                'children' => [
                    'Car Parts',
                    'Motorcycle Parts',
                    '4WD Parts & Accessories',
                    'Wheels & Tyres',
                    'Batteries',
                    'Car Audio & Electronics',
                    'Lights & Electrical',
                    'Roof Racks & Tow Bars',
                    'Interior Parts',
                    'Exterior & Body Parts',
                    'Engine & Mechanical Parts',
                    'Workshop & Car Care',
                    'Other Automotive',
                ],
            ],
            [
                'name' => 'Garden & Outdoor',
                'slug' => 'garden-outdoor',
                'icon' => 'img/category/home_garden.png',
                'children' => [
                    'Plants & Pots',
                    'Garden Furniture',
                    'BBQs & Outdoor Cooking',
                    'Lawn Mowers',
                    'Outdoor Power Equipment',
                    'Landscaping Supplies',
                    'Sheds & Outdoor Storage',
                    'Pools & Spa Equipment',
                    'Outdoor Lighting',
                    'Other Garden & Outdoor',
                ],
            ],
            [
                'name' => 'Sports & Fitness',
                'slug' => 'sports',
                'icon' => 'img/category/sports.png',
                'children' => [
                    'Gym & Fitness Equipment',
                    'Bikes & Cycling',
                    'Golf',
                    'Fishing',
                    'Water Sports',
                    'Team Sports',
                    'Racquet Sports',
                    'Camping & Hiking',
                    'Other Sports & Fitness',
                ],
            ],
            [
                'name' => 'Fashion',
                'slug' => 'fashion',
                'icon' => 'img/category/phone.png',
                'children' => [
                    "Men's Clothing",
                    "Women's Clothing",
                    "Kids' Clothing",
                    'Shoes',
                    'Bags & Handbags',
                    'Jewellery',
                    'Watches',
                    'Fashion Accessories',
                    'Workwear',
                    'Other Fashion',
                ],
            ],
            [
                'name' => 'Toys, Kids & Baby',
                'slug' => 'toys-kids-baby',
                'icon' => 'img/category/home_garden.png',
                'children' => [
                    'Toys',
                    'Baby Equipment',
                    'Prams & Strollers',
                    'Cots & Nursery Furniture',
                    'Kids Furniture',
                    'Kids Bikes & Scooters',
                    'Educational Toys',
                    'School Items',
                    'Other Kids & Baby',
                ],
            ],
            [
                'name' => 'Games & Gaming',
                'slug' => 'games-gaming',
                'icon' => 'img/category/electronics.png',
                'children' => [
                    'Gaming Consoles',
                    'Video Games',
                    'Gaming Accessories',
                    'Controllers',
                    'Retro Gaming',
                    'Board Games',
                    'Card Games',
                    'Other Games',
                ],
            ],
            [
                'name' => 'Collectables',
                'slug' => 'collectables',
                'icon' => 'img/category/home_garden.png',
                'children' => [
                    'Coins & Banknotes',
                    'Stamps',
                    'Trading Cards',
                    'Sports Memorabilia',
                    'Comics',
                    'Figurines',
                    'Vintage Items',
                    'Military Collectables',
                    'Pop Culture',
                    'Other Collectables',
                ],
            ],
            [
                'name' => 'Antiques',
                'slug' => 'antiques',
                'icon' => 'img/category/home_garden.png',
                'children' => [
                    'Antique Furniture',
                    'Ceramics & Glassware',
                    'Clocks',
                    'Art',
                    'Silverware',
                    'Vintage Jewellery',
                    'Decorative Items',
                    'Other Antiques',
                ],
            ],
            [
                'name' => 'Books & Media',
                'slug' => 'books-media',
                'icon' => 'img/category/education.png',
                'children' => [
                    'Books',
                    'Textbooks',
                    "Children's Books",
                    'Comics & Graphic Novels',
                    'Vinyl Records',
                    'CDs',
                    'DVDs & Blu-rays',
                    'Magazines',
                    'Other Books & Media',
                ],
            ],
            [
                'name' => 'Office & Business Equipment',
                'slug' => 'office-business-equipment',
                'icon' => 'img/category/laptop.png',
                'children' => [
                    'Desks',
                    'Office Chairs',
                    'Shelving',
                    'Filing Cabinets',
                    'Storage Cabinets',
                    'Printers & Scanners',
                    'Monitors',
                    'Office Electronics',
                    'Whiteboards',
                    'Reception Furniture',
                    'Warehouse & Storage Equipment',
                    'Shop & Retail Equipment',
                    'Other Office & Business Equipment',
                ],
            ],
            [
                'name' => 'Hobbies & Crafts',
                'slug' => 'hobbies-crafts',
                'icon' => 'img/category/home_tools.png',
                'children' => [
                    'Model Kits',
                    'RC Cars, Boats & Aircraft',
                    'Art Supplies',
                    'Sewing & Fabric',
                    'Craft Supplies',
                    'Woodworking',
                    '3D Printers & Supplies',
                    'Musical Instruments',
                    'Other Hobbies & Crafts',
                ],
            ],
            [
                'name' => 'Pet Supplies',
                'slug' => 'pet-supplies',
                'icon' => 'img/category/pet.png',
                'children' => [
                    'Beds & Bedding',
                    'Crates, Cages & Carriers',
                    'Aquariums & Fish Tanks',
                    'Bowls & Feeders',
                    'Leads, Collars & Harnesses',
                    'Pet Toys',
                    'Grooming Equipment',
                    'Pet Furniture & Scratchers',
                    'Enclosures & Kennels',
                    'Other Pet Supplies',
                ],
            ],
            [
                'name' => 'Free Stuff',
                'slug' => 'free-stuff',
                'icon' => 'img/category/home_garden.png',
                'children' => [
                    'Free Furniture',
                    'Free Building Materials',
                    'Free Garden Items',
                    'Free Appliances',
                    'Free Electronics',
                    'Moving Boxes',
                    'Scrap & Reusable Materials',
                    'Other Free Stuff',
                ],
            ],
            [
                'name' => 'Other / Miscellaneous',
                'slug' => 'other-miscellaneous',
                'icon' => 'img/category/home_tools.png',
                'children' => [
                    'Other Items',
                ],
            ],
        ];

        /*
         * These are the original OpenClassify demo categories that Sell My Junk
         * no longer uses. We deactivate them instead of deleting them so any
         * existing records remain intact until demo listings are rebuilt.
         */
        $legacySlugs = [
            'electronics-phones',
            'electronics-computers',
            'electronics-tablets',
            'electronics-tvs',

            'vehicles',
            'vehicles-cars',
            'vehicles-motorcycles',
            'vehicles-trucks',
            'vehicles-boats',

            'real-estate',
            'real-estate-for-sale',
            'real-estate-for-rent',
            'real-estate-commercial',

            'fashion-men',
            'fashion-women',
            'fashion-kids',
            'fashion-shoes',

            'home-garden',
            'home-garden-furniture',
            'home-garden-garden',
            'home-garden-appliances',

            'sports-outdoor',
            'sports-fitness',
            'sports-team-sports',

            'jobs',
            'jobs-full-time',
            'jobs-part-time',
            'jobs-freelance',

            'services',
            'services-cleaning',
            'services-repair',
            'services-education',
        ];

        DB::transaction(function () use ($categories, $legacySlugs): void {
            Category::query()
                ->whereIn('slug', $legacySlugs)
                ->update(['is_active' => false]);

            foreach ($categories as $index => $data) {
                $parent = Category::updateOrCreate(
                    ['slug' => $data['slug']],
                    [
                        'name' => $data['name'],
                        'slug' => $data['slug'],
                        'icon' => $data['icon'],
                        'parent_id' => null,
                        'level' => 0,
                        'sort_order' => $index,
                        'is_active' => true,
                    ]
                );

                foreach ($data['children'] as $childIndex => $childName) {
                    $childSlug = $data['slug'].'-'.Str::slug($childName);

                    Category::updateOrCreate(
                        ['slug' => $childSlug],
                        [
                            'name' => $childName,
                            'slug' => $childSlug,
                            'icon' => null,
                            'parent_id' => $parent->id,
                            'level' => 1,
                            'sort_order' => $childIndex,
                            'is_active' => true,
                        ]
                    );
                }
            }
        });
    }
}
