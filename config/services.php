<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'contextApi' => [
        'baseUrl' => env('CONTEXT_API_BASE_URL'),
    ],

    'availabilityApi' => [
        'baseUrl' => env('AVAILABILITY_API_BASE_URL'),
    ],

    'mockBrokerApi' => [
        'baseUrl' => env('MOCK_BROKER_API_BASE_URL'),
    ],
];
