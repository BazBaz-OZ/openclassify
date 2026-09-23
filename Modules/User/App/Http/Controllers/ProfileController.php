<?php

declare(strict_types=1);

namespace Modules\User\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Modules\Location\Models\City;
use Modules\Location\Models\Country;
use Modules\Location\Models\District;

class ProfileController extends Controller
{
    public function edit(): RedirectResponse
    {
        return redirect()->route('panel.profile.edit');
    }

    public function update(Request $request): RedirectResponse
    {
        $australia = collect(Country::quickCreateOptions())->first(
            fn (array $country): bool =>
                mb_strtolower(trim((string) $country['name']))
                    === 'australia'
        );

        $australiaId = is_array($australia)
            ? (int) $australia['id']
            : null;

        $allowedCityIds = collect(City::quickCreateOptions())
            ->filter(
                fn (array $city): bool =>
                    $australiaId !== null
                    && (int) $city['country_id'] === $australiaId
            )
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        $allowedDistricts = collect(District::quickCreateOptions())
            ->filter(
                fn (array $district): bool =>
                    in_array(
                        (int) $district['city_id'],
                        $allowedCityIds,
                        true
                    )
            )
            ->values();

        $allowedDistrictIds = $allowedDistricts
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $validated = $request->validateWithBag('updateProfile', [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique('users')->ignore($request->user()->id),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'location_city_id' => [
                'nullable',
                'integer',
                'required_with:location_district_id',
                Rule::in($allowedCityIds),
            ],
            'location_district_id' => [
                'nullable',
                'integer',
                'required_with:location_city_id',
                Rule::in($allowedDistrictIds),
                function (
                    string $attribute,
                    mixed $value,
                    \Closure $fail
                ) use ($request, $allowedDistricts): void {
                    if ($value === null || $value === '') {
                        return;
                    }

                    $district = $allowedDistricts->firstWhere(
                        'id',
                        (int) $value
                    );

                    if (
                        ! is_array($district)
                        || (int) $district['city_id']
                            !== (int) $request->input('location_city_id')
                    ) {
                        $fail(
                            'Please choose a suburb / area that belongs to the selected city.'
                        );
                    }
                },
            ],
            'show_phone' => ['nullable', 'boolean'],
            'show_email' => ['nullable', 'boolean'],
        ]);

        $phone = trim((string) ($validated['phone'] ?? ''));

        $locationDistrictId = isset(
            $validated['location_district_id']
        )
            ? (int) $validated['location_district_id']
            : null;

        $selectedDistrict = $locationDistrictId
            ? $allowedDistricts->firstWhere(
                'id',
                $locationDistrictId
            )
            : null;

        $defaultSuburb = is_array($selectedDistrict)
            ? trim((string) $selectedDistrict['name'])
            : null;

        $showPhone = $request->boolean('show_phone');
        $showEmail = $request->boolean('show_email');

        unset(
            $validated['phone'],
            $validated['location_city_id'],
            $validated['location_district_id'],
            $validated['show_phone'],
            $validated['show_email']
        );

        $request->user()->fill($validated);

        $emailChanged = $request->user()->isDirty('email');

        if ($emailChanged) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        if ($emailChanged) {
            $request->user()->sendEmailVerificationNotification();
        }

        $request->user()->profile()->updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                'phone' => $phone !== '' ? $phone : null,
                'city' => $defaultSuburb ?: null,
                'country' => $defaultSuburb ? 'Australia' : null,
                'show_phone' => $showPhone,
                'show_email' => $showEmail,
            ],
        );

        return redirect()
            ->route('panel.profile.edit')
            ->with(
                'status',
                $emailChanged
                    ? 'profile-updated-email-verification'
                    : 'profile-updated'
            );
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();
        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
