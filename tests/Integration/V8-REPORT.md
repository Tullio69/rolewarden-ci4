# V8 — Demo e documentazione: collaudo indipendente

## Riverifica — 2026-10-10, Collaudatore ad Hoc (Claude), commit `50894c9`, zip `build/*-1.0.0-rc.v8b.zip`

**Esito: PASS. D1-D5 corretti, regressione V2-V7 chiusa (aggiornamento 15:35: le due suite di development mancanti sono passate, vedi sotto).** Testo originale della riverifica: **PASS con riserva. D1-D5 corretti.** Li ho verificati sul comportamento servito, non sul diff.

La riserva riguarda la regressione: tutta production e sette suite su nove di development sono pulite, ma **V5 e V4 in development non hanno ancora un'esecuzione pulita**. Al primo lancio erano state bloccate dallo strumento; la riesecuzione è stata fermata da Claude Code per memoria di sistema insufficiente e non l'ho rilanciata.

Mandato, perimetro e regole sono quelli del collaudo. Dei file cambiati ho letto solo quelli del perimetro (demo/README.md, THIRD-PARTY-LICENSES.md, api.html), mai il codice né il diff.

| Suite | Risultato |
| --- | ---: |
| Suite V8, `verify-v8.50894c9.output.txt` | 226/227, 2 AMB (l'unico FAIL è P06d, giudicato a mano qui sotto) |
| Documentazione offline in Chrome, `verify-v8.docs.50894c9.output.txt` | 8/8 |
| Suite V7 sugli zip rc.v8b, `verify-v8.v7.50894c9.output.txt` | 181/181, 1 AMB |
| Pacchetti V7, `verify-v8.v7package.50894c9.output.txt` | 15/16 (P04 superato da V8, vedi sotto) |

### Difetti: stato

| Difetto | Stato | Evidenza |
| --- | --- | --- |
| D1, conflitto sullo username | **Corretto** | A22-username: uno username della demo già preso è rifiutato con un messaggio, senza testo SQL, con il database identico (contatori compresi). Ho anche indotto un guasto a metà installazione: un trigger del database rifiuta il terzo account (A22-mid, A22-mid-b). Il comando esce con codice diverso da zero, tutto ciò che aveva creato viene tolto e il database è identico. Sulla console non c'è testo SQL; il motivo («v8 induced failure») è solo in `writable/logs`. Il trigger è stato rimosso subito dopo |
| D2, 404 con dettaglio in ambiente `demo` | **Corretto** | C13a: ho applicato alla copia l'istruzione del README della demo (`error_404.php`, 1 occorrenza). Con quella, in `CI_ENVIRONMENT = demo` né un indirizzo sconosciuto (C15) né un documento inesistente (C15b) mostrano rotta, percorsi o traccia. Il guasto del database dà la pagina generica (C16) |
| D3, versione del framework | **Corretto** | P07: tutti i pacchetti di `vendor/` hanno la versione elencata, CodeIgniter 4.7.5 compreso |
| A2, autoloader di Composer | Risolto dall'autore | P08b: l'autoloader è elencato (MIT), con `vendor/composer/LICENSE` accanto |
| A3, versione di IBM Plex | Risolto dall'autore | Colonna «Latin subset» |
| D4, `UserRoles::assign()` | **Corretto** | F19b: `(new \RoleWarden\Models\UserRoles())->assign($userId, $roleId)`, copiato come scritto, funziona e ha effetto subito |
| D5, README alla radice dello zip completo | **Corretto** | P06e: CHANGELOG.md e THIRD-PARTY-LICENSES.md alla radice, identici a quelli del modulo; il README rimanda a `modules/rolewarden/documentation/index.html`, che esiste. P06d resta FAIL solo perché ora controlla ogni percorso tra apici inversi del README: uno è `documentation/design-guide.html`, ma la frase precisa «open it in a browser from the module folder». Lo giudico corretto e lo annoto come osservazione O7 |

Tutti gli altri controlli del primo giro passano ancora: installazione e rimozione dopo un uso reale senza residui, conflitti, `migrate --all`, permessi, vetrina, indipendenza, zip completo, esempi della documentazione, traduzione, log.

### Regressione V2-V7 su `50894c9`

| Suite | development | production |
| --- | ---: | ---: |
| V6 | 151/151 | 151/151 |
| V5 | 177/177 (chiusa alle 15:33) | 177/177 |
| V4 | 207/232 (chiusa alle 15:23): i 25 FAIL sono quelli già noti della data ISO | 207/232: i 25 FAIL sono quelli già noti della data ISO |
| V2 `test` / `extra` / `adapt` | 142/143 (G07, noto) + 33 + 16 | 156/157 (G07, noto) + 42 + 16 |
| V3 principale / supplementare / filtri array | 139 + 69 + 55 | 139 + 69 + 55 |

I numeri coincidono con quelli di V7 su `2314cfb`. Snapshot `f7f37c04…e21e64` identico in tutte le 20 esecuzioni.

**Perché V5 e V4 in development non sono chiuse.** Le cartelle di lavoro della regressione stanno ora nello scratchpad, perché Windows non lascia cancellare i profili Chrome che la V6 crea dentro la cartella di lavoro. Due suite però cercano file nella cartella sopra quella di lavoro:
- V5 cerca `../verify-v5.css.py` e si è fermata alla sezione 2, dopo 23 PASS (`verify-v8.regression.v5.development.50894c9.firstrun.console.txt`);
- V4 cerca le catture di riferimento: per questo nel primo giro falliscono anche i 7 `R-D2-PREV` e 2 in più di `R-CONTENT` e `R-BODY`, oltre ai FAIL noti della data ISO (`verify-v8.v4.development.50894c9.firstrun.*`).

Ho copiato quei file accanto alle cartelle di lavoro, e con quei file V5 e V4 in production sono passate pulite. La riesecuzione di V5 e V4 in development è stata poi fermata da Claude Code per memoria di sistema insufficiente. Le istruzioni dicono di non rilanciarla da solo. Ho fermato i processi per PID e ripristinato il database (hash identico). Per chiuderle basta, con le variabili di processo impostate e una suite alla volta:

`bash verify-v8.regression-all.sh v5:development v4:development`

**Chiusura, ore 15:35.** Su richiesta del coordinatore ho lanciato `bash verify-v8.regression-all.sh v5:development v4:development`, una suite alla volta.
- **V4 development:** 207/232. I 25 FAIL sono esattamente quelli già noti della data ISO nelle email, superati da V7 (`R-TZ`, `R-CONTENT`, `R-BODY`, `R-D2-TEXT`, `R-D2-HTML`, 5 ciascuno). Non c'è nessun altro FAIL: gli errori di strumento del primo giro sono spariti.
- **V5 development:** 177/177. Al primo tentativo si è fermata subito con «existing work directory»: era rimasta la cartella della riesecuzione interrotta, che conteneva profili Chrome bloccati. L'ho spostata in `scratchpad/v8reg2/locked-v5-development` e ho rilanciato.
- **Snapshot e pulizia:** `f7f37c04…e21e64` identico in entrambe le esecuzioni, Mailpit vuoto, nessun processo rimasto.

**Totale della regressione, esclusi i 25 FAIL noti della data ISO:**
- development 989/990, con il solo G07 già superato da V5;
- production 1012/1013, con il solo G07.

**Totale grezzo:** development 989/1015 e production 1012/1038, uguali a V7 su `2314cfb`. In ciascun ambiente lo scarto è fatto dai 25 FAIL V4 già noti più G07.

**P04 dei pacchetti V7 superato da V8.** Il controllo V7 voleva nel modulo solo README, composer.json, `documentation/` e `src/`. Ora ci sono anche CHANGELOG.md e THIRD-PARTY-LICENSES.md, che la SPEC V8 richiede. È un'aspettativa superata, non un difetto.

**Ambiguità.** A1 (voci del log scritte da un admin vero sugli oggetti della demo) e A4 (dati seminati in inglese, cioè l'A1 di V7) restano aperte e non decise, come le ambiguità aperte di V7.

**Osservazione nuova.** O7: il README alla radice dello zip completo nomina `documentation/design-guide.html`, con l'indicazione di aprirlo «from the module folder».

**Pulizia.**
- **Database e Mailpit:** database identico allo snapshot (hash identico), Mailpit vuoto.
- **Processi:** nessun processo mio attivo, verificato per riga di comando.
- **Cartelle:** junction rimosse per prime e `.env` cancellati. Restano da cancellare a mano solo file di profili Chrome che Windows rifiuta di cancellare:
  - `scratchpad/v8reg2`
  - `scratchpad/v8rv-docs`
  - quelle del primo giro: `tests/Integration/verify-v8.regress.locked-chrome`, `scratchpad/v8reg`, `scratchpad/v8run-docs`

Nessun commit.

---

## Collaudo — 2026-10-10, Collaudatore ad Hoc (Claude), commit `f06a29d`, zip `build/*-1.0.0-rc.v8.zip`

**Esito: FAIL. V8 non approvata.** Un difetto medio tocca direttamente la definizione di fatto: un conflitto sullo username non è rifiutato senza danni (D1). Ci sono poi quattro difetti minori (D2-D5). In più la regressione V2-V7 non è stata completata: il job è stato fermato da Claude Code per memoria di sistema insufficiente e, come da istruzioni, non l'ho rilanciato.

| Suite | Risultato |
| --- | ---: |
| Suite V8 (`verify-v8.php`, fasi P, A-G e L) | **215/221 PASS, 6 FAIL, 4 AMB** |
| Documentazione renderizzata offline in Chrome headless (`verify-v8.docs.py`) | 8/8 |
| Regressione V2-V7 su `f06a29d` | **non completata** (vedi "Cosa non ho potuto provare") |

La suite V8 completa è in `verify-v8.f06a29d.output.txt`: 214 PASS, 6 FAIL, 4 AMB. La fase P l'ho poi rieseguita da sola (`verify-v8.f06a29d.P.output.txt`) dopo due correzioni dello strumento:
- P10 dava un falso positivo, sull'esempio di chiave HMAC nella documentazione di Shield in `vendor/`;
- ho aggiunto il controllo P06d.

Nella fase P rieseguita P10 passa e P06d fallisce. Il totale in tabella combina le due esecuzioni.

### Perimetro e regole

- **Letto.** SPEC ("App demo" con le decisioni V8, "Requisiti di pubblicazione", "Pannello admin"), BRIEF v1.0, `_AI-LOG.md`, README, `demo/README.md`, tutta `documentation/`, CHANGELOG, THIRD-PARTY-LICENSES, i file di licenza degli asset, i contratti `src/Authorization/Contracts/`, i file di lingua della demo, gli script di collaudo precedenti.
- **Non aperto.** Nessun controller, model, installer, comando, vista, config della demo o del modulo. Nessun diff, nessun `bin/build-package`, nessun test unitario, nessun file del repo `../rolewarden-demo`.
- **Due file del modulo copiati senza leggerli.** In F8 ho copiato `src/Views/layouts/panel.php` e `auth/login.php` come override, aggiungendo in testa un marcatore. In F10 ho caricato `src/Language/en/RoleWarden.php` come array per creare `fr`, come chiede la documentazione.
- **Ambiente.**
  - Copie di `../rolewarden-app-test` in una cartella mia, con il modulo da `git archive f06a29d` agganciato come junction. `git archive` lascia fuori `demo/` per l'export-ignore, quindi la demo viene da `git archive f06a29d:demo`; P02 verifica che sia identica allo zip demo.
  - Applicazione completa: lo zip estratto, servito con `php -S ... vendor/codeigniter4/framework/system/rewrite.php`, document root `public/`.
- **Database.** Solo `rolewarden_test` su 3317. Credenziali solo come variabili di processo, mai nei `.env`, tutti cancellati a fine esecuzione. Email solo a Mailpit, verso `@example.com` o `*.test` (L05).
- **Avviso sugli output.** La password del database coincide con il nome del prodotto in minuscolo, quindi negli output ogni `rolewarden` minuscolo è sostituito da `[omesso]`.

### Esito per punto del mandato

| # | Punto | Esito | Controlli |
| --- | --- | --- | --- |
| 1 | Installazione e rimozione con un comando, nessun residuo | **FAIL** (D1) | A00-A23 |
| 2 | Permessi al lavoro | PASS | B01-B36 |
| 3 | Modalità vetrina | **FAIL** minore (D2) | C01-C16 |
| 4 | Indipendenza | PASS | P01, P05, D01-D07 |
| 5 | Applicazione completa (zip) | PASS | E01-E07 |
| 6 | Documentazione | **FAIL** minore (D4, D5) | P11-P12, F01-F21, render 8/8 |
| 7 | CHANGELOG e licenze | **FAIL** minore (D3) | P06, P07-P09 |
| 8 | Traduzione della demo | PASS (+ A4) | P13, G01-G03 |
| 9 | Regressione | **non completata** | - |
| 10 | Pubblicazione: credenziali e log | PASS, salvo la riga ERROR di D1 | P10, L01-L04 |

**1. Installazione e rimozione.**
- `demo:install` e `demo:uninstall` sono un comando ciascuno. La rimozione senza installazione è innocua: database identico, contatori compresi (A01).
- L'installazione ripetuta è rifiutata con exit 1 e «The demo is already installed.», senza danni (A09).
- Lo schema è quello approvato: una sola tabella `demo_documents`, colonne, FK CASCADE/SET NULL, indici su `status` e `owner_id` (A03). Cinque permessi `documents.*` non di sistema (A04). Catena Reader ← Author ← Approver con i permessi esatti (A05), tre account attivi (A06), documenti di esempio nei tre stati (A07). `Demo.showcaseUsers` con i tre id (A08) e riga di migrazione in `RoleWardenDemo` (A08b).
- La password viene da `demo.password`, sia come variabile di processo sia nel `.env`, e non è stampata (A02b, A21). Senza, è generata (18 caratteri esadecimali), stampata una volta e valida per i tre account (A20-A20c).
- `migrate --all` crea la tabella della demo. Dopo, la rimozione riporta il database com'era e `demo:install` funziona (A18-A19c).
- **Uso reale, poi rimozione** (A13-A15e): accessi dei tre account, ricordami, documenti creati, modificati, inviati, approvati e cancellati, lingua italiana e modo scuro, tentativi falliti, cambio account in vetrina, modifiche dal pannello su ruoli e utenti della demo, ruolo demo e override dati a un utente vero che scrive un documento, revoca di sessione, uscite. La rimozione non lascia nessuna riga che riguardi account, ruoli, permessi, impostazioni o migrazione della demo, e non toglie nulla che esistesse prima. Il confronto è completo su tutte le tabelle, tutte le righe e tutte le colonne.
- **Cosa resta dopo la rimozione, e la specifica non attribuisce alla demo:**
  - accessi, sessioni e `last_active` degli account veri;
  - il re-hash della password che Shield fa all'accesso degli account veri;
  - l'impostazione `RoleWarden.activityPrunedOn` della pulizia giornaliera del log;
  - i contatori AUTO_INCREMENT;
  - le voci del log scritte dall'admin vero sugli oggetti della demo (ambiguità A1).
- **Riaggancio.** Con le due righe di aggancio ancora presenti, dopo la rimozione `/demo` risponde 404 in development e in production, senza SQL (A16). Reinstallazione e nuova rimozione sono pulite (A17, A17b).
- **Conflitti** (A22):
  - email già esistente, slug di ruolo già esistente, permesso `documents.view` già esistente: tutti rifiutati senza danni, con un messaggio;
  - l'account vero con l'email della demo non viene preso in carico;
  - un ruolo con lo stesso nome ma slug diverso: la demo si installa e la rimozione non tocca il ruolo dell'applicazione;
  - **username già esistente: FAIL, è D1.**

**2. Permessi.**
- Pulsanti per ruolo e per proprietà (B01-B03):
  - il Reader non ne ha nessuno;
  - l'Author ha Nuovo, Modifica sui propri, Invia sulle proprie bozze;
  - l'Approver ha Approva sugli inviati, Elimina su tutti, Modifica sui propri.
- Rifiutate tutte le richieste costruite a mano, con CSRF valido (B05-B18): creazione senza `documents.create`, modifica o invio di documenti altrui, approvazione senza permesso, cancellazione senza permesso, approvazione di una bozza o di un approvato, invio di un documento che non è in bozza, ospite, id inesistenti o malformati. Nessun 500 e nessun SQL.
- Il super admin non può modificare documenti altrui (404).
- Percorso normale corretto (B19-B24). Salvare un approvato lo riporta in bozza, come promette il form, e azzera `approved_by` e `approved_at`.
- CSRF su ogni form, richieste senza token o con token sbagliato rifiutate (B25, B26). Markup in titolo e testo escapato. La conferma di cancellazione passa il titolo come unico letterale JS (B27-B29). Validazione su vuoto, 161 caratteri, 70.000 caratteri e campi mancanti senza 500 (B30). 160 caratteri accentati ed emoji conservati (B31).
- **Effetto alla richiesta successiva, senza nuovo accesso** (B32-B36): un permesso concesso o revocato al ruolo dal pannello, un override negato o concesso all'utente, un account disattivato.

**3. Vetrina.**
- Spenta di default: niente barra, cambio a mano rifiutato (C01, C02).
- Con `demo.showcase = true` nel `.env` la barra mostra esattamente i tre account. Il cambio avviene senza password e senza modificare ruoli, assegnazioni o elenco (C03-C05, C10).
- Cambio rifiutato verso: super admin vero, utente vero, utente vero disattivato, id inesistente, 0, valori malformati, account demo disattivato. Rifiutato anche senza CSRF, con CSRF sbagliato, via GET, da ospite (C06-C09).
- In production con il flag acceso: niente barra e cambio rifiutato (C11, C12).
- In ambiente `demo` (`Boot/demo.php` copiato da `production.php`): barra presente, cambio funzionante, nessuna toolbar. Un guasto del database dà la pagina generica (C13, C14, C16). **Il 404 mostra il dettaglio della rotta: è D2 (C15).**

**4. Indipendenza.**
- Lo zip del modulo è identico a `git archive f06a29d` (P01) e nessun file di `src/` cita la demo (P05). README, CHANGELOG e documentazione citano la demo come prodotto, ed è atteso.
- Modulo senza cartella e senza righe della demo: pannello e schermate a 200, `/demo` 404 (D01-D03).
- Demo dallo zip in un'app pulita con le due righe del README: install, uso, uninstall puliti (D04-D07).

**5. Applicazione completa.**
- La demo sta in `modules/rolewarden-demo`, identica allo zip, con le due righe di aggancio presenti (P03, P04).
- `/demo` non risponde prima dell'installer né dopo, finché la demo non è installata. Nessun 500 (E01, E04).
- Installer V7 dal codice al primo super admin, con file di blocco (E02, E03).
- `demo:install`, uso e `demo:uninstall` puliti, con il `.env` scritto dall'installer (E05-E07).

**6. Documentazione.**
- Otto pagine, tutte aperte offline dallo zip del modulo e da `modules/rolewarden` dell'app: link, ancore e fogli di stile risolti (P11).
- Resa in Chrome headless con lo stile caricato: font Plex e layout. Gli screenshot sono `verify-v8.docs.*.png`.
- Copre i sei temi richiesti (P12). Inglese corretto e scorrevole: non ho trovato errori.
- **Esempi provati davvero nella copia** (F01-F21): migrazione dei permessi propri e suo rollback, filtro `can` su GET, POST e lista di permessi, helper nelle viste, `$user->can()`, `hasPermission()`, `inGroup()` e `getGroups()`, `service('rolewarden')` con `can`, `canAny`, `canAll`, `permissions`, `authorize` (con `AuthorizationException`) e `InvalidArgumentException` sugli slug non validi, `forgetUser` e `forgetRole`. Poi override di layout e login, riga di lingua sovrascritta, lingua `fr` aggiunta (nome nella propria lingua, ripiego in inglese), tema `ocean` con `extraThemes` e `themeStylesheet`, `rolewarden.lockAttempts` e `rolewarden.tablePrefix` dal `.env`, eventi `rolewarden.mail` (chiavi documentate) e `rolewarden.activity`, super admin creato a mano con i comandi e l'INSERT di installation.html, i comandi della demo.
- **Discrepanze:** D4, D5 e l'osservazione O1.

**7. Licenze.**
- CHANGELOG e THIRD-PARTY-LICENSES sono nello zip del modulo e in `modules/rolewarden` dell'app (P06).
- Ogni pacchetto di `vendor/` ha il suo file di licenza (P08). Alpine 3.17.4 ha la licenza MIT accanto, Plex ha l'OFL accanto, l'avviso di Tailwind 4.3.3 è conservato (P09).
- **Versione del framework sbagliata: D3.** Ambiguità A2 e A3.

**8. Traduzione.**
- Chiavi e segnaposto uguali in en e it, 32 righe (P13). Italiano curato.
- Demo in italiano, per autore e approvatore che lo scelgono dal profilo: nessun testo d'interfaccia inglese su elenco, creazione, modifica, attributi e toast compresi, `lang="it"` (G01, G02).
- Restano in inglese i dati seminati: nomi dei ruoli (anche sui pulsanti della vetrina), descrizioni dei permessi e documenti di esempio. È l'ambiguità A1 di V7, ancora aperta (A4 qui sotto).

**10. Pubblicazione.**
- Nei tre zip nessuna credenziale, chiave, `.env`, chiave privata, percorso o URL personale (P10). `homepage` del modulo: rolewarden.com, sito del prodotto.
- Nessun 500, nessun SQL a schermo, nessun warning PHP nel server con `error_reporting=-1` (L01-L03).
- Log dell'applicazione a soglia 9 puliti in B-G, salvo i rifiuti indotti di proposito: CSRF mancante e URI con caratteri vietati (L04). **In A c'è la riga ERROR di D1.**

### Difetti

**D1 — medio. Conflitto sullo username: l'installazione fallisce a metà e lascia residui** (A22-username, L04A).

Riproduzione:
1. Partire da un database installato.
2. Creare un utente vero con username `demo-author` ed email `someone@v8.test`.
3. Eseguire `php spark demo:install`.

Comportamento osservato:
- Exit 1, e sulla console il testo grezzo del database: «Duplicate entry 'demo-author' for key 'username'». Nel log c'è una riga `ERROR`.
- Il database resta con 23 differenze rispetto a prima, tra cui i cinque permessi `documents.*`, i ruoli e almeno l'account reader.
- Un nuovo `demo:install` risponde «Something named like the demo already exists in auth_identities: remove it first.».
- `demo:uninstall` ripulisce senza toccare l'utente vero (A22-username-b, A22-username-c).

Il mandato chiede «conflitto con un account ... con lo stesso nome già esistente» rifiutato senza danni. Con l'email, lo slug di ruolo e il permesso il controllo preventivo c'è e funziona; con lo username manca, e l'installazione non è atomica.

**D2 — minore. Ambiente `demo`: il 404 mostra il dettaglio della rotta** (C15).

`CI_ENVIRONMENT = demo` con `app/Config/Boot/demo.php` copiato da `production.php`, come dice il README della demo. `GET /demo/abc/edit` risponde 404 con «Can't find a route for 'GET: demo/abc/edit'.»: la vista 404 di CodeIgniter mostra il messaggio in ogni ambiente diverso da `production`.

La SPEC vuole un ambiente che «si comporta come production (niente errori a schermo, niente toolbar)». La ricetta del README non copre la vista degli errori. Il guasto del database invece dà la pagina generica (C16).

**D3 — minore. THIRD-PARTY-LICENSES.md: versione del framework sbagliata** (P07).

Nell'applicazione completa c'è `codeigniter4/framework` v4.7.5 (`vendor/composer/installed.json` e `composer.lock`). Il file elenca 4.7.4. Gli altri cinque pacchetti coincidono.

**D4 — minore, documentazione. `UserRoles::assign()` non è statico** (F19b).

api.html dice: «Assigning a role through `RoleWarden\Models\UserRoles::assign($userId, $roleId)` does it for you». Chiamato come scritto, lancia `Error: Non-static method RoleWarden\Models\UserRoles::assign() cannot be called statically`. Funziona con `model(\RoleWarden\Models\UserRoles::class)->assign(...)` e ha effetto subito (F19). La documentazione non dice come ottenere l'istanza.

**D5 — minore, documentazione. README alla radice dell'applicazione completa** (P06d).

Il `README.md` alla radice dello zip dell'app rimanda a `documentation/index.html`, `CHANGELOG.md` e `THIRD-PARTY-LICENSES.md`. Dalla radice quei file non esistono: stanno sotto `modules/rolewarden/`.

### Ambiguità di specifica (non decise)

- **A1 — Voci del log scritte da un admin vero sugli oggetti della demo.** Dopo la rimozione restano 4 voci: `role.permission_granted` e `role.permission_revoked` sul ruolo Reader, `user.override_granted` di `documents.view` a un utente vero, `role.permission_granted` di `documents.view` al ruolo `user`. Resta anche la voce `user.role_assigned` all'utente vero, con dettaglio `{"role":"Author"}`. Le voci con un account della demo come attore o come soggetto vengono invece tolte. La SPEC dice di togliere «le loro voci di log ... nient'altro»: queste non sono chiaramente «loro», né chiaramente estranee.
- **A2 — Autoloader di Composer.** È spedito nell'app (`vendor/composer/`, MIT, licenza accanto) ma non è citato in THIRD-PARTY-LICENSES.md (P08c).
- **A3 — Versione di IBM Plex** lasciata vuota in THIRD-PARTY-LICENSES.md (P09d).
- **A4 — Dati seminati in inglese nella demo italiana:** ruoli, permessi, documenti di esempio (G03). È l'A1 di V7, non contata come difetto.

### Osservazioni (non contate)

- **O1.** `rolewarden.activity`: per `user.password_set` `details` è `null`, mentre api.html scrive «`$entry['details']` is JSON».
- **O2.** Per il super admin la cifra «Your document permissions» è 0, anche se ha tutti i pulsanti. Coerente con `permissions()` documentato («a super admin also passes checks on slugs not listed»), ma in una demo può confondere.
- **O3.** Lo zip demo non contiene CHANGELOG né licenze. Non spedisce codice di terzi.
- **O4.** La barra della vetrina compare anche a un super admin vero collegato (C10, INFO). Il cambio vale solo verso gli account demo.
- **O5.** In italiano la colonna «Owner» diventa «Autore», come il ruolo Author: «Proprietario» sarebbe meno ambiguo.
- **O6.** `shield:user activate` della procedura manuale chiede una conferma interattiva [y, n]. La documentazione non lo dice.

### Cosa non ho potuto provare

- **Regressione V2-V7 su `f06a29d`.**
  - Prima di fermarsi, la V6 in development ha dato 151/151 e lo smoke `adapt` in development 16/16. I due output non sono conservati: ho cancellato quelli dei giri interrotti; restano le righe con snapshot identico in `verify-v8.environment.txt`.
  - Il primo giro si è bloccato alla pulizia: Windows ha rifiutato di cancellare un profilo Chrome della V6 dentro la cartella di lavoro. Ho corretto il runner.
  - Un secondo lancio si è sovrapposto al primo, che il TaskStop non aveva fermato: ho fermato tutto per PID e ripristinato il database.
  - Il terzo giro, pulito, è stato fermato da Claude Code per memoria di sistema insufficiente. Le istruzioni dicono di non rilanciarlo da solo.
  - I runner sono pronti: `verify-v8.regression.sh`, `verify-v8.regress.py`, `verify-v8.suite7.py`. Servono `RW_V8_SCRATCH` e le credenziali come variabili di processo.
- **Altro.** Controllo visivo in un browser vero di demo e vetrina (dovuto all'autore); ultima PHP 8.x e ultima CI 4.x (solo PHP 8.3.11 e CI 4.7.4 nella copia, 4.7.5 nello zip); Apache, sottocartella e reverse proxy per la demo; messa online su rolewarden.com (fuori perimetro).

### Pulizia

- **Database.** `rolewarden_test` è tornato allo snapshot iniziale, SHA256 `f7f37c04…e21e64`, identico prima e dopo ogni esecuzione (`verify-v8.environment.txt`). Dopo le interruzioni l'ho ripristinato a mano: stesso hash.
- **Mailpit.** Vuoto.
- **Processi.** Nessun php, python o Chrome miei attivi, tutti chiusi per PID. Il Chrome rimasto è dell'utente: padre `explorer`.
- **Cartelle.** Cartelle di lavoro rimosse, junction prima, `.env` cancellati. Restano da cancellare a mano tre cartelle con soli file di profili Chrome, accesso negato da Windows anche dopo `icacls /reset`:
  - `tests/Integration/verify-v8.regress.locked-chrome` (544 file);
  - `scratchpad/v8reg`;
  - `scratchpad/v8run-docs`.

Nessun commit.
