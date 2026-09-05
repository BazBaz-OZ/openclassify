<?php

return [
    'plans' => [
        'free' => [
            'name' => 'Free',
            'price_monthly' => 0,
            'ai_scans' => 3,
            'ai_period' => 'lifetime',
            'active_virtual_garages' => 1,
            'virtual_garage_photos' => 5,
        ],

        'member' => [
            'name' => 'SMJ Member',
            'price_monthly' => 6.99,
            'stripe_price_id' => env('STRIPE_MEMBER_PRICE_ID'),
            'ai_scans' => 40,
            'ai_period' => 'monthly',
            'active_virtual_garages' => 5,
            'virtual_garage_photos' => 30,
        ],

        'pro' => [
            'name' => 'SMJ Pro',
            'price_monthly' => 14.99,
            'stripe_price_id' => env('STRIPE_PRO_PRICE_ID'),
            'ai_scans' => 150,
            'ai_period' => 'monthly',
            'active_virtual_garages' => 20,
            'virtual_garage_photos' => 100,
        ],
    ],
];
