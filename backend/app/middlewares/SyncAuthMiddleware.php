<?php

namespace App\Middlewares;

use Core\Request;
use Core\Response;

/**
 * Auth máquina-a-máquina para el intermediario PC (sync-agent).
 *
 * Acepta cualquiera de:
 *  - Header X-Sync-Key: <SYNC_API_KEY>  (recomendado, configurado en .env)
 *  - JWT Bearer válido (reutiliza AuthMiddleware para el panel/debug manual)
 *
 * Si SYNC_API_KEY no está definido en .env, rechaza todo push/pull
 * (fail-closed) excepto /health.
 */
class SyncAuthMiddleware
{
    public function handle(Request $request, Response $response): void
    {
        $expected = $_ENV['SYNC_API_KEY'] ?? getenv('SYNC_API_KEY') ?: null;

        $given = $request->getHeader('X-Sync-Key');
        if ($given === null) {
            // Algunos hostings renombran headers a $_SERVER
            $given = $_SERVER['HTTP_X_SYNC_KEY'] ?? $_SERVER['REDIRECT_HTTP_X_SYNC_KEY'] ?? null;
        }

        if ($expected && $given && hash_equals((string)$expected, (string)$given)) {
            return; // OK por API key
        }

        // Fallback: JWT de usuario (útil para probar pull desde el panel con login)
        $auth = new AuthMiddleware();
        $auth->handle($request, $response);
    }
}
