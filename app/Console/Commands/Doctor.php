<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * One-shot health check for a deployed server: run it after deploying (or when
 * something misbehaves) and read — or paste — the output. Checks read-only
 * state and connectivity; it never changes data or sends email.
 */
class Doctor extends Command
{
    protected $signature = 'app:doctor';

    protected $description = 'Check that this server is configured correctly (env, database, mail, Pusher, Xendit, cron)';

    private int $failures = 0;

    public function handle(): int
    {
        $this->line('<options=bold>A&J CITY OASIS deployment check</>');

        $this->section('Application');
        $this->check('APP_ENV is production', app()->isProduction(), 'Currently "'.app()->environment().'"', warnOnly: true);
        $this->check('APP_DEBUG is off', ! config('app.debug'), 'Debug pages would show secrets to visitors — set APP_DEBUG=false');
        $this->check('APP_KEY is set', filled(config('app.key')), 'Run: php artisan key:generate');
        $this->check('APP_URL uses https', str_starts_with((string) config('app.url'), 'https://'), 'APP_URL is "'.config('app.url').'"', warnOnly: true);
        $this->check('Config is cached', app()->configurationIsCached(), 'Run: php artisan optimize', warnOnly: true);

        $this->section('Database');
        try {
            DB::connection()->getPdo();
            $this->check('Connected to '.DB::connection()->getDatabaseName(), true);

            $ran = DB::table('migrations')->pluck('migration')->all();
            $pending = collect(glob(database_path('migrations/*.php')))
                ->map(fn ($f) => basename($f, '.php'))
                ->diff($ran);
            $this->check('No pending migrations', $pending->isEmpty(), 'Pending: '.$pending->implode(', ').' — run: php artisan migrate --force');
            $this->check('Rooms exist', DB::table('rooms')->exists(), 'Run: php artisan db:seed --force', warnOnly: true);
            $this->check('An admin account exists', DB::table('users')->where('role', 'admin')->exists(), 'Run: php artisan app:create-admin you@example.com');
            $this->check(
                'Demo "password" accounts are absent',
                ! DB::table('users')->whereIn('email', ['admin@ajoasis.test', 'staff@ajoasis.test'])->exists() || ! app()->isProduction(),
                'Delete admin@ajoasis.test / staff@ajoasis.test — their password is public',
            );
        } catch (Throwable $e) {
            $this->check('Database connection', false, $this->short($e));
        }

        $this->section('Files');
        $this->check('public/storage link exists (room photos)', is_link(public_path('storage')) || is_dir(public_path('storage')), 'Run: ln -s ../storage/app/public public/storage  (artisan storage:link needs symlink(), which shared hosts disable)');
        $this->check('storage/ is writable', is_writable(storage_path('logs')) && is_writable(storage_path('framework/cache')), 'Fix permissions: chmod -R 775 storage bootstrap/cache');

        $this->section('Scheduler (cron)');
        try {
            $last = Cache::get('scheduler:heartbeat');
            $this->check(
                'Cron ran in the last 5 minutes'.($last ? ' (last: '.$last.')' : ''),
                // past->diffInMinutes(now) is positive; Carbon 3 returns a signed value
                // for now->diffInMinutes(past), which would always look "recent".
                $last && Carbon::parse($last)->diffInMinutes(now()) <= 5,
                'Add a cron job running every minute: php '.base_path('artisan').' schedule:run',
            );
        } catch (Throwable $e) {
            // The cache is stored in the database — keep going so the mail,
            // Pusher and Xendit checks below still run.
            $this->check('Read scheduler heartbeat', false, 'Cache unavailable: '.$this->short($e));
        }

        $this->section('Email');
        if (config('mail.default') === 'smtp') {
            try {
                $transport = Mail::mailer('smtp')->getSymfonyTransport();
                $transport->start();
                $transport->stop();
                $this->check('SMTP login to '.config('mail.mailers.smtp.host'), true);
            } catch (Throwable $e) {
                $this->check('SMTP login to '.config('mail.mailers.smtp.host'), false, $this->short($e));
            }
        } else {
            $this->check('MAIL_MAILER is smtp', false, 'Currently "'.config('mail.default').'" — emails are not being delivered', warnOnly: true);
        }

        $this->section('Real-time (Pusher)');
        if (config('broadcasting.default') === 'pusher') {
            try {
                Broadcast::connection()->getPusher()->get('/channels');
                $this->check('Pusher credentials accepted', true);
            } catch (Throwable $e) {
                $this->check('Pusher credentials accepted', false, $this->short($e));
            }
        } else {
            $this->check('BROADCAST_CONNECTION is pusher', false, 'Currently "'.config('broadcasting.default').'" — no live notifications', warnOnly: true);
        }

        $this->section('Payments (Xendit)');
        $key = (string) config('xendit.secret_key');
        if (config('xendit.fake_mode')) {
            $this->check('XENDIT_FAKE_MODE is off', false, 'Payments are simulated — no Xendit checkout page', warnOnly: true);
        } else {
            // The client approved demo payments only — a live key would charge real money.
            $this->check('Secret key is a TEST key (xnd_development_)', str_starts_with($key, 'xnd_development_'), 'Use the Test mode key from the Xendit dashboard, never a live one');
            try {
                $ok = Http::withBasicAuth($key, '')->timeout(10)->get('https://api.xendit.co/balance')->successful();
                $this->check('Xendit accepts the key', $ok, 'Xendit rejected the key — check XENDIT_SECRET_KEY');
            } catch (Throwable $e) {
                $this->check('Xendit reachable', false, $this->short($e));
            }
            $this->check('Webhook callback token is set', filled(config('xendit.callback_token')), 'Set XENDIT_CALLBACK_TOKEN from Xendit > Settings > Webhooks', warnOnly: true);
            $this->line('    Webhook URL for the Xendit dashboard: <comment>'.rtrim((string) config('app.url'), '/').'/api/webhooks/xendit</comment>');
        }

        return $this->finish();
    }

    private function finish(): int
    {
        $this->newLine();
        if ($this->failures === 0) {
            $this->info('All required checks passed.');

            return self::SUCCESS;
        }

        $this->error("{$this->failures} required check(s) failed — fix the ✗ items above.");

        return self::FAILURE;
    }

    private function section(string $title): void
    {
        $this->newLine();
        $this->line("<fg=cyan>{$title}</>");
    }

    private function check(string $label, bool $ok, string $hint = '', bool $warnOnly = false): void
    {
        if ($ok) {
            $this->line("  <fg=green>✓</> {$label}");

            return;
        }

        if ($warnOnly) {
            $this->line("  <fg=yellow>!</> {$label}".($hint ? " <fg=gray>— {$hint}</>" : ''));

            return;
        }

        $this->failures++;
        $this->line("  <fg=red>✗</> {$label}".($hint ? " <fg=gray>— {$hint}</>" : ''));
    }

    private function short(Throwable $e): string
    {
        return mb_strimwidth(preg_replace('/\s+/', ' ', $e->getMessage()), 0, 160, '…');
    }
}
