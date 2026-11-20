<?php

namespace App\Chat\Tools;

use App\Chat\Tools\Concerns\FiltersByMasterData;
use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\User;
use App\Services\AssetService;
use App\Services\MasterDataService;
use Illuminate\Contracts\JsonSchema\JsonSchema;

/** Employees: only assets they currently borrow (AssetService → visibleTo). */
class SearchAssets extends Tool
{
    use FiltersByMasterData;

    public function __construct(User $actor, private AssetService $assets, protected MasterDataService $master)
    {
        parent::__construct($actor);
    }

    public function name(): string
    {
        return 'search_assets';
    }

    public function description(): string
    {
        return 'Search assets by free text (code, name, serial number), category, location and/or status. Returns total and up to 20 rows.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('Free text matched against asset code, name and serial number'),
            'category' => $schema->string()->description($this->categoryHint()),
            'location' => $schema->string()->description($this->locationHint()),
            'status' => $schema->string()->enum(array_column(AssetStatus::cases(), 'value')),
            'limit' => $schema->integer()->min(1)->max(self::MAX_ROWS)->description('Rows to return, default '.self::DEFAULT_ROWS),
        ];
    }

    protected function run(array $arguments): array
    {
        $result = $this->assets->search($this->actor, [
            'q' => $this->string($arguments, 'query'),
            'category_id' => $this->categoryFilter($this->string($arguments, 'category')),
            'location_id' => $this->locationFilter($this->string($arguments, 'location')),
            'status' => $this->oneOf($arguments, 'status', array_column(AssetStatus::cases(), 'value')),
        ], $this->limit($arguments));

        return $this->rows($result['total'], $result['items'], fn (Asset $a) => [
            'code' => $a->code,
            'name' => $a->name,
            'category' => $a->category->name,
            'location' => $a->location->name,
            'status' => $a->status->value,
        ]);
    }
}
