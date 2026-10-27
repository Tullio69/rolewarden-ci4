# Audit contro la checklist CodeCanyon

Data: 2026-10-27. Base: `main` a `7537690` (1.1 completa ma non rilasciata). Passo 1 della
roadmap del Regista ("Audit del codice: requisiti PHP/CI4, licenza, dipendenze"). Ogni
esito cita la prova eseguita in sessione. L'audit non corregge il codice: le voci DA
CORREGGERE diventano passi Intervento sulla card Notion.

Legenda: **OK**, **DA CORREGGERE**, **DECISIONE** (di Fabio).

## 1. Requisiti PHP / CodeIgniter / Shield

| Voce | Prova | Esito |
| --- | --- | --- |
| PHP minimo coerente | `composer.json` `php ^8.3`, `Installer::MIN_PHP = '8.3.0'`, README e `documentation/installation.html` "8.3 or later" | OK |
| Test unitari su PHP minimo | `vendor/bin/phpunit` su PHP 8.3.11: 90 test, 2954 asserzioni, OK | OK |
| PHP 8.4 e 8.5 | collaudo S2 del 2026-10-18 (`_AI-LOG.md`): app e modulo su php 8.3/8.4/8.5 in Docker, log puliti con `error_reporting -1`. La regressione della 1.1 è stata fatta solo sul PHP dell'host (8.3) | DA CORREGGERE: ripetere una passata 8.5 sulla 1.1 prima di dichiarare "tested up to 8.5" |
| CI4 e Shield | lock: framework v4.7.4, shield v1.4.1, settings v2.4.0, coerenti con `^4.7.4` e `^1.4` | OK |
| `composer validate` | "lock file is not up to date": il campo `version` è stato aggiunto dopo l'ultimo `composer update`. `composer.lock` è in `.gitignore`, quindi il problema riguardava solo la copia locale e non chi installa | OK, corretto il 2026-10-27 con `composer update --lock` (nessun pacchetto cambiato) |
| Versione dichiarata | `composer.json` `"version": "1.0.0"`; CHANGELOG con la 1.1 sotto `[Unreleased]`; unico tag `v1.0.0`; `build/` solo zip 1.0.0 | DECISIONE: numero e data della release (1.1.0), poi bump, CHANGELOG, tag e `bin/build-package 1.1.0` |
| PHPStan (livello 5) su `src` (corretto il 2026-10-27) | 3 errori: `RolesController.php:173` catch morto di `ProtectionException`; `UsersController.php:50` confronto `!== null` sempre vero; `SignInThrottle.php:54` `after()` con ritorno `void` incompatibile con `FilterInterface` | DA CORREGGERE: piccoli, nessuno blocca l'esecuzione, ma un revisore Envato con PHPStan li vede |
| PHPStan su tutto il progetto | `composer analyse` analizza anche `tests/Integration` (script di collaudo, più di 1000 errori) e quindi fallisce | DA CORREGGERE: escludere `tests/Integration` in `phpstan.neon.dist` così `composer analyse` torna un segnale utile (gli script non vanno nei pacchetti) |

## 2. Licenza

| Voce | Prova | Esito |
| --- | --- | --- |
| Licenza del prodotto | `composer.json` `"license": "proprietary"`; README "Proprietary. Sold on CodeCanyon."; nessun file LICENSE in conflitto. La licenza d'uso la danno Envato Regular/Extended | OK |
| Licenze di terzi nel modulo | `src/Assets/fonts/LICENSE.txt` (OFL 1.1), `src/Assets/js/vendor/LICENSE-alpine.md`, header `/*! tailwindcss v4.3.3 \| MIT License */` in `panel.css`; Alpine nel file è 3.17.4 come dichiarato | OK |
| Licenze di terzi nell'app zip | `vendor/composer/installed.json` di `rolewarden-app-1.0.0.zip`: framework 4.7.5, settings 2.4.0, shield 1.4.1, translations 4.7.4, laminas-escaper 2.18.0 (BSD-3), psr/log 3.0.2: tutto coincide con `THIRD-PARTY-LICENSES.md` | OK. Alla build della 1.1 la tabella va riallineata se Composer installa versioni nuove |

## 3. Dipendenze e risorse esterne

| Voce | Prova | Esito |
| --- | --- | --- |
| Vulnerabilità note | `composer audit`: nessuna advisory | OK |
| Dipendenze runtime | solo CI4, Shield (e Settings tramite Shield); translations solo suggerita | OK |
| Dev-dependency fuori dai pacchetti | lo zip modulo è un `git archive`: niente `vendor/`, niente `require-dev` installato; l'app zip usa `--no-dev` | OK |
| CDN, font remoti, phone-home | grep di `http(s)://` in `src`: solo la nota licenza Tailwind e una stringa d'errore di Alpine. Asset serviti dal modulo | OK |

## 4. Qualità del codice (criteri di revisione Envato)

| Voce | Prova | Esito |
| --- | --- | --- |
| Residui di debug | grep di `var_dump`, `print_r`, `dd(`, `dump(`, `console.log`, `TODO`, `FIXME` in `src`: nessun risultato | OK |
| Query parametrizzate | uniche query grezze in `ActivityLog.php:148-151`, con binding `?`; LIMIT e OFFSET passati come interi da `max()` | OK |
| Errori SQL a schermo | le eccezioni vanno in `log_message` (5 punti), mai a schermo; S2 lo ha già verificato col database spento in production | OK |
| CSRF | filtro `csrf` su tutto il gruppo `rolewarden` e sull'installer (`src/Config/RouteRegistrar.php`); S2: 28 form su 28 | OK |
| Credenziali nel codice | grep di password, secret, api key e token con valore letterale in `src`: nessun risultato | OK |

## 5. Pacchetto

| Voce | Prova | Esito |
| --- | --- | --- |
| Contenuto dello zip modulo | `git archive HEAD`: 197 file; radice `src/`, `documentation/`, README, CHANGELOG, THIRD-PARTY-LICENSES, `composer.json`. Niente tests, docs, file AI, `.env` | OK |
| `.regista.json` | entrava nello zip: in questa sessione ho aggiunto `/.regista.json export-ignore` a `.gitattributes` | OK, corretto |
| `composer.lock` | non c'è nello zip modulo: corretto per una libreria | OK |
| Zip 1.1 | non ancora costruiti. `bin/build-package` cancella `build/` e i 1.0.0 vanno conservati altrove prima | dipende dalla DECISIONE sulla versione |

## Voci [DA VERIFICARE] di `inventario.md`

1. PHP 8.5 testata: sì per la 1.0 (S2), no per la 1.1. Vedi la sezione 1.
2. Database diversi da MySQL/MariaDB: nessun test. Resta da non dichiarare.
3. Browser minimi: nessun test di compatibilità. Restano quelli indicativi di Tailwind v4 e Alpine 3 (Chrome/Edge 111+, Firefox 128+, Safari 16.4+).
4. Prima immagine sulla dashboard: ora possibile, la 1.1 è su GitHub. `inventario.md` e `scheda-prodotto.md` vanno ripassati sulla 1.1 (barra, verifica permesso, ricerca comandi, dashboard).
5. Prezzo e analisi dei concorrenti: fuori dallo scope di questo audit. Resta aperta.

## Azioni proposte (passi Intervento)

1. Fatto il 2026-10-27: lock riallineato in locale, 3 errori PHPStan corretti, `tests/Integration` escluso; `composer analyse` pulito.
2. Passata PHP 8.5 (e 8.4) sulla 1.1 con il collaudatore ad Hoc.
3. Decisione sulla release 1.1.0: bump, CHANGELOG datato, tag, zip.
4. Ripasso di `inventario.md` e `scheda-prodotto.md` sulla 1.1.
