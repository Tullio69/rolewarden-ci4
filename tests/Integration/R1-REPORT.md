# R1 - Cancello di pubblicazione della 1.1.0 (Collaudatore ad Hoc)

Commit collaudato: `7e3730a` su `main`. Zip `1.1.0-rc.r1` costruiti con `bin/build-package` in un worktree separato
(i `build/*-1.0.0.zip` del repository principale sono rimasti identici, sha256 controllati). Voce della definizione di fatto
(`docs/BRIEF-v1.1.md`, "Aggiornamento e pubblicazione"): "Tutte le voci di pubblicazione della v1.0 restano vere (PHP 8.3-8.5,
CI 4.7.4 e ultima 4.x, sottocartella, reverse proxy, nessun errore SQL a schermo in production, nessuna credenziale nel pacchetto)".

Collaudo in due sessioni: 2026-10-27 (interrotta alle 23:29 durante `m474p85`) e ripresa del 2026-10-28.

## Esito

**Punti 1-4 (matrice Docker, sottocartella, proxy, database spento, pacchetti): PASS.**
**Punto 5 (regressione U1-U5 e motion su PHP 8.5 + CI 4.7.5): NON ESEGUITO** per un limite dello strumento di collaudo (sotto).
I tre comportamenti da riverificare (fase B) sono PASS in ogni scenario che la esegue.

Difetti del modulo: **nessuno**. Stato di approvazione: **non approvabile così com'è**, manca il punto 5. Decide chi lancia
se accettare la copertura PHP 8.5 data dalla matrice HTTP o far eseguire la regressione in un altro modo (vedi "Cosa resta").

## Matrice (esiti finali)

| Scenario | PHP | CI | Ambiente | Esito |
|---|---|---|---|---|
| v83 / v84 / v85 (app da zip, installer, fasi I,U,E,D,S,T,K,B,L) | 8.3 / 8.4 / 8.5 | 4.7.5 | development | 85/85 ciascuno (ripresa) |
| v83prod / v84prod / v85prod | 8.3 / 8.4 / 8.5 | 4.7.5 | production | 67/67 ciascuno |
| sub (link simbolico `/shop/admin`) | 8.5 | 4.7.5 | development | 62/62 |
| alias (Apache `Alias`) | 8.5 | 4.7.5 | development | 30/30 |
| x2 (nginx HTTPS, `$proxyIPs`, `cookie.secure`) | 8.5 | 4.7.5 | production | 43/43 (ripresa) |
| x0 (proxy senza `$proxyIPs`) | 8.5 | 4.7.5 | production | 19/19 |
| zprod / zprod83 (database spento a metà uso) | 8.5 / 8.3 | 4.7.5 | production | 21/21 ciascuno |
| zdev (database spento) | 8.5 | 4.7.5 | development | 20/21, Z01 atteso (sotto) |
| m474p83 (modulo da solo, spark) | 8.3 | 4.7.4 | development | 46/46 (ripresa) |
| m474p85 (modulo da solo, installer) | 8.5 | 4.7.4 | development | 53/53 (ripresa) |
| m475p83 (modulo da solo, installer) | 8.3 | 4.7.5 | development | 53/53 (ripresa) |
| m475p84 / m475p85 (modulo da solo, spark) | 8.4 / 8.5 | 4.7.5 | development | 46/46 ciascuno (ripresa) |
| pacchetti (`verify-r1.package.py`) | - | - | - | 31/31 |

PHP effettivi: 8.3.36, 8.4.27, 8.5.12. CI 4.7.5 è l'ultima 4.x su Packagist al momento del collaudo.
Log: L01-L05 PASS ovunque (nessun WARNING/NOTICE/DEPRECATED oltre ai rifiuti provocati dalla suite, log PHP vuoto con
`error_reporting -1`, nessuna risposta 500 né testo SQL/PHP); i log delle app modulo non hanno deprecation, warning o notice.

**Tre comportamenti dopo `4be1a8d`** (fase B; `verify-r1.s3.php`):
- B10-B14: ciclo di ereditarietà e ruolo padre di sé stesso: messaggio della protezione sulla pagina, nessuna pagina d'errore, niente salvato; una modifica valida salva.
- B20-B23: togliere il flag super admin all'ultimo ruolo super con un super admin attivo: messaggio della protezione, niente salvato; con due ruoli super la modifica è accettata.
- B30-B33: lista utenti senza filtro, filtrata per ogni ruolo, col filtro vuoto del form e con solo `role=` nell'indirizzo: 200, utenti giusti, nessun errore.
- B40-B46: soglie di blocco e limite per indirizzo salvati dalla schermata Impostazioni e applicati da `rw-signin` (anche email non registrata, stesso messaggio; oltre il limite rifiutata anche la password giusta).

**Pacchetti** (P01-P09): zip modulo e demo uguali a `git archive 7e3730a`; nessun `.env`, log, test, docs, file AI, `.regista.json`
o credenziale; `THIRD-PARTY-LICENSES.md` coerente con i pacchetti installati nell'app zip (framework 4.7.5, settings 2.4.0,
shield 1.4.1, translations 4.7.4, laminas-escaper 2.18.0, psr/log 3.0.2), testi di licenza presenti.

## FAIL della prima sessione e loro natura

| FAIL (2026-10-27) | Natura | Prova |
|---|---|---|
| m474p83, 35 FAIL a cascata da M01 | **difetto dello strumento** | `verify-r1.docker/module-app.sh` creava `owner@s2.test`, la suite entra come `owner@s3.test` (`OWNER`). Corretto: 46/46. |
| T01 su v83/v84/v85 (e t85 della ripresa): parola "set" | **difetto dello strumento** | "set" è settembre abbreviato sull'asse dei grafici della dashboard: `<span>29 set</span><span>6 ott</span>` diventa "29 set6 ott" nel testo, e il filtro delle date con `\b` non lo riconosceva. Corretto: 85/85 su 8.3, 8.4 e 8.5. |
| x2: X204, X206, X207 | **ambiente** | `docker network inspect r1-net` restituiva un gateway vuoto; l'indirizzo registrato era già quello giusto (172.30.5.1). Rieseguito senza modifiche: 43/43. |
| zdev: Z01 | **atteso per decisione già presa** | In development è la pagina di debug di CodeIgniter a mostrare `DatabaseException: Unable to connect…`. SPEC, "Esiti di S2": in development la pagina di debug può mostrare l'errore del database, il divieto vale per production (zprod, zprod83 PASS). Riga INFO aggiunta: **66 risposte con il testo, 66 su 66 sono la pagina di debug di development, 0 altrove.** |
| m474p85 non finito | interruzione | Rieseguito: 53/53. |

## Punto 5: regressione su PHP 8.5 + CI 4.7.5, non eseguita

Strumento: `verify-r1.reg.*` (copia delle suite U1-U5 e motion; app servita da `php -S` in un container `php:8.5`, Chrome sull'host).
Tre difetti dello strumento trovati e corretti in `verify-r1.v7lib.php`, nessuno del modulo:
1. il punto d'ingresso dell'immagine passa da `sh` (dash), che scarta le variabili d'ambiente con il punto (`database.default.*`): ora `--entrypoint php`;
2. il `.env` delle app di prova (127.0.0.1:3317, SMTP 127.0.0.1:1026) vince sulle variabili d'ambiente, perché il DotEnv di CodeIgniter scrive la chiave col punto in `$_ENV`: ora un container `r1-fwd` (nginx stream, `verify-r1.docker/fwd.conf`) di cui i container PHP condividono la rete inoltra 127.0.0.1:3317 e :1026 all'host;
3. con le prime due correzioni l'installazione base passa (S01, S02, SET-1 PASS), ma ogni richiesta attraverso il bind mount di Windows costa 2-8 s (misurato; opcache non cambia nulla) e Chrome va in timeout su `Page.loadEventFired` (ERR-console-*). `motion.development` non è finito in 10 minuti ed è stato fermato; database poi trovato all'hash di riferimento, container rimossi.

Con questo strumento la regressione su PHP 8.5 non è eseguibile in tempi utili. Non è un esito sul modulo.

## Cosa resta (decide chi lancia)

- Accettare come copertura PHP 8.5 + 4.7.5 la matrice HTTP (v85, v85prod, sub, x2, m475p85: pannello in uso, B, E, T, K, L) e la regressione 1.1 già PASS su PHP 8.3 dell'host con CI 4.7.4 (MOTION-REPORT, su 933154b, prima dei quattro file cambiati dopo `4be1a8d`), **oppure**
- far eseguire le suite U1-U5/motion con l'app copiata dentro il container (non in bind mount), o con un PHP 8.5 nativo per Windows sull'host: lavoro sullo strumento, non sul modulo.

## Ambiguità di specifica

Nessuna nuova. Nota già nota dall'audit, non decisa qui: `composer.json` del modulo dichiara `"version": "1.0.0"` e il
CHANGELOG ha solo `[Unreleased]` per la 1.1 (P07 PASS sull'esistente); è preparazione del rilascio 1.1.0.

## Strumento: modifiche dichiarate (ripresa)

- `verify-r1.docker/module-app.sh`: indirizzo e nome del proprietario (`owner@s3.test`, `s3owner`).
- `verify-r1.s3.php`: T01, filtro delle date `(?<!\d)…(?!\p{L})` e regex delle evidenze con confini di lettera; Z01, riga INFO del conteggio fuori dalla pagina di debug.
- `verify-r1.v7lib.php`: `--entrypoint php`, container `r1-fwd`, rimozione del container all'uscita.
- `verify-r1.docker/fwd.conf`: nuovo.

## Ambiente

- `rolewarden_test` (MariaDB 3317), mai `rolewarden`. All'inizio della ripresa il database non era vuoto (interruzione di `m474p85`):
  ripristinato dallo snapshot di quel giro, che ha l'hash di riferimento. Ogni scenario: hash prima e dopo identico
  `f7f37c04…e21e64`, Mailpit vuoto, container `r1-*` rimossi (`verify-r1.environment.txt`).
- Log del container rimasto dall'interruzione: `verify-r1.m474p85-interrupted.container.log` (password tolta).
- Nessuna credenziale scritta in file: credenziali solo dalle variabili d'ambiente del processo.
- Pulizia finale: vedi la voce in `_AI-LOG.md`.
