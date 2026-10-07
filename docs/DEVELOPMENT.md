# RoleWarden: developing the module

Notes for whoever works on this repository. Not shipped: `docs/` is excluded from the
release zips (see `.gitattributes`); buyers read `README.md`.

## Status

Pre-release. Currently building the MVP; see `_AI-LOG.md` for the current state of the work.

## Repository layout

```
src/
  Authorization/   Framework-agnostic authorization layer (resolver, hierarchy, contracts)
  Config/          Module configuration and route/service registration
  Controllers/     Admin panel controllers
  Database/        Migrations and seeds
  Entities/        User entity extending Shield's
  Filters/         Route filters
  Helpers/         View helpers
  Language/        Translatable strings
  Models/          Data access
  Views/           Admin panel views (overridable by the host application)
  Assets/          Compiled CSS and JS for the panel
tests/
  Unit/            Authorization logic
  Integration/     Framework and Shield integration
```

The CodeIgniter test application lives in a sibling directory,
`../rolewarden-app-test`, generated locally with `composer create-project
codeigniter4/appstarter` and wired to this package through a Composer path
repository. It is kept outside this package so the path symlink in its
`vendor/` does not point back at its own parent.

## Local development

The database runs in Docker; PHP and Composer run natively on the host.

```
docker compose up -d
```

This starts MariaDB 11 on port 3306 with two databases, `rolewarden` for the test
application and `rolewarden_test` for the suite, plus Adminer on
<http://localhost:8080> (server `db`, user `root`, password `rolewarden`).

If port 3306 or 8080 is already taken, override them without editing the file:

```
DB_PORT=3307 ADMINER_PORT=8081 docker compose up -d
```

Then open Adminer on the port you chose. If another project's Adminer already sits on
8080, that page still loads, but its `db` is a different server: "Access denied for user
'root'" there means you are on the wrong Adminer, not using the wrong password.

The same command starts Mailpit, which catches every email the test application sends:
point the test app at it in its `.env` (`email.protocol = smtp`, `email.SMTPHost =
127.0.0.1`, `email.SMTPPort = 1026`, `email.SMTPCrypto = ''`) and read the messages on
<http://localhost:8026>. Nothing leaves the machine. `MAIL_SMTP_PORT` and `MAIL_UI_PORT`
move it the same way as the ports above.

The panel CSS is compiled from `resources/css/panel.css` with the standalone Tailwind CSS
CLI (no Node or npm): `bin/build-css` downloads the pinned version once into `bin/.cache/` and
writes `src/Assets/css/panel.css`, which is committed; `bin/build-css --watch` rebuilds on
every change. Edit the source, never the compiled file. People who install the module never
build anything.

`docker compose down -v` deletes the data volume and recreates both databases from
scratch on the next start. Use it when migrations leave the schema in a state that is
faster to rebuild than to repair.

Credentials here are deliberately trivial: this stack is local only and never exposed.

## Building the release zips

`bin/build-package 1.0.0` builds both into `build/` from the committed `HEAD`, never from
uncommitted files: the module with `git archive` (without tests, docs and tooling, see
`.gitattributes`), and the application from CodeIgniter's app starter with
`composer create-project`, Shield's own `shield:setup`, the module copied into
`modules/rolewarden` and the wiring of this README applied to the starter's configuration.
It also enables CSRF globally, leaves Shield only its default `user` group (roles live in
the panel), lets the browser choose between English and Italian, and opens the home page on
the installer until RoleWarden is installed. It needs git, PHP 8.3 and Composer.

