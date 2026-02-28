<?php

namespace mhrshuvo\JourneyLog\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;

class LogCustomerJourney
{
    public function handle(Request $request, Closure $next)
    {
        $header = config('journeylog.header', 'X-Journey-ID');
        $sessionKey = config('journeylog.session_key', 'journey_id');

        // Priority: Header first (works for both web and API), then session (web only), then generate new
        $journeyId = $request->header($header);

        if (! $journeyId && $request->hasSession()) {
            $journeyId = $request->session()->get($sessionKey);
        }

        if (! $journeyId) {
            $journeyId = Str::random(12);
        }

        // Always store the resolved journey ID in session (if session is available)
        // This ensures header values are persisted in session for subsequent requests
        if ($request->hasSession()) {
            $request->session()->put($sessionKey, $journeyId);
        }

        Context::add('journey_id', $journeyId);

        return $next($request);
    }
}
