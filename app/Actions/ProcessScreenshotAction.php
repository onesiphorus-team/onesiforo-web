<?php

declare(strict_types=1);

namespace App\Actions;

use App\Events\ApplianceScreenshotReceived;
use App\Models\ApplianceScreenshot;
use App\Models\OnesiBox;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProcessScreenshotAction
{
    public function execute(
        OnesiBox $box,
        CarbonInterface $capturedAt,
        int $width,
        int $height,
        UploadedFile $file,
    ): ApplianceScreenshot {
        $uuid = substr(Str::uuid()->toString(), 0, 8);
        $filename = $capturedAt->format('Y-m-d\TH-i-s') . "_{$uuid}.webp";
        $directory = "onesi-boxes/{$box->id}/screenshots";
        $path = "{$directory}/{$filename}";

        Storage::disk('local')->putFileAs(
            $directory,
            $file,
            $filename,
            ['visibility' => 'private']
        );

        $screenshot = ApplianceScreenshot::create([
            'onesi_box_id' => $box->id,
            'captured_at'  => $capturedAt,
            'width'        => $width,
            'height'       => $height,
            'bytes'        => $file->getSize(),
            'storage_path' => $path,
        ]);

        event(new ApplianceScreenshotReceived($screenshot));

        return $screenshot;
    }
}
