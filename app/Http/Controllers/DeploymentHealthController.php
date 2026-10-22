<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Throwable;

class DeploymentHealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        try {
            DB::select('SELECT 1');
            $revision = Process::path(base_path())->run(['git', 'rev-parse', 'HEAD']);
            $tag = Process::path(base_path())->run(['git', 'describe', '--tags', '--exact-match', 'HEAD']);

            if (! $revision->successful() || ! $tag->successful()) {
                return response()->json(['status' => 'unavailable'], 503, ['Cache-Control' => 'no-store']);
            }

            return response()->json([
                'status' => 'ok',
                'version' => trim($tag->output()),
                'revision' => trim($revision->output()),
            ], headers: ['Cache-Control' => 'no-store']);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['status' => 'unavailable'], 503, ['Cache-Control' => 'no-store']);
        }
    }
}
