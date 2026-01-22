<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\OnesiBox;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware that ensures the authenticated OnesiBox appliance is active.
 *
 * Returns a 403 Forbidden response if the appliance is disabled (is_active=false).
 * This middleware should be applied to all appliance API routes.
 */
class EnsureApplianceIsActive
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof OnesiBox && ! $user->is_active) {
            return response()->json([
                'message' => 'Appliance disabilitata.',
                'error_code' => 'E003',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
