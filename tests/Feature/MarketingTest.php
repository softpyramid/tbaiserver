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

test('validated claims and optional strips stay off by default', function () {
    $response = $this->get(route('home'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('welcome')
        ->where('tbai.showValidatedClaims', false)
        ->where('tbai.showSupporterLogos', false)
        ->where('tbai.showPartnerLine', false)
        ->where('tbai.partnerLine', null)
        ->where('tbai.developmentNotice', 'TB-AI is in development. It is decision support, not a diagnostic device. The microscopist makes the final call.')
        ->where('tbai.contact.email', 'contact-us@dhub-ai.info')
        ->where('tbai.contact.location', 'Lahore, Pakistan')
        ->has('featureTiles', 3)
        ->where('featureTiles.0.title', 'Smartphone-based')
        ->where('featureTiles.1.title', 'Works with existing microscopes')
        ->where('featureTiles.2.title', 'Built for resource-limited settings')
        ->where('testimonials', [])
        ->where('supporters', []));

    $response->assertSee('Smartphone-based', false);
    $response->assertSee('contact-us@dhub-ai.info', false);
    $response->assertSee('TB-AI is in development.', false);
    $response->assertDontSee('97% Accuracy', false);
    $response->assertDontSee('GeneXpert', false);
    $response->assertDontSee('10x cheaper', false);
    $response->assertDontSee('What Clinical Leaders Say', false);
    $response->assertDontSee('Falling Walls Foundation', false);
    $response->assertDontSee('Technical partner: Soft Pyramid', false);
});

test('validated claims, supporter names, and the partner line can be switched on', function () {
    config([
        'tbai.show_validated_claims' => true,
        'tbai.show_supporter_logos' => true,
        'tbai.show_partner_line' => true,
    ]);

    $response = $this->get(route('home'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('tbai.showValidatedClaims', true)
        ->where('tbai.developmentNotice', null)
        ->where('tbai.partnerLine', 'Technical partner: Soft Pyramid')
        ->has('featureTiles', 4)
        ->where('featureTiles.0.title', '97% Accuracy')
        ->where('featureTiles.1.title', '< 3 Minutes')
        ->where('featureTiles.2.body', 'Smear diagnostic costs are reduced to cents per test, over 10x cheaper than GeneXpert hardware.')
        ->where('testimonials.0.name', 'Dr. Asif Mahmood')
        ->where('supporters.0', 'Falling Walls Foundation')
        ->has('supporters', 10));

    $response->assertSee('97% Accuracy', false);
    $response->assertSee('10x cheaper than GeneXpert', false);
    $response->assertSee('Falling Walls Foundation', false);
    $response->assertSee('Technical partner: Soft Pyramid', false);
    $response->assertSee('Dr. Asif Mahmood', false);
    $response->assertDontSee('TB-AI is in development.', false);
    $response->assertDontSee('Works with existing microscopes', false);
});

test('public marketing copy does not use an em dash', function () {
    $files = [
        resource_path('js/pages/welcome.tsx'),
        resource_path('js/components/marketing-shell.tsx'),
        resource_path('js/pages/privacy.tsx'),
        resource_path('js/pages/terms.tsx'),
        resource_path('js/pages/access-request.tsx'),
        config_path('tbai.php'),
    ];

    foreach ($files as $file) {
        expect(file_get_contents($file))->not->toContain("\u{2014}");
    }
});
