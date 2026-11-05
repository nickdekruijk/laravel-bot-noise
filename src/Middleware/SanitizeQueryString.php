<?php

namespace NickDeKruijk\BotNoise\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Replaces invalid UTF-8 in the query string, so it can't break json_encode
 * further down, for example in a Livewire snapshot.
 */
class SanitizeQueryString
{
    public function handle(Request $request, Closure $next): Response
    {
        $query = $request->query->all();

        if (! mb_check_encoding($query, 'UTF-8')) {
            $request->query->replace(mb_convert_encoding($query, 'UTF-8', 'UTF-8'));
        }

        return $next($request);
    }
}
