<?php

namespace App\Chat\Tools\Concerns;

use App\Services\MasterDataService;
use InvalidArgumentException;

/**
 * Turns the LLM's free-text category/location ("laptops", "L2") into IDs. An unknown
 * name is an invalid_argument that lists the valid ones, so the LLM retries instead of
 * concluding "there are none" (spike lesson #15).
 *
 * @property-read MasterDataService $master
 */
trait FiltersByMasterData
{
    /** @return list<int>|null null when no filter was asked for */
    protected function categoryFilter(?string $term): ?array
    {
        if ($term === null) {
            return null;
        }

        return $this->master->categoryIds($term)
            ?: throw new InvalidArgumentException("Unknown category \"{$term}\". Categories: ".$this->master->categoryLabels().'.');
    }

    /** @return list<int>|null */
    protected function locationFilter(?string $term): ?array
    {
        if ($term === null) {
            return null;
        }

        return $this->master->locationIds($term)
            ?: throw new InvalidArgumentException("Unknown location \"{$term}\". Locations: ".$this->master->locationLabels().'.');
    }

    /** Valid values for the schema description, so the LLM can pick an exact one. */
    protected function categoryHint(): string
    {
        return 'Category code or name, e.g. one of: '.$this->master->categoryLabels().'.';
    }

    protected function locationHint(): string
    {
        return 'Location code or name (partial is fine, e.g. "L2" for every Head Office L2 room). Locations: '.$this->master->locationLabels().'.';
    }
}
