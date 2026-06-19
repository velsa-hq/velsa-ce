<?php

namespace Database\Seeders;

use App\Models\Space;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Demo-fixture permanent features (walls, columns, doors) on named spaces
 * so the diagram editor reads as a real room. Coordinates assume the
 * editor default 1000x600 stage at 10 px/foot. Keyed on name, no-ops when
 * a space is missing. Idempotent: skips spaces that already have constraints.
 */
class SpaceConstraintsSeeder extends Seeder
{
    public function run(): void
    {
        $applied = 0;

        foreach ($this->plan() as $spaceName => $constraints) {
            $space = Space::query()
                ->where('name', $spaceName)
                ->whereNull('constraints_json')
                ->first();
            if ($space === null) {
                continue;
            }

            $space->update([
                'constraints_json' => $this->withIds($constraints),
            ]);
            $applied++;
        }

        $this->command->info("SpaceConstraintsSeeder: applied to {$applied} spaces.");
    }

    /**
     * @param  list<array<string,mixed>>  $constraints
     * @return list<array<string,mixed>>
     */
    protected function withIds(array $constraints): array
    {
        return array_map(function (array $c, int $i) {
            $c['id'] = 'seed_con_'.$i.'_'.Str::random(6);

            return $c;
        }, $constraints, array_keys($constraints));
    }

    /**
     * @return array<string, list<array<string,mixed>>>
     */
    protected function plan(): array
    {
        return [
            // perimeter walls inset 20 px + interior columns on a 24 ft grid
            'Coral Reef Grand Ballroom + Exhibit Hall A' => [
                ['kind' => 'wall', 'x' => 500, 'y' => 30, 'width_ft' => 96, 'height_ft' => 0.6, 'rotation' => 0, 'label' => 'North wall'],
                ['kind' => 'wall', 'x' => 500, 'y' => 570, 'width_ft' => 96, 'height_ft' => 0.6, 'rotation' => 0, 'label' => 'South wall'],
                ['kind' => 'wall', 'x' => 30, 'y' => 300, 'width_ft' => 54, 'height_ft' => 0.6, 'rotation' => 90, 'label' => 'West wall'],
                ['kind' => 'wall', 'x' => 970, 'y' => 300, 'width_ft' => 54, 'height_ft' => 0.6, 'rotation' => 90, 'label' => 'East wall'],
                ['kind' => 'door', 'x' => 470, 'y' => 570, 'width_ft' => 6, 'height_ft' => 0.6, 'rotation' => 0, 'label' => 'Lobby entrance'],
                ['kind' => 'door', 'x' => 530, 'y' => 570, 'width_ft' => 6, 'height_ft' => 0.6, 'rotation' => 0],
                ['kind' => 'column', 'x' => 310, 'y' => 230, 'width_ft' => 2, 'height_ft' => 2],
                ['kind' => 'column', 'x' => 690, 'y' => 230, 'width_ft' => 2, 'height_ft' => 2],
                ['kind' => 'column', 'x' => 310, 'y' => 410, 'width_ft' => 2, 'height_ft' => 2],
                ['kind' => 'column', 'x' => 690, 'y' => 410, 'width_ft' => 2, 'height_ft' => 2],
                ['kind' => 'outlet', 'x' => 950, 'y' => 220, 'width_ft' => 0.6, 'height_ft' => 0.6],
                ['kind' => 'outlet', 'x' => 950, 'y' => 380, 'width_ft' => 0.6, 'height_ft' => 0.6],
            ],

            // stage apron + side egress doors
            'Main Theater' => [
                ['kind' => 'wall', 'x' => 500, 'y' => 220, 'width_ft' => 60, 'height_ft' => 0.8, 'rotation' => 0, 'label' => 'Stage apron'],
                ['kind' => 'wall', 'x' => 50, 'y' => 420, 'width_ft' => 40, 'height_ft' => 0.6, 'rotation' => 90],
                ['kind' => 'wall', 'x' => 950, 'y' => 420, 'width_ft' => 40, 'height_ft' => 0.6, 'rotation' => 90],
                ['kind' => 'door', 'x' => 80, 'y' => 540, 'width_ft' => 4, 'height_ft' => 0.6, 'rotation' => 0, 'label' => 'Exit'],
                ['kind' => 'door', 'x' => 920, 'y' => 540, 'width_ft' => 4, 'height_ft' => 0.6, 'rotation' => 0, 'label' => 'Exit'],
            ],

            // bench dividers + court-side outlets
            'Sentinel Bay Arena' => [
                ['kind' => 'wall', 'x' => 200, 'y' => 540, 'width_ft' => 28, 'height_ft' => 0.4, 'rotation' => 0, 'label' => 'Team A bench'],
                ['kind' => 'wall', 'x' => 800, 'y' => 540, 'width_ft' => 28, 'height_ft' => 0.4, 'rotation' => 0, 'label' => 'Team B bench'],
                ['kind' => 'outlet', 'x' => 500, 'y' => 540, 'width_ft' => 0.6, 'height_ft' => 0.6, 'label' => 'Press'],
            ],

            // vendor-bay perimeter columns
            'Indoor Expo Hall' => [
                ['kind' => 'column', 'x' => 200, 'y' => 200, 'width_ft' => 2, 'height_ft' => 2],
                ['kind' => 'column', 'x' => 500, 'y' => 200, 'width_ft' => 2, 'height_ft' => 2],
                ['kind' => 'column', 'x' => 800, 'y' => 200, 'width_ft' => 2, 'height_ft' => 2],
                ['kind' => 'column', 'x' => 200, 'y' => 400, 'width_ft' => 2, 'height_ft' => 2],
                ['kind' => 'column', 'x' => 500, 'y' => 400, 'width_ft' => 2, 'height_ft' => 2],
                ['kind' => 'column', 'x' => 800, 'y' => 400, 'width_ft' => 2, 'height_ft' => 2],
                ['kind' => 'door', 'x' => 940, 'y' => 300, 'width_ft' => 12, 'height_ft' => 0.6, 'rotation' => 90, 'label' => 'Load-in'],
            ],
        ];
    }
}
