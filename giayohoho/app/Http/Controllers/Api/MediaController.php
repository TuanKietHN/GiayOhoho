<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaController extends Controller
{
    public function show(string $path)
    {
        $objectName = urldecode($path);
        if ($objectName === '' || str_contains($objectName, '..') || Str::startsWith($objectName, ['/','\\'])) {
            abort(400, 'Media path is invalid.');
        }

        $disk = Storage::disk(config('filesystems.default', 'local'));
        $candidates = [$objectName, 'public/'.$objectName];
        $found = collect($candidates)->first(fn (string $candidate) => $disk->exists($candidate));

        if (! $found) {
            abort(404);
        }

        return response($disk->get($found), 200, [
            'Content-Type' => $disk->mimeType($found) ?: 'application/octet-stream',
            'Content-Length' => (string) $disk->size($found),
            'Cache-Control' => 'public, max-age=604800',
        ]);
    }
}
