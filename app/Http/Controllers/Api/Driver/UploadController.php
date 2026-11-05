<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use App\Support\DriverTaskPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Photos, signatures and POD documents from the driver app. The app uploads each file with its own client uuid,
 * so a retried upload after a dropped connection lands on the same path instead of a duplicate file.
 */
class UploadController extends Controller
{
    public const KINDS = [
        'pickup_photo', 'delivery_photo', 'signature', 'pod_document',
        'failed_photo', 'break_bulk_photo', 'handover_photo',
    ];

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:15360', 'mimes:jpg,jpeg,png,webp,heic,pdf'],
            'kind' => ['required', 'in:'.implode(',', self::KINDS)],
            'client_uuid' => ['nullable', 'uuid'],
        ]);

        $driverId = $request->user()->driver_id;
        abort_unless($driverId, 403, 'No driver profile linked to this account.');

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'jpg');
        $name = ($data['client_uuid'] ?? (string) Str::uuid()).'.'.$extension;
        $directory = 'driver-app/'.$driverId.'/'.$data['kind'];
        $path = $directory.'/'.$name;

        if (! Storage::disk('public')->exists($path)) {
            $file->storeAs($directory, $name, 'public');
        }

        return response()->json([
            'data' => [
                'path' => $path,
                'url' => DriverTaskPayload::url($path),
                'kind' => $data['kind'],
            ],
        ], 201);
    }
}
