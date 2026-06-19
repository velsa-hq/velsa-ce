<?php

use App\Enums\TemplateKind;
use App\Models\DocumentTemplate;
use App\Services\Signing\DocuSignSignatureProvider as DS;
use App\Services\SystemSettings\SystemSettings;
use Database\Seeders\ContractsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('builds the full field set for the primary signer', function () {
    $provider = new DS(app(SystemSettings::class));
    $tabs = (fn () => $this->tabsForSigner(1, true))->call($provider);

    expect($tabs->getSignHereTabs())->toHaveCount(1)
        ->and($tabs->getSignHereTabs()[0]->getAnchorString())->toBe(DS::ANCHOR_SIGNATURE)
        ->and($tabs->getInitialHereTabs()[0]->getAnchorString())->toBe(DS::ANCHOR_INITIALS)
        ->and($tabs->getDateSignedTabs()[0]->getAnchorString())->toBe(DS::ANCHOR_DATE)
        ->and($tabs->getCheckboxTabs()[0]->getAnchorString())->toBe(DS::ANCHOR_AGREE)
        ->and($tabs->getCheckboxTabs()[0]->getRequired())->toBe('true');
});

it('gives additional signers only a per-recipient signature anchor', function () {
    $provider = new DS(app(SystemSettings::class));
    $tabs = (fn () => $this->tabsForSigner(2, false))->call($provider);

    expect($tabs->getSignHereTabs())->toHaveCount(1)
        ->and($tabs->getSignHereTabs()[0]->getAnchorString())->toBe('\\s2\\')
        ->and($tabs->getInitialHereTabs())->toBeNull()
        ->and($tabs->getDateSignedTabs())->toBeNull()
        ->and($tabs->getCheckboxTabs())->toBeNull();
});

it('seeds a contract template carrying every provider anchor', function () {
    $this->seed(ContractsSeeder::class);

    $template = DocumentTemplate::query()
        ->where('kind', TemplateKind::Contract->value)
        ->whereNull('venue_id')
        ->first();

    expect($template)->not->toBeNull();
    foreach ([DS::ANCHOR_SIGNATURE, DS::ANCHOR_INITIALS, DS::ANCHOR_DATE, DS::ANCHOR_AGREE] as $anchor) {
        expect($template->body_html)->toContain($anchor);
    }
});
