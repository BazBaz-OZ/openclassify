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
                'Acacia Ridge',
                'Albion',
                'Alderley',
                'Algester',
                'Annerley',
                'Anstead',
                'Archerfield',
                'Ascot',
                'Ashgrove',
                'Aspley',
                'Auchenflower',
                'Bald Hills',
                'Balmoral',
                'Banks Creek',
                'Banyo',
                'Bardon',
                'Bellbowrie',
                'Belmont',
                'Boondall',
                'Bowen Hills',
                'Bracken Ridge',
                'Bridgeman Downs',
                'Brighton',
                'Brisbane Airport',
                'Brisbane City',
                'Brookfield',
                'Bulimba',
                'Bulwer',
                'Burbank',
                'Calamvale',
                'Camp Hill',
                'Cannon Hill',
                'Carina',
                'Carina Heights',
                'Carindale',
                'Carseldine',
                'Chandler',
                'Chapel Hill',
                'Chelmer',
                'Chermside',
                'Chermside West',
                'Chuwar',
                'Clayfield',
                'Coopers Plains',
                'Coorparoo',
                'Corinda',
                'Cowan Cowan',
                'Darra',
                'Deagon',
                'Doolandella',
                'Drewvale',
                'Durack',
                'Dutton Park',
                'Eagle Farm',
                'East Brisbane',
                'Eight Mile Plains',
                'Ellen Grove',
                'England Creek',
                'Enoggera',
                'Enoggera Reservoir',
                'Everton Park',
                'Fairfield',
                'Ferny Grove',
                'Fig Tree Pocket',
                'Fitzgibbon',
                'Forest Lake',
                'Fortitude Valley',
                'Gaythorne',
                'Geebung',
                'Gordon Park',
                'Graceville',
                'Grange',
                'Greenslopes',
                'Gumdale',
                'Hamilton',
                'Hawthorne',
                'Heathwood',
                'Hemmant',
                'Hendra',
                'Herston',
                'Highgate Hill',
                'Holland Park',
                'Holland Park West',
                'Inala',
                'Indooroopilly',
                'Jamboree Heights',
                'Jindalee',
                'Kalinga',
                'Kangaroo Point',
                'Karana Downs',
                'Karawatha',
                'Kedron',
                'Kelvin Grove',
                'Kenmore',
                'Kenmore Hills',
                'Keperra',
                'Kholo',
                'Kooringal',
                'Kuraby',
                'Lake Manchester',
                'Larapinta',
                'Lota',
                'Lutwyche',
                'Lytton',
                'Macgregor',
                'Mackenzie',
                'Manly',
                'Manly West',
                'Mansfield',
                'McDowall',
                'Middle Park',
                'Milton',
                'Mitchelton',
                'Moggill',
                'Moorooka',
                'Moreton Island',
                'Morningside',
                'Mount Coot-tha',
                'Mount Crosby',
                'Mount Gravatt',
                'Mount Gravatt East',
                'Mount Ommaney',
                'Murarrie',
                'Nathan',
                'New Farm',
                'Newmarket',
                'Newstead',
                'Norman Park',
                'Northgate',
                'Nudgee',
                'Nudgee Beach',
                'Nundah',
                'Oxley',
                'Paddington',
                'Pallara',
                'Parkinson',
                'Petrie Terrace',
                'Pinjarra Hills',
                'Pinkenba',
                'Port of Brisbane',
                'Pullenvale',
                'Ransome',
                'Red Hill',
                'Richlands',
                'Riverhills',
                'Robertson',
                'Rochedale',
                'Rocklea',
                'Runcorn',
                'Salisbury',
                'Sandgate',
                'Seven Hills',
                'Seventeen Mile Rocks',
                'Sherwood',
                'Shorncliffe',
                'Sinnamon Park',
                'South Brisbane',
                'Spring Hill',
                'St Lucia',
                'Stafford',
                'Stafford Heights',
                'Stones Corner',
                'Stretton',
                'Sumner',
                'Sunnybank',
                'Sunnybank Hills',
                'Taigum',
                'Taringa',
                'Tarragindi',
                'Teneriffe',
                'Tennyson',
                'The Gap',
                'Tingalpa',
                'Toowong',
                'Upper Brookfield',
                'Upper Kedron',
                'Upper Mount Gravatt',
                'Virginia',
                'Wacol',
                'Wakerley',
                'Wavell Heights',
                'West End',
                'Westlake',
                'Willawong',
                'Wilston',
                'Windsor',
                'Wishart',
                'Woolloongabba',
                'Wooloowin',
                'Wynnum',
                'Wynnum West',
                'Yeerongpilly',
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
