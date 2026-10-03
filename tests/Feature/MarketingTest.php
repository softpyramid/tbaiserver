<?php

test('public pages are not indexed and send security headers', function () {
    $response = $this->get(route('home'));

    $response->assertOk();
    $response->assertSee('noindex, nofollow', false);
    $response->assertSee('TB-AI | TB register and decision support', false);
    $response->assertSee('rel="canonical"', false);
    $response->assertSee('property="og:title"', false);
    $response->assertSee('property="og:description"', false);
    $response->assertSee('property="og:type"', false);
    $response->assertSee('property="og:url"', false);
    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
    $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');

    $policy = (string) $response->headers->get('Content-Security-Policy');

    expect($policy)
        ->toContain("default-src 'self'")
        ->toContain("script-src 'self' 'nonce-")
        ->toContain("style-src 'self' 'unsafe-inline'")
        ->toContain("img-src 'self' data:")
        ->toContain("font-src 'self'")
        ->toContain("connect-src 'self'")
        ->toContain("frame-ancestors 'self'")
        ->toContain("base-uri 'self'")
        ->toContain("form-action 'self'");

    expect($response->headers->has('Content-Security-Policy-Report-Only'))->toBeFalse();

    preg_match("/'nonce-([^']+)'/", $policy, $matches);
    $response->assertSee('nonce="'.$matches[1].'"', false);
});

test('the sitemap lists the public pages as valid xml', function () {
    $response = $this->get(route('sitemap'));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

    $xml = simplexml_load_string($response->getContent());

    expect($xml)->not->toBeFalse();

    $locations = [];

    foreach ($xml->url as $url) {
        $locations[] = (string) $url->loc;
    }

    expect($locations)->toEqualCanonicalizing([
        url('/'),
        url('/privacy'),
        url('/terms'),
        url('/request-access'),
    ]);
});

test('robots txt disallows the whole site', function () {
    $this->get('/robots.txt')
        ->assertOk()
        ->assertSee('Disallow: /', false);
});

test('privacy and terms pages are available', function () {
    $this->get(route('privacy'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('privacy'));

    $this->get(route('terms'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('terms'));
});
