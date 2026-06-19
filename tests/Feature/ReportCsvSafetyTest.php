<?php

use App\Support\Csv;

it('neutralizes spreadsheet formula injection in csv cells', function () {
    expect(Csv::cell('=1+1'))->toBe("'=1+1")
        ->and(Csv::cell('+44'))->toBe("'+44")
        ->and(Csv::cell('-2'))->toBe("'-2")
        ->and(Csv::cell('@SUM(A1)'))->toBe("'@SUM(A1)")
        ->and(Csv::cell('Civic Center'))->toBe('Civic Center')
        ->and(Csv::cell(42))->toBe(42)
        ->and(Csv::cell(''))->toBe('');
});
