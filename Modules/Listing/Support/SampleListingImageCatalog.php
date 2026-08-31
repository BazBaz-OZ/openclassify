<?php

declare(strict_types=1);

namespace Modules\Listing\Support;

use Illuminate\Support\Collection;
use Modules\Category\Models\Category;

final class SampleListingImageCatalog
{
    private const DIRECTORY = 'sample_image';

    private const MAX_PIXELS = 12000000;

    private const MAX_EDGE = 4200;

    /*
     * Only map an image where the existing sample photo reasonably represents
     * the item. Categories without a suitable sample deliberately receive no
     * image instead of an unrelated one.
     */
    private const CATEGORY_IMAGES = [
        'computers & laptops' => [
            'laptop.jpg',
            'macbook.jpg',
            'tech product macbook digital image render macbook pro.jpg',
        ],
        'mobile phones' => [
            'phone.jpeg',
        ],
        'headphones' => [
            'headphones.jpg',
        ],
        'beds & bedroom furniture' => [
            'house interior design home interior bedroom.jpg',
        ],
        'home decor' => [
            'house interior design home interior bedroom.jpg',
        ],
        'kitchenware & dining' => [
            'cup.jpg',
        ],

        'roofing materials' => [
            'roof large house fence gate.jpg',
        ],
        'fencing & gates' => [
            'roof large house fence gate.jpg',
        ],

        "men's clothing" => [
            'grey product photography hat sustainable fashion beanie ethical fashion ambleside .jpg',
            'nike-sport-wear.png',
        ],
        "kids' clothing" => [
            'nike-sport-wear.png',
        ],
        'shoes' => [
            'fashion natural wedding product shoes.jpg',
        ],
        'watches' => [
            'smart-watch.jpg',
            ' watch_band.jpg',
        ],
        'fashion accessories' => [
            'sunglasses.jpg',
            'grey product photography hat sustainable fashion beanie ethical fashion ambleside .jpg',
        ],

    ];

    public static function uniquePaths(): Collection
    {
        return self::allPaths()
            ->sortBy(fn (string $path): string => strtolower((string) basename($path)))
            ->map(fn (string $path): array => [
                'path' => $path,
                'hash' => md5_file($path) ?: strtolower((string) basename($path)),
            ])
            ->unique('hash')
            ->pluck('path')
            ->values();
    }

    public static function pathFor(Category $category, int $seed): ?string
    {
        $name = strtolower(trim((string) $category->name));

        $paths = collect(self::CATEGORY_IMAGES[$name] ?? [])
            ->map(
                fn (string $fileName): string => public_path(
                    self::DIRECTORY.'/'.$fileName
                )
            )
            ->filter(fn (string $path): bool => self::isAllowed($path))
            ->values();

        if ($paths->isEmpty()) {
            return null;
        }

        return $paths->get(abs($seed) % $paths->count());
    }

    public static function fileNameFor(
        string $absolutePath,
        string $slug
    ): string {
        $extension = strtolower((string) pathinfo(
            $absolutePath,
            PATHINFO_EXTENSION
        ));

        $hash = md5_file($absolutePath);

        $hashSuffix = is_string($hash) && $hash !== ''
            ? '-'.substr($hash, 0, 8)
            : '';

        return $slug.$hashSuffix.($extension !== '' ? '.'.$extension : '');
    }

    private static function allPaths(): Collection
    {
        $paths = glob(public_path(self::DIRECTORY.'/*')) ?: [];

        return collect($paths)
            ->filter(function (string $path): bool {
                if (! self::isAllowed($path)) {
                    return false;
                }

                $extension = strtolower((string) pathinfo(
                    $path,
                    PATHINFO_EXTENSION
                ));

                return in_array(
                    $extension,
                    ['jpg', 'jpeg', 'png', 'webp'],
                    true
                );
            })
            ->values();
    }

    private static function isAllowed(string $path): bool
    {
        if (! is_file($path)) {
            return false;
        }

        if (
            filesize($path) >
            (int) config('media-library.max_file_size', 10 * 1024 * 1024)
        ) {
            return false;
        }

        $dimensions = @getimagesize($path);

        if (! is_array($dimensions)) {
            return false;
        }

        $width = (int) ($dimensions[0] ?? 0);
        $height = (int) ($dimensions[1] ?? 0);

        if ($width < 1 || $height < 1) {
            return false;
        }

        if (max($width, $height) > self::MAX_EDGE) {
            return false;
        }

        return ($width * $height) <= self::MAX_PIXELS;
    }
}
