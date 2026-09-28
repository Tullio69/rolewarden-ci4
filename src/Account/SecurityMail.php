<?php

declare(strict_types=1);

namespace RoleWarden\Account;

use CodeIgniter\Events\Events;
use CodeIgniter\HTTP\UserAgent;
use CodeIgniter\Shield\Entities\User;
use RoleWarden\Models\UserModel;
use RoleWarden\Settings\SignIn;

/**
 * Security emails (SPEC "Sistema di notifiche", v1.0): sign-in from a new
 * device or IP, password changed, too many failed sign-ins.
 *
 * Messages are queued during the request and sent once the response has gone
 * out: from a shutdown function, after fastcgi_finish_request() where the
 * server has it (CodeIgniter's post_system still runs before the response).
 * Each one fires "rolewarden.mail" first; a listener returning false takes it
 * over, e.g. to put it on the host's own queue, and it is not sent here.
 * Templates are views under RoleWarden\Views\emails, overridable like the panel's.
 */
final class SecurityMail
{
    /** @var list<array{to: string, subject: string, view: string, data: array<string, mixed>}> */
    private static array $queue = [];

    /**
     * Shield's login event. The attempt being completed is already in
     * auth_logins, so it is left out of the comparison.
     */
    public static function onLogin(User $user): void
    {
        $request = service('request');
        $ip = $request->getIPAddress();
        $userAgent = (string) $request->getUserAgent();
        $past = db_connect()->table(config('Auth')->tables['logins'])
            ->select('ip_address, user_agent, date')->where('user_id', $user->id)->where('success', 1)
            ->orderBy('id', 'desc')->limit(500)->get()->getResultArray();

        if ($past !== [] && $past[0]['ip_address'] === $ip && (string) $past[0]['user_agent'] === $userAgent && strtotime($past[0]['date']) >= time() - 60) {
            array_shift($past);
        }

        $seen = array_map(static fn (array $row): array => [(string) $row['ip_address'], self::device((string) $row['user_agent'])], $past);
        $device = self::device($userAgent);

        if (self::isNewAccess($ip, $device, $seen)) {
            self::queue($user, 'newSignIn', 'new_sign_in', ['ip' => $ip, 'device' => $device, 'at' => date('Y-m-d H:i')]);
        }
    }

    /**
     * Shield's failedLogin event: the failure is already recorded, so the
     * alert goes out exactly once, when the count reaches the lock threshold.
     *
     * @param array<string, mixed> $credentials
     */
    public static function onFailedLogin(array $credentials): void
    {
        helper('setting');
        $email = is_string($credentials['email'] ?? null) ? trim($credentials['email']) : '';

        if ($email === '' || ! self::lockJustStarted(count(SignIn::recentFailures($email)), (int) setting('RoleWarden.lockAttempts'))) {
            return;
        }

        $data = ['email' => $email, 'minutes' => (int) setting('RoleWarden.lockMinutes'), 'ip' => service('request')->getIPAddress()];
        $recipients = [];
        $owner = model(UserModel::class)->findByCredentials(['email' => $email]);

        if ($owner !== null) {
            $recipients[(int) $owner->id] = $owner;
        }

        foreach (self::alertRecipients() as $admin) {
            $recipients[(int) $admin->id] ??= $admin;
        }

        foreach ($recipients as $recipient) {
            self::queue($recipient, 'tooManyAttempts', 'too_many_attempts', $data + ['isOwner' => $owner !== null && (int) $owner->id === (int) $recipient->id]);
        }
    }

    public static function passwordChanged(User $user, bool $byAdmin): void
    {
        self::queue($user, 'passwordChanged', 'password_changed', ['byAdmin' => $byAdmin, 'at' => date('Y-m-d H:i')]);
    }

    /**
     * Whether ($ip, $device) is new for a user whose earlier successful
     * sign-ins are $seen: either one never appeared before. With no earlier
     * sign-in there is nothing to compare with, so it is not new.
     *
     * @param list<array{0: string, 1: string}> $seen [ip, device] pairs
     */
    public static function isNewAccess(string $ip, string $device, array $seen): bool
    {
        if ($seen === []) {
            return false;
        }

        return ! in_array($ip, array_column($seen, 0), true) || ! in_array($device, array_column($seen, 1), true);
    }

    public static function lockJustStarted(int $failures, int $attempts): bool
    {
        return $attempts > 0 && $failures === $attempts;
    }

    /**
     * Sends the queued messages. Registered as a shutdown function by queue().
     */
    public static function flush(): void
    {
        if (self::$queue === []) {
            return;
        }

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }

        $config = config('Email');

        foreach (self::$queue as $message) {
            if (Events::trigger('rolewarden.mail', $message) === false) {
                continue;
            }

            if ($config->fromEmail === '') {
                log_message('warning', 'RoleWarden: security email not sent, Config\Email::$fromEmail is empty.');

                continue;
            }

            $email = service('email', null, false);
            $email->setFrom($config->fromEmail, $config->fromName);
            $email->setTo($message['to']);
            $email->setSubject($message['subject']);
            $email->setMailType('html');
            $email->setMessage(view('RoleWarden\Views\emails\\' . $message['view'], $message['data']));

            if (! $email->send(false)) {
                log_message('error', 'RoleWarden: security email to {to} not sent.', ['to' => $message['to']]);
            }
        }

        self::$queue = [];
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function queue(object $user, string $subjectKey, string $view, array $data): void
    {
        $to = (string) ($user->email ?? '');

        if ($to === '') {
            return;
        }

        if (self::$queue === []) {
            register_shutdown_function([self::class, 'flush']);
        }

        $app = (string) (parse_url(config('App')->baseURL, PHP_URL_HOST) ?: 'RoleWarden');
        self::$queue[] = [
            'to' => $to,
            'subject' => lang('RoleWarden.mail.' . $subjectKey . '.subject', [$app]),
            'view' => $view,
            'data' => $data + ['app' => $app, 'name' => ActivityLog::userLabel($user)],
        ];
    }

    /**
     * Users holding security.alerts. Candidates are users with any role or a
     * granted override; the resolver has the last word (inheritance, super
     * admin, denials, deactivated accounts).
     *
     * @return list<object>
     */
    private static function alertRecipients(): array
    {
        $cfg = config('RoleWarden');
        $db = db_connect();
        $ids = array_unique(array_map('intval', array_merge(
            array_column($db->table($cfg->table('user_roles'))->select('user_id')->get()->getResultArray(), 'user_id'),
            array_column($db->table($cfg->table('user_permissions'))->select('user_id')->where('granted', 1)->get()->getResultArray(), 'user_id'),
        )));
        $resolver = service('rolewarden');
        $holders = array_values(array_filter($ids, static fn (int $id): bool => $resolver->can($id, 'security.alerts')));

        return $holders === [] ? [] : model(UserModel::class)->whereIn('id', $holders)->findAll();
    }

    /** Browser and system, without versions: what a person calls "my device". */
    private static function device(string $userAgent): string
    {
        $agent = new UserAgent();
        $agent->parse($userAgent);
        $browser = $agent->getBrowser();
        $platform = $agent->getPlatform();

        return $browser === '' && $platform === '' ? $userAgent : trim($browser . ' / ' . $platform);
    }
}
