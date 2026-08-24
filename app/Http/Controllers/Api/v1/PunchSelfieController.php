<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\PunchLog;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PunchSelfieController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'punch_id' => 'nullable|integer',
            'client_punch_id' => 'nullable|string',
            'file' => 'required|image|max:10240', // max 10MB
        ]);

        $file = $request->file('file');
        $extension = $file->getClientOriginalExtension() ?: 'jpg';
        $filename = 'selfie_' . Str::random(20) . '.' . $extension;

        // Store in public uploads or storage
        $path = $file->storeAs('selfies', $filename, 'public');

        if (!empty($validated['punch_id'])) {
            PunchLog::where('id', $validated['punch_id'])->update(['selfie_filename' => $filename]);
        } elseif (!empty($validated['client_punch_id'])) {
            PunchLog::where('client_punch_id', $validated['client_punch_id'])->update(['selfie_filename' => $filename]);
        }

        return response()->json([
            'status' => 'ok',
            'filename' => $filename,
            'url' => Storage::disk('public')->url($path),
            'message' => 'Selfie uploaded successfully.',
        ]);
    }
}
