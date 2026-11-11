<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Display timezone
    |--------------------------------------------------------------------------
    |
    | Timestamps are stored in UTC. The operator works in India, and IndiaMART
    | sends arrival times as Asia/Kolkata wall clock, so every screen renders
    | times in this zone. Keep storage in UTC: only presentation is localised.
    |
    */

    'display_timezone' => env('DISPLAY_TIMEZONE', 'Asia/Kolkata'),

    /*
    |--------------------------------------------------------------------------
    | Scoring policy
    |--------------------------------------------------------------------------
    |
    | The floor is the matcher score at or above which a catalogue candidate is
    | treated as real. PRD 5.2.2 sets the initial value at 0.55. The policy
    | version is stored on every assessment so a band can always be traced back
    | to the rules that produced it.
    |
    */

    'matcher_floor' => (float) env('MATCHER_FLOOR', 0.55),

    'policy_version' => env('POLICY_VERSION', 'v1.1.0'),

    /*
    |--------------------------------------------------------------------------
    | Domestic enquiries
    |--------------------------------------------------------------------------
    |
    | This is a third-party exporter: it sells out of India, not into India.
    | Enquiries from an Indian buyer are recorded as a destination hard reject.
    | The switch exists because a domestic trader buying for re-export is
    | otherwise a legitimate buyer, and the operator may decide to keep those.
    |
    */

    'reject_domestic' => (bool) env('REJECT_DOMESTIC', true),

    /*
    |--------------------------------------------------------------------------
    | Operator identity used in drafts
    |--------------------------------------------------------------------------
    |
    | Every draft is signed by a named desk, not by an anonymous "sales team".
    | Keep the phone and email here rather than in a template so a change is
    | made once and the fact checker keeps treating them as traceable facts.
    |
    */

    'operator' => [
        'company' => env('OPERATOR_COMPANY', 'Bliss Pharmex'),
        'desk' => env('OPERATOR_DESK', 'Bliss Pharmex Export Desk'),
        'city' => env('OPERATOR_CITY', 'Nagpur'),
        'country' => env('OPERATOR_COUNTRY', 'India'),
        'phone' => env('OPERATOR_PHONE', '+91 78210 27619'),
        'email' => env('OPERATOR_EMAIL', 'exports@blisspharmex.in'),
    ],

];
