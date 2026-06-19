<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Client;
use App\Models\Exhibitor;
use App\Models\ExhibitorPermit;
use App\Models\InsuranceCertificate;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Authenticated download gateway for sensitive documents (PII / contracts).
 * Every download is authorized against the requesting identity: staff by
 * cross-venue permission, exhibitors by ownership. Forced to download, never
 * inline, so an uploaded file can't execute on the app origin.
 */
class MediaController extends Controller
{
    /**
     * Servable collections. Display images stay on their public URL path;
     * only sensitive document collections route through here.
     *
     * @var list<string>
     */
    private const SERVABLE_COLLECTIONS = ['certificate', 'document', 'documents'];

    public function show(Request $request, Media $media): StreamedResponse
    {
        abort_unless(in_array($media->collection_name, self::SERVABLE_COLLECTIONS, true), 404);

        abort_unless($this->canAccess($request, $media), 403);

        return Storage::disk($media->disk)->download(
            $media->getPathRelativeToRoot(),
            $media->file_name,
        );
    }

    private function canAccess(Request $request, Media $media): bool
    {
        $owner = $media->model;
        $user = $request->user();
        $exhibitor = $request->user('exhibitor');

        return match (true) {
            $owner instanceof InsuranceCertificate => $this->canAccessInsurance($owner, $user, $exhibitor),
            $owner instanceof ExhibitorPermit => $this->canAccessPermit($owner, $user, $exhibitor),
            $owner instanceof Client => $user instanceof User && $user->hasVenuePermission('clients.manage'),
            $owner instanceof Booking => $user instanceof User && $user->hasVenuePermission('bookings.edit'),
            default => false,
        };
    }

    private function canAccessInsurance(InsuranceCertificate $certificate, ?Authenticatable $user, ?Authenticatable $exhibitor): bool
    {
        if ($user instanceof User && $user->hasVenuePermission('compliance.view')) {
            return true;
        }

        return $exhibitor instanceof Exhibitor
            && $certificate->holder_type === $exhibitor->getMorphClass()
            && (int) $certificate->holder_id === (int) $exhibitor->getKey();
    }

    private function canAccessPermit(ExhibitorPermit $permit, ?Authenticatable $user, ?Authenticatable $exhibitor): bool
    {
        if ($user instanceof User && $user->hasVenuePermission('compliance.view')) {
            return true;
        }

        return $exhibitor instanceof Exhibitor
            && (int) $permit->exhibitor_id === (int) $exhibitor->getKey();
    }
}
