<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckInstallation
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // If not installed and not accessing installation routes
        if (!file_exists(storage_path('installed')) && 
            !$request->is('install*') && 
            !$request->is('assets/*')) {
            return redirect()->route('install.index');
        }

        // If installed and trying to access installation routes
        if (file_exists(storage_path('installed')) && $request->is('install*')) {
            return redirect('/');
        }

        return $next($request);
    }
}
