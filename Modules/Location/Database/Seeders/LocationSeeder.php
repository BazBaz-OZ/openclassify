<?php

declare(strict_types=1);

namespace Modules\Location\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Location\Models\City;
use Modules\Location\Models\Country;
use Modules\Location\Models\District;
use Tapp\FilamentCountryCodeField\Enums\CountriesEnum;

class LocationSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->countries() as $country) {
            Country::updateOrCreate(
                ['code' => $country['code']],
                [
                    'name' => $country['name'],
                    'phone_code' => $country['phone_code'],
                    'is_active' => $country['code'] === 'AU',
                ]
            );
        }

        $australia = Country::query()->where('code', 'AU')->first();

        if (! $australia) {
            return;
        }

        $australianCities = $this->australianCities();

        foreach ($australianCities as $city) {
            City::updateOrCreate(
                ['country_id' => (int) $australia->id, 'name' => $city],
                ['is_active' => true]
            );
        }

        City::query()
            ->where('country_id', (int) $australia->id)
            ->whereNotIn('name', $australianCities)
            ->delete();

        foreach ($this->australianDistricts() as $cityName => $districtNames) {
            $city = City::query()
                ->where('country_id', (int) $australia->id)
                ->where('name', $cityName)
                ->first();

            if (! $city) {
                continue;
            }

            foreach ($districtNames as $districtName) {
                District::updateOrCreate(
                    [
                        'city_id' => (int) $city->id,
                        'name' => $districtName,
                    ],
                    ['is_active' => true]
                );
            }

            District::query()
                ->where('city_id', (int) $city->id)
                ->whereNotIn('name', $districtNames)
                ->delete();
        }
    }

    private function countries(): array
    {
        $countries = [];

        foreach (CountriesEnum::cases() as $countryEnum) {
            $value = $countryEnum->value;
            $phoneCode = $this->normalizePhoneCode($countryEnum->getCountryCode());

            if ($value === 'us_ca') {
                $countries['US'] = [
                    'code' => 'US',
                    'name' => 'United States',
                    'phone_code' => $phoneCode,
                ];
                $countries['CA'] = [
                    'code' => 'CA',
                    'name' => 'Canada',
                    'phone_code' => $phoneCode,
                ];

                continue;
            }

            if ($value === 'ru_kz') {
                $countries['RU'] = [
                    'code' => 'RU',
                    'name' => 'Russia',
                    'phone_code' => $phoneCode,
                ];
                $countries['KZ'] = [
                    'code' => 'KZ',
                    'name' => 'Kazakhstan',
                    'phone_code' => $phoneCode,
                ];

                continue;
            }

            $key = 'filament-country-code-field::countries.'.$value;
            $labelEn = trim((string) trans($key, [], 'en'));

            $name = $labelEn !== '' && $labelEn !== $key ? $labelEn : strtoupper($value);

            $iso2 = strtoupper(explode('_', $value)[0] ?? $value);

            $countries[$iso2] = [
                'code' => $iso2,
                'name' => $name,
                'phone_code' => $phoneCode,
            ];
        }

        return collect($countries)
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    private function normalizePhoneCode(string $phoneCode): string
    {
        $normalized = trim(explode(',', $phoneCode)[0]);
        $normalized = str_replace(' ', '', $normalized);

        return substr($normalized, 0, 10);
    }

    private function australianCities(): array
    {
        return [
            'Brisbane',
            'Canberra',
            'Gold Coast',
            'Ipswich',
            'Toowoomba',
        ];
    }

    private function australianDistricts(): array
    {
        return [
            'Canberra' => [
                'Belconnen',
                'East Canberra',
                'Gungahlin',
                'Inner North and City',
                'Inner South',
                'Molonglo Valley',
                'Tuggeranong',
                'Weston Creek',
                'Woden',
            ],

            'Ipswich' => [
                'Amberley',
                'Ashwell',
                'Augustine Heights',
                'Barellan Point',
                'Basin Pocket',
                'Bellbird Park',
                'Blacksoil',
                'Blackstone',
                'Booval',
                'Brassall',
                'Brookwater',
                'Bundamba',
                'Calvert',
                'Camira',
                'Carole Park',
                'Churchill',
                'Chuwar',
                'Coalfalls',
                'Collingwood Park',
                'Deebing Heights',
                'Dinmore',
                'East Ipswich',
                'Eastern Heights',
                'Ebbw Vale',
                'Ebenezer',
                'Flinders View',
                'Gailes',
                'Goodna',
                'Goolman',
                'Grandchester',
                'Haigslea',
                'Ipswich',
                'Ironbark',
                'Jeebropilly',
                'Karalee',
                'Karrabin',
                'Lanefield',
                'Leichhardt',
                'Lower Mount Walker',
                'Marburg',
                'Moores Pocket',
                'Mount Forbes',
                'Mount Marrow',
                'Mount Mort',
                'Mount Walker West',
                'Muirlea',
                'Mutdapilly',
                'New Chum',
                'Newtown',
                'North Booval',
                'North Ipswich',
                'North Tivoli',
                'One Mile',
                'Peak Crossing',
                'Pine Mountain',
                'Purga',
                'Raceview',
                'Redbank',
                'Redbank Plains',
                'Ripley',
                'Riverview',
                'Rosewood',
                'Sadliers Crossing',
                'Silkstone',
                'South Ripley',
                'Spring Mountain',
                'Springfield',
                'Springfield Central',
                'Springfield Lakes',
                'Swanbank',
                'Tallegalla',
                'Thagoona',
                'The Bluff',
                'Tivoli',
                'Walloon',
                'West Ipswich',
                'White Rock',
                'Willowbank',
                'Woodend',
                'Woolshed',
                'Wulkuraka',
                'Yamanto',
            ],

            'Brisbane' => [
                'Alderley',
                'Annerley',
                'Ascot',
                'Ashgrove',
                'Aspley',
                'Bald Hills',
                'Balmoral',
                'Bardon',
                'Bellbowrie',
                'Boondall',
                'Bowen Hills',
                'Bracken Ridge',
                'Bridgeman Downs',
                'Brighton',
                'Brisbane City',
                'Bulimba',
                'Calamvale',
                'Camp Hill',
                'Cannon Hill',
                'Carina',
                'Carina Heights',
                'Carindale',
                'Carseldine',
                'Chapel Hill',
                'Chermside',
                'Chermside West',
                'Clayfield',
                'Coorparoo',
                'Corinda',
                'Deagon',
                'Dutton Park',
                'Eight Mile Plains',
                'Enoggera',
                'Everton Park',
                'Fairfield',
                'Ferny Grove',
                'Fig Tree Pocket',
                'Fortitude Valley',
                'Geebung',
                'Graceville',
                'Grange',
                'Hamilton',
                'Hawthorne',
                'Hendra',
                'Highgate Hill',
                'Indooroopilly',
                'Jindalee',
                'Kangaroo Point',
                'Kedron',
                'Kenmore',
                'Kuraby',
                'Lutwyche',
                'Manly',
                'Mansfield',
                'Milton',
                'Mitchelton',
                'Moorooka',
                'Morningside',
                'Mount Gravatt',
                'Mount Gravatt East',
                'New Farm',
                'Newmarket',
                'Newstead',
                'Northgate',
                'Nundah',
                'Oxley',
                'Paddington',
                'Rochedale',
                'Runcorn',
                'Sandgate',
                'Sherwood',
                'South Brisbane',
                'Spring Hill',
                'St Lucia',
                'Stafford',
                'Stafford Heights',
                'Sunnybank',
                'Sunnybank Hills',
                'Taringa',
                'Teneriffe',
                'The Gap',
                'Toowong',
                'Upper Mount Gravatt',
                'West End',
                'Wilston',
                'Windsor',
                'Woolloongabba',
                'Wynnum',
                'Wynnum West',
                'Yeronga',
                'Zillmere',
            ],

            'Gold Coast' => [
                'Broadbeach',
                'Burleigh Heads',
                'Coomera',
                'Helensvale',
                'Labrador',
                'Mermaid Beach',
                'Nerang',
                'Palm Beach',
                'Robina',
                'Southport',
                'Surfers Paradise',
                'Varsity Lakes',
            ],

            'Toowoomba' => [
                'Centenary Heights',
                'Darling Heights',
                'East Toowoomba',
                'Glenvale',
                'Harristown',
                'Highfields',
                'Kearneys Spring',
                'Middle Ridge',
                'Mount Lofty',
                'Newtown',
                'North Toowoomba',
                'Rangeville',
                'South Toowoomba',
                'Toowoomba City',
                'Wilsonton',
            ],
        ];
    }

}
