<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\AndroidApp;
use Illuminate\Http\JsonResponse;

/**
 * Tells the Android app what the newest published build is, so it can offer an
 * in-app update instead of the tenant having to be sent a new APK by hand.
 * "build": 0 means nothing is published.
 */
class AppVersionController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(AndroidApp::latest() ?? ['build' => 0, 'version' => null, 'apk_url' => null, 'notes' => null]);
    }
}
