<?php

declare(strict_types=1);

namespace Modules\Location\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Location\Models\City;
use Modules\Location\Models\Country;
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
            'Adelaide',
            'Belconnen',
            'Brisbane',
            'Canberra',
            'Darwin',
            'Geelong',
            'Gold Coast',
            'Gungahlin',
            'Hobart',
            'Ipswich',
            'Melbourne',
            'Newcastle',
            'Perth',
            'Springfield Lakes',
            'Sydney',
            'Woden',
            'Wollongong',
        ];
    }
}
