<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\UserRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LibraryMediaController extends Controller
{
    public function store(Request $request)
    {
        if (!UserRole::isOneOf($request->user(), ['admin', 'specialist', 'organization'])) {
            abort(403, 'Forbidden');
        }

        $data = $request->validate([
            'file' => 'required|file|max:102400|mimetypes:image/jpeg,image/png,image/webp,video/mp4,video/quicktime,video/x-matroska,video/x-msvideo',
        ]);

        $file = $data['file'];
        $path = $file->store('library-media', 'public');
        $mime = $file->getMimeType();
        $type = Str::startsWith($mime, 'video') ? 'video' : 'image';

        return response()->json([
            'url' => Storage::disk('public')->url($path),
            'type' => $type,
        ], 201);
    }
}
