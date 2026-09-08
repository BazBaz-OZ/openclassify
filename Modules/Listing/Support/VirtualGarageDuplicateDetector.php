<?php

declare(strict_types=1);

namespace Modules\Listing\Support;

use Illuminate\Support\Str;
use Modules\Listing\Models\VirtualGarageItem;

class VirtualGarageDuplicateDetector
{
    private const GENERIC_WORDS = [
        'dvd',
        'disc',
        'discs',
        'disk',
        'disks',

        'game',
        'games',
        'case',
        'cases',

        'set',
        'collection',
        'boxed',
        'box',
        'pack',
        'pair',

        'book',
        'paperback',
        'hardcover',

        'nintendo',
        'switch',

        /*
         * Harmless descriptive noise that AI may
         * inconsistently include in otherwise
         * identical titles.
         */
        'new',
        'in',
        'ear',
    ];

    private const NUMBER_WORDS = [
        'one' => '1',
        'two' => '2',
        'three' => '3',
        'four' => '4',
        'five' => '5',
        'six' => '6',
        'seven' => '7',
        'eight' => '8',
        'nine' => '9',
        'ten' => '10',
        'eleven' => '11',
        'twelve' => '12',
    ];

    private const TOKEN_ALIASES = [
        'controllers' => 'controller',
    ];

    public function signature(
        string $title
    ): ?string {
        $tokens =
            $this->normalisedTokens(
                $title
            );

        if ($tokens === null) {
            return null;
        }

        return implode(
            '|',
            $tokens
        );
    }

    public function findStrongMatch(
        int $virtualGarageId,
        ?int $currentPhotoId,
        string $title
    ): ?VirtualGarageItem {
        $candidateTokens =
            $this->normalisedTokens(
                $title
            );

        if ($candidateTokens === null) {
            return null;
        }

        $existingItems =
            VirtualGarageItem::query()
                ->where(
                    'virtual_garage_id',
                    $virtualGarageId
                )
                ->when(
                    $currentPhotoId !== null,
                    fn ($query) =>
                        $query->where(
                            'virtual_garage_photo_id',
                            '!=',
                            $currentPhotoId
                        )
                )
                ->where(
                    'status',
                    '!=',
                    VirtualGarageItem::STATUS_SKIPPED
                )
                ->orderBy('id')
                ->get();

        foreach ($existingItems as $existing) {
            $existingTokens =
                $this->normalisedTokens(
                    $existing->title
                );

            if ($existingTokens === null) {
                continue;
            }

            if (
                $this->isStrongMatch(
                    $candidateTokens,
                    $existingTokens
                )
            ) {
                return $existing;
            }
        }

        return null;
    }

    private function isStrongMatch(
        array $left,
        array $right
    ): bool {
        /*
         * Exact canonical title match is our safest
         * and most common duplicate case.
         */
        if ($left === $right) {
            return true;
        }

        /*
         * Fuzzy matching is deliberately conservative.
         * Very short names such as:
         *
         *   Snipperclips Plus
         *
         * must not get merged with a different edition
         * merely because one word happens to match.
         */
        if (
            count($left) < 4
            || count($right) < 4
        ) {
            return false;
        }

        /*
         * When BOTH AI descriptions contain numbers,
         * differing numbers are significant.
         *
         * This protects:
         *   Season 4 vs Season 5
         *   Toy Story 1-3 vs 1-4
         *   model/version numbers where both are seen.
         *
         * If only one AI result notices a SKU, however,
         * we can still compare the descriptive title.
         */
        $leftNumbers =
            array_values(
                array_filter(
                    $left,
                    static fn (string $token): bool =>
                        ctype_digit(
                            $token
                        )
                )
            );

        $rightNumbers =
            array_values(
                array_filter(
                    $right,
                    static fn (string $token): bool =>
                        ctype_digit(
                            $token
                        )
                )
            );

        if (
            $leftNumbers !== []
            && $rightNumbers !== []
            && $leftNumbers !== $rightNumbers
        ) {
            return false;
        }

        $intersection =
            array_values(
                array_intersect(
                    $left,
                    $right
                )
            );

        $intersectionCount =
            count($intersection);

        $smallerCount =
            min(
                count($left),
                count($right)
            );

        $largerCount =
            max(
                count($left),
                count($right)
            );

        if (
            $smallerCount === 0
            || $largerCount === 0
        ) {
            return false;
        }

        /*
         * Require essentially all of the shorter title
         * plus strong coverage of the longer title.
         *
         * Example:
         *
         * Fienza TONO robe hook chrome
         * Fienza TONO robe hook chrome 85104
         *
         * matches, but unrelated vaguely similar titles
         * should not.
         */
        $shortCoverage =
            $intersectionCount
            / $smallerCount;

        $longCoverage =
            $intersectionCount
            / $largerCount;

        return
            $shortCoverage >= 0.90
            && $longCoverage >= 0.72;
    }

    private function normalisedTokens(
        string $title
    ): ?array {
        $title =
            Str::ascii(
                Str::lower(
                    $title
                )
            );

        $title =
            str_replace(
                ['–', '—'],
                '-',
                $title
            );

        /*
         * Disc count describes packaging rather than
         * identity. AI commonly includes it in one
         * description and omits it in another.
         */
        $title =
            preg_replace(
                '/\b(?:one|two|three|four|five|six|seven|eight|nine|ten|eleven|twelve|\d+)\s*-?\s*discs?\b/',
                ' ',
                $title
            ) ?? $title;

        /*
         * Turn numeric ranges into stable tokens:
         *
         * 1-3 -> 1 2 3
         */
        $title =
            preg_replace_callback(
                '/\b(\d+)\s*-\s*(\d+)\b/',
                function (
                    array $matches
                ): string {
                    $start =
                        (int)
                        $matches[1];

                    $end =
                        (int)
                        $matches[2];

                    if (
                        $end <= $start
                        || ($end - $start) > 10
                    ) {
                        return $matches[0];
                    }

                    return implode(
                        ' ',
                        range(
                            $start,
                            $end
                        )
                    );
                },
                $title
            ) ?? $title;

        $tokens =
            preg_split(
                '/[^a-z0-9]+/',
                $title,
                -1,
                PREG_SPLIT_NO_EMPTY
            );

        if (! is_array($tokens)) {
            return null;
        }

        $normalised = [];

        foreach ($tokens as $token) {
            $token =
                self::NUMBER_WORDS[
                    $token
                ]
                ?? $token;

            $token =
                self::TOKEN_ALIASES[
                    $token
                ]
                ?? $token;

            if (
                in_array(
                    $token,
                    self::GENERIC_WORDS,
                    true
                )
            ) {
                continue;
            }

            $normalised[] =
                $token;
        }

        foreach (
            [
                'unknown',
                'unclear',
                'unidentified',
                'unsure',
            ]
            as $weakWord
        ) {
            if (
                in_array(
                    $weakWord,
                    $normalised,
                    true
                )
            ) {
                return null;
            }
        }

        $normalised =
            array_values(
                array_unique(
                    $normalised
                )
            );

        sort(
            $normalised,
            SORT_STRING
        );

        if (
            count($normalised) < 2
        ) {
            return null;
        }

        return $normalised;
    }
}
