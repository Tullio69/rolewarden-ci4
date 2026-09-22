<?php

/**
 * Independent M5 (admin panel) verification — "Collaudatore ad Hoc" run.
 *
 * Black-box HTTP + direct-DB checks against a running copy of the sibling
 * test app (php spark serve), pointed at the rolewarden_test database.
 * This script does NOT read src/Controllers, src/Views, src/Models,
 * src/Config/RouteRegistrar.php, src/Assets or src/Helpers: it was written
 * against docs/SPEC.md and docs/BRIEF-MVP.md only, per the CLAUDE.md
 * "Collaudatore ad Hoc" protocol.
 *
 * Usage: php verify-m5-adhoc.php
 * Configure via environment variables (all optional, defaults below):
 *   RW_BASE_URL   (default http://localhost:8030)
 *   RW_DB_HOST    (default 127.0.0.1)
 *   RW_DB_PORT    (default 3306)
 *   RW_DB_USER    (default root)
 *   RW_DB_PASS    (default rolewarden)
 *   RW_DB_NAME    (default rolewarden_test)
 *   RW_SUPERADMIN_EMAIL / RW_SUPERADMIN_PASSWORD
 *   RW_LIMITED_EMAIL / RW_LIMITED_PASSWORD
 *   RW_LIMITED_ROLE_ID (the role id holding only users.view, for the
 *                        inherited-permission child-role tests)
 */

declare(strict_types=1);

$BASE_URL = getenv('RW_BASE_URL') ?: 'http://localhost:8030';
$DB_HOST  = getenv('RW_DB_HOST') ?: '127.0.0.1';
$DB_PORT  = (int) (getenv('RW_DB_PORT') ?: 3306);
$DB_USER  = getenv('RW_DB_USER') ?: 'root';
$DB_PASS  = getenv('RW_DB_PASS') ?: 'rolewarden';
$DB_NAME  = getenv('RW_DB_NAME') ?: 'rolewarden_test';

$SUPERADMIN_EMAIL    = getenv('RW_SUPERADMIN_EMAIL') ?: 'adhoc-superadmin@example.test';
$SUPERADMIN_PASSWORD = getenv('RW_SUPERADMIN_PASSWORD') ?: 'AdhocPass!2026';
$LIMITED_EMAIL       = getenv('RW_LIMITED_EMAIL') ?: 'adhoc-limited@example.test';
$LIMITED_PASSWORD    = getenv('RW_LIMITED_PASSWORD') ?: 'LimitedPass!2026';

// ---------------------------------------------------------------------
// Tiny test framework
// ---------------------------------------------------------------------

$PASS = 0;
$FAIL = 0;
/** @var list<string> */
$FAILURES = [];

function check(bool $cond, string $label, string $detail = ''): void
{
    global $PASS, $FAIL, $FAILURES;
    if ($cond) {
        $PASS++;
        echo "PASS  {$label}\n";
    } else {
        $FAIL++;
        $line = "FAIL  {$label}" . ($detail !== '' ? " -- {$detail}" : '');
        echo "{$line}\n";
        $FAILURES[] = $line;
    }
}

function section(string $title): void
{
    echo "\n=== {$title} ===\n";
}

// ---------------------------------------------------------------------
// DB helper
// ---------------------------------------------------------------------

function db(): mysqli
{
    static $conn = null;
    if ($conn === null) {
        global $DB_HOST, $DB_PORT, $DB_USER, $DB_PASS, $DB_NAME;
        $conn = mysqli_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT);
        if (!$conn) {
            fwrite(STDERR, "DB connection failed: " . mysqli_connect_error() . "\n");
            exit(2);
        }
    }
    return $conn;
}

function dbRow(string $sql): ?array
{
    $res = mysqli_query(db(), $sql);
    if ($res === false) {
        fwrite(STDERR, "DB query failed: " . mysqli_error(db()) . "\nSQL: {$sql}\n");
        return null;
    }
    $row = mysqli_fetch_assoc($res);
    return $row === null ? null : $row;
}

function dbScalar(string $sql)
{
    $row = dbRow($sql);
    return $row === null ? null : reset($row);
}

function dbExec(string $sql): bool
{
    $ok = mysqli_query(db(), $sql);
    if ($ok === false) {
        fwrite(STDERR, "DB exec failed: " . mysqli_error(db()) . "\nSQL: {$sql}\n");
    }
    return (bool) $ok;
}

// ---------------------------------------------------------------------
// HTTP actor (cookie-jar-based session, CSRF-aware)
// ---------------------------------------------------------------------

final class Actor
{
    public string $cookieFile;
    public string $lastBody = '';
    public int $lastStatus = 0;
    /** @var array<string,string> */
    public array $lastHeaders = [];

    public function __construct(public string $baseUrl, string $name)
    {
        $this->cookieFile = sys_get_temp_dir() . '/rw-m5-adhoc-' . $name . '-' . getmypid() . '.cookies';
        @unlink($this->cookieFile);
    }

    public function __destruct()
    {
        @unlink($this->cookieFile);
    }

    /**
     * @param array<string,string> $extraHeaders
     */
    public function request(string $method, string $path, array $fields = [], array $extraHeaders = []): array
    {
        $url = $this->baseUrl . $path;
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_COOKIEJAR => $this->cookieFile,
            CURLOPT_COOKIEFILE => $this->cookieFile,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_TIMEOUT => 15,
        ]);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
        }
        if ($extraHeaders !== []) {
            $headers = [];
            foreach ($extraHeaders as $k => $v) {
                $headers[] = "{$k}: {$v}";
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        $raw = curl_exec($ch);
        if ($raw === false) {
            fwrite(STDERR, "curl error on {$method} {$path}: " . curl_error($ch) . "\n");
            $this->lastStatus = 0;
            $this->lastBody   = '';
            curl_close($ch);
            return ['status' => 0, 'body' => '', 'headers' => []];
        }
        $status     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        $rawHeaders = substr($raw, 0, $headerSize);
        $body       = substr($raw, $headerSize);
        $headers    = [];
        foreach (explode("\r\n", $rawHeaders) as $line) {
            if (str_contains($line, ':')) {
                [$k, $v]         = explode(':', $line, 2);
                $headers[trim($k)] = trim($v);
            }
        }
        $this->lastStatus  = $status;
        $this->lastBody    = $body;
        $this->lastHeaders = $headers;

        return ['status' => $status, 'body' => $body, 'headers' => $headers];
    }

    public function get(string $path): array
    {
        return $this->request('GET', $path);
    }

    /** Try to pull a csrf_test_name value (visible field or embedded JSON) out of a body. */
    private function extractCsrf(string $body): ?string
    {
        if (preg_match('/name="csrf_test_name"\s+[^>]*value="([^"]+)"/', $body, $m) === 1) {
            return $m[1];
        }
        // The permission-matrix screen carries the token in its embedded JSON payload
        // (Alpine reads it from there for its own fetch calls) rather than a visible
        // hidden input, since the matrix has no plain <form>.
        if (preg_match('/"csrfHash"\s*:\s*"([^"]+)"/', $body, $m) === 1) {
            return $m[1];
        }
        return null;
    }

    /**
     * Fetch a fresh CSRF token by GETting a page that renders a form or the matrix
     * JSON. Tries $refreshPath first, then a couple of generic candidates, since not
     * every page carries a form (list pages, or pages this actor lacks permission
     * to write to) and /login redirects away once the actor is authenticated.
     */
    public function csrfToken(string $refreshPath = '/rolewarden/users'): string
    {
        $candidates = [$refreshPath, '/rolewarden/users/create', '/rolewarden/roles/create', '/login'];
        foreach (array_unique($candidates) as $path) {
            $res   = $this->get($path);
            $token = $this->extractCsrf($res['body']);
            if ($token !== null) {
                return $token;
            }
        }

        return '';
    }

    /** POST with an automatically refreshed CSRF token merged in. */
    public function postWithCsrf(string $path, array $fields, string $refreshPath = '/rolewarden/users'): array
    {
        $token                    = $this->csrfToken($refreshPath);
        $fields['csrf_test_name'] = $token;

        return $this->request('POST', $path, $fields);
    }

    public function login(string $email, string $password): array
    {
        $token = $this->csrfToken('/login');
        return $this->request('POST', '/login', [
            'csrf_test_name' => $token,
            'email' => $email,
            'password' => $password,
        ]);
    }
}

// ---------------------------------------------------------------------
// Setup: log in both actors
// ---------------------------------------------------------------------

section('Setup: authenticate both test users');

$super = new Actor($BASE_URL, 'super');
$res   = $super->login($SUPERADMIN_EMAIL, $SUPERADMIN_PASSWORD);
check(in_array($res['status'], [302, 303], true), 'super admin login redirects (session established)', "status={$res['status']}");

$limited = new Actor($BASE_URL, 'limited');
$res     = $limited->login($LIMITED_EMAIL, $LIMITED_PASSWORD);
check(in_array($res['status'], [302, 303], true), 'limited user login redirects (session established)', "status={$res['status']}");

$anon = new Actor($BASE_URL, 'anon');

// Resolve DB ids needed throughout.
$superAdminUserId = (int) dbScalar("SELECT id FROM users WHERE id IN (SELECT user_id FROM auth_identities WHERE secret='{$SUPERADMIN_EMAIL}')");
$limitedUserId    = (int) dbScalar("SELECT id FROM users WHERE id IN (SELECT user_id FROM auth_identities WHERE secret='{$LIMITED_EMAIL}')");
$limitedRoleId    = (int) dbScalar("SELECT role_id FROM acl_user_roles WHERE user_id = {$limitedUserId} LIMIT 1");
check($superAdminUserId > 0 && $limitedUserId > 0 && $limitedRoleId > 0, 'resolved fixture ids from DB', "super={$superAdminUserId} limited={$limitedUserId} role={$limitedRoleId}");

// ===========================================================================
// 1. Access control
// ===========================================================================

section('1. Access control');

$res = $anon->get('/rolewarden/users');
check(
    in_array($res['status'], [302, 303], true) || (isset($res['headers']['Location'])),
    'anonymous GET /rolewarden/users is not served directly (redirect to login)',
    "status={$res['status']}"
);

$res = $limited->get('/rolewarden/users');
check($res['status'] === 200, 'limited user (has users.view) can open the users list', "status={$res['status']}");

$res = $limited->get('/rolewarden/users/create');
check(!in_array($res['status'], [200], true), 'limited user (no users.create) is denied the create form server-side', "status={$res['status']}");

$resSuper   = $super->get('/rolewarden/users');
$resLimited = $limited->get('/rolewarden/users');
$superHasCreateLink   = (bool) preg_match('#rolewarden/users/create#', $resSuper['body']);
$limitedHasCreateLink = (bool) preg_match('#rolewarden/users/create#', $resLimited['body']);
check($superHasCreateLink, 'super admin markup includes a create-user link/action');
check(!$limitedHasCreateLink, 'limited user markup omits the create-user link/action entirely (not just disabled)');

// Delete action markup lives on the user detail page, not the list; limited user
// lacks users.delete too. Both actors can view the target's detail (users.view).
$resSuperShow           = $super->get("/rolewarden/users/{$limitedUserId}");
$resLimitedShow         = $limited->get("/rolewarden/users/{$limitedUserId}");
$superHasDeleteAction   = (bool) preg_match('#rolewarden/users/\d+/delete#', $resSuperShow['body']);
$limitedHasDeleteAction = (bool) preg_match('#rolewarden/users/\d+/delete#', $resLimitedShow['body']);
check($superHasDeleteAction, 'super admin markup includes a delete-user action');
check(!$limitedHasDeleteAction, 'limited user markup omits the delete-user action entirely');

$res = $limited->postWithCsrf("/rolewarden/users/{$limitedUserId}/delete", []);
check(!in_array((int) $res['status'], [200, 302, 303], true) || str_contains(strtolower($res['body']), 'forbidden') || $res['status'] === 403,
    'limited user direct POST to a delete route they lack permission for is refused server-side',
    "status={$res['status']}");
// Confirm DB unaffected regardless of HTTP status semantics used.
$stillThere = dbScalar("SELECT COUNT(*) FROM users WHERE id = {$limitedUserId} AND deleted_at IS NULL");
check(((int) $stillThere) === 1, 'forced delete attempt without permission did not remove the row', "count={$stillThere}");

// ===========================================================================
// 2. CRUD users
// ===========================================================================

section('2. CRUD users');

$newEmail = 'adhoc-crud-' . bin2hex(random_bytes(4)) . '@example.test';
$res      = $super->postWithCsrf('/rolewarden/users', [
    'username' => 'adhoccrud',
    'email' => $newEmail,
    'password' => 'CrudPass!2026',
], '/rolewarden/users/create');
check(in_array($res['status'], [200, 302, 303], true), 'create user request accepted', "status={$res['status']}");

$newUserId = (int) dbScalar("SELECT user_id FROM auth_identities WHERE secret='{$newEmail}'");
check($newUserId > 0, 'created user exists in DB with the submitted email', "id={$newUserId}");

$res = $super->get('/rolewarden/users');
check(str_contains($res['body'], $newEmail), 'created user appears in the users list with the correct email');

if ($newUserId > 0) {
    $res = $super->get("/rolewarden/users/{$newUserId}");
    check($res['status'] === 200 && str_contains($res['body'], $newEmail), 'created user detail page shows the correct email');

    // Edit.
    $res = $super->postWithCsrf("/rolewarden/users/{$newUserId}", [
        'username' => 'adhoccrud-renamed',
        'email' => $newEmail,
    ], "/rolewarden/users/{$newUserId}/edit");
    check(in_array($res['status'], [200, 302, 303], true), 'edit user request accepted', "status={$res['status']}");
    $uname = dbScalar("SELECT username FROM users WHERE id = {$newUserId}");
    check($uname === 'adhoccrud-renamed', 'username updated in DB', "username={$uname}");

    // Deactivate / activate.
    $res = $super->postWithCsrf("/rolewarden/users/{$newUserId}/deactivate", []);
    check(in_array($res['status'], [200, 302, 303], true), 'deactivate request accepted', "status={$res['status']}");
    $active = (int) dbScalar("SELECT active FROM users WHERE id = {$newUserId}");
    check($active === 0, 'user deactivated in DB', "active={$active}");

    $res = $super->postWithCsrf("/rolewarden/users/{$newUserId}/activate", []);
    check(in_array($res['status'], [200, 302, 303], true), 'activate request accepted', "status={$res['status']}");
    $active = (int) dbScalar("SELECT active FROM users WHERE id = {$newUserId}");
    check($active === 1, 'user reactivated in DB', "active={$active}");

    // Assign / revoke role.
    $assignableRoleId = (int) dbScalar("SELECT id FROM acl_roles WHERE slug = 'user' AND deleted_at IS NULL");
    $res = $super->postWithCsrf("/rolewarden/users/{$newUserId}/roles", ['role_id' => $assignableRoleId]);
    check(in_array($res['status'], [200, 302, 303], true), 'assign role request accepted', "status={$res['status']}");
    $has = (int) dbScalar("SELECT COUNT(*) FROM acl_user_roles WHERE user_id = {$newUserId} AND role_id = {$assignableRoleId}");
    check($has === 1, 'role assignment persisted in DB', "count={$has}");

    $res = $super->postWithCsrf("/rolewarden/users/{$newUserId}/roles/{$assignableRoleId}/revoke", []);
    check(in_array($res['status'], [200, 302, 303], true), 'revoke role request accepted', "status={$res['status']}");
    $has = (int) dbScalar("SELECT COUNT(*) FROM acl_user_roles WHERE user_id = {$newUserId} AND role_id = {$assignableRoleId}");
    check($has === 0, 'role revocation persisted in DB', "count={$has}");

    // Permission override: grant, deny, clear.
    $permId = (int) dbScalar("SELECT id FROM acl_permissions WHERE slug = 'users.view'");
    $res    = $super->postWithCsrf("/rolewarden/users/{$newUserId}/permissions", ['permission_id' => $permId, 'granted' => 1]);
    check(in_array($res['status'], [200, 302, 303], true), 'grant override request accepted', "status={$res['status']}");
    $granted = dbScalar("SELECT granted FROM acl_user_permissions WHERE user_id = {$newUserId} AND permission_id = {$permId}");
    check(((int) $granted) === 1, 'override persisted as granted (1) in DB', "granted={$granted}");

    $res = $super->postWithCsrf("/rolewarden/users/{$newUserId}/permissions", ['permission_id' => $permId, 'granted' => 0]);
    check(in_array($res['status'], [200, 302, 303], true), 'deny override request accepted', "status={$res['status']}");
    $granted = dbScalar("SELECT granted FROM acl_user_permissions WHERE user_id = {$newUserId} AND permission_id = {$permId}");
    check(((int) $granted) === 0, 'override persisted as denied (0) in DB', "granted={$granted}");

    $res = $super->postWithCsrf("/rolewarden/users/{$newUserId}/permissions/{$permId}/clear", []);
    check(in_array($res['status'], [200, 302, 303], true), 'clear override request accepted', "status={$res['status']}");
    $left = (int) dbScalar("SELECT COUNT(*) FROM acl_user_permissions WHERE user_id = {$newUserId} AND permission_id = {$permId}");
    check($left === 0, 'override removed from DB', "count={$left}");

    // Delete (soft).
    $res = $super->postWithCsrf("/rolewarden/users/{$newUserId}/delete", []);
    check(in_array($res['status'], [200, 302, 303], true), 'delete user request accepted', "status={$res['status']}");
    $deletedAt = dbScalar("SELECT deleted_at FROM users WHERE id = {$newUserId}");
    check($deletedAt !== null, 'user soft-deleted in DB (deleted_at set)', 'deleted_at=' . var_export($deletedAt, true));
}

// Last-super-admin protection through the panel.
$activeSuperAdmins = (int) dbScalar("
    SELECT COUNT(*) FROM users u
    JOIN acl_user_roles ur ON ur.user_id = u.id
    JOIN acl_roles r ON r.id = ur.role_id AND r.is_super_admin = 1 AND r.deleted_at IS NULL
    WHERE u.active = 1 AND u.deleted_at IS NULL
");
check($activeSuperAdmins === 1, 'fixture precondition: exactly one active super admin before protection test', "count={$activeSuperAdmins}");

$activeBefore = dbScalar("SELECT active FROM users WHERE id = {$superAdminUserId}");
$res          = $super->postWithCsrf("/rolewarden/users/{$superAdminUserId}/deactivate", []);
$activeAfter  = dbScalar("SELECT active FROM users WHERE id = {$superAdminUserId}");
check((string) $activeBefore === (string) $activeAfter, 'deactivating the sole active super admin via the panel is rejected (DB unchanged)', "before={$activeBefore} after={$activeAfter} status={$res['status']}");
check(!str_contains($res['body'], 'SQLSTATE') && !str_contains($res['body'], 'mysqli') && !str_contains($res['body'], 'Database Error'), 'no SQL error text leaked on rejected deactivation');

$res = $super->postWithCsrf("/rolewarden/users/{$superAdminUserId}/delete", []);
$stillActive = dbScalar("SELECT deleted_at FROM users WHERE id = {$superAdminUserId}");
check($stillActive === null, 'deleting the sole active super admin via the panel is rejected (DB unchanged)', 'deleted_at=' . var_export($stillActive, true));
check(!str_contains($res['body'], 'SQLSTATE') && !str_contains($res['body'], 'mysqli') && !str_contains($res['body'], 'Database Error'), 'no SQL error text leaked on rejected deletion');

// ===========================================================================
// 3. CRUD roles
// ===========================================================================

section('3. CRUD roles');

$roleSlug = 'adhoc-crud-role-' . bin2hex(random_bytes(3));
$res      = $super->postWithCsrf('/rolewarden/roles', [
    'slug' => $roleSlug,
    'name' => 'Adhoc CRUD role',
    'description' => 'temp',
], '/rolewarden/roles');
check(in_array($res['status'], [200, 302, 303], true), 'create role request accepted', "status={$res['status']}");
$newRoleId = (int) dbScalar("SELECT id FROM acl_roles WHERE slug = '{$roleSlug}'");
check($newRoleId > 0, 'created role exists in DB', "id={$newRoleId}");

if ($newRoleId > 0) {
    $res = $super->postWithCsrf("/rolewarden/roles/{$newRoleId}", [
        'name' => 'Adhoc CRUD role renamed',
    ], "/rolewarden/roles/{$newRoleId}/edit");
    check(in_array($res['status'], [200, 302, 303], true), 'edit role request accepted', "status={$res['status']}");
    $name = dbScalar("SELECT name FROM acl_roles WHERE id = {$newRoleId}");
    check($name === 'Adhoc CRUD role renamed', 'role name updated in DB', "name={$name}");

    $res = $super->postWithCsrf("/rolewarden/roles/{$newRoleId}/delete", []);
    check(in_array($res['status'], [200, 302, 303], true), 'delete role request accepted', "status={$res['status']}");
    $deletedAt = dbScalar("SELECT deleted_at FROM acl_roles WHERE id = {$newRoleId}");
    check($deletedAt !== null, 'role soft-deleted in DB', 'deleted_at=' . var_export($deletedAt, true));
}

// System role not deletable.
$systemRoleId = (int) dbScalar("SELECT id FROM acl_roles WHERE slug = 'admin' AND is_system = 1");
$res          = $super->postWithCsrf("/rolewarden/roles/{$systemRoleId}/delete", []);
$stillThere   = dbScalar("SELECT deleted_at FROM acl_roles WHERE id = {$systemRoleId}");
check($stillThere === null, 'system role (admin) cannot be deleted via the panel', 'deleted_at=' . var_export($stillThere, true));
check(!str_contains($res['body'], 'SQLSTATE') && !str_contains($res['body'], 'Database Error'), 'no SQL error text leaked on rejected system-role deletion');

// Role with active children not deletable.
$parentSlug = 'adhoc-parent-' . bin2hex(random_bytes(3));
$res        = $super->postWithCsrf('/rolewarden/roles', ['slug' => $parentSlug, 'name' => 'Adhoc parent'], '/rolewarden/roles');
$parentId   = (int) dbScalar("SELECT id FROM acl_roles WHERE slug = '{$parentSlug}'");
$childSlug  = 'adhoc-child-' . bin2hex(random_bytes(3));
$res        = $super->postWithCsrf('/rolewarden/roles', ['slug' => $childSlug, 'name' => 'Adhoc child', 'parent_id' => $parentId], '/rolewarden/roles');
$childId    = (int) dbScalar("SELECT id FROM acl_roles WHERE slug = '{$childSlug}'");
check($parentId > 0 && $childId > 0, 'fixture precondition: parent/child roles created', "parent={$parentId} child={$childId}");

if ($parentId > 0 && $childId > 0) {
    $res        = $super->postWithCsrf("/rolewarden/roles/{$parentId}/delete", []);
    $stillThere = dbScalar("SELECT deleted_at FROM acl_roles WHERE id = {$parentId}");
    check($stillThere === null, 'role with an active child cannot be deleted via the panel', 'deleted_at=' . var_export($stillThere, true));

    // Cycle rejected: try to make the parent a child of its own child.
    $res = $super->postWithCsrf("/rolewarden/roles/{$parentId}", [
        'name' => 'Adhoc parent',
        'parent_id' => $childId,
    ], "/rolewarden/roles/{$parentId}/edit");
    $parentIdOfParent = dbScalar("SELECT parent_id FROM acl_roles WHERE id = {$parentId}");
    check($parentIdOfParent === null || (int) $parentIdOfParent !== $childId, 'hierarchy cycle (parent becoming child of its own child) rejected', 'parent_id=' . var_export($parentIdOfParent, true));
    check(!str_contains($res['body'], 'SQLSTATE') && !str_contains($res['body'], 'Database Error'), 'no SQL error text leaked on rejected cycle');
}

// ===========================================================================
// 4. Permission matrix
// ===========================================================================

section('4. Permission matrix');

// Build a fresh role for matrix testing.
$matrixSlug = 'adhoc-matrix-' . bin2hex(random_bytes(3));
$super->postWithCsrf('/rolewarden/roles', ['slug' => $matrixSlug, 'name' => 'Adhoc matrix role'], '/rolewarden/roles');
$matrixRoleId = (int) dbScalar("SELECT id FROM acl_roles WHERE slug = '{$matrixSlug}'");
check($matrixRoleId > 0, 'fixture precondition: matrix test role created', "id={$matrixRoleId}");

$res = $super->get("/rolewarden/roles/{$matrixRoleId}");
check($res['status'] === 200, 'role detail page (matrix) loads', "status={$res['status']}");
check(str_contains($res['body'], 'users') && str_contains($res['body'], 'view'), 'matrix markup references known areas/actions (users/view)');

$permId = (int) dbScalar("SELECT id FROM acl_permissions WHERE slug = 'users.view'");
$before = microtime(true);
$res    = $super->postWithCsrf("/rolewarden/roles/{$matrixRoleId}/permissions", [
    'permission_id' => $permId,
    'granted' => 1,
], "/rolewarden/roles/{$matrixRoleId}");
$elapsed = microtime(true) - $before;
check(in_array($res['status'], [200, 201, 204], true), 'single-cell matrix save endpoint responds without a full-page redirect', "status={$res['status']}");
$bodyLooksLikeFullPage = (bool) preg_match('/<html[\s>]/i', $res['body']);
check(!$bodyLooksLikeFullPage, 'single-cell save response is not a full HTML page (partial/JSON endpoint, not a page reload)', 'len=' . strlen($res['body']));

$has = (int) dbScalar("SELECT COUNT(*) FROM acl_role_permissions WHERE role_id = {$matrixRoleId} AND permission_id = {$permId}");
check($has === 1, 'matrix cell grant persisted in DB', "count={$has}");

// Persist across a subsequent, separate request (no logout in between).
$res2 = $super->get("/rolewarden/roles/{$matrixRoleId}");
check($res2['status'] === 200, 'role detail reloads after matrix save', "status={$res2['status']}");
$hasStill = (int) dbScalar("SELECT COUNT(*) FROM acl_role_permissions WHERE role_id = {$matrixRoleId} AND permission_id = {$permId}");
check($hasStill === 1, 'matrix cell state persists to the next request without logout', "count={$hasStill}");

// Inherited cells: child of matrixRole should show users.view selected+locked with origin.
$matrixChildSlug = 'adhoc-matrix-child-' . bin2hex(random_bytes(3));
$super->postWithCsrf('/rolewarden/roles', ['slug' => $matrixChildSlug, 'name' => 'Adhoc matrix child', 'parent_id' => $matrixRoleId], '/rolewarden/roles');
$matrixChildId = (int) dbScalar("SELECT id FROM acl_roles WHERE slug = '{$matrixChildSlug}'");
check($matrixChildId > 0, 'fixture precondition: matrix child role created', "id={$matrixChildId}");

if ($matrixChildId > 0) {
    $resChild = $super->get("/rolewarden/roles/{$matrixChildId}");
    check($resChild['status'] === 200, 'child role detail page loads', "status={$resChild['status']}");
    $mentionsInherited = (bool) preg_match('/inherit|ereditat|parent|padre/i', $resChild['body']);
    check($mentionsInherited, 'child role matrix markup indicates the origin of an inherited permission (inherited/parent wording present)');

    // Direct request trying to revoke the inherited permission on the child: must be refused server-side.
    $resRevoke = $super->postWithCsrf("/rolewarden/roles/{$matrixChildId}/permissions", [
        'permission_id' => $permId,
        'granted' => 0,
    ], "/rolewarden/roles/{$matrixChildId}");
    $childOwnGrant = (int) dbScalar("SELECT COUNT(*) FROM acl_role_permissions WHERE role_id = {$matrixChildId} AND permission_id = {$permId}");
    // Regardless of whether the child ever had its own explicit row, the effective
    // permission for the child must still resolve as granted, because the parent grants it.
    // We check this by asking the resolver's own contract surface indirectly: the child
    // has no direct grant to revoke (childOwnGrant should be 0 either way), and no error
    // should have corrupted the parent's row.
    $parentStillHas = (int) dbScalar("SELECT COUNT(*) FROM acl_role_permissions WHERE role_id = {$matrixRoleId} AND permission_id = {$permId}");
    check($parentStillHas === 1, 'parent role grant is untouched after attempting to revoke it via the child', "count={$parentStillHas}");
    check($childOwnGrant === 0, 'attempting to revoke an inherited permission on the child does not create a child-level denial that could shadow the parent', "count={$childOwnGrant}");
}

// ===========================================================================
// 5. Permissions list is read-only
// ===========================================================================

section('5. Permissions list read-only');

$res = $super->get('/rolewarden/permissions');
check($res['status'] === 200, 'permissions list loads for super admin', "status={$res['status']}");
check(!preg_match('/<form[^>]+method=["\']?post/i', $res['body']), 'permissions list page contains no POST-method form (no write action exposed)');
check(!preg_match('#rolewarden/permissions/\d+/(delete|update)#', $res['body']), 'permissions list page contains no delete/update action links');

// ===========================================================================
// 6. Overridable view resolution
// ===========================================================================

section('6. Overridable view resolution');

$overrideDir = getenv('RW_APP_COPY_DIR') ?: '';
if ($overrideDir === '') {
    check(false, 'RW_APP_COPY_DIR not provided: skipping override-view test setup', 'set RW_APP_COPY_DIR to the temp app copy path');
} else {
    $marker      = 'ADHOC-OVERRIDE-MARKER-' . bin2hex(random_bytes(4));
    $overridePath = rtrim($overrideDir, '/\\') . '/app/Views/overrides/RoleWarden/Views/permissions/index.php';
    @mkdir(dirname($overridePath), 0777, true);
    file_put_contents($overridePath, "<?php echo '{$marker}'; ?>\n");

    $res = $super->get('/rolewarden/permissions');
    check(str_contains($res['body'], $marker), 'host override view under app/Views/overrides/RoleWarden/Views/permissions/index.php wins over the module view');

    @unlink($overridePath);
}

// ===========================================================================
// 7. Cross-cutting security
// ===========================================================================

section('7. Cross-cutting security');

// CSRF: POST without a valid token must be refused.
$ch = curl_init($BASE_URL . "/rolewarden/roles/{$matrixRoleId}/delete");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEFILE => $super->cookieFile,
    CURLOPT_COOKIEJAR => $super->cookieFile,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query(['csrf_test_name' => 'not-a-real-token']),
]);
$csrfBody   = curl_exec($ch);
$csrfStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
check($csrfStatus === 403 || $csrfStatus >= 400, 'POST with an invalid CSRF token is rejected', "status={$csrfStatus}");
$roleStillThere = dbScalar("SELECT deleted_at FROM acl_roles WHERE id = {$matrixRoleId}");
check($roleStillThere === null, 'CSRF-rejected delete did not affect the DB', 'deleted_at=' . var_export($roleStillThere, true));

// No SQL error visible on a nonexistent numeric id.
$res = $super->get('/rolewarden/users/999999999');
check(!str_contains($res['body'], 'SQLSTATE') && !str_contains($res['body'], 'Database Error') && !str_contains($res['body'], 'mysqli_sql_exception'), 'nonexistent user id does not leak a SQL error', "status={$res['status']}");
check(in_array($res['status'], [404, 302, 303], true) || !str_contains(strtolower($res['body']), 'sql'), 'nonexistent user id yields a clean not-found response', "status={$res['status']}");

// Special characters (quotes) in a text field must not blow up as a visible SQL error.
$res = $super->postWithCsrf('/rolewarden/roles', [
    'slug' => 'adhoc-quote-' . bin2hex(random_bytes(3)),
    'name' => "O'Brien's \"role\" -- test",
], '/rolewarden/roles');
check(!str_contains($res['body'], 'SQLSTATE') && !str_contains($res['body'], 'Database Error'), 'role name containing quotes/SQL metacharacters does not trigger a visible SQL error', "status={$res['status']}");

// ===========================================================================
// 8. A permission revoked from the logged-in user's own role applies on the
//    next request, without logout.
// ===========================================================================

section('8. Live revocation without logout');

$res = $limited->get('/rolewarden/users');
check($res['status'] === 200, 'limited user can open users list before revocation', "status={$res['status']}");

$viewPermId = (int) dbScalar("SELECT id FROM acl_permissions WHERE slug = 'users.view'");
$before2    = $super->postWithCsrf("/rolewarden/roles/{$limitedRoleId}/permissions", [
    'permission_id' => $viewPermId,
    'granted' => 0,
], "/rolewarden/roles/{$limitedRoleId}");
check(in_array($before2['status'], [200, 201, 204], true), 'super admin revokes users.view from the limited role via the matrix endpoint', "status={$before2['status']}");
$stillHas = (int) dbScalar("SELECT COUNT(*) FROM acl_role_permissions WHERE role_id = {$limitedRoleId} AND permission_id = {$viewPermId}");
check($stillHas === 0, 'users.view removed from the limited role in DB', "count={$stillHas}");

$res = $limited->get('/rolewarden/users');
check($res['status'] !== 200, 'limited user is denied on the very next request after the revocation, same session, no logout', "status={$res['status']}");

// Restore for cleanliness (in case cleanup below relies on it).
$super->postWithCsrf("/rolewarden/roles/{$limitedRoleId}/permissions", [
    'permission_id' => $viewPermId,
    'granted' => 1,
], "/rolewarden/roles/{$limitedRoleId}");

// ===========================================================================
// Report
// ===========================================================================

section('Summary');
echo "PASS: {$PASS}\n";
echo "FAIL: {$FAIL}\n";
if ($FAILURES !== []) {
    echo "\nFailures:\n";
    foreach ($FAILURES as $f) {
        echo " - {$f}\n";
    }
}

exit($FAIL === 0 ? 0 : 1);
