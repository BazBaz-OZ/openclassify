@php
    $garageSuburb = old(
        'city',
        $garageSuburb ?? ''
    );

    $garageCountry = old(
        'country',
        $garageCountry ?? 'Australia'
    );

    $garageCityId = old(
        'location_city_id',
        $selectedLocationCityId ?? ''
    );
@endphp

<div class="field">
    <label
        class="field__label"
        for="virtual-garage-city"
    >
        City
    </label>

    <select
        id="virtual-garage-city"
        name="location_city_id"
        class="select"
        autocomplete="off"
    >
        <option value="">
            Select city
        </option>

        @foreach($locationCities as $city)
            <option
                value="{{ $city['id'] }}"
                @selected(
                    (string) $garageCityId
                        === (string) $city['id']
                )
            >
                {{ $city['name'] }}
            </option>
        @endforeach
    </select>
</div>

<div class="field">
    <label
        class="field__label"
        for="virtual-garage-suburb"
    >
        Suburb / Area
    </label>

    <select
        id="virtual-garage-suburb"
        name="city"
        class="select"
        autocomplete="off"
        data-current-suburb="{{ $garageSuburb }}"
    >
        <option value="">
            Select suburb / area
        </option>
    </select>

    <p class="text-muted">
        Defaults to your usual selling location.
        Change it if these items are somewhere else.
    </p>

    @error('city')
        <p class="field__error">
            {{ $message }}
        </p>
    @enderror
</div>

<div class="field">
    <label
        class="field__label"
        for="virtual-garage-country-display"
    >
        Country
    </label>

    <input
        id="virtual-garage-country-display"
        type="text"
        class="input"
        value="{{ $garageCountry ?: 'Australia' }}"
        readonly
        autocomplete="off"
    >

    <input
        type="hidden"
        name="country"
        value="{{ $garageCountry ?: 'Australia' }}"
    >
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const citySelect =
        document.getElementById('virtual-garage-city');

    const suburbSelect =
        document.getElementById('virtual-garage-suburb');

    if (!citySelect || !suburbSelect) {
        return;
    }

    const districts = @json($locationDistricts);

    const originalSuburb =
        suburbSelect.dataset.currentSuburb || '';

    function populateSuburbs(
        preferredSuburb = ''
    ) {
        const cityId =
            String(citySelect.value || '');

        suburbSelect.innerHTML = '';

        const placeholder =
            document.createElement('option');

        placeholder.value = '';
        placeholder.textContent =
            'Select suburb / area';

        suburbSelect.appendChild(placeholder);

        if (!cityId) {
            suburbSelect.disabled = true;
            return;
        }

        const matching = districts
            .filter(function (district) {
                return String(district.city_id)
                    === cityId;
            })
            .sort(function (a, b) {
                return String(a.name).localeCompare(
                    String(b.name),
                    undefined,
                    {
                        sensitivity: 'base'
                    }
                );
            });

        matching.forEach(function (district) {
            const option =
                document.createElement('option');

            option.value = district.name;
            option.textContent = district.name;

            if (
                preferredSuburb &&
                String(district.name)
                    .toLowerCase()
                === String(preferredSuburb)
                    .toLowerCase()
            ) {
                option.selected = true;
            }

            suburbSelect.appendChild(option);
        });

        suburbSelect.disabled = false;
    }

    populateSuburbs(originalSuburb);

    citySelect.addEventListener(
        'change',
        function () {
            populateSuburbs('');
        }
    );
});
</script>
