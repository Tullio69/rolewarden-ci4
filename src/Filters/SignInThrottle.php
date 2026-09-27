<?php

declare(strict_types=1);

namespace RoleWarden\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use RoleWarden\Settings\SignIn;

/**
 * Filter "rw-signin", put on Shield's login route by Config\Registrar. Two
 * limits from the Settings screen, checked before Shield sees the form:
 *
 * - sign-in posts per minute from one IP address, on CodeIgniter's throttler,
 *   like Shield's fixed "auth-rates" filter but configurable;
 * - a lock on the email after too many failed sign-ins, counted from the
 *   attempts Shield records in auth_logins. It is counted per email typed, not
 *   per account, so it says nothing about whether the email is registered.
 */
class SignInThrottle implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null): ?RedirectResponse
    {
        if (! $request instanceof IncomingRequest || $request->getMethod() !== 'POST') {
            return null;
        }

        helper('setting');

        if (service('throttler')->check('rw-signin-' . md5($request->getIPAddress()), (int) setting('RoleWarden.signInRate'), 60) === false) {
            return redirect()->back()->withInput()->with('error', lang('RoleWarden.signIn.tooMany'));
        }

        $email = trim((string) $request->getPost('email'));

        if ($email === '') {
            return null;
        }

        $minutes = (int) setting('RoleWarden.lockMinutes');
        $attempts = (int) setting('RoleWarden.lockAttempts');
        $logins = db_connect()->table(config('Auth')->tables['logins']);
        $since = date('Y-m-d H:i:s', time() - $minutes * 60);
        $lastSuccess = $logins->select('date')->where('identifier', $email)->where('success', 1)->orderBy('date', 'desc')->limit(1)->get()->getRow('date');

        if ($lastSuccess !== null && $lastSuccess > $since) {
            $since = $lastSuccess;
        }

        $failures = array_map(
            static fn (array $row): int => (int) strtotime($row['date']),
            $logins->select('date')->where('identifier', $email)->where('success', 0)->where('date >', $since)
                ->orderBy('date', 'desc')->limit($attempts)->get()->getResultArray(),
        );

        $wait = SignIn::lockedFor($failures, $attempts, $minutes, time());

        if ($wait > 0) {
            return redirect()->back()->withInput()->with('error', lang('RoleWarden.signIn.locked', [(int) ceil($wait / 60)]));
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): void
    {
    }
}
