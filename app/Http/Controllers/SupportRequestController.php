<?php

namespace App\Http\Controllers;

use App\Enums\SupportRequestCategory;
use App\Http\Requests\StoreSupportRequest;
use App\Mail\SupportRequestSubmitted;
use App\Models\SupportRequest;
use App\Services\SystemSettings\SystemSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class SupportRequestController extends Controller
{
    public function __construct(private readonly SystemSettings $settings) {}

    public function create(): Response
    {
        return Inertia::render('support/create', [
            'categories' => array_map(
                fn (SupportRequestCategory $c) => ['value' => $c->value, 'label' => $c->label()],
                SupportRequestCategory::cases(),
            ),
        ]);
    }

    public function store(StoreSupportRequest $request): RedirectResponse
    {
        $supportRequest = SupportRequest::create([
            'user_id' => $request->user()?->id,
            'category' => $request->string('category')->value(),
            'subject' => $request->string('subject')->value(),
            'body' => $request->string('body')->value(),
            'page_url' => $request->string('page_url')->value() ?: null,
            'app_version' => config('app.version'),
        ]);

        $this->notifyRecipients($supportRequest);

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Thanks - your request has been sent to support.',
        ]);
    }

    /**
     * Notify support recipients when enabled. The request is already persisted,
     * so a disabled mailer or empty recipient list is a no-op, not an error.
     */
    private function notifyRecipients(SupportRequest $supportRequest): void
    {
        if (! $this->settings->get('support.notifications_enabled', false)) {
            return;
        }

        $recipients = $this->recipients();
        if ($recipients === []) {
            return;
        }

        Mail::to($recipients)->queue(new SupportRequestSubmitted($supportRequest));
    }

    /**
     * @return list<string>
     */
    private function recipients(): array
    {
        $raw = (string) $this->settings->get('support.recipients', '');

        return collect(preg_split('/[\s,;]+/', $raw) ?: [])
            ->map(fn (string $e) => trim($e))
            ->filter(fn (string $e) => filter_var($e, FILTER_VALIDATE_EMAIL) !== false)
            ->unique()
            ->values()
            ->all();
    }
}
