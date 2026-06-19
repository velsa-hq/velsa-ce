<?php

namespace App\Services\Import\Importers;

use App\Enums\AccountType;
use App\Models\ChartOfAccount;
use App\Services\Import\AbstractImporter;
use App\Services\Import\ImportField;
use App\Services\Import\ImportRowResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Imports GL accounts into the chart of accounts. A row names its parent by
 * the parent's account code, which may already exist or be defined earlier in
 * the same file; codes seen this run are tracked (fresh instance per run, see
 * ImportRegistry::fresh) so parents-before-children files resolve in one pass
 * and duplicate codes are caught before the unique constraint.
 *
 * Order parents before children (sort by code); a child whose parent appears
 * later fails that row and can be re-imported once the parent exists.
 */
class ChartOfAccountImporter extends AbstractImporter
{
    /** @var array<string, true> account codes validated/created this run */
    private array $seenCodes = [];

    public function key(): string
    {
        return 'chart-of-accounts';
    }

    public function label(): string
    {
        return 'Chart of accounts';
    }

    public function description(): string
    {
        return 'GL account codes journal entries post against, with optional parent rollups. Order parents before children.';
    }

    public function fields(): array
    {
        return [
            new ImportField('code', 'Code', required: true,
                hint: 'GL account code, e.g. "1010". Unique.',
                aliases: ['account code', 'gl code', 'number', 'account number', 'acct']),
            new ImportField('name', 'Name', required: true,
                hint: 'Account name, e.g. "Cash - Operating".',
                aliases: ['account name', 'title']),
            new ImportField('account_type', 'Account type', required: true,
                hint: 'asset, liability, equity, revenue, or expense.',
                aliases: ['type', 'category']),
            new ImportField('account_subtype', 'Subtype',
                hint: 'Free-text grouping, e.g. "cash".',
                aliases: ['subtype', 'sub type', 'class']),
            new ImportField('description', 'Description', aliases: ['notes', 'memo']),
            new ImportField('parent_code', 'Parent code',
                hint: "The parent account's code for rollups (blank = top level).",
                aliases: ['parent', 'parent account', 'rollup', 'parent number']),
            new ImportField('is_postable', 'Postable',
                hint: 'Yes for detail accounts you post to; No for rollup headers. Default Yes.',
                aliases: ['postable', 'detail', 'allow posting']),
        ];
    }

    public function import(array $row, bool $dryRun): ImportRowResult
    {
        $data = [
            'code' => $this->clean($row['code'] ?? null),
            'name' => $this->clean($row['name'] ?? null),
            'account_type' => $this->clean($row['account_type'] ?? null),
            'account_subtype' => $this->clean($row['account_subtype'] ?? null),
            'description' => $this->clean($row['description'] ?? null),
            'parent_code' => $this->clean($row['parent_code'] ?? null),
            'is_postable' => $this->clean($row['is_postable'] ?? null),
        ];

        $validator = Validator::make($data, [
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:120'],
            'account_type' => ['required', 'string'],
            'account_subtype' => ['nullable', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:255'],
            'parent_code' => ['nullable', 'string', 'max:20'],
        ]);

        if ($validator->fails()) {
            return ImportRowResult::failures($this->failuresFrom($validator));
        }

        $type = $this->resolveType($data['account_type']);

        if ($type === null) {
            return ImportRowResult::failure(
                "Unrecognized account type \"{$data['account_type']}\" - expected asset, liability, equity, revenue, or expense.",
                'account_type',
            );
        }

        if ($this->codeExists($data['code'])) {
            return ImportRowResult::failure("Duplicate account code \"{$data['code']}\".", 'code');
        }

        $parentId = null;

        if ($data['parent_code'] !== null) {
            if ($data['parent_code'] === $data['code']) {
                return ImportRowResult::failure('An account cannot be its own parent.', 'parent_code');
            }

            $parent = ChartOfAccount::query()->where('code', $data['parent_code'])->first();

            if ($parent === null && ! isset($this->seenCodes[$data['parent_code']])) {
                return ImportRowResult::failure(
                    "Parent code \"{$data['parent_code']}\" not found - define it earlier in the file or import it first.",
                    'parent_code',
                );
            }

            $parentId = $parent?->getKey();
        }

        if ($dryRun) {
            $this->seenCodes[$data['code']] = true;

            return ImportRowResult::success();
        }

        $account = ChartOfAccount::query()->create([
            'code' => $data['code'],
            'name' => $data['name'],
            'description' => $data['description'],
            'account_type' => $type->value,
            'account_subtype' => $data['account_subtype'],
            'parent_account_id' => $parentId,
            'is_postable' => $this->parseBool($data['is_postable'], default: true),
        ]);

        $this->seenCodes[$data['code']] = true;

        return ImportRowResult::success([$account]);
    }

    /**
     * Referenced (kept on reversal) once it carries journal entries or has
     * child accounts. Imported children are removed first (reversal runs in
     * reverse row order), so a parent only blocks on an outside-the-import child.
     */
    public function isReferenced(Model $model): bool
    {
        return $model instanceof ChartOfAccount
            && ($model->journalEntries()->exists() || $model->children()->exists());
    }

    private function codeExists(string $code): bool
    {
        return isset($this->seenCodes[$code])
            || ChartOfAccount::query()->where('code', $code)->exists();
    }

    private function resolveType(string $value): ?AccountType
    {
        $token = Str::of($value)->lower()->replaceMatches('/[^a-z0-9]+/', '')->value();

        return match (true) {
            in_array($token, ['asset', 'assets'], true) => AccountType::Asset,
            in_array($token, ['liability', 'liabilities', 'liab'], true) => AccountType::Liability,
            in_array($token, ['equity', 'fundbalance', 'netassets'], true) => AccountType::Equity,
            in_array($token, ['revenue', 'revenues', 'income', 'sales'], true) => AccountType::Revenue,
            in_array($token, ['expense', 'expenses', 'cost', 'costs', 'expenditure'], true) => AccountType::Expense,
            default => null,
        };
    }

    private function parseBool(?string $value, bool $default): bool
    {
        if ($value === null) {
            return $default;
        }

        $token = Str::of($value)->lower()->replaceMatches('/[^a-z0-9]+/', '')->value();

        return match (true) {
            in_array($token, ['1', 'true', 'yes', 'y', 't', 'x', 'postable', 'detail'], true) => true,
            in_array($token, ['0', 'false', 'no', 'n', 'f', 'header', 'rollup', 'group'], true) => false,
            default => $default,
        };
    }
}
