<?php

declare(strict_types=1);

namespace Modules\Listing\Support;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Modules\Category\Models\Category;
use Throwable;

use function Laravel\Ai\agent;

class VirtualGaragePhotoAnalyzer
{
    public function analyze(UploadedFile $image): array
    {
        $provider = (string) config(
            'quick-listing.ai_provider',
            'openai'
        );

        $model = config('quick-listing.ai_model');

        $providerKey = config(
            "ai.providers.{$provider}.key"
        );

        if (blank($providerKey)) {
            return [
                'items' => [],
                'error' => 'AI provider key is missing.',
            ];
        }

        $categories = Category::activeAiCatalog();

        if ($categories->isEmpty()) {
            return [
                'items' => [],
                'error' => 'No active categories available.',
            ];
        }

        /*
         * Keep parent categories available while building
         * breadcrumb paths, but never allow AI to classify
         * directly into a category that has active children.
         *
         * Example:
         * Sports & Fitness > Water Sports
         *
         * The AI may choose Water Sports, but not the
         * Sports & Fitness parent itself.
         */
        $parentIds = $categories
            ->pluck('parent_id')
            ->filter(
                fn ($id): bool =>
                    $id !== null
            )
            ->map(
                fn ($id): int =>
                    (int) $id
            )
            ->unique()
            ->values();

        $catalog = $this
            ->buildCatalog($categories)
            ->reject(
                fn (array $category): bool =>
                    $parentIds->contains(
                        (int) $category['id']
                    )
            )
            ->values();

        $categoryIds = $catalog
            ->pluck('id')
            ->values()
            ->all();

        $catalogText = $catalog
            ->map(
                fn (array $category): string =>
                    "{$category['id']}: {$category['path']}"
            )
            ->implode("\n");

        try {
            $response = agent(
                instructions: <<<'INSTRUCTIONS'
You are an Australian second-hand marketplace assistant.

Analyse a garage-sale photo and identify each distinct sellable item
that can reasonably be seen.

A single photo may contain many separate products.

For every detected item:
- give it a short marketplace title
- choose the best category ID from the supplied catalog
- suggest a realistic second-hand price in Australian dollars
- give a short useful description
- estimate condition only when visible
- provide a confidence score from 0 to 1
- provide a tight bounding box around the visible item

Bounding box rules:
- coordinates are normalised from 0 to 1
- x is the left edge of the item
- y is the top edge of the item
- width is the item's width
- height is the item's height
- keep the box reasonably tight around the sellable object
- include the complete object where practical
- do not return pixel coordinates

Do not combine clearly separate objects into one item.
Do not invent objects that are not visible.
Ignore walls, floors, shelving and general background clutter unless
they are obviously items being sold.
Use only category IDs supplied in the catalog.

Antique and vintage classification rules:
- Do not classify an item as Antique, Vintage or Collectable merely because
  it is decorative, ornate, old-fashioned in style, dusty or used.
- Use ordinary marketplace categories such as Home Decor or Figurines unless
  there is clear visible evidence that the item is genuinely antique,
  vintage or specifically collectable.
- Do not infer age, rarity, provenance or collectable status from appearance
  alone when those facts cannot reasonably be established from the image.
INSTRUCTIONS,
                schema: fn (JsonSchema $schema): array => [
                    'items' => $schema->array()
                        ->items(
                            $schema->object([
                                'title' => $schema
                                    ->string()
                                    ->required(),

                                'category_id' => $schema
                                    ->integer()
                                    ->enum($categoryIds)
                                    ->nullable(),

                                'suggested_price' => $schema
                                    ->number()
                                    ->min(0)
                                    ->nullable(),

                                'description' => $schema
                                    ->string()
                                    ->required(),

                                'condition' => $schema
                                    ->string()
                                    ->nullable(),

                                'confidence' => $schema
                                    ->number()
                                    ->min(0)
                                    ->max(1)
                                    ->required(),

                                'bounding_box' => $schema
                                    ->object([
                                        'x' => $schema
                                            ->number()
                                            ->min(0)
                                            ->max(1)
                                            ->required(),

                                        'y' => $schema
                                            ->number()
                                            ->min(0)
                                            ->max(1)
                                            ->required(),

                                        'width' => $schema
                                            ->number()
                                            ->min(0)
                                            ->max(1)
                                            ->required(),

                                        'height' => $schema
                                            ->number()
                                            ->min(0)
                                            ->max(1)
                                            ->required(),
                                    ])
                                    ->required(),
                            ])
                        )
                        ->max(30)
                        ->required(),
                ],
            )->prompt(
                prompt: <<<PROMPT
Analyse this Virtual Garage photo.

Find the separate sellable objects visible in the image.

Category catalog:

{$catalogText}

Pricing rules:
- Prices must be estimates in AUD.
- Assume ordinary used condition unless the photo clearly suggests
  otherwise.
- Be conservative.
- Do not price an item you cannot identify with reasonable confidence.

Return every distinct sellable item you can reasonably identify.
PROMPT,
                attachments: [$image],
                provider: $provider,
                model: is_string($model) && $model !== ''
                    ? $model
                    : null,
            );

            $items = collect($response['items'] ?? [])
                ->filter(
                    fn ($item): bool =>
                        is_array($item)
                        && filled($item['title'] ?? null)
                )
                ->map(function (array $item) use (
                    $categoryIds
                ): array {
                    $categoryId =
                        isset($item['category_id'])
                        && is_numeric($item['category_id'])
                            ? (int) $item['category_id']
                            : null;

                    if (
                        $categoryId !== null
                        && ! in_array(
                            $categoryId,
                            $categoryIds,
                            true
                        )
                    ) {
                        $categoryId = null;
                    }

                    return [
                        'title' => trim(
                            (string) $item['title']
                        ),

                        'category_id' => $categoryId,

                        'suggested_price' =>
                            isset($item['suggested_price'])
                            && is_numeric(
                                $item['suggested_price']
                            )
                                ? round(
                                    (float)
                                    $item['suggested_price'],
                                    2
                                )
                                : null,

                        'description' => trim(
                            (string)
                            ($item['description'] ?? '')
                        ),

                        'condition' => filled(
                            $item['condition'] ?? null
                        )
                            ? trim(
                                (string)
                                $item['condition']
                            )
                            : null,

                        'confidence' =>
                            isset($item['confidence'])
                            && is_numeric(
                                $item['confidence']
                            )
                                ? max(
                                    0,
                                    min(
                                        1,
                                        (float)
                                        $item['confidence']
                                    )
                                )
                                : null,

                        'bounding_box' =>
                            $this->normaliseBoundingBox(
                                $item['bounding_box'] ?? null
                            ),
                    ];
                })
                ->values()
                ->all();

            return [
                'items' => $items,
                'error' => null,
            ];
        } catch (Throwable $exception) {
            report($exception);

            return [
                'items' => [],
                'error' => $exception->getMessage(),
            ];
        }
    }

    public function relocaliseExistingItems(
        UploadedFile $image,
        array $existingItems
    ): array {
        $provider = (string) config(
            'quick-listing.ai_provider',
            'openai'
        );

        $model = config(
            'quick-listing.ai_model'
        );

        $providerKey = config(
            "ai.providers.{$provider}.key"
        );

        if (blank($providerKey)) {
            return [
                'locations' => [],
                'error' =>
                    'AI provider key is missing.',
            ];
        }

        $targets = collect(
            $existingItems
        )
            ->filter(
                fn ($item): bool =>
                    is_array($item)
                    && isset($item['id'])
                    && is_numeric($item['id'])
                    && filled(
                        $item['title']
                        ?? null
                    )
            )
            ->map(
                fn (array $item): array => [
                    'id' =>
                        (int) $item['id'],

                    'title' =>
                        trim(
                            (string)
                            $item['title']
                        ),
                ]
            )
            ->values();

        if ($targets->isEmpty()) {
            return [
                'locations' => [],
                'error' =>
                    'No existing items supplied.',
            ];
        }

        $itemIds =
            $targets
                ->pluck('id')
                ->all();

        $targetText =
            $targets
                ->map(
                    fn (array $item): string =>
                        $item['id']
                        .': '
                        .$item['title']
                )
                ->implode("\n");

        try {
            $response = agent(
                instructions: <<<'INSTRUCTIONS'
You are locating already-known marketplace items inside a photograph.

Do NOT identify new products.
Do NOT invent additional items.
Do NOT change titles, categories, prices, descriptions or conditions.

You will receive a list containing an item ID and its known title.

For every listed item that you can visibly locate:
- return the exact supplied item ID
- return a tight bounding box around that specific physical object
- return a localisation confidence from 0 to 1

Bounding box rules:
- coordinates are normalised from 0 to 1
- x is the left edge
- y is the top edge
- width is the visible object's width
- height is the visible object's height
- include the complete visible object where practical
- do not return pixel coordinates
- do not use one large box for several different items
- when several similar products are stacked together, use labels,
  cover artwork, spine text and physical position to distinguish them

If a listed item cannot be located reliably, omit it rather than guessing.
INSTRUCTIONS,

                schema: fn (
                    JsonSchema $schema
                ): array => [
                    'locations' =>
                        $schema->array()
                            ->items(
                                $schema->object([
                                    'item_id' =>
                                        $schema
                                            ->integer()
                                            ->enum(
                                                $itemIds
                                            )
                                            ->required(),

                                    'confidence' =>
                                        $schema
                                            ->number()
                                            ->min(0)
                                            ->max(1)
                                            ->required(),

                                    'bounding_box' =>
                                        $schema
                                            ->object([
                                                'x' =>
                                                    $schema
                                                        ->number()
                                                        ->min(0)
                                                        ->max(1)
                                                        ->required(),

                                                'y' =>
                                                    $schema
                                                        ->number()
                                                        ->min(0)
                                                        ->max(1)
                                                        ->required(),

                                                'width' =>
                                                    $schema
                                                        ->number()
                                                        ->min(0)
                                                        ->max(1)
                                                        ->required(),

                                                'height' =>
                                                    $schema
                                                        ->number()
                                                        ->min(0)
                                                        ->max(1)
                                                        ->required(),
                                            ])
                                            ->required(),
                                ])
                            )
                            ->max(
                                count($itemIds)
                            )
                            ->required(),
                ],

            )->prompt(
                prompt: <<<PROMPT
Locate these EXISTING Virtual Garage items in the attached photograph.

{$targetText}

Important:
- These records already exist.
- Return only localisation information.
- Do not create or describe other objects.
- Match each returned item_id to the correct physical product.
PROMPT,

                attachments: [$image],

                provider: $provider,

                model:
                    is_string($model)
                    && $model !== ''
                        ? $model
                        : null,
            );

            $seen = [];

            $locations =
                collect(
                    $response[
                        'locations'
                    ] ?? []
                )
                    ->filter(
                        fn ($location): bool =>
                            is_array($location)
                            && isset(
                                $location[
                                    'item_id'
                                ]
                            )
                            && is_numeric(
                                $location[
                                    'item_id'
                                ]
                            )
                    )
                    ->map(function (
                        array $location
                    ) use (
                        $itemIds,
                        &$seen
                    ): ?array {
                        $itemId =
                            (int)
                            $location[
                                'item_id'
                            ];

                        if (
                            ! in_array(
                                $itemId,
                                $itemIds,
                                true
                            )
                            || isset(
                                $seen[$itemId]
                            )
                        ) {
                            return null;
                        }

                        $box =
                            $this
                                ->normaliseBoundingBox(
                                    $location[
                                        'bounding_box'
                                    ] ?? null
                                );

                        if (! $box) {
                            return null;
                        }

                        $seen[$itemId] =
                            true;

                        return [
                            'item_id' =>
                                $itemId,

                            'confidence' =>
                                isset(
                                    $location[
                                        'confidence'
                                    ]
                                )
                                && is_numeric(
                                    $location[
                                        'confidence'
                                    ]
                                )
                                    ? max(
                                        0,
                                        min(
                                            1,
                                            (float)
                                            $location[
                                                'confidence'
                                            ]
                                        )
                                    )
                                    : null,

                            'bounding_box' =>
                                $box,
                        ];
                    })
                    ->filter()
                    ->values()
                    ->all();

            return [
                'locations' =>
                    $locations,

                'error' => null,
            ];
        } catch (Throwable $exception) {
            report($exception);

            return [
                'locations' => [],
                'error' =>
                    $exception
                        ->getMessage(),
            ];
        }
    }

    public function locatePointerPoints(
        UploadedFile $image,
        array $existingItems
    ): array {
        $provider = (string) config(
            'quick-listing.ai_provider',
            'openai'
        );

        $model = config(
            'quick-listing.ai_model'
        );

        $providerKey = config(
            "ai.providers.{$provider}.key"
        );

        if (blank($providerKey)) {
            return [
                'points' => [],
                'error' => 'AI provider key is missing.',
            ];
        }

        $targets = collect($existingItems)
            ->filter(
                fn ($item): bool =>
                    is_array($item)
                    && isset($item['id'])
                    && is_numeric($item['id'])
                    && filled($item['title'] ?? null)
            )
            ->map(
                fn (array $item): array => [
                    'id' => (int) $item['id'],
                    'title' => trim(
                        (string) $item['title']
                    ),
                ]
            )
            ->values();

        if ($targets->isEmpty()) {
            return [
                'points' => [],
                'error' => 'No existing items supplied.',
            ];
        }

        $itemIds = $targets
            ->pluck('id')
            ->all();

        $targetText = $targets
            ->map(
                fn (array $item): string =>
                    $item['id']
                    .': '
                    .$item['title']
            )
            ->implode("\n");

        try {
            $response = agent(
                instructions: <<<'INSTRUCTIONS'
You are locating already-known marketplace items inside a photograph.

Do not identify new products.
Do not alter titles or descriptions.

For each supplied item that you can confidently locate, return ONE precise
pointer point that lies directly on that physical item.

Pointer rules:
- coordinates are normalised from 0 to 1
- x is horizontal position from the left
- y is vertical position from the top
- the point MUST physically lie on the requested object
- never put the point in empty space between objects
- never point at a neighbouring item
- for books, DVDs, games or stacked cases, place the point directly on the
  printed title, logo, or distinctive text of that exact spine
- for ordinary objects, choose a visually distinctive part near its centre
- if the exact item cannot be located reliably, omit it instead of guessing

This point will be used as the tip of an arrow shown to a buyer, so precision
matters more than returning every item.
INSTRUCTIONS,

                schema: fn (
                    JsonSchema $schema
                ): array => [
                    'points' =>
                        $schema->array()
                            ->items(
                                $schema->object([
                                    'item_id' =>
                                        $schema
                                            ->integer()
                                            ->enum($itemIds)
                                            ->required(),

                                    'confidence' =>
                                        $schema
                                            ->number()
                                            ->min(0)
                                            ->max(1)
                                            ->required(),

                                    'point' =>
                                        $schema
                                            ->object([
                                                'x' =>
                                                    $schema
                                                        ->number()
                                                        ->min(0)
                                                        ->max(1)
                                                        ->required(),

                                                'y' =>
                                                    $schema
                                                        ->number()
                                                        ->min(0)
                                                        ->max(1)
                                                        ->required(),
                                            ])
                                            ->required(),
                                ])
                            )
                            ->max(count($itemIds))
                            ->required(),
                ],

            )->prompt(
                prompt: <<<PROMPT
Place one precise pointer point on each of these EXISTING items:

{$targetText}

For stacked Nintendo Switch cases, the point should sit directly on the
printed spine text belonging to the named game, not between cases and not on
another game's spine.
PROMPT,

                attachments: [$image],

                provider: $provider,

                model:
                    is_string($model)
                    && $model !== ''
                        ? $model
                        : null,
            );

            $seen = [];

            $points = collect(
                $response['points'] ?? []
            )
                ->map(function (
                    $result
                ) use (
                    $itemIds,
                    &$seen
                ): ?array {
                    if (
                        ! is_array($result)
                        || ! isset($result['item_id'])
                        || ! is_numeric(
                            $result['item_id']
                        )
                    ) {
                        return null;
                    }

                    $itemId =
                        (int) $result['item_id'];

                    if (
                        ! in_array(
                            $itemId,
                            $itemIds,
                            true
                        )
                        || isset($seen[$itemId])
                    ) {
                        return null;
                    }

                    $point =
                        $this->normalisePointerPoint(
                            $result['point'] ?? null
                        );

                    if (! $point) {
                        return null;
                    }

                    $seen[$itemId] = true;

                    return [
                        'item_id' => $itemId,

                        'confidence' =>
                            isset(
                                $result['confidence']
                            )
                            && is_numeric(
                                $result['confidence']
                            )
                                ? max(
                                    0,
                                    min(
                                        1,
                                        (float)
                                        $result['confidence']
                                    )
                                )
                                : null,

                        'point' => $point,
                    ];
                })
                ->filter()
                ->values()
                ->all();

            return [
                'points' => $points,
                'error' => null,
            ];
        } catch (Throwable $exception) {
            report($exception);

            return [
                'points' => [],
                'error' =>
                    $exception->getMessage(),
            ];
        }
    }

    private function normalisePointerPoint(
        mixed $point
    ): ?array {
        if (! is_array($point)) {
            return null;
        }

        if (
            ! isset($point['x'], $point['y'])
            || ! is_numeric($point['x'])
            || ! is_numeric($point['y'])
        ) {
            return null;
        }

        return [
            'x' => round(
                max(
                    0,
                    min(
                        1,
                        (float) $point['x']
                    )
                ),
                4
            ),

            'y' => round(
                max(
                    0,
                    min(
                        1,
                        (float) $point['y']
                    )
                ),
                4
            ),
        ];
    }

    private function normaliseBoundingBox(
        mixed $boundingBox
    ): ?array {
        if (! is_array($boundingBox)) {
            return null;
        }

        foreach (
            ['x', 'y', 'width', 'height']
            as $key
        ) {
            if (
                ! array_key_exists($key, $boundingBox)
                || ! is_numeric($boundingBox[$key])
            ) {
                return null;
            }
        }

        $x = max(
            0,
            min(1, (float) $boundingBox['x'])
        );

        $y = max(
            0,
            min(1, (float) $boundingBox['y'])
        );

        $width = max(
            0,
            min(1 - $x, (float) $boundingBox['width'])
        );

        $height = max(
            0,
            min(1 - $y, (float) $boundingBox['height'])
        );

        /*
         * Ignore obviously unusable boxes.
         */
        if ($width <= 0 || $height <= 0) {
            return null;
        }

        return [
            'x' => round($x, 4),
            'y' => round($y, 4),
            'width' => round($width, 4),
            'height' => round($height, 4),
        ];
    }

    private function buildCatalog(
        Collection $categories
    ): Collection {
        $byId = $categories->keyBy('id');

        return $categories->map(
            function (Category $category) use (
                $byId
            ): array {
                $path = [$category->name];
                $parentId = $category->parent_id;

                while (
                    $parentId
                    && $byId->has($parentId)
                ) {
                    $parent = $byId->get($parentId);

                    $path[] = $parent->name;
                    $parentId = $parent->parent_id;
                }

                return [
                    'id' => (int) $category->id,
                    'path' => implode(
                        ' > ',
                        array_reverse($path)
                    ),
                ];
            }
        );
    }
}
