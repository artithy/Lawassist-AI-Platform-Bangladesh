<?php

namespace App\Services\AI\Embeddings;

use Illuminate\Container\Attributes\Bind;
use InvalidArgumentException;

/**
 * Turns text into embedding vectors. Code that needs embeddings (the future search over legal documents) depends on this
 * interface and not on Google, so the provider can change without touching that code.
 *
 * Every vector returned has exactly dimensions() numbers, all finite, and unit length (L2 norm 1), so cosine similarity and
 * dot product give the same ranking. Vectors from different models or different sizes can not be compared with each other.
 *
 * The container hands out the Google implementation.
 */
#[Bind(GoogleEmbeddingService::class)]
interface EmbeddingService
{
    /** Name of the model that makes the vectors. Store it next to every stored vector. */
    public function model(): string;

    /** How many numbers each vector has. @throws EmbeddingMisconfigured */
    public function dimensions(): int;

    /**
     * Embeds a text that will be stored and searched (a law, a section, a chunk of a document).
     *
     * @return list<float>
     *
     * @throws InvalidArgumentException when the text is unusable (blank, not UTF-8, absurdly long); nothing is sent
     * @throws EmbeddingException for every failure of the service itself
     */
    public function embedDocument(string $text): array;

    /**
     * Embeds a user's question, to be compared with vectors made by embedDocument().
     *
     * @return list<float>
     *
     * @throws InvalidArgumentException when the text is unusable; nothing is sent
     * @throws EmbeddingException for every failure of the service itself
     */
    public function embedQuery(string $text): array;

    /**
     * Embeds many stored texts, in as few requests as the batch size allows. The result has one vector per text, in the same
     * order. An empty list gives an empty result and sends nothing. If any request fails the exception is thrown and no
     * partial result is returned.
     *
     * @param  list<string>  $texts
     * @return list<list<float>>
     *
     * @throws InvalidArgumentException when $texts is not a list of usable texts; nothing is sent
     * @throws EmbeddingException for every failure of the service itself
     */
    public function embedDocuments(array $texts): array;
}
