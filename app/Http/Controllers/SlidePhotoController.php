<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSlidePhotosRequest;
use App\Models\DailyReturn;
use App\Models\SlidePhoto;
use App\Models\Team;
use App\Support\SlidePhotoStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SlidePhotoController extends Controller
{
    public function store(StoreSlidePhotosRequest $request, Team $current_team, DailyReturn $dailyReturn, SlidePhotoStore $store): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        /** @var array<int, string|null> $labels */
        $labels = (array) $request->input('labels', []);
        $files = Arr::wrap($request->validated('photos'));
        $saved = 0;

        foreach ($files as $index => $file) {
            if ($file instanceof UploadedFile) {
                $label = $labels[$index] ?? null;
                $store->store($dailyReturn, $user, $file, is_string($label) ? $label : null);
                $saved++;
            }
        }

        Inertia::flash('slidePhotosSaved', $saved);

        return back();
    }

    public function show(Request $request, SlidePhoto $slidePhoto): StreamedResponse
    {
        Gate::authorize('view', $slidePhoto);

        return $this->stream($slidePhoto->path, $slidePhoto->mime_type, $slidePhoto);
    }

    public function thumbnail(Request $request, SlidePhoto $slidePhoto): StreamedResponse
    {
        Gate::authorize('view', $slidePhoto);
        abort_if($slidePhoto->thumbnail_path === null, 404);

        return $this->stream($slidePhoto->thumbnail_path, 'image/jpeg', $slidePhoto);
    }

    public function destroy(Request $request, Team $current_team, SlidePhoto $slidePhoto): RedirectResponse
    {
        abort_unless($current_team->facility !== null && $slidePhoto->facility_id === $current_team->facility->id, 404);
        Gate::authorize('delete', $slidePhoto);

        $slidePhoto->delete();
        Inertia::flash('slidePhotoDeleted', true);

        return back();
    }

    private function stream(string $path, string $mime, SlidePhoto $photo): StreamedResponse
    {
        $disk = Storage::disk($photo->disk);
        abort_unless($disk->exists($path), 404);

        return $disk->response($path, 'slide-'.$photo->id.'.'.pathinfo($path, PATHINFO_EXTENSION), [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'",
        ], 'inline');
    }
}
