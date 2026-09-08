<?php

declare(strict_types=1);

namespace Modules\Listing\Support;

use GdImage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Modules\Listing\Models\VirtualGarageItem;
use Throwable;

class VirtualGarageItemSpotlighter
{
    private const MAX_WIDTH = 1600;
    private const MAX_HEIGHT = 1200;

    /** VISION_VERTICAL_LAYOUT_V1 */
    private array $verticalLayoutCache = [];

    public function generate(
        VirtualGarageItem $item
    ): ?array {
        $item->loadMissing('photo');

        $photo = $item->photo;
        $box = $item->bounding_box;

        if (
            ! $photo
            || ! is_array($box)
        ) {
            return null;
        }

        foreach (
            ['x', 'y', 'width', 'height']
            as $key
        ) {
            if (
                ! isset($box[$key])
                || ! is_numeric($box[$key])
            ) {
                return null;
            }
        }

        try {
            $disk = Storage::disk(
                $photo->disk
            );

            $sourcePath = $disk->path(
                $photo->path
            );

            if (! is_file($sourcePath)) {
                return null;
            }

            $binary = file_get_contents(
                $sourcePath
            );

            if ($binary === false) {
                return null;
            }

            $source = @imagecreatefromstring(
                $binary
            );

            if (! $source instanceof GdImage) {
                return null;
            }

            try {
                $sourceWidth =
                    imagesx($source);

                $sourceHeight =
                    imagesy($source);

                if (
                    $sourceWidth < 1
                    || $sourceHeight < 1
                ) {
                    return null;
                }

                $scale = min(
                    1,
                    self::MAX_WIDTH
                        / $sourceWidth,
                    self::MAX_HEIGHT
                        / $sourceHeight
                );

                $width = max(
                    1,
                    (int) round(
                        $sourceWidth
                        * $scale
                    )
                );

                $height = max(
                    1,
                    (int) round(
                        $sourceHeight
                        * $scale
                    )
                );

                $sharp =
                    imagecreatetruecolor(
                        $width,
                        $height
                    );

                if (! $sharp instanceof GdImage) {
                    return null;
                }

                try {
                    imagecopyresampled(
                        $sharp,
                        $source,
                        0,
                        0,
                        0,
                        0,
                        $width,
                        $height,
                        $sourceWidth,
                        $sourceHeight
                    );

                    $background =
                        imagecreatetruecolor(
                            $width,
                            $height
                        );

                    if (
                        ! $background
                        instanceof GdImage
                    ) {
                        return null;
                    }

                    try {
                        imagecopy(
                            $background,
                            $sharp,
                            0,
                            0,
                            0,
                            0,
                            $width,
                            $height
                        );

                        /*
                         * Make the surroundings softer,
                         * but keep enough detail that the
                         * buyer understands the scene.
                         */
                        for (
                            $i = 0;
                            $i < 3;
                            $i++
                        ) {
                            imagefilter(
                                $background,
                                IMG_FILTER_GAUSSIAN_BLUR
                            );
                        }

                        imagefilter(
                            $background,
                            IMG_FILTER_BRIGHTNESS,
                            -25
                        );

                        $x = $this->clampRatio(
                            (float) $box['x']
                        );

                        $y = $this->clampRatio(
                            (float) $box['y']
                        );

                        $boxWidth =
                            max(
                                0.01,
                                min(
                                    1,
                                    (float)
                                    $box['width']
                                )
                            );

                        $boxHeight =
                            max(
                                0.01,
                                min(
                                    1,
                                    (float)
                                    $box['height']
                                )
                            );

                        $orientation =
                            $this->classifyOrientation(
                                $boxWidth,
                                $boxHeight
                            );

                        /*
                         * VISION_VERTICAL_LAYOUT_V1
                         *
                         * For rows of tall objects, the AI may
                         * identify the correct left-to-right
                         * order while expressing X coordinates
                         * relative to the object group rather
                         * than the entire photograph.
                         *
                         * OpenCV finds the real physical group
                         * limits and we remap the AI box into
                         * that photographic coordinate space.
                         */
                        if (
                            $orientation === 'vertical'
                        ) {
                            $calibratedBox =
                                $this->calibrateVerticalBox(
                                    $item,
                                    [
                                        'x' =>
                                            $x,

                                        'y' =>
                                            $y,

                                        'width' =>
                                            $boxWidth,

                                        'height' =>
                                            $boxHeight,
                                    ]
                                );

                            $x =
                                (float)
                                $calibratedBox['x'];

                            $y =
                                (float)
                                $calibratedBox['y'];

                            $boxWidth =
                                (float)
                                $calibratedBox['width'];

                            $boxHeight =
                                (float)
                                $calibratedBox['height'];
                        }

                        /*
                         * Keep the detected object and some
                         * surrounding context sharp.
                         */
                        $paddingX =
                            $orientation === 'vertical'
                                ? max(
                                    0.012,
                                    $boxWidth * 0.20
                                )
                                : max(
                                    0.02,
                                    $boxWidth * 0.12
                                );

                        $paddingY =
                            $orientation === 'horizontal'
                                ? max(
                                    0.012,
                                    $boxHeight * 0.22
                                )
                                : max(
                                    0.02,
                                    $boxHeight * 0.10
                                );

                        $focusLeft =
                            max(
                                0,
                                $x - $paddingX
                            );

                        $focusTop =
                            max(
                                0,
                                $y - $paddingY
                            );

                        $focusRight =
                            min(
                                1,
                                $x
                                    + $boxWidth
                                    + $paddingX
                            );

                        $focusBottom =
                            min(
                                1,
                                $y
                                    + $boxHeight
                                    + $paddingY
                            );

                        $px =
                            (int) floor(
                                $focusLeft
                                * $width
                            );

                        $py =
                            (int) floor(
                                $focusTop
                                * $height
                            );

                        $pr =
                            (int) ceil(
                                $focusRight
                                * $width
                            );

                        $pb =
                            (int) ceil(
                                $focusBottom
                                * $height
                            );

                        $pw =
                            max(
                                1,
                                $pr - $px
                            );

                        $ph =
                            max(
                                1,
                                $pb - $py
                            );

                        imagecopy(
                            $background,
                            $sharp,
                            $px,
                            $py,
                            $px,
                            $py,
                            $pw,
                            $ph
                        );

                        [
                            $targetRatioX,
                            $targetRatioY,
                        ] =
                            $this
                                ->resolveTargetRatios(
                                    $item,
                                    [
                                        'x' =>
                                            $x,

                                        'y' =>
                                            $y,

                                        'width' =>
                                            $boxWidth,

                                        'height' =>
                                            $boxHeight,
                                    ],
                                    $orientation
                                );

                        $targetX =
                            (int) round(
                                $targetRatioX
                                * $width
                            );

                        $targetY =
                            (int) round(
                                $targetRatioY
                                * $height
                            );

                        /*
                         * Colours.
                         */
                        $black =
                            imagecolorallocate(
                                $background,
                                20,
                                20,
                                20
                            );

                        $white =
                            imagecolorallocate(
                                $background,
                                255,
                                255,
                                255
                            );

                        $red =
                            imagecolorallocate(
                                $background,
                                220,
                                38,
                                38
                            );

                        $labelText =
                            'This item';

                        $font = 5;

                        $textWidth =
                            imagefontwidth(
                                $font
                            )
                            * strlen(
                                $labelText
                            );

                        $textHeight =
                            imagefontheight(
                                $font
                            );

                        $bubblePaddingX = 11;
                        $bubblePaddingY = 7;

                        $bubbleWidth =
                            $textWidth
                            + (
                                $bubblePaddingX
                                * 2
                            );

                        $bubbleHeight =
                            $textHeight
                            + (
                                $bubblePaddingY
                                * 2
                            );

                        $placement =
                            $this
                                ->chooseCalloutPlacement(
                                    $width,
                                    $height,
                                    $px,
                                    $py,
                                    $pr,
                                    $pb,
                                    $targetX,
                                    $targetY,
                                    $bubbleWidth,
                                    $bubbleHeight,
                                    $orientation
                                );

                        /*
                         * Label.
                         */
                        imagefilledrectangle(
                            $background,
                            $placement[
                                'bubble_x'
                            ],
                            $placement[
                                'bubble_y'
                            ],
                            $placement[
                                'bubble_x'
                            ]
                                + $bubbleWidth,
                            $placement[
                                'bubble_y'
                            ]
                                + $bubbleHeight,
                            $white
                        );

                        imagerectangle(
                            $background,
                            $placement[
                                'bubble_x'
                            ],
                            $placement[
                                'bubble_y'
                            ],
                            $placement[
                                'bubble_x'
                            ]
                                + $bubbleWidth,
                            $placement[
                                'bubble_y'
                            ]
                                + $bubbleHeight,
                            $black
                        );

                        imagestring(
                            $background,
                            $font,
                            $placement[
                                'bubble_x'
                            ]
                                + $bubblePaddingX,
                            $placement[
                                'bubble_y'
                            ]
                                + $bubblePaddingY,
                            $labelText,
                            $black
                        );

                        /*
                         * Arrow.
                         */
                        $this->drawArrow(
                            $background,
                            $placement[
                                'line_x'
                            ],
                            $placement[
                                'line_y'
                            ],
                            $targetX,
                            $targetY,
                            $black,
                            $white
                        );

                        /*
                         * Precise target dot.
                         */
                        imagefilledellipse(
                            $background,
                            $targetX,
                            $targetY,
                            18,
                            18,
                            $white
                        );

                        imageellipse(
                            $background,
                            $targetX,
                            $targetY,
                            18,
                            18,
                            $black
                        );

                        imagefilledellipse(
                            $background,
                            $targetX,
                            $targetY,
                            8,
                            8,
                            $red
                        );

                        ob_start();

                        $written =
                            imagewebp(
                                $background,
                                null,
                                86
                            );

                        $webp =
                            ob_get_clean();

                        if (
                            ! $written
                            || ! is_string(
                                $webp
                            )
                            || $webp === ''
                        ) {
                            return null;
                        }

                        $hash =
                            substr(
                                sha1(
                                    json_encode(
                                        $box
                                    )
                                    .':'
                                    .$orientation
                                    .':v6:'
                                    .$webp
                                ),
                                0,
                                12
                            );

                        $path =
                            'virtual-garages/'
                            .$item
                                ->virtual_garage_id
                            .'/items/spotlight-'
                            .$item->getKey()
                            .'-'
                            .$hash
                            .'.webp';

                        $aiData =
                            is_array(
                                $item->ai_data
                            )
                                ? $item->ai_data
                                : [];

                        $old =
                            $aiData[
                                'spotlight_file'
                            ] ?? null;

                        $disk->put(
                            $path,
                            $webp
                        );

                        if (
                            is_array($old)
                            && filled(
                                $old['path']
                                ?? null
                            )
                            && (
                                $old['path']
                                !== $path
                            )
                        ) {
                            $disk->delete(
                                (string)
                                $old['path']
                            );
                        }

                        $aiData[
                            'spotlight_file'
                        ] = [
                            'disk' =>
                                $photo->disk,

                            'path' =>
                                $path,

                            'width' =>
                                $width,

                            'height' =>
                                $height,

                            'version' => 6,

                            'style' =>
                                'orientation-aware-vision',

                            'orientation' =>
                                $orientation,

                            'callout_side' =>
                                $placement[
                                    'side'
                                ],
                        ];

                        $item->forceFill([
                            'ai_data' =>
                                $aiData,
                        ])->save();

                        return $aiData[
                            'spotlight_file'
                        ];
                    } finally {
                        imagedestroy(
                            $background
                        );
                    }
                } finally {
                    imagedestroy(
                        $sharp
                    );
                }
            } finally {
                imagedestroy(
                    $source
                );
            }
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }


    private function calibrateVerticalBox(
        VirtualGarageItem $item,
        array $box
    ): array {
        $photo = $item->photo;

        if (! $photo) {
            return $box;
        }

        $photoId =
            (int)
            $item->virtual_garage_photo_id;

        /*
         * Cache the layout because generate() is normally
         * called repeatedly for every item from the same
         * photograph.
         *
         * This means one OpenCV request per photo rather
         * than one request per DVD/item.
         */
        if (
            ! array_key_exists(
                $photoId,
                $this->verticalLayoutCache
            )
        ) {
            $siblings =
                VirtualGarageItem::query()
                    ->where(
                        'virtual_garage_photo_id',
                        $photoId
                    )
                    ->whereNotNull(
                        'bounding_box'
                    )
                    ->orderBy('sort_order')
                    ->get();

            $items = [];

            foreach ($siblings as $sibling) {
                $siblingBox =
                    $sibling->bounding_box;

                if (! is_array($siblingBox)) {
                    continue;
                }

                $valid = true;

                foreach (
                    [
                        'x',
                        'y',
                        'width',
                        'height',
                    ]
                    as $key
                ) {
                    if (
                        ! isset(
                            $siblingBox[$key]
                        )
                        || ! is_numeric(
                            $siblingBox[$key]
                        )
                    ) {
                        $valid = false;

                        break;
                    }
                }

                if (! $valid) {
                    continue;
                }

                $items[] = [
                    'id' =>
                        (int)
                        $sibling->getKey(),

                    'title' =>
                        (string)
                        $sibling->title,

                    'bounding_box' => [
                        'x' =>
                            (float)
                            $siblingBox['x'],

                        'y' =>
                            (float)
                            $siblingBox['y'],

                        'width' =>
                            (float)
                            $siblingBox['width'],

                        'height' =>
                            (float)
                            $siblingBox['height'],
                    ],
                ];
            }

            $layout = null;

            if (count($items) >= 4) {
                try {
                    $response =
                        Http::timeout(10)
                            ->post(
                                'http://vision:8001'
                                .'/layout/vertical',
                                [
                                    'path' =>
                                        $photo->path,

                                    'items' =>
                                        $items,
                                ]
                            );

                    if ($response->successful()) {
                        $result =
                            $response->json();

                        if (is_array($result)) {
                            $layout = $result;
                        }
                    }
                } catch (Throwable $exception) {
                    /*
                     * Vision correction is an enhancement.
                     * If the service ever becomes unavailable,
                     * fall back to the original AI coordinates
                     * instead of breaking Virtual Garage.
                     */
                    report($exception);
                }
            }

            $this->verticalLayoutCache[
                $photoId
            ] = $layout;
        }

        $layout =
            $this->verticalLayoutCache[
                $photoId
            ];

        if (
            ! is_array($layout)
            || ! (
                $layout['detected']
                ?? false
            )
        ) {
            return $box;
        }

        $confidence =
            (float)
            ($layout['confidence']
                ?? 0);

        $groupLeft =
            (float)
            ($layout['group_left']
                ?? 0);

        $groupRight =
            (float)
            ($layout['group_right']
                ?? 1);

        $span =
            $groupRight
            - $groupLeft;

        /*
         * Reject suspicious OpenCV results.
         */
        if (
            $confidence < 0.65
            || $groupLeft < 0
            || $groupRight > 1
            || $groupRight <= $groupLeft
            || $span < 0.25
            || $span > 0.95
        ) {
            return $box;
        }

        /*
         * Important:
         *
         * Do NOT use clampRatio() here because that method
         * deliberately prevents values below 0.02. The AI
         * legitimately returned x=0 for the first DVD, and
         * that is exactly what we need for group-relative
         * coordinate remapping.
         */
        $originalLeft =
            max(
                0.0,
                min(
                    1.0,
                    (float)
                    ($box['x'] ?? 0)
                )
            );

        $originalRight =
            max(
                0.0,
                min(
                    1.0,
                    (float)
                    ($box['x'] ?? 0)
                    + (float)
                    ($box['width'] ?? 0)
                )
            );

        $correctedLeft =
            $groupLeft
            + (
                $originalLeft
                * $span
            );

        $correctedRight =
            $groupLeft
            + (
                $originalRight
                * $span
            );

        $correctedWidth =
            max(
                0.01,
                $correctedRight
                - $correctedLeft
            );

        return [
            'x' =>
                max(
                    0.0,
                    min(
                        1.0,
                        $correctedLeft
                    )
                ),

            'y' =>
                (float)
                ($box['y'] ?? 0),

            'width' =>
                min(
                    1.0,
                    $correctedWidth
                ),

            'height' =>
                (float)
                ($box['height'] ?? 0),
        ];
    }


    private function classifyOrientation(
        float $width,
        float $height
    ): string {
        if (
            $width
            >= ($height * 2.2)
        ) {
            return 'horizontal';
        }

        if (
            $height
            >= ($width * 2.2)
        ) {
            return 'vertical';
        }

        return 'normal';
    }

    private function resolveTargetRatios(
        VirtualGarageItem $item,
        array $box,
        string $orientation
    ): array {
        $x =
            (float)
            ($box['x'] ?? 0);

        $y =
            (float)
            ($box['y'] ?? 0);

        $width =
            (float)
            ($box['width'] ?? 0);

        $height =
            (float)
            ($box['height'] ?? 0);

        /*
         * Horizontal stacks:
         *
         * Keep the correction that worked well on
         * the Nintendo cases. This correction is
         * ONLY applied to wide, shallow objects.
         */
        $isStackedHorizontal =
            $orientation === 'horizontal'
            && $width >= 0.60
            && $height <= 0.12
            && $width
                > ($height * 4);

        if ($isStackedHorizontal) {
            $centerY =
                $y
                + ($height * 0.50);

            $siblings =
                VirtualGarageItem::query()
                    ->where(
                        'virtual_garage_photo_id',
                        $item
                            ->virtual_garage_photo_id
                    )
                    ->whereNotNull(
                        'bounding_box'
                    )
                    ->get();

            $centres = [];

            foreach (
                $siblings
                as $sibling
            ) {
                $bb =
                    $sibling
                        ->bounding_box;

                if (
                    ! is_array($bb)
                ) {
                    continue;
                }

                $sw =
                    (float)
                    ($bb['width']
                        ?? 0);

                $sh =
                    (float)
                    ($bb['height']
                        ?? 0);

                if (
                    $sw < 0.45
                    || $sh > 0.16
                    || $sw
                        <= ($sh * 3)
                ) {
                    continue;
                }

                $sy =
                    (float)
                    ($bb['y']
                        ?? 0);

                $centres[] =
                    $sy
                    + ($sh * 0.50);
            }

            sort($centres);

            $spacings = [];

            for (
                $i = 1;
                $i < count(
                    $centres
                );
                $i++
            ) {
                $spacing =
                    $centres[$i]
                    - $centres[
                        $i - 1
                    ];

                if (
                    $spacing >= 0.025
                    && $spacing <= 0.12
                ) {
                    $spacings[] =
                        $spacing;
                }
            }

            $rowSpacing =
                max(
                    $height,
                    0.055
                );

            if ($spacings) {
                sort($spacings);

                $middle =
                    intdiv(
                        count($spacings),
                        2
                    );

                if (
                    count($spacings)
                        % 2
                    === 0
                ) {
                    $rowSpacing =
                        (
                            $spacings[
                                $middle - 1
                            ]
                            + $spacings[
                                $middle
                            ]
                        ) / 2;
                } else {
                    $rowSpacing =
                        $spacings[
                            $middle
                        ];
                }
            }

            return [
                /*
                 * Title area rather than extreme
                 * left/right of the spine.
                 */
                $this->clampRatio(
                    $x
                    + (
                        $width
                        * 0.38
                    )
                ),

                /*
                 * Existing successful stack correction.
                 */
                $this->clampRatio(
                    $centerY
                    - $rowSpacing
                ),
            ];
        }

        /*
         * Vertical objects:
         *
         * Never apply horizontal row correction.
         * Point directly into the centre of that
         * item's own vertical bounding box.
         */
        if (
            $orientation === 'vertical'
        ) {
            return [
                $this->clampRatio(
                    $x
                    + (
                        $width
                        * 0.50
                    )
                ),

                $this->clampRatio(
                    $y
                    + (
                        $height
                        * 0.50
                    )
                ),
            ];
        }

        /*
         * Ordinary irregular items can use a precise
         * AI pointer if one has been generated.
         */
        $aiData =
            is_array($item->ai_data)
                ? $item->ai_data
                : [];

        $pointer =
            $aiData[
                'pointer_point'
            ] ?? null;

        if (
            is_array($pointer)
            && isset(
                $pointer['x'],
                $pointer['y']
            )
            && is_numeric(
                $pointer['x']
            )
            && is_numeric(
                $pointer['y']
            )
            && (
                ! isset(
                    $pointer[
                        'confidence'
                    ]
                )
                || (
                    is_numeric(
                        $pointer[
                            'confidence'
                        ]
                    )
                    && (float)
                        $pointer[
                            'confidence'
                        ]
                        >= 0.70
                )
            )
        ) {
            return [
                $this->clampRatio(
                    (float)
                    $pointer['x']
                ),

                $this->clampRatio(
                    (float)
                    $pointer['y']
                ),
            ];
        }

        return [
            $this->clampRatio(
                $x
                + ($width * 0.50)
            ),

            $this->clampRatio(
                $y
                + ($height * 0.50)
            ),
        ];
    }

    private function chooseCalloutPlacement(
        int $imageWidth,
        int $imageHeight,
        int $focusLeft,
        int $focusTop,
        int $focusRight,
        int $focusBottom,
        int $targetX,
        int $targetY,
        int $bubbleWidth,
        int $bubbleHeight,
        string $orientation
    ): array {
        $margin = 14;
        $gap = 16;

        $spaces = [
            'left' =>
                max(
                    0,
                    $focusLeft
                ),

            'right' =>
                max(
                    0,
                    $imageWidth
                    - $focusRight
                ),

            'top' =>
                max(
                    0,
                    $focusTop
                ),

            'bottom' =>
                max(
                    0,
                    $imageHeight
                    - $focusBottom
                ),
        ];

        /*
         * Orientation preference.
         *
         * Vertical objects:
         * prefer left/right.
         *
         * Horizontal objects:
         * prefer top/bottom.
         *
         * Normal objects:
         * simply use whatever side has
         * the most available space.
         */
        $preference = match (
            $orientation
        ) {
            'vertical' => [
                'left' => 180,
                'right' => 180,
                'top' => 40,
                'bottom' => 40,
            ],

            'horizontal' => [
                'left' => 40,
                'right' => 40,
                'top' => 180,
                'bottom' => 180,
            ],

            default => [
                'left' => 80,
                'right' => 80,
                'top' => 80,
                'bottom' => 80,
            ],
        };

        $scores = [];

        foreach (
            [
                'left',
                'right',
                'top',
                'bottom',
            ]
            as $side
        ) {
            $required =
                in_array(
                    $side,
                    [
                        'left',
                        'right',
                    ],
                    true
                )
                    ? $bubbleWidth
                        + $gap
                        + $margin
                    : $bubbleHeight
                        + $gap
                        + $margin;

            $fitBonus =
                $spaces[$side]
                    >= $required
                    ? 1000
                    : 0;

            $scores[$side] =
                $fitBonus
                + $spaces[$side]
                + $preference[
                    $side
                ];
        }

        arsort($scores);

        $side =
            array_key_first(
                $scores
            ) ?: 'left';

        if ($side === 'left') {
            $bubbleX =
                max(
                    $margin,
                    $focusLeft
                        - $bubbleWidth
                        - $gap
                );

            $bubbleY =
                $this->clampInt(
                    $targetY
                        - (int)
                        round(
                            $bubbleHeight
                            / 2
                        ),
                    $margin,
                    max(
                        $margin,
                        $imageHeight
                        - $bubbleHeight
                        - $margin
                    )
                );

            return [
                'side' =>
                    'left',

                'bubble_x' =>
                    $bubbleX,

                'bubble_y' =>
                    $bubbleY,

                'line_x' =>
                    $bubbleX
                    + $bubbleWidth,

                'line_y' =>
                    $bubbleY
                    + (int)
                    round(
                        $bubbleHeight
                        / 2
                    ),
            ];
        }

        if ($side === 'right') {
            $bubbleX =
                min(
                    $imageWidth
                        - $bubbleWidth
                        - $margin,
                    $focusRight
                        + $gap
                );

            $bubbleX =
                max(
                    $margin,
                    $bubbleX
                );

            $bubbleY =
                $this->clampInt(
                    $targetY
                        - (int)
                        round(
                            $bubbleHeight
                            / 2
                        ),
                    $margin,
                    max(
                        $margin,
                        $imageHeight
                        - $bubbleHeight
                        - $margin
                    )
                );

            return [
                'side' =>
                    'right',

                'bubble_x' =>
                    $bubbleX,

                'bubble_y' =>
                    $bubbleY,

                'line_x' =>
                    $bubbleX,

                'line_y' =>
                    $bubbleY
                    + (int)
                    round(
                        $bubbleHeight
                        / 2
                    ),
            ];
        }

        if ($side === 'top') {
            $bubbleX =
                $this->clampInt(
                    $targetX
                        - (int)
                        round(
                            $bubbleWidth
                            / 2
                        ),
                    $margin,
                    max(
                        $margin,
                        $imageWidth
                        - $bubbleWidth
                        - $margin
                    )
                );

            $bubbleY =
                max(
                    $margin,
                    $focusTop
                        - $bubbleHeight
                        - $gap
                );

            return [
                'side' =>
                    'top',

                'bubble_x' =>
                    $bubbleX,

                'bubble_y' =>
                    $bubbleY,

                'line_x' =>
                    $bubbleX
                    + (int)
                    round(
                        $bubbleWidth
                        / 2
                    ),

                'line_y' =>
                    $bubbleY
                    + $bubbleHeight,
            ];
        }

        /*
         * Bottom fallback.
         */
        $bubbleX =
            $this->clampInt(
                $targetX
                    - (int)
                    round(
                        $bubbleWidth
                        / 2
                    ),
                $margin,
                max(
                    $margin,
                    $imageWidth
                    - $bubbleWidth
                    - $margin
                )
            );

        $bubbleY =
            min(
                $imageHeight
                    - $bubbleHeight
                    - $margin,
                $focusBottom
                    + $gap
            );

        $bubbleY =
            max(
                $margin,
                $bubbleY
            );

        return [
            'side' =>
                'bottom',

            'bubble_x' =>
                $bubbleX,

            'bubble_y' =>
                $bubbleY,

            'line_x' =>
                $bubbleX
                + (int)
                round(
                    $bubbleWidth
                    / 2
                ),

            'line_y' =>
                $bubbleY,
        ];
    }

    private function drawArrow(
        GdImage $image,
        int $fromX,
        int $fromY,
        int $toX,
        int $toY,
        int $outlineColor,
        int $lineColor
    ): void {
        imagesetthickness(
            $image,
            6
        );

        imageline(
            $image,
            $fromX,
            $fromY,
            $toX,
            $toY,
            $outlineColor
        );

        imagesetthickness(
            $image,
            3
        );

        imageline(
            $image,
            $fromX,
            $fromY,
            $toX,
            $toY,
            $lineColor
        );

        $angle =
            atan2(
                $toY - $fromY,
                $toX - $fromX
            );

        $arrowLength = 16;

        $arrowSpread =
            deg2rad(
                24
            );

        $x1 =
            (int) round(
                $toX
                - (
                    $arrowLength
                    * cos(
                        $angle
                        - $arrowSpread
                    )
                )
            );

        $y1 =
            (int) round(
                $toY
                - (
                    $arrowLength
                    * sin(
                        $angle
                        - $arrowSpread
                    )
                )
            );

        $x2 =
            (int) round(
                $toX
                - (
                    $arrowLength
                    * cos(
                        $angle
                        + $arrowSpread
                    )
                )
            );

        $y2 =
            (int) round(
                $toY
                - (
                    $arrowLength
                    * sin(
                        $angle
                        + $arrowSpread
                    )
                )
            );

        imagesetthickness(
            $image,
            6
        );

        imageline(
            $image,
            $toX,
            $toY,
            $x1,
            $y1,
            $outlineColor
        );

        imageline(
            $image,
            $toX,
            $toY,
            $x2,
            $y2,
            $outlineColor
        );

        imagesetthickness(
            $image,
            3
        );

        imageline(
            $image,
            $toX,
            $toY,
            $x1,
            $y1,
            $lineColor
        );

        imageline(
            $image,
            $toX,
            $toY,
            $x2,
            $y2,
            $lineColor
        );
    }

    private function clampRatio(
        float $value
    ): float {
        return max(
            0.02,
            min(
                0.98,
                $value
            )
        );
    }

    private function clampInt(
        int $value,
        int $min,
        int $max
    ): int {
        return max(
            $min,
            min(
                $max,
                $value
            )
        );
    }
}
