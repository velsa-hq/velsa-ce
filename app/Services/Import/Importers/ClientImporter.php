<?php

namespace App\Services\Import\Importers;

use App\Enums\ClientType;
use App\Models\Client;
use App\Models\Contact;
use App\Services\Import\AbstractImporter;
use App\Services\Import\ImportField;
use App\Services\Import\ImportRowResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Imports clients and, when supplied, their primary contact.
 */
class ClientImporter extends AbstractImporter
{
    public function key(): string
    {
        return 'clients';
    }

    public function label(): string
    {
        return 'Clients';
    }

    public function description(): string
    {
        return 'Organizations and individuals you do business with, with an optional primary contact.';
    }

    public function fields(): array
    {
        return [
            new ImportField('name', 'Name', required: true,
                hint: 'Organization or person name.',
                aliases: ['client', 'organization', 'org', 'company', 'account']),
            new ImportField('type', 'Type',
                hint: 'individual, business, government, nonprofit, or educational.',
                aliases: ['category', 'kind']),
            new ImportField('industry', 'Industry',
                hint: 'Free-text sector, e.g. "Hospitality".',
                aliases: ['sector', 'vertical']),
            new ImportField('source', 'Source',
                hint: 'Where this client came from, e.g. "Referral".',
                aliases: ['origin', 'lead source']),
            new ImportField('notes', 'Notes', aliases: ['comment', 'comments', 'description']),
            new ImportField('primary_contact_name', 'Primary contact name',
                hint: 'Creates a primary contact when a name or email is given.',
                aliases: ['contact', 'contact name', 'attention']),
            new ImportField('primary_contact_email', 'Primary contact email',
                aliases: ['email', 'e-mail', 'contact email']),
            new ImportField('primary_contact_phone', 'Primary contact phone',
                aliases: ['phone', 'telephone', 'tel', 'contact phone']),
        ];
    }

    public function import(array $row, bool $dryRun): ImportRowResult
    {
        $data = [
            'name' => $this->clean($row['name'] ?? null),
            'type' => $this->clean($row['type'] ?? null),
            'industry' => $this->clean($row['industry'] ?? null),
            'source' => $this->clean($row['source'] ?? null),
            'notes' => $this->clean($row['notes'] ?? null),
            'primary_contact_name' => $this->clean($row['primary_contact_name'] ?? null),
            'primary_contact_email' => $this->clean($row['primary_contact_email'] ?? null),
            'primary_contact_phone' => $this->clean($row['primary_contact_phone'] ?? null),
        ];

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'industry' => ['nullable', 'string', 'max:120'],
            'source' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'primary_contact_name' => ['nullable', 'string', 'max:255'],
            'primary_contact_email' => ['nullable', 'email', 'max:255'],
            'primary_contact_phone' => ['nullable', 'string', 'max:40'],
        ]);

        if ($validator->fails()) {
            return ImportRowResult::failures($this->failuresFrom($validator));
        }

        $type = $this->resolveType($data['type']);

        if ($data['type'] !== null && $type === null) {
            return ImportRowResult::failure(
                "Unrecognized client type \"{$data['type']}\" - expected individual, business, government, nonprofit, or educational.",
                'type',
            );
        }

        if ($dryRun) {
            return ImportRowResult::success();
        }

        $client = Client::query()->create([
            'name' => $data['name'],
            'type' => $type?->value,
            'industry' => $data['industry'],
            'source' => $data['source'],
            'notes' => $data['notes'],
        ]);

        $created = [$client];

        if ($data['primary_contact_name'] !== null || $data['primary_contact_email'] !== null) {
            $contact = $client->contacts()->create([
                'name' => $data['primary_contact_name'] ?? $data['name'],
                'email' => $data['primary_contact_email'],
                'phone' => $data['primary_contact_phone'],
                'is_primary' => true,
            ]);

            $client->update(['primary_contact_id' => $contact->getKey()]);
            $created[] = $contact;
        }

        return ImportRowResult::success($created);
    }

    // a client with a lead or booking is in use; reversal must not delete it.
    // contacts are owned by the client and never block.
    public function isReferenced(Model $model): bool
    {
        return $model instanceof Client
            && ($model->leads()->exists() || $model->bookings()->exists());
    }

    // null for an unrecognized non-empty value; caller treats that as a row error
    private function resolveType(?string $value): ?ClientType
    {
        if ($value === null) {
            return null;
        }

        $token = Str::of($value)->lower()->replaceMatches('/[^a-z0-9]+/', '')->value();

        return match (true) {
            in_array($token, ['individual', 'person', 'personal', 'indiv'], true) => ClientType::Individual,
            in_array($token, ['business', 'corporation', 'corporate', 'company', 'commercial', 'forprofit', 'llc', 'inc'], true) => ClientType::Business,
            in_array($token, ['government', 'govt', 'gov', 'municipal', 'public', 'county', 'city', 'state', 'federal'], true) => ClientType::Government,
            in_array($token, ['nonprofit', 'charity', 'ngo', '501c3'], true) => ClientType::Nonprofit,
            in_array($token, ['educational', 'education', 'school', 'university', 'college', 'academic'], true) => ClientType::Educational,
            default => null,
        };
    }
}
