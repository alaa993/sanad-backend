<?php

namespace App\Http\Controllers\Api\V1\Specialist;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\SpecialistDocument;
use App\Models\SpecialistProfile;
use Illuminate\Support\Facades\Cache;

class SpecialistDocumentController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $cacheKey = "spec:documents:{$user->id}";
        $payload = Cache::remember($cacheKey, 30, function () use ($user) {
            $documents = SpecialistDocument::where('user_id', $user->id)
                ->orderByDesc('created_at')
                ->get();
            $profile = SpecialistProfile::firstOrCreate(['user_id' => $user->id], []);

            return [
                'status' => $profile->status,
                'verification_notes' => $profile->verification_notes,
                'documents' => $documents,
            ];
        });

        return response()->json($payload);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|string|max:50',
            'title' => 'nullable|string|max:255',
            'file' => 'required|file|max:8192|mimes:pdf,jpg,jpeg,png,doc,docx',
        ]);

        $user = $request->user();
        $path = $request->file('file')->store("specialists/{$user->id}", 'public');

        $document = SpecialistDocument::create([
            'user_id' => $user->id,
            'type' => $data['type'],
            'title' => $data['title'] ?? null,
            'file_path' => $path,
            'meta' => [
                'original_name' => $request->file('file')->getClientOriginalName(),
                'mime' => $request->file('file')->getMimeType(),
            ],
        ]);

        $profile = SpecialistProfile::firstOrCreate(['user_id' => $user->id], []);
        if ($profile->status === 'pending') {
            $profile->status = 'under_review';
            $profile->save();
        }
        Cache::forget("spec:documents:{$user->id}");

        return response()->json($document, 201);
    }

    public function destroy(Request $request, $id)
    {
        $document = SpecialistDocument::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->firstOrFail();

        if ($document->file_path && Storage::disk('public')->exists($document->file_path)) {
            Storage::disk('public')->delete($document->file_path);
        }

        $document->delete();
        Cache::forget("spec:documents:{$request->user()->id}");
        return response()->json(['ok' => true]);
    }
}
