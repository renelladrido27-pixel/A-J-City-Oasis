<?php

return [
    'secret_key' => env('XENDIT_SECRET_KEY'),
    'callback_token' => env('XENDIT_CALLBACK_TOKEN'),

    /*
     * TEMPORARY test-run switch. When true, no real Xendit API call is made —
     * "paying" an invoice immediately marks it PAID via the same completion
     * path the real webhook uses. Turn this off once Xendit is unblocked.
     */
    'fake_mode' => env('XENDIT_FAKE_MODE', false),
];
