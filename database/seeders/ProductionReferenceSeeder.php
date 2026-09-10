<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Category\Database\Seeders\CategorySeeder;
use Modules\Listing\Database\Seeders\ListingCustomFieldSeeder;
use Modules\Location\Database\Seeders\LocationSeeder;

class ProductionReferenceSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            LocationSeeder::class,
            CategorySeeder::class,
            ListingCustomFieldSeeder::class,
        ]);
    }
}
