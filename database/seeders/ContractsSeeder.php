<?php

namespace Database\Seeders;

use App\Enums\BookingStatus;
use App\Enums\ContractStatus;
use App\Enums\TemplateKind;
use App\Models\Booking;
use App\Models\Contract;
use App\Models\ContractSigner;
use App\Models\DocumentTemplate;
use App\Services\Signing\ContractDispatcher;
use Illuminate\Database\Seeder;

/**
 * Seeds a global contract/proposal template plus contracts across
 * active bookings. Skips contract creation if any already exist.
 */
class ContractsSeeder extends Seeder
{
    public function run(): void
    {
        DocumentTemplate::query()->firstOrCreate(
            ['kind' => TemplateKind::Contract->value, 'venue_id' => null, 'name' => 'Standard County Event Contract'],
            [
                'version' => 1,
                'is_active' => true,
                // demo body keeps real merge fields and the literal
                // anchor labels (Signature:, Initials:, Date:, "agree to
                // the terms") that DocuSignSignatureProvider binds signing
                // fields to - keep in sync with its ANCHOR_* constants
                'body_html' => <<<'HTML'
                    <div style="font-family:Arial,Helvetica,sans-serif;color:#374151;font-size:12px;line-height:1.55;max-width:720px;">
                      <table style="width:100%;border-collapse:collapse;border-bottom:3px solid #052D5B;margin-bottom:16px;">
                        <tr>
                          <td style="vertical-align:middle;padding-bottom:10px;">
                            <table style="border-collapse:collapse;"><tr>
                              <td style="width:44px;height:44px;background:#052D5B;color:#ffffff;border-radius:10px;text-align:center;vertical-align:middle;font-size:24px;font-weight:700;">V</td>
                              <td style="padding-left:12px;vertical-align:middle;">
                                <div style="font-size:17px;font-weight:700;color:#052D5B;">{{venue.name}}</div>
                                <div style="font-size:10px;color:#6b7280;letter-spacing:1.5px;">EVENT SERVICES &amp; VENUE MANAGEMENT</div>
                              </td>
                            </tr></table>
                          </td>
                          <td style="vertical-align:middle;text-align:right;padding-bottom:10px;">
                            <div style="font-size:15px;font-weight:700;color:#111827;">EVENT SERVICES AGREEMENT</div>
                            <div style="font-size:11px;color:#6b7280;">Ref. {{booking.reference}}</div>
                          </td>
                        </tr>
                      </table>

                      <p>This Event Services Agreement (the "Agreement") is entered into between
                      <strong>{{venue.name}}</strong> (the "Venue") and <strong>{{client.name}}</strong>
                      (the "Client") for the event known as "{{booking.name}}", scheduled
                      {{booking.start_date}} through {{booking.end_date}}, for a total fee of
                      <strong>{{booking.total}}</strong>. The parties agree as follows.</p>

                      <h3 style="color:#052D5B;font-size:13px;margin:14px 0 4px;">1. Services &amp; Scope</h3>
                      <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor
                      incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud
                      exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. The Venue shall
                      provide the spaces, staffing, and services described in the event order incorporated
                      herein by reference.</p>

                      <h3 style="color:#052D5B;font-size:13px;margin:14px 0 4px;">2. Fees &amp; Payment</h3>
                      <p>Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu
                      fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa
                      qui officia deserunt mollit anim id est laborum. A deposit equal to fifty percent
                      (50%) of the total fee is due upon execution, with the balance due thirty (30) days
                      prior to the event date.</p>

                      <h3 style="color:#052D5B;font-size:13px;margin:14px 0 4px;">3. Use of Premises</h3>
                      <p>Sed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium
                      doloremque laudantium, totam rem aperiam, eaque ipsa quae ab illo inventore veritatis
                      et quasi architecto beatae vitae dicta sunt explicabo. The Client shall use the
                      premises solely for the stated event and in compliance with all applicable rules and
                      regulations.</p>

                      <h3 style="color:#052D5B;font-size:13px;margin:14px 0 4px;">4. Cancellation</h3>
                      <p>Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit, sed
                      quia consequuntur magni dolores eos qui ratione voluptatem sequi nesciunt. Cancellations
                      received more than sixty (60) days prior to the event receive a full refund less a
                      reasonable administrative fee; cancellations within fourteen (14) days forfeit the
                      deposit.</p>

                      <h3 style="color:#052D5B;font-size:13px;margin:14px 0 4px;">5. Liability &amp; Indemnification</h3>
                      <p>At vero eos et accusamus et iusto odio dignissimos ducimus qui blanditiis
                      praesentium voluptatum deleniti atque corrupti quos dolores et quas molestias
                      excepturi sint occaecati cupiditate non provident. Each party shall indemnify the
                      other against claims arising from its own negligence or willful misconduct.</p>

                      <h3 style="color:#052D5B;font-size:13px;margin:16px 0 4px;">Acceptance</h3>
                      <p>By initialing here (Initials: __________) and signing below, the Client accepts
                      this Agreement in full.</p>
                      <p style="margin:8px 0;">&nbsp;&nbsp;&nbsp;&nbsp;I have read and agree to the terms.</p>
                      <p style="margin-top:18px;">Signature: ______________________________
                      &nbsp;&nbsp;&nbsp;&nbsp; Date: __________</p>
                    </div>
                HTML,
            ],
        );

        DocumentTemplate::query()->firstOrCreate(
            ['kind' => TemplateKind::Proposal->value, 'venue_id' => null, 'name' => 'Standard Proposal'],
            [
                'version' => 1,
                'is_active' => true,
                'body_html' => <<<'HTML'
                    <div style="font-family:Arial,Helvetica,sans-serif;color:#374151;font-size:12px;line-height:1.55;max-width:720px;">
                      <table style="width:100%;border-collapse:collapse;border-bottom:3px solid #052D5B;margin-bottom:16px;">
                        <tr>
                          <td style="vertical-align:middle;padding-bottom:10px;">
                            <table style="border-collapse:collapse;"><tr>
                              <td style="width:44px;height:44px;background:#052D5B;color:#ffffff;border-radius:10px;text-align:center;vertical-align:middle;font-size:24px;font-weight:700;">V</td>
                              <td style="padding-left:12px;vertical-align:middle;">
                                <div style="font-size:17px;font-weight:700;color:#052D5B;">{{venue.name}}</div>
                                <div style="font-size:10px;color:#6b7280;letter-spacing:1.5px;">EVENT SERVICES &amp; VENUE MANAGEMENT</div>
                              </td>
                            </tr></table>
                          </td>
                          <td style="vertical-align:middle;text-align:right;padding-bottom:10px;">
                            <div style="font-size:15px;font-weight:700;color:#111827;">EVENT PROPOSAL</div>
                            <div style="font-size:11px;color:#6b7280;">Ref. {{booking.reference}}</div>
                          </td>
                        </tr>
                      </table>

                      <p>Prepared for <strong>{{client.name}}</strong> for the event "{{booking.name}}",
                      proposed {{booking.start_date}} through {{booking.end_date}} at {{venue.name}}.
                      Thank you for the opportunity to host your event.</p>

                      <h3 style="color:#052D5B;font-size:13px;margin:14px 0 4px;">Overview</h3>
                      <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor
                      incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud
                      exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Coordination
                      runs from the initial site walkthrough through teardown on event day.</p>

                      <h3 style="color:#052D5B;font-size:13px;margin:14px 0 4px;">Proposed Scope</h3>
                      <p>Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu
                      fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa
                      qui officia deserunt mollit anim id est laborum. Includes venue access, setup and
                      teardown, on-site coordination, and standard audiovisual support.</p>

                      <h3 style="color:#052D5B;font-size:13px;margin:14px 0 4px;">Investment</h3>
                      <p>Estimated total: <strong>{{booking.total}}</strong>. Sed ut perspiciatis unde omnis
                      iste natus error sit voluptatem accusantium doloremque laudantium. Final pricing is
                      confirmed in the Event Services Agreement.</p>

                      <p style="margin-top:16px;color:#6b7280;font-size:11px;">This proposal is valid for
                      thirty (30) days from the date issued. We look forward to working with you.</p>
                    </div>
                HTML,
            ],
        );

        if (Contract::query()->count() > 0) {
            $this->command?->info('ContractsSeeder: contracts already present, skipping contract creation.');

            return;
        }

        $template = DocumentTemplate::query()
            ->where('kind', TemplateKind::Contract->value)
            ->whereNull('venue_id')
            ->first();

        // bookings at the contract stage of life
        $bookings = Booking::query()
            ->whereIn('status', [BookingStatus::Tentative->value, BookingStatus::Definite->value, BookingStatus::Completed->value])
            ->limit(10)
            ->get();

        $statusBuckets = [
            ContractStatus::Draft,
            ContractStatus::Sent,
            ContractStatus::Sent,
            ContractStatus::Viewed,
            ContractStatus::PartiallySigned,
            ContractStatus::Signed,
            ContractStatus::Signed,
            ContractStatus::Signed,
            ContractStatus::Declined,
            ContractStatus::Expired,
        ];

        foreach ($bookings as $idx => $booking) {
            $status = $statusBuckets[$idx % count($statusBuckets)];

            $contract = Contract::query()->create([
                'booking_id' => $booking->id,
                'template_id' => $template?->id,
                'kind' => 'contract',
                'status' => $status->value,
                'total_cents' => $booking->total_cents,
                'rendered_html' => '<h1>'.$booking->name.'</h1><p>Total: $'.number_format($booking->total_cents / 100, 2).'</p>',
                'provider' => 'docusign',
                'provider_envelope_id' => $status === ContractStatus::Draft ? null : 'env_seed_'.$booking->id,
                'sent_at' => $status !== ContractStatus::Draft ? now()->subDays(rand(1, 14)) : null,
                'viewed_at' => in_array($status, [ContractStatus::Viewed, ContractStatus::PartiallySigned, ContractStatus::Signed, ContractStatus::Declined], true) ? now()->subDays(rand(1, 10)) : null,
                'signed_at' => $status === ContractStatus::Signed ? now()->subDays(rand(1, 7)) : null,
                'declined_at' => $status === ContractStatus::Declined ? now()->subDays(rand(1, 7)) : null,
                'decline_reason' => $status === ContractStatus::Declined ? 'Date conflict' : null,
                'expired_at' => $status === ContractStatus::Expired ? now()->subDays(rand(1, 5)) : null,
            ]);

            ContractSigner::query()->create([
                'contract_id' => $contract->id,
                'signing_order' => 1,
                'role' => 'client',
                'name' => $booking->client?->name ?? 'Client',
                'email' => 'signer'.($idx + 1).'@example.test',
                'viewed_at' => $contract->viewed_at,
                'signed_at' => $contract->signed_at,
                'declined_at' => $contract->declined_at,
            ]);
        }

        $this->command?->info('ContractsSeeder: created '.$bookings->count().' contracts across active bookings.');

        // drop one addendum on the first signed parent via the dispatcher
        // so the audit + reference flow matches the runtime path
        $signedParent = Contract::query()
            ->where('status', ContractStatus::Signed->value)
            ->whereNull('parent_contract_id')
            ->orderBy('id')
            ->first();
        if ($signedParent !== null) {
            app(ContractDispatcher::class)->draftAddendum(
                $signedParent,
                'Demo addendum - adjusts the load-in window by 90 minutes.',
            );
            $this->command?->info("ContractsSeeder: drafted demo addendum on {$signedParent->reference}.");
        }
    }
}
