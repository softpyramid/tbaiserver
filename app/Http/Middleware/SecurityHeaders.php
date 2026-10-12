<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Add baseline security headers, including an enforced content security policy.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();

        $response = $next($request);

        // This runs first in the web group, so the session is only readable
        // once the response is built. A response is private when the person
        // is signed in at that point (this includes the login redirect).
        if ($request->user() !== null) {
            $response->headers->set('Cache-Control', 'no-store, private');
            $response->headers->set('Pragma', 'no-cache');
        }

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set(
            'Strict-Transport-Security',
            'max-age=31536000; includeSubDomains',
        );
        $response->headers->set('Content-Security-Policy', $this->contentSecurityPolicy($nonce));
        $this->prepareHtml($response);

        return $response;
    }

    /**
     * Make the HTML match the content security policy.
     *
     * Style tags must not carry a nonce: listing that nonce in style-src
     * turns off 'unsafe-inline', and the tags Sonner and nprogress inject
     * have no nonce. Browsers also drop an IPv6 host such as http://[::1]:5173
     * from the policy, so the Vite dev origin is rewritten to localhost,
     * which reaches the same server.
     */
    private function prepareHtml(Response $response): void
    {
        $contentType = (string) $response->headers->get('Content-Type', '');

        if (! str_contains($contentType, 'text/html') || ! $response instanceof HttpResponse) {
            return;
        }

        $content = $response->getContent();

        if (! is_string($content)) {
            return;
        }

        $updated = preg_replace('/<style\b([^>]*)\snonce="[^"]*"/', '<style$1', $content) ?? $content;
        $updated = preg_replace('/(<link\b[^>]*rel="stylesheet"[^>]*)\snonce="[^"]*"/', '$1', $updated) ?? $updated;
        $updated = str_replace('://[::1]', '://localhost', $updated);

        if ($updated === $content) {
            return;
        }

        $original = $response->original;
        $response->setContent($updated);
        $response->original = $original;
    }

    /**
     * Build the content security policy for this request.
     *
     * The Vite dev server origin is added only while `public/hot` exists, so
     * local hot reload can load. A production response stays on 'self'.
     */
    private function contentSecurityPolicy(string $nonce): string
    {
        $script = "script-src 'self' 'nonce-{$nonce}'";
        $style = "style-src 'self' 'unsafe-inline'";
        $font = "font-src 'self'";
        $connect = "connect-src 'self'";

        $viteOrigin = $this->viteDevOrigin();

        if ($viteOrigin !== null) {
            $socketOrigin = preg_replace('#^http#', 'ws', $viteOrigin) ?? $viteOrigin;
            $script .= ' '.$viteOrigin;
            $style .= ' '.$viteOrigin;
            $font .= ' '.$viteOrigin;
            $connect .= ' '.$viteOrigin.' '.$socketOrigin;
        }

        return implode('; ', [
            "default-src 'self'",
            $script,
            $style,
            "img-src 'self' data: blob:",
            $font,
            $connect,
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "form-action 'self'",
        ]);
    }

    /**
     * Origin of the running Vite dev server, when one is running.
     */
    private function viteDevOrigin(): ?string
    {
        if (! Vite::isRunningHot()) {
            return null;
        }

        $contents = file_get_contents(public_path('hot'));

        if (! is_string($contents)) {
            return null;
        }

        $origin = rtrim(trim($contents), '/');
        $origin = str_replace('://[::1]', '://localhost', $origin);

        return $origin === '' ? null : $origin;
    }
}
