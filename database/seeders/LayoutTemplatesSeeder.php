<?php

namespace Database\Seeders;

use App\Models\LayoutTemplate;
use Illuminate\Database\Seeder;

/**
 * Global starter layout templates (space_id = null) shown in every
 * venue's editor. Coordinates assume the editor's 1000x600 stage at
 * 10 px/foot; absolute, not parametric. Idempotent.
 */
class LayoutTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        if (LayoutTemplate::query()->whereNull('space_id')->count() > 0) {
            $this->command?->info('LayoutTemplatesSeeder: global templates exist, skipping.');

            return;
        }

        foreach ($this->templates() as $tpl) {
            $seats = collect($tpl['objects'])
                ->sum(fn (array $o) => (int) ($o['props']['seats'] ?? 0));

            LayoutTemplate::query()->create([
                'space_id' => null,
                'name' => $tpl['name'],
                'category' => $tpl['category'],
                'description' => $tpl['description'],
                'objects_json' => $tpl['objects'],
                'object_count' => count($tpl['objects']),
                'seat_count' => $seats,
            ]);
        }

        $this->command?->info('LayoutTemplatesSeeder: created '.count($this->templates()).' global layout templates.');
    }

    /**
     * @return list<array{name:string,category:string,description:string,objects:list<array<string,mixed>>}>
     */
    protected function templates(): array
    {
        return [
            [
                'name' => 'Banquet rounds - 80 guests',
                'category' => 'banquet',
                'description' => '10 rounds of 8 in a 5x2 grid with a 4x8 stage. Fits a small-to-mid ballroom.',
                'objects' => $this->banquetRounds(cols: 5, rows: 2, originX: 220, originY: 220, pitch: 130, withStage: true),
            ],
            [
                'name' => 'Banquet rounds - 240 guests',
                'category' => 'banquet',
                'description' => '30 rounds of 8 in a 6x5 grid with a 4x8 stage. Standard convention-center banquet.',
                'objects' => $this->banquetRounds(cols: 6, rows: 5, originX: 180, originY: 140, pitch: 95, withStage: true),
            ],
            [
                'name' => 'Classroom - 60 students',
                'category' => 'classroom',
                'description' => '6-ft rectangular tables seating 6 each, 10 rows of 1, plus a head table.',
                'objects' => $this->classroom(rows: 10, originX: 500, originY: 200, rowPitch: 38, withHeadTable: true),
            ],
            [
                'name' => 'U-shape boardroom - 20 seats',
                'category' => 'u_shape',
                'description' => 'Rectangular tables along three sides of a U, presenter open-end.',
                'objects' => $this->uShape(originX: 500, originY: 320, span: 380, depth: 260),
            ],
            [
                'name' => 'Trade show - 36 booths (6x6)',
                'category' => 'booth_grid',
                'description' => '10x10 booths in a 6x6 grid with cross-aisles. Good demo of the exhibitor floor.',
                'objects' => $this->boothGrid(cols: 6, rows: 6, originX: 150, originY: 110, pitch: 130),
            ],
            [
                'name' => 'Cocktail reception - 60 guests',
                'category' => 'reception',
                'description' => '12 high-tops scattered around the room - typical pre-event reception layout.',
                'objects' => $this->cocktailScatter(count: 12, centerX: 500, centerY: 300, radius: 220),
            ],
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    protected function banquetRounds(int $cols, int $rows, int $originX, int $originY, int $pitch, bool $withStage): array
    {
        $objects = [];
        $n = 1;
        for ($r = 0; $r < $rows; $r++) {
            for ($c = 0; $c < $cols; $c++) {
                $objects[] = [
                    'id' => 'tpl_round_'.$n,
                    'type' => 'round_table_60',
                    'x' => $originX + $c * $pitch,
                    'y' => $originY + $r * $pitch,
                    'rotation' => 0,
                    'props' => ['seats' => 8],
                ];
                $n++;
            }
        }

        if ($withStage) {
            $objects[] = [
                'id' => 'tpl_stage',
                'type' => 'stage_4x8',
                'x' => 500,
                'y' => 60,
                'rotation' => 0,
                'props' => [],
            ];
        }

        return $objects;
    }

    /**
     * @return list<array<string,mixed>>
     */
    protected function classroom(int $rows, int $originX, int $originY, int $rowPitch, bool $withHeadTable): array
    {
        $objects = [];
        for ($i = 0; $i < $rows; $i++) {
            $objects[] = [
                'id' => 'tpl_row_'.($i + 1),
                'type' => 'rect_table_6',
                'x' => $originX,
                'y' => $originY + $i * $rowPitch,
                'rotation' => 0,
                'props' => ['seats' => 6],
            ];
        }

        if ($withHeadTable) {
            $objects[] = [
                'id' => 'tpl_head',
                'type' => 'rect_table_8',
                'x' => $originX,
                'y' => $originY - 80,
                'rotation' => 0,
                'props' => ['seats' => 8],
            ];
            $objects[] = [
                'id' => 'tpl_stage',
                'type' => 'stage_4x8',
                'x' => $originX,
                'y' => $originY - 150,
                'rotation' => 0,
                'props' => [],
            ];
        }

        return $objects;
    }

    /**
     * @return list<array<string,mixed>>
     */
    protected function uShape(int $originX, int $originY, int $span, int $depth): array
    {
        $objects = [];
        // head: 3 rect_table_8 along the top
        for ($i = -1; $i <= 1; $i++) {
            $objects[] = [
                'id' => 'tpl_head_'.($i + 2),
                'type' => 'rect_table_8',
                'x' => $originX + $i * 90,
                'y' => $originY - $depth / 2,
                'rotation' => 0,
                'props' => ['seats' => 4],
            ];
        }
        // left leg: 3 rect_table_6 rotated 90 deg
        for ($i = 0; $i < 3; $i++) {
            $objects[] = [
                'id' => 'tpl_left_'.($i + 1),
                'type' => 'rect_table_6',
                'x' => $originX - $span / 2,
                'y' => $originY - $depth / 2 + 80 + $i * 70,
                'rotation' => 90,
                'props' => ['seats' => 4],
            ];
        }
        // right leg: 3 rect_table_6 rotated 90 deg
        for ($i = 0; $i < 3; $i++) {
            $objects[] = [
                'id' => 'tpl_right_'.($i + 1),
                'type' => 'rect_table_6',
                'x' => $originX + $span / 2,
                'y' => $originY - $depth / 2 + 80 + $i * 70,
                'rotation' => 90,
                'props' => ['seats' => 4],
            ];
        }

        return $objects;
    }

    /**
     * @return list<array<string,mixed>>
     */
    protected function boothGrid(int $cols, int $rows, int $originX, int $originY, int $pitch): array
    {
        $objects = [];
        $n = 1;
        for ($r = 0; $r < $rows; $r++) {
            for ($c = 0; $c < $cols; $c++) {
                // cross-aisle gap every 3 cols / 3 rows
                $xOffset = $c >= 3 ? 30 : 0;
                $yOffset = $r >= 3 ? 30 : 0;
                $objects[] = [
                    'id' => 'tpl_booth_'.sprintf('%03d', $n),
                    'type' => 'booth_10x10',
                    'x' => $originX + $c * $pitch + $xOffset,
                    'y' => $originY + $r * $pitch + $yOffset,
                    'rotation' => 0,
                    'props' => ['booth_number' => sprintf('%03d', 100 + $n)],
                ];
                $n++;
            }
        }

        return $objects;
    }

    /**
     * @return list<array<string,mixed>>
     */
    protected function cocktailScatter(int $count, int $centerX, int $centerY, int $radius): array
    {
        $objects = [];
        for ($i = 0; $i < $count; $i++) {
            $angle = ($i / $count) * 2 * M_PI;
            $r = $i % 2 === 0 ? $radius : $radius * 0.6;
            $objects[] = [
                'id' => 'tpl_cocktail_'.($i + 1),
                'type' => 'cocktail',
                'x' => (int) round($centerX + $r * cos($angle)),
                'y' => (int) round($centerY + $r * sin($angle)),
                'rotation' => 0,
                'props' => ['seats' => 4],
            ];
        }

        return $objects;
    }
}
