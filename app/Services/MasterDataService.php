<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Location;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Loose lookups of categories and locations by code or name, for chatbot filters.
 * Master data is not row-restricted: every role may know which categories exist.
 */
class MasterDataService
{
    /**
     * IDs of the categories $term names: an exact code/name match wins, otherwise every
     * category whose code or name contains the term (case-insensitive, "Laptops" → "Laptop").
     *
     * @return list<int> empty when nothing matches
     */
    public function categoryIds(string $term): array
    {
        return $this->match(Category::query(), $term);
    }

    /** @return list<int> same rules as categoryIds(), e.g. "L2" matches every Head Office L2 room */
    public function locationIds(string $term): array
    {
        return $this->match(Location::query(), $term);
    }

    /** "Name (CODE)" of every category, comma separated, for tool descriptions and error hints. */
    public function categoryLabels(): string
    {
        return Category::orderBy('name')->get(['code', 'name'])->map(fn (Category $c) => "{$c->name} ({$c->code})")->implode(', ');
    }

    public function locationLabels(): string
    {
        return Location::orderBy('name')->get(['code', 'name'])->map(fn (Location $l) => "{$l->name} ({$l->code})")->implode(', ');
    }

    /**
     * @param  Builder<Category>|Builder<Location>  $query
     * @return list<int>
     */
    private function match(Builder $query, string $term): array
    {
        $term = Str::of($term)->lower()->squish()->value();
        if ($term === '') {
            return [];
        }

        $exact = (clone $query)->where(fn (Builder $w) => $w->whereRaw('lower(code) = ?', [$term])->orWhereRaw('lower(name) = ?', [$term]))->pluck('id');
        if ($exact->isNotEmpty()) {
            return array_values($exact->map(fn ($id) => (int) $id)->all());
        }

        // "laptops" / "monitors" / "switches": also try the singular
        $variants = array_unique([$term, Str::singular($term), preg_replace('/e?s$/', '', $term) ?: $term]);

        return array_values($query->where(function (Builder $w) use ($variants) {
            foreach ($variants as $v) {
                $w->orWhereLike('code', "%{$v}%")->orWhereLike('name', "%{$v}%");
            }
        })->pluck('id')->map(fn ($id) => (int) $id)->all());
    }
}
