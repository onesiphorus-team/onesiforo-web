<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\ProcessScreenshotAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreScreenshotRequest;
use App\Models\ApplianceScreenshot;
use App\Models\OnesiBox;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ScreenshotController extends Controller
{
    public function store(
        StoreScreenshotRequest $request,
        ProcessScreenshotAction $action,
    ): JsonResponse {
        /** @var OnesiBox $box */
        $box = $request->user();

        /** @var UploadedFile $file */
        $file = $request->file('screenshot');

        $screenshot = $action->execute(
            $box,
            $request->date('captured_at'),
            $request->integer('width'),
            $request->integer('height'),
            $file,
        );

        return response()->json(['id' => $screenshot->id], 201);
    }

    public function show(ApplianceScreenshot $screenshot): StreamedResponse
    {
        Gate::authorize('view', $screenshot);

        return Storage::disk('local')->download(
            $screenshot->storage_path,
            basename($screenshot->storage_path),
            [
                'Content-Type' => 'image/webp',
                'Cache-Control' => 'private, max-age=60',
            ],
        );
    }
}
