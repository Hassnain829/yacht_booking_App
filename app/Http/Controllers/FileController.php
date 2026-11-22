<?php

namespace App\Http\Controllers;

use App\Models\Media;
use App\Services\MediaService;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Serves a private file after MediaPolicy allows it (PLAN §9); everyone else gets a 404. */
class FileController extends Controller
{
    public function __invoke(Media $media, MediaService $files): StreamedResponse
    {
        Gate::authorize('view', $media);

        return $files->download($media);
    }
}
