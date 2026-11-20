<?php

namespace Database\Seeders;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Enums\Role;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Location;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo org: 3 demo accounts + ~100 users, master data, ~1,000 assets.
 * Loans, movements and maintenance come from HistorySeeder.
 */
class DemoSeeder extends Seeder
{
    /** shortcut: one shared demo password, fine for a non-public demo; change before any public exposure. */
    public const PASSWORD = 'password';

    /** code => [name, useful life, share of assets, brand => models] */
    private const CATEGORIES = [
        'LAP' => ['Laptop', 4, 32, ['Lenovo' => ['ThinkPad T14', 'ThinkPad E14', 'ThinkPad X1 Carbon'], 'Dell' => ['Latitude 5440', 'Latitude 7440'], 'HP' => ['EliteBook 840', 'ProBook 450'], 'Apple' => ['MacBook Air M3', 'MacBook Pro 14']]],
        'MON' => ['Monitor', 5, 20, ['Dell' => ['P2422H', 'U2723QE'], 'LG' => ['27UL500', '24MP400'], 'Samsung' => ['S24C310', 'ViewFinity S6']]],
        'PRJ' => ['Projector', 5, 4, ['Epson' => ['EB-X51', 'EB-E01'], 'BenQ' => ['MW560']]],
        'PRN' => ['Printer', 5, 6, ['HP' => ['LaserJet M211', 'LaserJet Pro 4003'], 'Epson' => ['L3210', 'L5290'], 'Brother' => ['HL-L2375']]],
        'PHN' => ['Phone', 3, 10, ['Samsung' => ['Galaxy A55', 'Galaxy S24'], 'Apple' => ['iPhone 15'], 'Xiaomi' => ['Redmi Note 13']]],
        'TAB' => ['Tablet', 3, 6, ['Apple' => ['iPad 10th Gen', 'iPad Air M2'], 'Samsung' => ['Galaxy Tab S9 FE']]],
        'NET' => ['Network Device', 5, 7, ['Cisco' => ['Catalyst 1000', 'Meraki MR36'], 'MikroTik' => ['hAP ax2', 'CRS326'], 'Ubiquiti' => ['UniFi U6 Lite']]],
        'FUR' => ['Furniture', 8, 15, ['Informa' => ['Office Chair Ergo', 'Standing Desk 120'], 'Chitose' => ['Meeting Chair Yamato'], 'Ikea' => ['Bekant Desk', 'Alex Drawer']]],
    ];

    private const LOCATIONS = [
        'HO-L1-WH' => 'Head Office L1 – Warehouse',
        'HO-L1-LB' => 'Head Office L1 – Lobby',
        'HO-L2-IT' => 'Head Office L2 – IT Room',
        'HO-L2-MR' => 'Head Office L2 – Meeting Room',
        'HO-L3-FN' => 'Head Office L3 – Finance',
        'HO-L3-HR' => 'Head Office L3 – HR',
        'HO-SRV' => 'Head Office – Service Room',
        'BR-SBY' => 'Surabaya Branch',
        'BR-BDG' => 'Bandung Branch',
    ];

    private const ASSET_COUNT = 1000;

    public function run(): void
    {
        $this->seedUsers();

        $locations = collect(self::LOCATIONS)
            ->map(fn (string $name, string $code) => Location::create(['code' => $code, 'name' => $name]))
            ->values();

        $admin = User::where('email', 'admin@demo.test')->firstOrFail();

        foreach (self::CATEGORIES as $code => [$name, $life, $share, $models]) {
            $category = Category::create(['code' => $code, 'name' => $name, 'default_useful_life_years' => $life]);
            $count = intdiv(self::ASSET_COUNT * $share, 100);

            for ($i = 1; $i <= $count; $i++) {
                $brand = fake()->randomElement(array_keys($models));
                $status = fake()->randomElement([...array_fill(0, 95, AssetStatus::Available), ...array_fill(0, 3, AssetStatus::Broken), AssetStatus::Disposed, AssetStatus::Disposed]);

                (new Asset([
                    'name' => $brand.' '.fake()->randomElement($models[$brand]),
                    'condition' => $status === AssetStatus::Broken ? AssetCondition::Poor : fake()->randomElement([AssetCondition::Good, AssetCondition::Good, AssetCondition::Fair]),
                    'brand' => $brand,
                    'serial_number' => $code === 'FUR' ? null : strtoupper(fake()->unique()->bothify('??########')),
                    'purchase_date' => $purchased = fake()->dateTimeBetween('-5 years', '-2 months'),
                    'purchase_price' => $this->price($code),
                    'useful_life_years' => $life,
                ]))->forceFill([
                    'code' => sprintf('%s-%04d', $code, $i),
                    'category_id' => $category->id,
                    'location_id' => $locations->random()->id,
                    'status' => $status,
                    'created_by' => $admin->id,
                    'created_at' => $purchased,
                ])->save();
            }

            // keep the asset-code counter in step with the seeded codes
            $category->forceFill(['last_sequence' => $count])->save();
        }
    }

    private function seedUsers(): void
    {
        $demo = [
            ['Ega Admin', 'admin@demo.test', Role::Admin],
            ['Sari Warehouse', 'staff@demo.test', Role::WarehouseStaff],
            ['Budi Employee', 'employee@demo.test', Role::Employee],
        ];

        foreach ($demo as [$name, $email, $role]) {
            User::factory()->create(['name' => $name, 'email' => $email, 'password' => self::PASSWORD])->assignRole($role);
        }

        User::factory(4)->create()->each->assignRole(Role::WarehouseStaff);
        User::factory(92)->create()->each->assignRole(Role::Employee);
        User::factory(4)->inactive()->create()->each->assignRole(Role::Employee);
    }

    /** Rough Rupiah price per category, rounded to thousands. */
    private function price(string $category): int
    {
        [$min, $max] = match ($category) {
            'LAP' => [9_000, 32_000],
            'MON' => [1_500, 9_000],
            'PRJ' => [5_000, 12_000],
            'PRN' => [2_000, 8_000],
            'PHN' => [3_000, 18_000],
            'TAB' => [4_000, 14_000],
            'NET' => [1_000, 25_000],
            default => [500, 6_000],
        };

        return fake()->numberBetween($min, $max) * 1000;
    }
}
