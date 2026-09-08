<?php

declare(strict_types=1);

namespace Modules\Listing\Support;

use Illuminate\Http\UploadedFile;
use RuntimeException;

class UploadedImageSanitizer
{
    public function sanitize(UploadedFile $file): void
    {
        $path = $file->getRealPath();

        if (! is_string($path) || ! is_file($path)) {
            throw new RuntimeException(
                'Uploaded image could not be accessed.'
            );
        }

        $mime = (string) ($file->getMimeType() ?: '');

        if (! in_array(
            $mime,
            [
                'image/jpeg',
                'image/png',
                'image/webp',
            ],
            true
        )) {
            throw new RuntimeException(
                'Unsupported image type for sanitisation.'
            );
        }

        /*
         * Read JPEG orientation BEFORE re-encoding because EXIF will
         * intentionally disappear from the sanitised image.
         */
        $orientation = 1;

        if (
            $mime === 'image/jpeg'
            && function_exists('exif_read_data')
        ) {
            $exif = @exif_read_data(
                $path,
                'IFD0',
                true,
                false
            );

            $orientation = (int) (
                $exif['IFD0']['Orientation']
                ?? $exif['Orientation']
                ?? 1
            );
        }

        $contents = @file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException(
                'Uploaded image could not be read.'
            );
        }

        $image = @imagecreatefromstring($contents);

        if ($image === false) {
            throw new RuntimeException(
                'Uploaded image could not be decoded.'
            );
        }

        try {
            $image = $this->applyOrientation(
                $image,
                $orientation
            );

            /*
             * Preserve transparency where the source format supports it.
             */
            if (
                $mime === 'image/png'
                || $mime === 'image/webp'
            ) {
                imagealphablending($image, false);
                imagesavealpha($image, true);
            }

            $written = match ($mime) {
                'image/jpeg' => imagejpeg(
                    $image,
                    $path,
                    90
                ),

                'image/png' => imagepng(
                    $image,
                    $path,
                    6
                ),

                'image/webp' => function_exists('imagewebp')
                    ? imagewebp(
                        $image,
                        $path,
                        88
                    )
                    : false,

                default => false,
            };

            if (! $written) {
                throw new RuntimeException(
                    'Unable to write sanitised image.'
                );
            }

            /*
             * Symfony/Laravel may have already stat'ed the temporary
             * upload. Clear it so size information reflects the new file.
             */
            clearstatcache(true, $path);
        } finally {
            if (is_resource($image) || $image instanceof \GdImage) {
                imagedestroy($image);
            }
        }
    }

    private function applyOrientation(
        \GdImage $image,
        int $orientation
    ): \GdImage {
        switch ($orientation) {
            case 2:
                imageflip($image, IMG_FLIP_HORIZONTAL);
                break;

            case 3:
                $image = $this->rotate($image, 180);
                break;

            case 4:
                imageflip($image, IMG_FLIP_VERTICAL);
                break;

            case 5:
                imageflip($image, IMG_FLIP_HORIZONTAL);
                $image = $this->rotate($image, -90);
                break;

            case 6:
                $image = $this->rotate($image, -90);
                break;

            case 7:
                imageflip($image, IMG_FLIP_HORIZONTAL);
                $image = $this->rotate($image, 90);
                break;

            case 8:
                $image = $this->rotate($image, 90);
                break;
        }

        return $image;
    }

    private function rotate(
        \GdImage $image,
        int $degrees
    ): \GdImage {
        $rotated = imagerotate(
            $image,
            $degrees,
            0
        );

        if ($rotated === false) {
            return $image;
        }

        imagedestroy($image);

        return $rotated;
    }
}
