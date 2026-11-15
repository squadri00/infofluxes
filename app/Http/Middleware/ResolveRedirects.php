<?php

namespace App\Http\Middleware;

use App\Models\Redirect;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * When a page is not found, look for a redirect for that address (an old address of a page that was renamed, or one made by hand).
 * Only GET and HEAD, and only when the answer would have been "not found": a page that exists is never replaced by a redirect.
 */
class ResolveRedirects
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response->getStatusCode() !== 404 || ! in_array($request->getMethod(), ['GET', 'HEAD'], true)) {
            return $response;
        }
        $path = '/'.ltrim($request->path(), '/');
        $hit = Redirect::resolve($path);
        if ($hit === null) {
            return $response;
        }

        Redirect::query()->whereKey($hit['id'])->toBase()->update(['hits' => \Illuminate\Support\Facades\DB::raw('hits + 1'), 'last_hit_at' => now()]);

        $to = $hit['to'];
        $query = $request->getQueryString();
        if ($query !== null && $query !== '' && ! str_contains($to, '?')) {
            $to .= '?'.$query;   // ?view=index and the like are kept
        }

        return redirect()->to($to, $hit['code']);
    }
}
