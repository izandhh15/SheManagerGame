<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cierre total de la nueva generación (2026-10-24, decisión de Izan).
 *
 * Mientras dure la reconstrucción, nadie juega: la portada ES la página
 * de "nueva generación" y cualquier otra URL redirige a ella. No se
 * borra ni se toca ningún dato: partidas, usuarios y ligas siguen
 * intactos en la base de datos y vuelven al levantar este middleware.
 *
 * Excepciones: la propia página /nueva-generacion y el health check /up
 * (Wasmer lo usa para saber que la app está viva).
 */
class UnderConstruction
{
    public function handle(Request $request, Closure $next): Response
    {
        $path = trim($request->path(), '/');

        if ($path === 'nueva-generacion' || $path === 'up') {
            return $next($request);
        }

        if ($path === '') {
            return response()->view('next-gen');
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => 'SheManagerGame está en construcción: vuelve con la nueva generación.',
            ], 503);
        }

        return redirect()->route('next-gen');
    }
}
