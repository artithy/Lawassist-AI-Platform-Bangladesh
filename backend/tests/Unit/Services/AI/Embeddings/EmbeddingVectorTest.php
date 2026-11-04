<?php

namespace Tests\Unit\Services\AI\Embeddings;

use App\Services\AI\Embeddings\EmbeddingInvalidResponse;
use App\Services\AI\Embeddings\EmbeddingVector;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The numeric checks and the scaling to length 1. Plain PHPUnit: no application needed.
 *
 * Failure messages here must never print a whole vector, so comparisons are made on single numbers and on counts.
 */
class EmbeddingVectorTest extends TestCase
{
    /** @return list<float> */
    private function sample(int $size, int $seed = 1): array
    {
        $values = [];

        for ($i = 0; $i < $size; $i++) {
            $values[] = sin($i * 0.37 + $seed) * 0.5 + 0.01; // some negative, some positive, none zero
        }

        return $values;
    }

    // ---- validated -------------------------------------------------------------------------------------------

    public function test_a_good_vector_is_returned_as_a_list_of_floats(): void
    {
        $result = EmbeddingVector::validated([1, 2.5, -3, 0, 4], 5);

        $this->assertCount(5, $result);
        $this->assertSame([1.0, 2.5, -3.0, 0.0, 4.0], $result);
        $this->assertContainsOnlyFloat($result);
    }

    public function test_a_full_size_vector_passes(): void
    {
        $this->assertCount(1536, EmbeddingVector::validated($this->sample(1536), 1536));
    }

    /** @return array<string, array{mixed, string}> */
    public static function badVectors(): array
    {
        return [
            'null' => [null, 'missing_embedding'],
            'a string' => ['0.1,0.2', 'missing_embedding'],
            'a number' => [0.5, 'missing_embedding'],
            'an object with names' => [['a' => 1.0, 'b' => 2.0, 'c' => 3.0], 'not_a_list'],
            'keys not starting at 0' => [[1 => 1.0, 2 => 2.0, 3 => 3.0], 'not_a_list'],
            'too few values' => [[1.0, 2.0], 'dimension_mismatch'],
            'too many values' => [[1.0, 2.0, 3.0, 4.0], 'dimension_mismatch'],
            'empty' => [[], 'dimension_mismatch'],
            'a numeric string' => [[1.0, '2.0', 3.0], 'non_numeric_value'],
            'a null' => [[1.0, null, 3.0], 'non_numeric_value'],
            'a boolean' => [[1.0, true, 3.0], 'non_numeric_value'],
            'a nested list' => [[1.0, [2.0], 3.0], 'non_numeric_value'],
            'an object' => [[1.0, (object) ['x' => 1], 3.0], 'non_numeric_value'],
            'infinity' => [[1.0, INF, 3.0], 'non_finite_value'],
            'minus infinity' => [[1.0, -INF, 3.0], 'non_finite_value'],
            'not a number' => [[1.0, NAN, 3.0], 'non_finite_value'],
            'all zeros (int)' => [[0, 0, 0], 'zero_vector'],
            'all zeros (float)' => [[0.0, 0.0, -0.0], 'zero_vector'],
        ];
    }

    #[DataProvider('badVectors')]
    public function test_a_bad_vector_is_refused_with_a_reason_and_no_content(mixed $values, string $reason): void
    {
        try {
            EmbeddingVector::validated($values, 3);
            $this->fail('A bad vector was accepted.');
        } catch (EmbeddingInvalidResponse $e) {
            $this->assertSame($reason, $e->reason);
            $this->assertSame('The embedding service returned an unusable response.', $e->getMessage());
        }
    }

    public function test_json_that_decodes_to_infinity_is_caught(): void
    {
        $decoded = json_decode('{"values":[0.5,1e999,0.25]}', true);

        $this->expectException(EmbeddingInvalidResponse::class);
        EmbeddingVector::validated($decoded['values'], 3);
    }

    // ---- normalized ------------------------------------------------------------------------------------------

    public function test_the_classic_3_4_triangle_scales_to_0_6_and_0_8(): void
    {
        $result = EmbeddingVector::normalized([3, 4]);

        $this->assertEqualsWithDelta(0.6, $result[0], 1e-15);
        $this->assertEqualsWithDelta(0.8, $result[1], 1e-15);
    }

    public function test_signs_and_the_direction_are_kept(): void
    {
        $result = EmbeddingVector::normalized([-3.0, 0.0, 4.0]);

        $this->assertEqualsWithDelta(-0.6, $result[0], 1e-15);
        $this->assertSame(0.0, $result[1]);
        $this->assertEqualsWithDelta(0.8, $result[2], 1e-15);
    }

    #[DataProvider('sizes')]
    public function test_a_normalized_vector_has_length_one_at_every_size(int $size): void
    {
        $result = EmbeddingVector::normalized($this->sample($size, 3));

        $this->assertCount($size, $result);
        $this->assertEqualsWithDelta(1.0, EmbeddingVector::norm($result), 1e-12);
        $this->assertTrue(EmbeddingVector::isUnitLength($result, 1e-12));
    }

    /** @return array<string, array{int}> */
    public static function sizes(): array
    {
        return ['128' => [128], '768' => [768], '1536' => [1536], '3072' => [3072]];
    }

    public function test_normalizing_twice_changes_nothing_visible(): void
    {
        $once = EmbeddingVector::normalized($this->sample(256));
        $twice = EmbeddingVector::normalized($once);

        foreach ($once as $i => $value) {
            $this->assertEqualsWithDelta($value, $twice[$i], 1e-15);
        }
    }

    public function test_the_proportions_between_values_are_preserved(): void
    {
        $raw = $this->sample(64);
        $scaled = EmbeddingVector::normalized($raw);
        $factor = $scaled[0] / $raw[0];

        foreach ($raw as $i => $value) {
            $this->assertEqualsWithDelta($value * $factor, $scaled[$i], 1e-12);
        }
    }

    public function test_huge_values_do_not_overflow(): void
    {
        $result = EmbeddingVector::normalized(array_fill(0, 1536, 1.0e308));

        $this->assertEqualsWithDelta(1 / sqrt(1536), $result[0], 1e-12);
        $this->assertEqualsWithDelta(1.0, EmbeddingVector::norm($result), 1e-12);
    }

    public function test_tiny_values_do_not_underflow(): void
    {
        $result = EmbeddingVector::normalized(array_fill(0, 1536, 1.0e-300));

        $this->assertEqualsWithDelta(1 / sqrt(1536), $result[0], 1e-12);
        $this->assertEqualsWithDelta(1.0, EmbeddingVector::norm($result), 1e-12);
    }

    public function test_one_value_among_zeros(): void
    {
        $this->assertSame([0.0, -1.0, 0.0], EmbeddingVector::normalized([0.0, -7.0, 0.0]));
    }

    /** @return array<string, array{array<mixed>}> */
    public static function unscalable(): array
    {
        return [
            'empty' => [[]],
            'all zeros' => [[0.0, 0.0, 0.0]],
            'infinity' => [[1.0, INF]],
            'not a number' => [[1.0, NAN]],
        ];
    }

    #[DataProvider('unscalable')]
    public function test_a_vector_that_can_not_be_scaled_is_refused(array $vector): void
    {
        $this->expectException(InvalidArgumentException::class);
        EmbeddingVector::normalized($vector);
    }

    public function test_the_input_is_not_modified(): void
    {
        $raw = [3.0, 4.0];
        EmbeddingVector::normalized($raw);

        $this->assertSame([3.0, 4.0], $raw);
    }

    // ---- norm, unit length, similarity -----------------------------------------------------------------------

    public function test_the_norm(): void
    {
        $this->assertEqualsWithDelta(5.0, EmbeddingVector::norm([3, 4]), 1e-15);
        $this->assertSame(0.0, EmbeddingVector::norm([]));
        $this->assertSame(0.0, EmbeddingVector::norm([0.0, 0.0]));
    }

    public function test_unit_length_detection_respects_the_tolerance(): void
    {
        $this->assertTrue(EmbeddingVector::isUnitLength([0.6, 0.8]));
        $this->assertFalse(EmbeddingVector::isUnitLength([0.6, 0.81]));
        $this->assertTrue(EmbeddingVector::isUnitLength([0.6, 0.8001], 1e-3));
        $this->assertFalse(EmbeddingVector::isUnitLength([]));
    }

    public function test_cosine_similarity_of_known_vectors(): void
    {
        $this->assertEqualsWithDelta(1.0, EmbeddingVector::cosineSimilarity([1, 0], [5, 0]), 1e-12);
        $this->assertEqualsWithDelta(0.0, EmbeddingVector::cosineSimilarity([1, 0], [0, 3]), 1e-12);
        $this->assertEqualsWithDelta(-1.0, EmbeddingVector::cosineSimilarity([1, 2], [-2, -4]), 1e-12);
        $this->assertEqualsWithDelta(0.5, EmbeddingVector::cosineSimilarity([1, 0], [1, sqrt(3)]), 1e-12);
    }

    public function test_cosine_similarity_of_a_vector_with_itself_is_one(): void
    {
        $vector = $this->sample(1536);

        $this->assertEqualsWithDelta(1.0, EmbeddingVector::cosineSimilarity($vector, $vector), 1e-12);
    }

    public function test_cosine_similarity_of_vectors_of_different_sizes_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        EmbeddingVector::cosineSimilarity([1, 2], [1, 2, 3]);
    }

    public function test_cosine_similarity_never_leaves_minus_one_to_one(): void
    {
        $vector = $this->sample(3072, 9);
        $similarity = EmbeddingVector::cosineSimilarity($vector, $vector);

        $this->assertLessThanOrEqual(1.0, $similarity);
        $this->assertGreaterThanOrEqual(-1.0, $similarity);
    }
}
