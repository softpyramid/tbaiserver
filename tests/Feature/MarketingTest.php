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
    $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
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
