<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class CorsMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $origin = $request->header('Origin');
        $corsConfig = config('cors');
        
        // Déterminer l'origine autorisée
        $allowedOrigin = $this->getAllowedOrigin($origin, $corsConfig);

        $headers = [
            'Access-Control-Allow-Methods' => implode(', ', $corsConfig['allowed_methods']),
            'Access-Control-Allow-Headers' => implode(', ', $corsConfig['allowed_headers']),
            'Access-Control-Max-Age' => (string) $corsConfig['max_age'],
        ];

        // Si une origine est spécifiée et autorisée, l'utiliser
        if ($allowedOrigin) {
            $headers['Access-Control-Allow-Origin'] = $allowedOrigin;
            if ($corsConfig['supports_credentials']) {
                $headers['Access-Control-Allow-Credentials'] = 'true';
            }
        } else {
            // Si aucune origine n'est autorisée, utiliser '*' par défaut pour éviter les blocages
            // (sauf si supports_credentials est true)
            if (!$corsConfig['supports_credentials']) {
                $headers['Access-Control-Allow-Origin'] = '*';
            }
        }

        // Gérer les requêtes OPTIONS (preflight) en premier
        // Ne pas logger les requêtes OPTIONS pour éviter l'encombrement des logs
        if ($request->isMethod('OPTIONS')) {
            // Ajouter Cache-Control pour optimiser le cache du navigateur
            $headers['Cache-Control'] = 'public, max-age=' . $corsConfig['max_age'];
            return response()->json([], 200, $headers);
        }

        $response = $next($request);

        // Ajouter les headers CORS à toutes les réponses
        foreach ($headers as $key => $value) {
            $response->headers->set($key, $value);
        }

        return $response;
    }

    /**
     * Détermine si l'origine est autorisée et retourne l'origine à utiliser
     */
    private function getAllowedOrigin(?string $origin, array $config): ?string
    {
        if (!$origin) {
            // Si pas d'origine (requête same-origin), ne pas ajouter de header CORS
            return null;
        }

        $allowedOrigins = $config['allowed_origins'] ?? [];
        $allowedPatterns = $config['allowed_origins_patterns'] ?? [];
        $supportsCredentials = $config['supports_credentials'] ?? false;

        // Vérifier si '*' est dans les origines autorisées
        $allowAll = in_array('*', $allowedOrigins);

        // Si supports_credentials est true, on ne peut jamais utiliser '*'
        // Il faut retourner l'origine exacte, mais on accepte toutes les origines
        if ($allowAll && $supportsCredentials) {
            // Accepter toutes les origines mais retourner l'origine exacte
            // Vérifier quand même les patterns pour ngrok et autres tunnels
            foreach ($allowedPatterns as $pattern) {
                if ($pattern === '*') {
                    continue;
                }
                if (preg_match('#^' . $pattern . '$#', $origin)) {
                    return $origin;
                }
            }
            // Si aucun pattern ne correspond mais allowAll est true, accepter quand même
            return $origin;
        }

        // Si '*' est autorisé et credentials est false, utiliser '*'
        if ($allowAll && !$supportsCredentials) {
            return '*';
        }

        // Vérifier si l'origine est dans la liste exacte
        if (in_array($origin, $allowedOrigins)) {
            return $origin;
        }

        // Vérifier les patterns regex
        foreach ($allowedPatterns as $pattern) {
            // Ignorer '*' dans les patterns (ce n'est pas une regex valide)
            if ($pattern === '*') {
                continue;
            }
            
            // Tester le pattern regex
            if (preg_match('#^' . $pattern . '$#', $origin)) {
                return $origin;
            }
        }

        // Si allowAll est true mais qu'on arrive ici, accepter quand même l'origine
        // (pour gérer les cas où les patterns ne matchent pas mais qu'on veut autoriser)
        if ($allowAll) {
            return $origin;
        }

        // Origine non autorisée
        return null;
    }
}
