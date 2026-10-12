<?php

$flag = static function (string $key): bool {
    $value = env($key, false);

    return filter_var($value, FILTER_VALIDATE_BOOLEAN);
};

/*
 * Whether the command center areas (drivers, interventions, incidence, AI
 * tests) are on by default, per environment, when their env variable is
 * absent. Local, dev, and staging are on; production is off. An environment
 * that is not listed here (testing, for example) is off.
 */
$areaDefaults = [
    'local' => true,
    'dev' => true,
    'staging' => true,
    'production' => false,
];

/*
 * Flags for the command center pages. An explicit env value always wins.
 * When the variable is absent, the area follows $areaDefaults, and an
 * environment not listed there is off.
 */
$areaFlag = static function (string $key) use ($flag, $areaDefaults): bool {
    $value = env($key);

    if ($value === null || $value === '') {
        return $areaDefaults[(string) env('APP_ENV', 'production')] ?? false;
    }

    return $flag($key);
};

$interventions = (static function () use ($flag, $areaFlag): bool {
    $value = env('TBAI_SHOW_INTERVENTIONS');

    return $value === null || $value === ''
        ? $areaFlag('TBAI_SHOW_DRIVERS')
        : $flag('TBAI_SHOW_INTERVENTIONS');
})();

return [

    /*
    |--------------------------------------------------------------------------
    | Public claims
    |--------------------------------------------------------------------------
    |
    | Validated performance claims, the clinical testimonials, and the footer
    | development notice are tied to TBAI_SHOW_VALIDATED_CLAIMS. Leave it
    | false until Hira approves those claims for this site.
    |
    */

    'show_validated_claims' => $flag('TBAI_SHOW_VALIDATED_CLAIMS'),

    'show_supporter_logos' => $flag('TBAI_SHOW_SUPPORTER_LOGOS'),

    'show_partner_line' => $flag('TBAI_SHOW_PARTNER_LINE'),

    /*
    | Telehealth is not part of the current product. Leave it off.
    */

    'show_telehealth' => $flag('TBAI_SHOW_TELEHEALTH'),

    /*
    | Product areas that wait on programme sign-off. Screening is off by default.
    | Turning a flag on restores the links and the pages. While a flag is
    | off, the pages answer with the normal not found page.
    |
    | screening: the screening questionnaire, waiting on the TB programme.
    | drivers: Drivers and Interventions page of the command center.
    | interventions: the response and priority lines on that page. Follows drivers.
    | incidence: Incidence page of the command center.
    | ai_tests: AI Assisted Testing page and every AI-assisted figure.
    |
    | drivers, interventions, incidence, and ai_tests are on in local, dev, and
    | staging and off in production (and in any environment not listed in
    | area_defaults) unless the env variable says otherwise. An explicit true or
    | false in the environment always wins. area_defaults is the one place to
    | change the default for an environment.
    */

    'area_defaults' => $areaDefaults,

    'features' => [
        'screening' => $flag('TBAI_SHOW_SCREENING'),
        'drivers' => $areaFlag('TBAI_SHOW_DRIVERS'),
        'interventions' => $interventions,
        'incidence' => $areaFlag('TBAI_SHOW_INCIDENCE'),
        'ai_tests' => $areaFlag('TBAI_SHOW_AI_TESTS'),
    ],

    /*
    | Where programme roles and admins land after sign-in. Off opens the
    | detailed reports page. On opens the executive view. An explicit env
    | value always wins. Default is off in every environment.
    */

    'command_center' => [
        'landing' => $flag('TBAI_COMMAND_CENTER_LANDING'),
    ],

    /*
    | Command center copy owned by the programme, served by the API so it can
    | change without a release. The six lines per category are placeholders
    | until the programme confirms the wording.
    */
    'command' => [
        'driver_responses' => [
            'diagnostic_bottlenecks' => ['increase testing capacity', 'reduce specimen transport delays'],
            'unusual_positivity' => ['targeted screening', 'intensify contact investigation'],
            'missing_patients' => ['investigate treatment initiation gap', 'link every diagnosis to a treatment start'],
            'follow_up_risk' => ['trace interrupted patients', 'strengthen adherence support'],
            'testing_gaps' => ['improve referral completion', 'expand diagnostic access'],
            'resource_allocation' => ['prioritise high-burden BMUs', 'allocate GeneXpert and staff'],
        ],
        'default_period' => env('TBAI_COMMAND_DEFAULT_PERIOD', 'ytd'),
    ],

    // Slide photos on the BMU daily return. Stored on a private disk and
    // served only through authorized routes. No AI processing.
    'slide_photos' => [
        'disk' => env('TBAI_SLIDE_PHOTOS_DISK', 'slides'),
        'max_kilobytes' => 10240,
        'max_per_upload' => 10,
        'thumbnail_width' => 480,
    ],

    'development_notice' => 'TB-AI is in development. It is decision support, not a diagnostic device. The microscopist makes the final call.',

    'partner_line' => 'Technical partner: Soft Pyramid',

    'contact' => [
        'location' => 'Lahore, Pakistan',
        'email' => 'contact-us@dhub-ai.info',
        'reach' => 'Serving Globally',
    ],

    'neutral_tiles' => [
        [
            'title' => 'Smartphone-based',
            'body' => 'Operable on standard mid-range mobile devices.',
        ],
        [
            'title' => 'Works with existing microscopes',
            'body' => 'A technician photographs the smear on the microscope already on the bench.',
        ],
        [
            'title' => 'Built for resource-limited settings',
            'body' => 'Designed for labs and programmes that already have a microscope and a phone.',
        ],
    ],

    'validated_tiles' => [
        [
            'title' => '97% Accuracy',
            'body' => 'Rigorous clinical testing validates our model\'s performance against traditional expert microscopists.',
        ],
        [
            'title' => '< 3 Minutes',
            'body' => 'Drastically reduces patient wait times from hours or days to minutes, facilitating immediate treatment.',
        ],
        [
            'title' => 'Cost-Effective',
            'body' => 'Smear diagnostic costs are reduced to cents per test, over 10x cheaper than GeneXpert hardware.',
        ],
        [
            'title' => 'Smartphone-Based',
            'body' => 'Operable on standard mid-range mobile devices with no specialized lab machinery required.',
        ],
    ],

    'testimonials' => [
        [
            'quote' => 'TB-AI transformed our TB diagnostic workflow. What used to take 20 minutes and a skilled microscopist now takes 3 minutes with the same accuracy. This is the future of point-of-care testing.',
            'name' => 'Dr. Asif Mahmood',
            'role' => 'Head of Pulmonology, Gulab Devi Hospital',
        ],
        [
            'quote' => 'Using TB-AI in our remote outreach campaigns allowed us to screen 500 patients in a single day, something previously impossible without AI assistance.',
            'name' => 'Sarah Khan',
            'role' => 'Public Health Officer, Greenstar NGO',
        ],
        [
            'quote' => 'The integration process was smooth, and our lab technicians were fully trained in just 2 days. TB-AI has become an indispensable part of our diagnostic protocol.',
            'name' => 'Prof. Tariq Rehman',
            'role' => 'Director of Pathology, Shaukat Khanum Hospital',
        ],
    ],

    'supporters' => [
        'Falling Walls Foundation',
        'Prototypes for Humanity',
        'Dubai Future Foundation',
        'Startup Wise Guys',
        'Accelerating Asia',
        'Shaukat Khanum (SKMH)',
        'Greenstar NGO',
        'Saudi Society of Cytotechnology',
        'Gulab Devi Hospital',
        'Allied Hospitals',
    ],

];
