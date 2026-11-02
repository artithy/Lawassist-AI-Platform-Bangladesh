<?php

namespace App\Models;

use Database\Factories\LegalDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A piece of legal information (an act, a guide, a summary) that the platform shows to everybody.
 * Only admins may create, change or delete documents (enforced by the API layer, not here).
 */
#[Fillable(['title', 'category', 'description', 'content', 'source', 'publication_year'])]
class LegalDocument extends Model
{
    /** @use HasFactory<LegalDocumentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'publication_year' => 'integer',
        ];
    }

    /** Documents of one category, e.g. "Land & Property". */
    public function scopeInCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    /** Words in the title or the description (case-insensitive). */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        $term = trim($term);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $query) use ($term) {
            $query->whereLike('title', "%{$term}%")
                ->orWhereLike('description', "%{$term}%");
        });
    }
}
