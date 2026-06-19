<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Upload / remove documents attached to a client or booking, stored in the
 * record's "documents" media collection. Per-type entry points share the
 * store/destroy core so authorization stays per-route.
 */
class RecordDocumentController extends Controller
{
    public function storeClient(Request $request, Client $client): RedirectResponse
    {
        return $this->store($request, $client);
    }

    public function destroyClient(Client $client, Media $media): RedirectResponse
    {
        return $this->destroy($client, $media);
    }

    public function storeBooking(Request $request, Booking $booking): RedirectResponse
    {
        return $this->store($request, $booking);
    }

    public function destroyBooking(Booking $booking, Media $media): RedirectResponse
    {
        return $this->destroy($booking, $media);
    }

    private function store(Request $request, Client|Booking $owner): RedirectResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:20480', 'mimes:pdf,doc,docx,xls,xlsx,csv,txt,png,jpg,jpeg,webp,eml,msg'],
            'title' => ['nullable', 'string', 'max:150'],
        ]);

        $name = $validated['title']
            ?? pathinfo($request->file('file')->getClientOriginalName(), PATHINFO_FILENAME);

        $owner->addMediaFromRequest('file')
            ->usingName($name)
            ->toMediaCollection('documents');

        return back()->with('toast', ['type' => 'success', 'message' => 'Document attached.']);
    }

    private function destroy(Client|Booking $owner, Media $media): RedirectResponse
    {
        // media must belong to this record's documents collection - block
        // deleting a {media} bound to another record via this route
        abort_unless(
            $media->model_type === $owner->getMorphClass()
                && (int) $media->model_id === (int) $owner->getKey()
                && $media->collection_name === 'documents',
            404,
        );

        $media->delete();

        return back()->with('toast', ['type' => 'success', 'message' => 'Document removed.']);
    }
}
