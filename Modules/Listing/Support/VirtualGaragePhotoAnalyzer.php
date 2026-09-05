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

        $catalog = $this->buildCatalog($categories);

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

Do not combine clearly separate objects into one item.
Do not invent objects that are not visible.
Ignore walls, floors, shelving and general background clutter unless
they are obviously items being sold.
Use only category IDs supplied in the catalog.
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
