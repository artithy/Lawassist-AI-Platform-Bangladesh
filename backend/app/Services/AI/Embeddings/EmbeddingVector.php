<?php

namespace App\Services\AI\Embeddings;

use InvalidArgumentException;
use SensitiveParameter;

/**
 * Checks and normalizes embedding vectors. Pure functions: no state, no configuration, nothing logged.
 *
 * A vector from Google is trusted only after validated(): a plain list of exactly the expected number of finite numbers that
 * are not all zero. Only the 3072-number size comes back from Google already scaled to length 1; every other size must be
 * scaled by us, and we scale every vector, so the result always has unit length (L2 norm 1).
 *
 * The length is computed relative to the largest value, so very large or very small numbers can not overflow or underflow.
 */
final class EmbeddingVector
{
    /**
     * Returns the values as a list of floats, or throws EmbeddingInvalidResponse saying what is wrong (never the values).
     * $values comes straight from Google's answer and is marked #[SensitiveParameter], so a stack trace never shows a
     * fragment of the answer (a vector can also reveal what the private text it stands for was about).
     *
     * @return list<float>
     *
     * @throws EmbeddingInvalidResponse
     */
    public static function validated(#[SensitiveParameter] mixed $values, int $dimensions): array
    {
        if (! is_array($values)) {
            throw new EmbeddingInvalidResponse('missing_embedding');
        }

        if (! array_is_list($values)) {
            throw new EmbeddingInvalidResponse('not_a_list');
        }

        if (count($values) !== $dimensions) {
            throw new EmbeddingInvalidResponse('dimension_mismatch');
        }

        $clean = [];
        $hasNonZero = false;

        foreach ($values as $value) {
            // Strict on purpose: a JSON string such as "0.12", a boolean or null is not a number.
            if (! is_int($value) && ! is_float($value)) {
                throw new EmbeddingInvalidResponse('non_numeric_value');
            }

            $number = (float) $value;

            if (! is_finite($number)) { // JSON such as 1e999 decodes to INF
                throw new EmbeddingInvalidResponse('non_finite_value');
            }

            $hasNonZero = $hasNonZero || $number !== 0.0;
            $clean[] = $number;
        }

        if (! $hasNonZero) {
            throw new EmbeddingInvalidResponse('zero_vector');
        }

        return $clean;
    }

    /**
     * The same direction with length 1.
     *
     * @param  list<float|int>  $vector  non-empty, finite, not all zero
     * @return list<float>
     *
     * @throws InvalidArgumentException when the vector can not be scaled
     */
    public static function normalized(array $vector): array
    {
        $largest = self::largest($vector);

        if ($largest === 0.0) {
            throw new InvalidArgumentException('A vector that is empty or all zeros can not be normalized.');
        }

        $sum = 0.0;
        $scaled = [];

        foreach ($vector as $value) {
            $ratio = (float) $value / $largest; // between -1 and 1, so the square below can not overflow
            $scaled[] = $ratio;
            $sum += $ratio * $ratio;
        }

        $length = sqrt($sum); // between 1 and sqrt(count)

        return array_map(static fn (float $ratio): float => $ratio / $length, $scaled);
    }

    /** Euclidean length (L2 norm). 0.0 for an empty or all-zero vector. @param list<float|int> $vector */
    public static function norm(array $vector): float
    {
        $largest = self::largest($vector);

        if ($largest === 0.0) {
            return 0.0;
        }

        $sum = 0.0;

        foreach ($vector as $value) {
            $ratio = (float) $value / $largest;
            $sum += $ratio * $ratio;
        }

        return $largest * sqrt($sum);
    }

    /** True when the length is 1 within $tolerance. @param list<float|int> $vector */
    public static function isUnitLength(array $vector, float $tolerance = 1e-6): bool
    {
        return abs(self::norm($vector) - 1.0) <= $tolerance;
    }

    /**
     * Cosine similarity, from -1 to 1 (higher means more alike). Works for vectors of any length, not only unit ones.
     *
     * @param  list<float|int>  $a
     * @param  list<float|int>  $b
     *
     * @throws InvalidArgumentException when the sizes differ or a vector can not be scaled
     */
    public static function cosineSimilarity(array $a, array $b): float
    {
        if (count($a) !== count($b)) {
            throw new InvalidArgumentException('Vectors of different sizes can not be compared.');
        }

        $a = self::normalized($a);
        $b = self::normalized($b);
        $dot = 0.0;

        foreach ($a as $position => $value) {
            $dot += $value * $b[$position];
        }

        return max(-1.0, min(1.0, $dot));
    }

    /** @param list<float|int> $vector */
    private static function largest(array $vector): float
    {
        $largest = 0.0;

        foreach ($vector as $value) {
            $magnitude = abs((float) $value);

            if (! is_finite($magnitude)) {
                throw new InvalidArgumentException('A vector with a value that is not finite can not be used.');
            }

            $largest = max($largest, $magnitude);
        }

        return $largest;
    }
}
