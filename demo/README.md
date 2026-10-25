# RoleWarden demo

A small document register that shows RoleWarden's permissions at work. Three roles act on
the same documents: a **Reader** only reads, an **Author** writes and edits their own, an
**Approver** approves and deletes. Sign in as each one and watch half the buttons disappear.

It is also the model to copy for your own screens: routes gated by `can:<permission>`,
buttons shown with `can()`, and the one rule a permission cannot express (an author edits
only their own documents) checked in the controller. The module never depends on it.

## Install

The complete application already contains it in `modules/rolewarden-demo`, wired below;
only the command is left to run. In an application of your own, copy this folder (for
example to `modules/rolewarden-demo`) and add two lines:

`app/Config/Autoload.php`, in `$psr4`:

```php
'RoleWardenDemo' => ROOTPATH . 'modules/rolewarden-demo/src',
```

`app/Config/Routes.php`, after the module's own line:

```php
\RoleWardenDemo\Config\DemoRoutes::register($routes);
```

Then:

```
php spark demo:install
```

It creates the `demo_documents` table, the permissions `documents.view`, `documents.create`,
`documents.update`, `documents.approve` and `documents.delete`, the roles Reader, Author
(inherits Reader) and Approver (inherits Author), the accounts `reader@example.com`,
`author@example.com` and `approver@example.com`, and four sample documents. The accounts'
password is `demo.password` from `.env`; without it, one is generated and printed once.
Open `/demo`.

## Dashboard block

The demo adds a block to RoleWarden's dashboard, **Demo documents**: documents by status and
those written in the dashboard's period, for whoever holds `documents.view`. It is the model
for a block of your own: `src/Dashboard/DocumentsBlock.php` and its view
`src/Views/dashboard/documents.php`. The demo registers it through CodeIgniter's
auto-discovery, in `src/Config/Registrar.php`, so no line of your application changes; in your
own application list your blocks in `$dashboardBlocks` of `app/Config/RoleWarden.php`.

## Showcase mode

For a public demo or a product video: a bar at the top switches the session between the
three demo accounts without a password. Turn it on in `.env`:

```
demo.showcase = true
```

It only ever switches to the accounts `demo:install` created, and it is ignored when
`CI_ENVIRONMENT` is `production`. A public demo site runs in an environment of its own
instead (for example `CI_ENVIRONMENT = demo`, with an `app/Config/Boot/demo.php` copied from
`production.php`). CodeIgniter's 404 page shows its message in every environment but
`production`: in `app/Views/errors/html/error_404.php`, change
`ENVIRONMENT !== 'production'` to `! in_array(ENVIRONMENT, ['production', 'demo'], true)`.

## Remove

```
php spark demo:uninstall
```

It removes everything the demo added and nothing else: the table and its migration rows,
the roles, the permissions, the three accounts with their sessions, sign-in records and
activity log entries, the sample and later documents, and the showcase setting. Then
delete the folder and the two lines above.
