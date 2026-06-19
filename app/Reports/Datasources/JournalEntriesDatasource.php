<?php

namespace App\Reports\Datasources;

use App\Models\JournalEntry;
use App\Reports\DatasourceField;
use App\Reports\ReportDatasource;
use Illuminate\Database\Eloquent\Builder;

class JournalEntriesDatasource extends DatasourceDescriptor
{
    public function key(): ReportDatasource
    {
        return ReportDatasource::JournalEntries;
    }

    public function label(): string
    {
        return 'Journal entries';
    }

    public function query(): Builder
    {
        return JournalEntry::query()
            ->leftJoin('chart_of_accounts', 'chart_of_accounts.id', '=', 'journal_entries.chart_of_account_id')
            ->leftJoin('funds', 'funds.id', '=', 'journal_entries.fund_id')
            ->leftJoin('venues', 'venues.id', '=', 'journal_entries.venue_id')
            ->select('journal_entries.*');
    }

    public function fields(): array
    {
        return [
            'account_code' => new DatasourceField('account_code', 'Account code', 'string', 'journal_entries.account_code'),
            'account_name' => new DatasourceField('account_name', 'Account name', 'string', 'chart_of_accounts.name'),
            'account_type' => new DatasourceField(
                'account_type', 'Account type', 'enum', 'chart_of_accounts.account_type',
                options: [
                    ['value' => 'asset', 'label' => 'Asset'],
                    ['value' => 'liability', 'label' => 'Liability'],
                    ['value' => 'equity', 'label' => 'Equity'],
                    ['value' => 'revenue', 'label' => 'Revenue'],
                    ['value' => 'expense', 'label' => 'Expense'],
                ],
            ),
            'fund_code' => new DatasourceField('fund_code', 'Fund code', 'string', 'journal_entries.fund_code'),
            'fund_name' => new DatasourceField('fund_name', 'Fund name', 'string', 'funds.name'),
            'venue_name' => new DatasourceField('venue_name', 'Venue', 'string', 'venues.name'),
            'description' => new DatasourceField('description', 'Description', 'string', 'journal_entries.description'),
            'debit_cents' => new DatasourceField('debit_cents', 'Debit ($)', 'money', 'journal_entries.debit_cents', aggregatable: true),
            'credit_cents' => new DatasourceField('credit_cents', 'Credit ($)', 'money', 'journal_entries.credit_cents', aggregatable: true),
            'posted_on' => new DatasourceField('posted_on', 'Posted on', 'date', 'journal_entries.posted_on'),
            'source_type' => new DatasourceField('source_type', 'Source', 'string', 'journal_entries.source_type'),
        ];
    }
}
