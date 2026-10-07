<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * The Android build currently published for download / in-app update.
 *
 * Publishing = two files in storage/app/public/app/ (mobile/release.sh uploads
 * both): the APK, and version.json
 *   {"build": 2, "version": "1.0.1", "file": "AJ-City-Oasis-1.0.1.apk", "notes": "…"}
 */
class AndroidApp
{
    /**
     * @return array{build: int, version: string, apk_url: string, notes: ?string}|null
     */
    public static function latest(): ?array
    {
        $disk = Storage::disk('public');
        $info = $disk->exists('app/version.json') ? json_decode($disk->get('app/version.json'), true) : null;

        if (! is_array($info) || empty($info['build']) || empty($info['file']) || ! $disk->exists('app/'.$info['file'])) {
            return null;
        }

        return [
            'build' => (int) $info['build'],
            'version' => (string) ($info['version'] ?? ''),
            // asset(), not Storage::url(): built from the request's host (see RoomImage::url()).
            'apk_url' => asset('storage/app/'.$info['file']),
            'notes' => $info['notes'] ?? null,
        ];
    }
}
