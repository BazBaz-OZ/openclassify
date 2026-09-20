<?php

declare(strict_types=1);

namespace Modules\Panel\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Category\Models\Category;
use Modules\Listing\Models\Listing;

class UpdateListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $method = (string) $this->input('fulfilment_method');

        $this->merge([
            'country' => 'Australia',
            'delivery_scope' => in_array(
                $method,
                ['delivery', 'both'],
                true
            ) ? 'australia_wide' : null,
            'city' => $method === 'delivery'
                ? null
                : $this->input('city'),
        ]);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category_id' => [
                'required',
                'integer',
                Rule::in(
                    collect(Category::panelQuickCatalog())
                        ->reject(
                            fn (array $category): bool =>
                                (bool) ($category['has_children'] ?? false)
                        )
                        ->pluck('id')
                        ->all()
                ),
            ],
            'price' => ['nullable', 'numeric', 'min:0'],
            'quantity_total' => ['required', 'integer', 'min:1', 'max:1000000'],
            'fulfilment_method' => [
                'required',
                Rule::in(['pickup', 'delivery', 'both']),
            ],
            'delivery_scope' => [
                'nullable',
                Rule::in(['australia_wide']),
            ],
            'width' => ['nullable', 'numeric', 'min:0.01', 'max:999999.99'],
            'height' => ['nullable', 'numeric', 'min:0.01', 'max:999999.99'],
            'depth' => ['nullable', 'numeric', 'min:0.01', 'max:999999.99'],
            'dimension_unit' => ['nullable', Rule::in(['mm', 'cm', 'm'])],
            'weight' => ['nullable', 'numeric', 'decimal:0,1', 'min:0.1', 'max:999999.9'],
            'weight_unit' => ['nullable', Rule::in(['g', 'kg'])],
            'status' => ['required', Rule::in(array_keys(Listing::panelStatusOptions()))],
            'contact_phone' => ['nullable', 'string', 'max:60'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'country' => ['required', 'string', 'max:255'],
            'city' => [
                Rule::requiredIf(
                    fn (): bool => in_array(
                        (string) $this->input('fulfilment_method'),
                        ['pickup', 'both'],
                        true
                    )
                ),
                'nullable',
                'string',
                'max:255',
            ],
            'expires_at' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Listing title is required.',
            'category_id.required' => 'Please choose a category.',
            'category_id.in' => 'Please choose a valid category.',
            'price.numeric' => 'Listing price must be numeric.',
            'quantity_total.required' => 'Quantity is required.',
            'quantity_total.integer' => 'Quantity must be a whole number.',
            'quantity_total.min' => 'Quantity must be at least 1.',
            'fulfilment_method.required' => 'Please choose how the buyer will receive the item.',
            'fulfilment_method.in' => 'Please choose a valid pickup or delivery option.',
            'city.required' => 'Please enter the suburb / area for pickup.',
            'status.required' => 'Listing status is required.',
            'status.in' => 'Listing status is invalid.',
            'contact_email.email' => 'Contact email must be valid.',
            'expires_at.date' => 'Expiry date must be a valid date.',
        ];
    }
}
