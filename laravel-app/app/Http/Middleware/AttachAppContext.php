<?php

namespace App\Http\Middleware;

use App\Services\AppContextResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AttachAppContext
{
    public function __construct(private readonly AppContextResolver $resolver)
    {
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        [$tenant, $location, $locations] = $this->resolver->resolve($request);
        $request->attributes->set('tenant', $tenant);
        $request->attributes->set('location', $location);
        $request->attributes->set('locations', $locations);

        if (!$request->has('location_id') && !$request->query->has('location_id')) {
            $request->query->set('location_id', (string)$location->id);
        }

        return $next($request);
    }
}
