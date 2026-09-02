<div class="field">
    <label class="field__label" for="title">
        What are you looking for?
    </label>

    <input
        id="title"
        name="title"
        type="text"
        class="input"
        maxlength="150"
        required
        value="{{ old('title', $wanted->title ?? '') }}"
        placeholder="e.g. Cisco 24-port Gigabit switch"
    >

    @error('title')
        <p class="field__error">{{ $message }}</p>
    @enderror
</div>

<div class="field">
    <label class="field__label" for="category_id">
        Category
    </label>

    <select
        id="category_id"
        name="category_id"
        class="select"
    >
        <option value="">Any category</option>

        @foreach($categories as $id => $name)
            <option
                value="{{ $id }}"
                @selected(
                    (string) old(
                        'category_id',
                        $wanted->category_id ?? ''
                    ) === (string) $id
                )
            >
                {{ $name }}
            </option>
        @endforeach
    </select>
</div>

<div class="field">
    <label class="field__label" for="description">
        Details
    </label>

    <textarea
        id="description"
        name="description"
        class="textarea"
        rows="6"
        maxlength="4000"
        placeholder="Brand, model, condition, size, colour or anything else that matters..."
    >{{ old('description', $wanted->description ?? '') }}</textarea>
</div>

<div class="field">
    <label class="field__label" for="max_budget">
        Maximum budget
    </label>

    <span class="input-affix">
        <input
            id="max_budget"
            name="max_budget"
            type="number"
            min="1"
            step="0.01"
            class="input"
            value="{{ old('max_budget', $wanted->max_budget ?? '') }}"
            placeholder="Optional"
        >

        <span class="input-affix__suffix">
            AUD
        </span>
    </span>

    <p class="field__hint">
        Leave blank if you're open to offers.
    </p>
</div>

<div class="field__row field__row--two">

    <div class="field">
        <label class="field__label" for="city">
            City / Suburb
        </label>

        <input
            id="city"
            name="city"
            type="text"
            class="input"
            value="{{ old('city', $wanted->city ?? '') }}"
            placeholder="Springfield Lakes"
        >
    </div>

    <div class="field">
        <label class="field__label" for="country">
            Country
        </label>

        <input
            id="country"
            name="country"
            type="text"
            class="input"
            value="{{ old('country', $wanted->country ?? 'Australia') }}"
        >
    </div>
</div>
