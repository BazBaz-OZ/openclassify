<?php

declare(strict_types=1);

namespace Modules\Listing\Support;

use Illuminate\Support\Facades\Storage;
use Modules\Listing\Models\VirtualGarageItem;
use Throwable;

class VirtualGarageItemManualCropper
{
    private const TARGET_WIDTH = 960;
    private const TARGET_HEIGHT = 720;
    private const TARGET_RATIO = 4 / 3;

    private const MIN_ZOOM = 1.0;
    private const MAX_ZOOM = 4.0;

    private const MIN_ROTATION = -180.0;
    private const MAX_ROTATION = 180.0;

    private const VERSION = 3;

    public function generate(
        VirtualGarageItem $item
    ): ?array {
        $item->loadMissing('photo');

        $photo = $item->photo;

        $manualCrop =
            $this->normaliseCropState(
                $item->ai_data[
                    'manual_crop'
                ] ?? null
            );

        if (
            ! $photo
            || ! $manualCrop
            || blank($photo->disk)
            || blank($photo->path)
        ) {
            return null;
        }

        try {
            $disk =
                Storage::disk(
                    $photo->disk
                );

            $sourcePath =
                $disk->path(
                    $photo->path
                );

            if (! is_file($sourcePath)) {
                return null;
            }

            $contents =
                file_get_contents(
                    $sourcePath
                );

            if ($contents === false) {
                return null;
            }

            $source =
                @imagecreatefromstring(
                    $contents
                );

            if ($source === false) {
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

                [
                    $baseCropWidth,
                    $baseCropHeight,
                ] =
                    $this->baseCropSize(
                        $sourceWidth,
                        $sourceHeight
                    );

                $zoom =
                    (float)
                    $manualCrop['zoom'];

                $cropWidth =
                    max(
                        1,
                        (int) round(
                            $baseCropWidth
                            / $zoom
                        )
                    );

                $cropHeight =
                    max(
                        1,
                        (int) round(
                            $baseCropHeight
                            / $zoom
                        )
                    );

                $centreX =
                    (float)
                    $manualCrop[
                        'center_x'
                    ]
                    * $sourceWidth;

                $centreY =
                    (float)
                    $manualCrop[
                        'center_y'
                    ]
                    * $sourceHeight;

                $rotation =
                    (float)
                    $manualCrop[
                        'rotation'
                    ];

                $cropped =
                    $this
                        ->createRotatedCrop(
                            $source,
                            $sourceWidth,
                            $sourceHeight,
                            $centreX,
                            $centreY,
                            $cropWidth,
                            $cropHeight,
                            $rotation
                        );

                if ($cropped === false) {
                    return null;
                }

                try {
                    /*
                     * Produce a predictable lightweight
                     * 4:3 derivative for the card/listing.
                     */
                    $output =
                        imagecreatetruecolor(
                            self::TARGET_WIDTH,
                            self::TARGET_HEIGHT
                        );

                    if ($output === false) {
                        return null;
                    }

                    try {
                        imagealphablending(
                            $output,
                            false
                        );

                        imagesavealpha(
                            $output,
                            true
                        );

                        $transparent =
                            imagecolorallocatealpha(
                                $output,
                                0,
                                0,
                                0,
                                127
                            );

                        imagefill(
                            $output,
                            0,
                            0,
                            $transparent
                        );

                        imagealphablending(
                            $output,
                            true
                        );

                        imagecopyresampled(
                            $output,
                            $cropped,
                            0,
                            0,
                            0,
                            0,
                            self::TARGET_WIDTH,
                            self::TARGET_HEIGHT,
                            imagesx($cropped),
                            imagesy($cropped)
                        );

                        ob_start();

                        $written =
                            imagewebp(
                                $output,
                                null,
                                88
                            );

                        $binary =
                            ob_get_clean();

                        if (
                            ! $written
                            || ! is_string(
                                $binary
                            )
                            || $binary === ''
                        ) {
                            return null;
                        }

                        $hash =
                            substr(
                                sha1(
                                    $binary
                                    .':'
                                    .json_encode(
                                        $manualCrop
                                    )
                                ),
                                0,
                                12
                            );

                        $cropPath =
                            'virtual-garages/'
                            .$item
                                ->virtual_garage_id
                            .'/items/manual-'
                            .$item->getKey()
                            .'-v'
                            .self::VERSION
                            .'-'
                            .$hash
                            .'.webp';

                        $aiData =
                            is_array(
                                $item->ai_data
                            )
                                ? $item->ai_data
                                : [];

                        $previousFile =
                            $aiData[
                                'manual_crop_file'
                            ] ?? null;

                        $disk->put(
                            $cropPath,
                            $binary
                        );

                        if (
                            is_array(
                                $previousFile
                            )
                            && filled(
                                $previousFile[
                                    'disk'
                                ] ?? null
                            )
                            && filled(
                                $previousFile[
                                    'path'
                                ] ?? null
                            )
                            && $previousFile[
                                'path'
                            ] !== $cropPath
                        ) {
                            try {
                                Storage::disk(
                                    (string)
                                    $previousFile[
                                        'disk'
                                    ]
                                )->delete(
                                    (string)
                                    $previousFile[
                                        'path'
                                    ]
                                );
                            } catch (Throwable) {
                                /*
                                 * Old derivative cleanup
                                 * must never break saving.
                                 */
                            }
                        }

                        $aiData[
                            'manual_crop_file'
                        ] = [
                            'disk' =>
                                $photo->disk,

                            'path' =>
                                $cropPath,

                            'aspect_ratio' =>
                                '4:3',

                            'width' =>
                                self::TARGET_WIDTH,

                            'height' =>
                                self::TARGET_HEIGHT,

                            'rotation' =>
                                $rotation,

                            'version' =>
                                self::VERSION,
                        ];

                        $item->forceFill([
                            'ai_data' =>
                                $aiData,
                        ])->save();

                        return $aiData[
                            'manual_crop_file'
                        ];
                    } finally {
                        imagedestroy(
                            $output
                        );
                    }
                } finally {
                    imagedestroy(
                        $cropped
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

    private function createRotatedCrop(
        $source,
        int $sourceWidth,
        int $sourceHeight,
        float $centreX,
        float $centreY,
        int $cropWidth,
        int $cropHeight,
        float $rotation
    ) {
        /*
         * No rotation: use the simpler and faster path.
         */
        if (abs($rotation) < 0.01) {
            $cropX =
                (int) round(
                    $centreX
                    - ($cropWidth / 2)
                );

            $cropY =
                (int) round(
                    $centreY
                    - ($cropHeight / 2)
                );

            $cropX =
                max(
                    0,
                    min(
                        $cropX,
                        $sourceWidth
                        - $cropWidth
                    )
                );

            $cropY =
                max(
                    0,
                    min(
                        $cropY,
                        $sourceHeight
                        - $cropHeight
                    )
                );

            return imagecrop(
                $source,
                [
                    'x' => $cropX,
                    'y' => $cropY,
                    'width' =>
                        $cropWidth,
                    'height' =>
                        $cropHeight,
                ]
            );
        }

        /*
         * Work out how much source material is needed
         * to safely rotate the requested 4:3 crop.
         */
        $radians =
            deg2rad(
                abs($rotation)
            );

        $cos =
            abs(
                cos($radians)
            );

        $sin =
            abs(
                sin($radians)
            );

        $supportWidth =
            max(
                $cropWidth,
                (int) ceil(
                    ($cropWidth * $cos)
                    + ($cropHeight * $sin)
                )
            );

        $supportHeight =
            max(
                $cropHeight,
                (int) ceil(
                    ($cropWidth * $sin)
                    + ($cropHeight * $cos)
                )
            );

        /*
         * Small safety margin for interpolation.
         */
        $supportWidth += 8;
        $supportHeight += 8;

        $support =
            imagecreatetruecolor(
                $supportWidth,
                $supportHeight
            );

        if ($support === false) {
            return false;
        }

        imagealphablending(
            $support,
            false
        );

        imagesavealpha(
            $support,
            true
        );

        $transparent =
            imagecolorallocatealpha(
                $support,
                0,
                0,
                0,
                127
            );

        imagefill(
            $support,
            0,
            0,
            $transparent
        );

        imagealphablending(
            $support,
            true
        );

        /*
         * Put the seller-selected source position at
         * the exact centre of our temporary canvas.
         */
        $wantedLeft =
            $centreX
            - ($supportWidth / 2);

        $wantedTop =
            $centreY
            - ($supportHeight / 2);

        $sourceX =
            max(
                0,
                (int) floor(
                    $wantedLeft
                )
            );

        $sourceY =
            max(
                0,
                (int) floor(
                    $wantedTop
                )
            );

        $destX =
            max(
                0,
                (int) round(
                    $sourceX
                    - $wantedLeft
                )
            );

        $destY =
            max(
                0,
                (int) round(
                    $sourceY
                    - $wantedTop
                )
            );

        $copyWidth =
            min(
                $sourceWidth
                    - $sourceX,
                $supportWidth
                    - $destX
            );

        $copyHeight =
            min(
                $sourceHeight
                    - $sourceY,
                $supportHeight
                    - $destY
            );

        if (
            $copyWidth <= 0
            || $copyHeight <= 0
        ) {
            imagedestroy(
                $support
            );

            return false;
        }

        imagecopy(
            $support,
            $source,
            $destX,
            $destY,
            $sourceX,
            $sourceY,
            $copyWidth,
            $copyHeight
        );

        /*
         * CSS/browser positive rotation is clockwise.
         * GD's positive angle is counter-clockwise,
         * therefore invert the saved angle here.
         */
        $rotated =
            imagerotate(
                $support,
                -$rotation,
                $transparent
            );

        imagedestroy(
            $support
        );

        if ($rotated === false) {
            return false;
        }

        imagesavealpha(
            $rotated,
            true
        );

        $rotatedWidth =
            imagesx($rotated);

        $rotatedHeight =
            imagesy($rotated);

        $finalWidth =
            min(
                $cropWidth,
                $rotatedWidth
            );

        $finalHeight =
            min(
                $cropHeight,
                $rotatedHeight
            );

        $cropX =
            max(
                0,
                (int) round(
                    (
                        $rotatedWidth
                        - $finalWidth
                    ) / 2
                )
            );

        $cropY =
            max(
                0,
                (int) round(
                    (
                        $rotatedHeight
                        - $finalHeight
                    ) / 2
                )
            );

        $result =
            imagecrop(
                $rotated,
                [
                    'x' => $cropX,
                    'y' => $cropY,
                    'width' =>
                        $finalWidth,
                    'height' =>
                        $finalHeight,
                ]
            );

        imagedestroy(
            $rotated
        );

        return $result;
    }

    public function storeRenderedCrop(
        VirtualGarageItem $item,
        string $dataUrl
    ): ?array {
        $item->loadMissing('photo');

        $photo = $item->photo;

        if (
            ! $photo
            || blank($photo->disk)
        ) {
            return null;
        }

        if (
            ! preg_match(
                '#^data:image/(?:webp|jpeg|jpg|png);base64,(.+)$#s',
                $dataUrl,
                $matches
            )
        ) {
            return null;
        }

        $binary =
            base64_decode(
                $matches[1],
                true
            );

        if (
            $binary === false
            || strlen($binary) < 100
            || strlen($binary)
                > 12 * 1024 * 1024
        ) {
            return null;
        }

        $image =
            @imagecreatefromstring(
                $binary
            );

        if ($image === false) {
            return null;
        }

        try {
            $width =
                imagesx($image);

            $height =
                imagesy($image);

            if (
                $width < 320
                || $height < 240
            ) {
                return null;
            }

            $ratio =
                $width / $height;

            if (
                abs(
                    $ratio
                    - self::TARGET_RATIO
                ) > 0.03
            ) {
                return null;
            }

            ob_start();

            $success =
                imagewebp(
                    $image,
                    null,
                    88
                );

            $webp =
                ob_get_clean();

            if (
                ! $success
                || ! is_string($webp)
                || $webp === ''
            ) {
                return null;
            }

            $hash =
                substr(
                    sha1($webp),
                    0,
                    12
                );

            $path =
                'virtual-garages/'
                .$item
                    ->virtual_garage_id
                .'/items/manual-'
                .$item->getKey()
                .'-rendered-v'
                .self::VERSION
                .'-'
                .$hash
                .'.webp';

            $disk =
                Storage::disk(
                    $photo->disk
                );

            $aiData =
                is_array(
                    $item->ai_data
                )
                    ? $item->ai_data
                    : [];

            $oldFile =
                $aiData[
                    'manual_crop_file'
                ] ?? null;

            $disk->put(
                $path,
                $webp
            );

            if (
                is_array($oldFile)
                && filled(
                    $oldFile['disk']
                    ?? null
                )
                && filled(
                    $oldFile['path']
                    ?? null
                )
                && $oldFile['path']
                    !== $path
            ) {
                try {
                    Storage::disk(
                        (string)
                        $oldFile['disk']
                    )->delete(
                        (string)
                        $oldFile['path']
                    );
                } catch (Throwable) {
                    /*
                     * Cleanup must not
                     * break saving.
                     */
                }
            }

            $aiData[
                'manual_crop_file'
            ] = [
                'disk' =>
                    $photo->disk,

                'path' =>
                    $path,

                'aspect_ratio' =>
                    '4:3',

                'width' =>
                    $width,

                'height' =>
                    $height,

                'version' =>
                    self::VERSION,
            ];

            $item->forceFill([
                'ai_data' =>
                    $aiData,
            ])->save();

            return $aiData[
                'manual_crop_file'
            ];
        } finally {
            imagedestroy(
                $image
            );
        }
    }

    public function clear(
        VirtualGarageItem $item
    ): void {
        $aiData =
            is_array(
                $item->ai_data
            )
                ? $item->ai_data
                : [];

        $file =
            $aiData[
                'manual_crop_file'
            ] ?? null;

        if (
            is_array($file)
            && filled(
                $file['disk']
                ?? null
            )
            && filled(
                $file['path']
                ?? null
            )
        ) {
            try {
                Storage::disk(
                    (string)
                    $file['disk']
                )->delete(
                    (string)
                    $file['path']
                );
            } catch (Throwable) {
                /*
                 * Cleanup failure should not
                 * prevent reset.
                 */
            }
        }

        unset(
            $aiData['manual_crop'],
            $aiData['manual_crop_file']
        );

        $item->forceFill([
            'ai_data' =>
                $aiData,
        ])->save();
    }

    private function baseCropSize(
        int $sourceWidth,
        int $sourceHeight
    ): array {
        $sourceRatio =
            $sourceWidth
            / $sourceHeight;

        if (
            $sourceRatio
            >= self::TARGET_RATIO
        ) {
            $height =
                $sourceHeight;

            $width =
                (int) round(
                    $height
                    * self::TARGET_RATIO
                );
        } else {
            $width =
                $sourceWidth;

            $height =
                (int) round(
                    $width
                    / self::TARGET_RATIO
                );
        }

        return [
            $width,
            $height,
        ];
    }

    public function normaliseCropState(
        mixed $crop
    ): ?array {
        if (! is_array($crop)) {
            return null;
        }

        foreach (
            [
                'center_x',
                'center_y',
                'zoom',
            ]
            as $key
        ) {
            if (
                ! isset($crop[$key])
                || ! is_numeric(
                    $crop[$key]
                )
            ) {
                return null;
            }
        }

        $rotation =
            isset($crop['rotation'])
            && is_numeric(
                $crop['rotation']
            )
                ? (float)
                    $crop['rotation']
                : 0.0;

        return [
            'center_x' =>
                round(
                    max(
                        0,
                        min(
                            1,
                            (float)
                            $crop['center_x']
                        )
                    ),
                    5
                ),

            'center_y' =>
                round(
                    max(
                        0,
                        min(
                            1,
                            (float)
                            $crop['center_y']
                        )
                    ),
                    5
                ),

            'zoom' =>
                round(
                    max(
                        self::MIN_ZOOM,
                        min(
                            self::MAX_ZOOM,
                            (float)
                            $crop['zoom']
                        )
                    ),
                    3
                ),

            'rotation' =>
                round(
                    max(
                        self::MIN_ROTATION,
                        min(
                            self::MAX_ROTATION,
                            $rotation
                        )
                    ),
                    2
                ),
        ];
    }
}
