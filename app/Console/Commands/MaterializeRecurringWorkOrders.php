<?php

namespace App\Console\Commands;

use App\Services\RecurringTemplateMaterializer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('workorders:materialize')]
#[Description('Materialize recurring work-order templates into concrete work orders for the lookahead window.')]
class MaterializeRecurringWorkOrders extends Command
{
    public function handle(RecurringTemplateMaterializer $materializer): int
    {
        $created = $materializer->materializeAll();

        $this->info("Created {$created} work orders from recurring templates.");

        return self::SUCCESS;
    }
}
