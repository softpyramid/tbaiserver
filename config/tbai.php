<?php

$flag = static function (string $key): bool {
    $value = env($key, false);

    return filter_var($value, FILTER_VALIDATE_BOOLEAN);
};

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
