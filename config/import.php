<?php

use App\Services\Import\Importers\BookingImporter;
use App\Services\Import\Importers\ChartOfAccountImporter;
use App\Services\Import\Importers\ClientImporter;

return [

    /*
    |--------------------------------------------------------------------------
    | Registered importers
    |--------------------------------------------------------------------------
    |
    | Each class implements App\Services\Import\Importer and represents one
    | importable "kind". Add a class here to expose a new kind in the UI.
    |
    */

    'importers' => [
        ClientImporter::class,
        ChartOfAccountImporter::class,
        BookingImporter::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Upload constraints
    |--------------------------------------------------------------------------
    */

    // Disk + directory uploaded source files are stored on.
    'disk' => 'local',
    'directory' => 'imports',

    // Max upload size, in kilobytes.
    'max_file_size_kb' => 10240,

    // Number of error rows surfaced in the preview/commit summary (the full
    // set is always downloadable as a CSV).
    'error_sample_limit' => 50,

];
