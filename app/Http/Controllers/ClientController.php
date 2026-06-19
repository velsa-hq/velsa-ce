<?php

namespace App\Http\Controllers;

use App\Enums\ClientType;
use App\Enums\LeadStage;
use App\Http\Requests\ClientStoreRequest;
use App\Http\Requests\ClientUpdateRequest;
use App\Http\Requests\ContactRequest;
use App\Models\Booking;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Contract;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Client::class);

        $search = $request->string('q')->toString() ?: null;
        $type = $request->string('type')->toString() ?: null;

        $clients = Client::query()
            ->with(['primaryContact:id,name,email,phone', 'leads' => fn ($q) => $q->select('id', 'client_id', 'estimated_value_cents', 'probability', 'stage')])
            ->withCount(['contacts', 'leads'])
            ->when($search, fn ($q, $v) => $q->where('name', 'ilike', "%{$v}%"))
            ->when($type, fn ($q, $v) => $q->where('type', $v))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        $rows = $clients->getCollection()->map(function (Client $client) {
            $openLeads = $client->leads->whereNotIn('stage', [LeadStage::Won, LeadStage::Lost]);

            return [
                'id' => $client->id,
                'name' => $client->name,
                'type' => $client->type?->value,
                'industry' => $client->industry,
                'source' => $client->source,
                'primary_contact' => $client->primaryContact ? [
                    'name' => $client->primaryContact->name,
                    'email' => $client->primaryContact->email,
                    'phone' => $client->primaryContact->phone,
                ] : null,
                'contact_count' => $client->contacts_count,
                'lead_count' => $client->leads_count,
                'open_pipeline_cents' => (int) $openLeads->sum(fn ($l) => (int) round($l->estimated_value_cents * $l->probability)),
            ];
        });

        return Inertia::render('clients/index', [
            'clients' => [
                'data' => $rows,
                'meta' => [
                    'current_page' => $clients->currentPage(),
                    'last_page' => $clients->lastPage(),
                    'per_page' => $clients->perPage(),
                    'total' => $clients->total(),
                ],
                'links' => [
                    'prev' => $clients->previousPageUrl(),
                    'next' => $clients->nextPageUrl(),
                ],
            ],
            'filters' => ['q' => $search, 'type' => $type],
        ]);
    }

    public function show(Client $client): Response
    {
        $this->authorize('view', $client);

        $client->load([
            'primaryContact:id,name,email,phone,role',
            'contacts:id,client_id,name,email,phone,role,is_primary',
        ]);

        $bookings = Booking::query()
            ->where('client_id', $client->id)
            ->with(['venue:id,name,slug'])
            ->orderByDesc('start_at')
            ->limit(50)
            ->get(['id', 'reference', 'name', 'status', 'start_at', 'end_at', 'total_cents', 'venue_id']);

        $leads = $client->leads()
            ->with(['venue:id,name,slug'])
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'stage', 'estimated_value_cents', 'probability', 'expected_close_date', 'venue_id', 'closed_at']);

        $contracts = Contract::query()
            ->whereHas('booking', fn ($q) => $q->where('client_id', $client->id))
            ->with(['booking:id,reference,name'])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get(['id', 'reference', 'status', 'total_cents', 'sent_at', 'signed_at', 'booking_id']);

        return Inertia::render('clients/show', [
            'client' => [
                'id' => $client->id,
                'name' => $client->name,
                'type' => $client->type?->value,
                'industry' => $client->industry,
                'source' => $client->source,
                'notes' => $client->notes,
                'retired_at' => $client->retired_at?->toIso8601String(),
                'primary_contact' => $client->primaryContact ? [
                    'id' => $client->primaryContact->id,
                    'name' => $client->primaryContact->name,
                    'email' => $client->primaryContact->email,
                    'phone' => $client->primaryContact->phone,
                    'role' => $client->primaryContact->role,
                ] : null,
                'contacts' => $client->contacts->map(fn ($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'email' => $c->email,
                    'phone' => $c->phone,
                    'role' => $c->role,
                    'is_primary' => $c->is_primary,
                ])->all(),
            ],
            'bookings' => $bookings->map(fn (Booking $b) => [
                'id' => $b->id,
                'reference' => $b->reference,
                'name' => $b->name,
                'status' => $b->status?->value,
                'start_at' => $b->start_at?->toIso8601String(),
                'end_at' => $b->end_at?->toIso8601String(),
                'total_cents' => $b->total_cents,
                'venue_name' => $b->venue?->name,
                'venue_slug' => $b->venue?->slug,
            ])->all(),
            'leads' => $leads->map(fn ($l) => [
                'id' => $l->id,
                'name' => $l->name,
                'stage' => $l->stage?->value,
                'estimated_value_cents' => $l->estimated_value_cents,
                'probability' => $l->probability,
                'expected_close_date' => $l->expected_close_date?->toDateString(),
                'venue_name' => $l->venue?->name,
                'venue_slug' => $l->venue?->slug,
                'closed_at' => $l->closed_at?->toIso8601String(),
            ])->all(),
            'contracts' => $contracts->map(fn ($c) => [
                'id' => $c->id,
                'reference' => $c->reference,
                'status' => $c->status?->value,
                'total_cents' => $c->total_cents,
                'sent_at' => $c->sent_at?->toIso8601String(),
                'signed_at' => $c->signed_at?->toIso8601String(),
                'booking' => $c->booking ? [
                    'id' => $c->booking->id,
                    'reference' => $c->booking->reference,
                    'name' => $c->booking->name,
                ] : null,
            ])->all(),
            'documents' => $client->documentsForDisplay(),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Client::class);

        return Inertia::render('clients/create', [
            'types' => array_map(fn (ClientType $t) => $t->value, ClientType::cases()),
        ]);
    }

    public function store(ClientStoreRequest $request): RedirectResponse
    {
        $this->authorize('create', Client::class);

        $data = $request->validated();
        $client = Client::query()->create($this->clientAttributes($data));

        $contact = $data['contact'] ?? [];
        if (! empty($contact['name'])) {
            $created = Contact::create([
                'client_id' => $client->id,
                'name' => $contact['name'],
                'role' => $contact['role'] ?? null,
                'email' => $contact['email'] ?? null,
                'phone' => $contact['phone'] ?? null,
                'is_primary' => true,
            ]);
            $client->update(['primary_contact_id' => $created->id]);
        }

        // non-blocking dup-name warning
        $message = "Client {$client->name} created.";
        if (Client::query()->where('name', $client->name)->where('id', '!=', $client->id)->exists()) {
            $message .= ' Heads up: another client with this name already exists.';
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('clients.show', $client);
    }

    public function edit(Client $client): Response
    {
        $this->authorize('update', $client);

        $client->load('contacts:id,client_id,name,role,email,phone,is_primary');

        return Inertia::render('clients/edit', [
            'client' => [
                'id' => $client->id,
                'name' => $client->name,
                'type' => $client->type?->value,
                'industry' => $client->industry,
                'source' => $client->source,
                'notes' => $client->notes,
                'tax_id' => $client->tax_id_encrypted,
                'address' => $client->address_json ?? [],
                'custom_fields' => collect($client->custom_fields_json ?? [])
                    ->map(fn ($value, $key) => ['key' => (string) $key, 'value' => (string) $value])
                    ->values()
                    ->all(),
                'contacts' => $client->contacts->map(fn ($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'role' => $c->role,
                    'email' => $c->email,
                    'phone' => $c->phone,
                    'is_primary' => $c->is_primary,
                ])->all(),
            ],
            'types' => array_map(fn (ClientType $t) => $t->value, ClientType::cases()),
        ]);
    }

    public function update(ClientUpdateRequest $request, Client $client): RedirectResponse
    {
        $this->authorize('update', $client);

        $client->update($this->clientAttributes($request->validated()));

        Inertia::flash('toast', ['type' => 'success', 'message' => "Client {$client->name} updated."]);

        return to_route('clients.show', $client);
    }

    public function destroy(Client $client): RedirectResponse
    {
        $this->authorize('delete', $client);

        $name = $client->name;
        $client->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => "Client {$name} retired."]);

        return to_route('clients.index');
    }

    /** Route binds withTrashed. */
    public function restore(Client $client): RedirectResponse
    {
        $this->authorize('restore', $client);

        $client->restore();

        Inertia::flash('toast', ['type' => 'success', 'message' => "Client {$client->name} restored."]);

        return to_route('clients.show', $client);
    }

    public function archive(Request $request): Response
    {
        $this->authorize('viewAny', Client::class);

        $search = $request->string('q')->toString() ?: null;

        $clients = Client::onlyTrashed()
            ->with('primaryContact:id,name,email')
            ->withCount(['contacts', 'leads', 'bookings'])
            ->when($search, fn ($q, $v) => $q->whereRaw('lower(name) like ?', ['%'.mb_strtolower($v).'%']))
            ->orderByDesc('retired_at')
            ->paginate(25)
            ->withQueryString();

        $rows = $clients->getCollection()->map(fn (Client $client) => [
            'id' => $client->id,
            'name' => $client->name,
            'type' => $client->type?->value,
            'industry' => $client->industry,
            'primary_contact' => $client->primaryContact ? [
                'name' => $client->primaryContact->name,
                'email' => $client->primaryContact->email,
            ] : null,
            'contact_count' => $client->contacts_count,
            'lead_count' => $client->leads_count,
            'booking_count' => $client->bookings_count,
            'retired_at' => $client->retired_at?->toDateString(),
        ]);

        return Inertia::render('clients/archive', [
            'clients' => [
                'data' => $rows,
                'meta' => [
                    'current_page' => $clients->currentPage(),
                    'last_page' => $clients->lastPage(),
                    'total' => $clients->total(),
                ],
                'links' => [
                    'prev' => $clients->previousPageUrl(),
                    'next' => $clients->nextPageUrl(),
                ],
            ],
            'filters' => ['q' => $search],
        ]);
    }

    public function storeContact(ContactRequest $request, Client $client): RedirectResponse
    {
        $this->authorize('update', $client);

        $data = $request->validated();
        $contact = Contact::create([
            'client_id' => $client->id,
            'name' => $data['name'],
            'role' => $data['role'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'is_primary' => false,
        ]);

        // first contact is primary by default
        if (($data['is_primary'] ?? false) || $client->contacts()->count() === 1) {
            $this->makePrimary($client, $contact);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Contact added.']);

        return back();
    }

    public function updateContact(ContactRequest $request, Client $client, Contact $contact): RedirectResponse
    {
        $this->authorize('update', $client);

        $data = $request->validated();
        $contact->update([
            'name' => $data['name'],
            'role' => $data['role'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
        ]);

        if ($data['is_primary'] ?? false) {
            $this->makePrimary($client, $contact);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Contact updated.']);

        return back();
    }

    public function destroyContact(Client $client, Contact $contact): RedirectResponse
    {
        $this->authorize('update', $client);

        if ($client->primary_contact_id === $contact->id) {
            $client->update(['primary_contact_id' => null]);
        }
        $contact->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Contact removed.']);

        return back();
    }

    protected function makePrimary(Client $client, Contact $contact): void
    {
        $client->contacts()->where('id', '!=', $contact->id)->update(['is_primary' => false]);
        $contact->update(['is_primary' => true]);
        $client->update(['primary_contact_id' => $contact->id]);
    }

    /**
     * Shared by store + update; empty address / custom-field sets collapse to null.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function clientAttributes(array $data): array
    {
        $address = array_filter([
            'street' => $data['address']['street'] ?? null,
            'city' => $data['address']['city'] ?? null,
            'state' => $data['address']['state'] ?? null,
            'postal_code' => $data['address']['postal_code'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        $custom = [];
        foreach ($data['custom_fields'] ?? [] as $field) {
            $key = trim((string) ($field['key'] ?? ''));
            if ($key !== '') {
                $custom[$key] = (string) ($field['value'] ?? '');
            }
        }

        return [
            'name' => $data['name'],
            'type' => $data['type'],
            'industry' => $data['industry'] ?? null,
            'source' => $data['source'] ?? null,
            'notes' => $data['notes'] ?? null,
            'tax_id_encrypted' => ($data['tax_id'] ?? null) ?: null,
            'address_json' => $address !== [] ? $address : null,
            'custom_fields_json' => $custom !== [] ? $custom : null,
        ];
    }
}
