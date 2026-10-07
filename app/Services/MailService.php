<?php

namespace App\Services;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Every transactional email goes through here so a slow or failing SMTP server
 * can never break the booking/payment/billing action that triggered it.
 *
 * - After commit: several sends happen inside DB transactions (e.g.
 *   markBookingPaid). Sending there means an SMTP error would roll back a
 *   booking Xendit has already charged for — and an email could go out for a
 *   change that was later rolled back.
 * - After the response: SMTP round-trips (plus one per tenant for
 *   announcements) run once the page has been sent, not while the user waits.
 *   Artisan commands run these callbacks when the command finishes.
 * - rescue(): a failed send is logged, never thrown.
 *
 * No queue worker is needed, which keeps this working under `php artisan serve`
 * and on shared hosting.
 */
class MailService
{
    public function send(string $to, Mailable $mailable): void
    {
        DB::afterCommit(function () use ($to, $mailable) {
            // Terminating callbacks are never removed, so a process that serves
            // several requests (tests, a long-running worker) would re-run this
            // on every later request — send exactly once.
            $sent = false;

            app()->terminating(function () use ($to, $mailable, &$sent) {
                if ($sent) {
                    return;
                }
                $sent = true;

                rescue(fn () => Mail::to($to)->send($mailable), report: true);
            });
        });
    }
}
