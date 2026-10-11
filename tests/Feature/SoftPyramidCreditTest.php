<?php

use App\Models\User;

test('the credit strings have the agreed wording and no dashes', function () {
    $company = __('entry.credit_company');

    expect($company)->toBe('Soft Pyramid LLC')
        ->and(str_replace(':company', $company, __('entry.credit_developed_by')))->toBe('Developed by Soft Pyramid LLC')
        ->and(str_replace(':company', $company, __('entry.credit_partner')))->toBe('Technology partner: Soft Pyramid LLC')
        ->and(__('entry.credit_new_tab'))->toBe('(opens in a new tab)');

    foreach (['credit_developed_by', 'credit_partner', 'credit_company', 'credit_new_tab'] as $key) {
        expect(__('entry.'.$key))->not->toContain("\u{2014}")->not->toContain("\u{2013}");
    }
});

test('the login page shares the credit strings', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('i18n.entry.credit_developed_by', 'Developed by :company')
            ->where('i18n.entry.credit_partner', 'Technology partner: :company')
            ->where('i18n.entry.credit_company', 'Soft Pyramid LLC')
            ->where('i18n.entry.credit_new_tab', '(opens in a new tab)'));
});

test('an authenticated page shares the credit strings', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('i18n.entry.credit_developed_by', 'Developed by :company')
            ->where('i18n.entry.credit_partner', 'Technology partner: :company')
            ->where('i18n.entry.credit_company', 'Soft Pyramid LLC')
            ->where('i18n.entry.credit_new_tab', '(opens in a new tab)'));
});

test('the credit component links to softpyramid.com in a new tab', function () {
    $source = file_get_contents(resource_path('js/components/softpyramid-credit.tsx'));

    expect($source)
        ->toContain('export function SoftPyramidCredit')
        ->toContain("'https://softpyramid.com'")
        ->toContain('target="_blank"')
        ->toContain('rel="noopener"')
        ->toContain("t('credit_new_tab')")
        ->toContain('sr-only');
});

test('each placement renders the credit', function (string $path) {
    $source = file_get_contents(resource_path($path));

    expect($source)
        ->toContain("import { SoftPyramidCredit } from '@/components/softpyramid-credit';")
        ->toContain('<SoftPyramidCredit');
})->with([
    'auth layout' => 'js/layouts/auth/auth-simple-layout.tsx',
    'app sidebar' => 'js/components/app-sidebar.tsx',
    'command center shell' => 'js/command-center/shell.tsx',
    'about page' => 'js/pages/about.tsx',
    'marketing footer' => 'js/components/marketing-shell.tsx',
]);

test('the command center credit stays out of the header', function () {
    $source = file_get_contents(resource_path('js/command-center/shell.tsx'));
    $header = substr($source, strpos($source, '<header'), strpos($source, '</header>') - strpos($source, '<header'));

    expect($header)->not->toContain('SoftPyramidCredit')
        ->and($source)->toContain('<SoftPyramidCredit compact className="cc-credit" />')
        ->and(file_get_contents(resource_path('css/command-center.css')))->toContain('.cc-root .cc-credit {');
});

test('the marketing partner line stays off while its flag is off', function () {
    config(['tbai.show_partner_line' => false]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('tbai.showPartnerLine', false)
            ->where('tbai.partnerLine', null));
});
