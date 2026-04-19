<?php

namespace QuillBytes\PayrollEngine\Support;

use Money\Money;

final class TraceMetadata
{
    /**
     * @param  array<string, mixed>  $basis
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public static function line(
        string $source,
        string $appliedRule,
        string $formula,
        array $basis = [],
        array $extra = [],
    ): array {
        $normalizedExtra = self::normalize($extra);

        if (array_key_exists('basis', $normalizedExtra)) {
            $normalizedExtra['declared_basis'] = $normalizedExtra['basis'];
            unset($normalizedExtra['basis']);
        }

        return [
            ...$normalizedExtra,
            'source' => $source,
            'applied_rule' => $appliedRule,
            'formula' => $formula,
            'basis' => self::normalize($basis),
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    public static function normalize(array $metadata): array
    {
        $normalized = [];

        foreach ($metadata as $key => $value) {
            if (! is_string($key) || trim($key) === '') {
                continue;
            }

            $normalized[$key] = self::normalizeValue($value);
        }

        return $normalized;
    }

    private static function normalizeValue(mixed $value): mixed
    {
        if ($value instanceof Money) {
            return MoneyHelper::toFloat($value);
        }

        if (is_array($value)) {
            $normalized = [];

            foreach ($value as $key => $item) {
                $normalized[$key] = self::normalizeValue($item);
            }

            return $normalized;
        }

        return $value;
    }
}
