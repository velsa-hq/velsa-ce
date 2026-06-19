<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Journal export delivery
    |--------------------------------------------------------------------------
    |
    | How a rendered Workday export batch is transmitted after it's claimed
    | and rendered. The exporter always produces a downloadable artifact;
    | the transport (if any) delivers it onward.
    |
    |   none  - no automated send. Staff download the file and hand it off
    |           out-of-band; the batch stays "ready" until acknowledged.
    |   email - email the rendered file to the configured recipient.
    |
    | Future transports (sftp, api) slot in behind the same ExportTransport
    | interface, selected here, without touching the exporter or controller.
    |
    */

    'export' => [

        'transport' => env('ACCOUNTING_EXPORT_TRANSPORT', 'none'),

        'email' => [
            'recipient' => env('ACCOUNTING_EXPORT_EMAIL'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Journal posting accounts
    |--------------------------------------------------------------------------
    |
    | GL accounts the invoice flow posts to. Issuance recognizes revenue on
    | the accrual basis: debit A/R, credit the source's revenue account (and
    | sales-tax-payable for the tax portion). Payment then debits cash and
    | credits A/R. The revenue account is chosen per invoice source so
    | exhibitor, venue-rental, and other revenue land in the right line.
    |
    */

    'posting' => [

        'ar_account' => '1100',
        'cash_account' => '1010',
        'tax_account' => '2200',

        'revenue_accounts' => [
            'exhibitor_order' => '4300',
            'booking' => '4100',
            'default' => '4900',
        ],

        // Optional default fund tag for system-posted legs. Per-fund
        // coding (deriving the fund from venue / account / booking kind)
        // is a roadmap item; until then legs are posted untagged unless a
        // default is set here.
        'default_fund' => env('ACCOUNTING_DEFAULT_FUND'),

    ],

];
