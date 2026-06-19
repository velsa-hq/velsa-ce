<?php

use App\Models\AuditEvent;
use App\Models\ContractSigner;
use App\Models\ExhibitorPayment;
use App\Models\ExhibitorPermit;
use App\Models\InsuranceCertificate;
use App\Models\RateCard;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('audits creation of finance- and compliance-relevant records', function (string $modelClass, string $prefix) {
    $modelClass::factory()->create();

    expect(
        AuditEvent::query()->where('event_type', "{$prefix}.created")->exists()
    )->toBeTrue();
})->with([
    'exhibitor payment' => [ExhibitorPayment::class, 'exhibitorpayment'],
    'insurance certificate' => [InsuranceCertificate::class, 'insurancecertificate'],
    'rate card' => [RateCard::class, 'ratecard'],
    'exhibitor permit' => [ExhibitorPermit::class, 'exhibitorpermit'],
    'contract signer' => [ContractSigner::class, 'contractsigner'],
]);
