# Analisi concorrenti CodeCanyon - 2026-10-28

Dati raccolti dalle pagine pubbliche di codecanyon.net il 2026-10-28. Chiude l'analisi
"3-4 item analoghi" lasciata aperta in `docs/COMMERCIALE.md`. Le cifre cambiano: vanno
riviste prima dell'invio se passa molto tempo.

Ahrefs (volumi di ricerca delle keyword) non è disponibile sul piano attuale: le scelte di
titolo e tag si basano sui titoli e sui tag dei concorrenti più venduti.

## Item

| Item | Reg / Ext | Vendite | Rating | Ultimo agg. | Base |
|---|---|---|---|---|---|
| [Codeigniter 4 Login with Ion Auth and User management System](https://codecanyon.net/item/codeigniter-login-with-ion-auth/19878790) | $16 / $65 | 356 | 3.86 (29) | 8 lug 2026 | CI 4.7.3, Ion Auth 4 |
| [WebGuard - Advanced login management with SSO and MFA](https://codecanyon.net/item/webguard-advanced-php-login-and-user-management/35215272) | $39 / $149 | 329 | 4.71 (14) | 24 giu 2026 | CI 4.7+, PHP 8.3+ |
| [PHP Dashboard - Codeigniter 4 - Highchart Dashboard, User Access Management](https://codecanyon.net/item/php-dashboard-codeigniter-highcharts-ajax-form-mysql/19248072) | $20 / $125 | 207 | 4.14 (7) | 1 nov 2023 | CI 4.1/4.3 |
| [Appskull - Advanced User Login, Registration, Management, Roles & Permissions](https://codecanyon.net/item/appskull-advanced-user-login-registration-management-permissions/23974662) | $29 / $399 | 54 | n.d. | 21 gen 2025 | CI4 HMVC, PostgreSQL 14+ |
| [Admin Lite - PHP Admin Panel + User Management](https://codecanyon.net/item/admin-lite-powerfull-php-admin-panel/21136710) | $25 / $100 | 952 | 4.18 (34) | 27 giu 2022 | CI 3 |
| [Admin Panel with Roles & Permissions](https://codecanyon.net/item/admin-panel-with-roles-permissions/24182531) | $15 / $75 | 103 | n.d. | 19 lug 2019 | CI 3 |

Nessuno è costruito su Shield.

## Lamentele ricorrenti (lette nelle recensioni)

- Ion Auth: bug, demo non funzionante, PHP 8 non supportato per anni, funzioni promesse e
  assenti, supporto lento.
- WebGuard: add-on che si rompono a ogni aggiornamento di CodeIgniter, installer bloccato,
  SMTP, demo diversa dal prodotto, helpdesk irraggiungibile.

Gli aggiornamenti sono il punto debole di tutti: RoleWarden si aggiorna sostituendo la
cartella del modulo, va detto nella scheda.

## Cosa ha RoleWarden e loro no

Base Shield ufficiale; ereditarietà dei ruoli; negazione per utente che batte il super
admin; "Check a permission"; lockout e limiti configurabili dal pannello; sessioni;
import CSV; tre temi con controllo WCAG; Ctrl K; requisiti attuali (PHP 8.3-8.5,
CI 4.7.4+).

## Cosa pubblicizzano loro e RoleWarden no

2FA TOTP e SSO, social login, REST API/JWT, impersonazione, reCAPTCHA, export PDF/Excel,
20+ lingue e RTL, menu generato dai permessi. Login e 2FA restano di Shield: la scheda lo
dice nelle FAQ.

## Titoli e tag

Schema dei titoli: `[Nome] - [Framework] [Login/Admin Panel] + User Management, Roles &
Permissions`, con "Codeigniter 4" nei primi 30-40 caratteri. Tag ricorrenti: user
management, roles, permissions, admin panel, authentication, authorization, login,
codeigniter 4, rbac, activity log, user roles and permissions. "Shield" non compare in
nessun titolo né tag.

## Prezzo

$29 / $99 confermato: sotto WebGuard ($39), sopra gli item datati da $15-20; rialzo a $35
dopo circa 20 recensioni sopra 4.5. Il prezzo finale si concorda con Envato in revisione.
