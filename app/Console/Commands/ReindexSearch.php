<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\Client;
use App\Models\Contract;
use App\Models\EquipmentItem;
use App\Models\Exhibitor;
use App\Models\Invoice;
use App\Models\Space;
use App\Models\Venue;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;

/**
 * Nightly safety-net reindex of every Scout-searchable model. Scout keeps the
 * search index in sync synchronously on each Eloquent write, so this is not
 * required in steady state - it self-heals any drift from writes that bypass
 * Eloquent events (bulk imports, manual DB fixes, restores).
 *
 * No-op on the `collection` driver (local/dev), which searches the database
 * directly with no separate index.
 */
#[Signature('search:reindex')]
#[Description('Reindex all Scout-searchable models (safety-net against index drift)')]
class ReindexSearch extends Command
{
    /** @var list<class-string<Model>> */
    private const MODELS = [
        Booking::class,
        Client::class,
        Contract::class,
        EquipmentItem::class,
        Exhibitor::class,
        Invoice::class,
        Space::class,
        Venue::class,
    ];

    public function handle(): int
    {
        if (config('scout.driver') === 'collection') {
            $this->info('Scout driver is "collection" (no external index) - nothing to reindex.');

            return self::SUCCESS;
        }

        foreach (self::MODELS as $model) {
            $this->info("Reindexing {$model} ...");
            Artisan::call('scout:import', ['model' => $model], $this->getOutput());
        }

        $this->info('Search reindex complete.');

        return self::SUCCESS;
    }
}
