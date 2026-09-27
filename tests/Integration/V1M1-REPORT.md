# V1M1 - Collaudo indipendente della tappa V1 "Riscontri e impostazioni"

Collaudatore ad Hoc (Claude), in sostituzione di Codex fermato per memoria insufficiente. Commit collaudato
86f6935, percorso di aggiornamento da 3631fd2. Data 2026-09-27.

**Esito: PASS.** 58 controlli su 58 in `verify-v1-m1.php`. Regressione `verify-v1.sh`: 176/177 in development,
12/12 in production; l'unico FAIL (D04) e' un'aspettativa superata dalla decisione V1, non un difetto (vedi sotto).
**Approvazione:** tappa approvata dal lato del collaudo HTTP; resta dovuto il controllo visivo in un browser vero
che il brief chiede per ogni schermata nuova (Impostazioni, toast).

## Metodo

- Letti solo: SPEC (Pannello admin con le decisioni del 2026-09-27, Sistema di notifiche), BRIEF-v1.0, `_AI-LOG.md`,
  README (Updating the module, Wiring the admin panel, Settings and the default role), design system (README,
  Toast, Settings, Button), `src/Authorization/Contracts/`, i file gia' presenti in `tests/Integration/`. Nessun
  controller, model, view, asset, helper, Language, Config, Settings/, Database/, test unitario o diff aperto.
  Nomi di campi e formati letti dall'HTML e dal JSON serviti.
- Copia di `../rolewarden-app-test` nello scratchpad (`robocopy /E /XJ`), junction verso il modulo ricreata a mano,
  `writable/cache`, `session`, `logs` svuotati. Il modulo a 3631fd2 e a 86f6935 estratto con `git archive` in due
  cartelle; l'aggiornamento e' la sostituzione della junction, come "Replace the module's folder".
- `.env` della copia: development, `database.default.database = rolewarden_test`, porta 3307, nessuna credenziale.
  Credenziali solo da `RW_DB_USERNAME`/`RW_DB_PASSWORD`, passate come variabili di processo; lo script si ferma se
  `.env` non nomina `rolewarden_test`. Un solo `php -S` alla volta su :8070, chiuso a fine fase.
- Installazione per README in passi separati (Settings, Shield, RoleWarden, seed), utenti di prova in formato
  Shield (`verify-v1.fixtures.php`), piu' un ruolo `m1-viewer` (users.view + settings.view) e un secondo ruolo
  super admin non di sistema `m1-root`, scritti a database.

## Risultati per punto

| Punto | Controlli | Esito |
| --- | --- | --- |
| 1. Toast | E01-E14, G04 (15), piu' le verifiche di toast dentro D04, D06-D08, F02a, F06, F08 | 15/15 PASS |
| 2. Permessi settings.view / settings.update | A01-A09, B01-B02, C01-C08 (19) | 19/19 PASS |
| 3. Schermata Impostazioni | D01-D11 (10) | 10/10 PASS |
| 4. Ruolo predefinito | F01-F09 (10) | 10/10 PASS |
| 5. Regressione `verify-v1.sh` | 177 development + 12 production | 176 + 12 PASS, 1 FAIL atteso (D04) |
| 6. Errori PHP/SQL e log | G01-G03, G05 (4) | 4/4 PASS |

Dettagli salienti:

- **Toast senza JavaScript.** Dopo ogni azione a modulo la pagina d'arrivo contiene un `<noscript>` con
  `rw-toasts rw-toasts--static`, senza pulsante di chiusura: "Saved Settings saved.", "Saved Role created.",
  "Error: System records cannot be deleted." e cosi' via. L'errore porta `role="alert"`, il successo e' classe
  `rw-toast--success` senza alcun `granted`. La pila per JavaScript e' `<div class="rw-toasts" aria-live="polite">`
  con i messaggi nel JSON di `x-data` e il template con `role` legato al tipo e chiusura `aria-label="Dismiss"`.
  Coperti: salvataggio e rifiuto delle impostazioni, creazione, modifica e cancellazione di ruolo, rifiuto della
  cancellazione di un ruolo di sistema, assegnazione e revoca di ruolo, rifiuto della revoca che toglierebbe
  `roles.assign` (A2), disattivazione, creazione utente.
- **Validazione.** Ruolo vuoto e utente con email non valida: "Error:" con l'elenco dei campi sulla pagina del modulo,
  nessun toast (ne' statico ne' nel JSON).
- **JSON della matrice.** Successo `{"ok":true}`; casella ereditata: 422 "Inherited from V1 Parent: change it on that
  role."; permesso inesistente: 404 "Role or permission not found." (ruolo) e "User or permission not found."
  (override utente); nessun testo SQL.
- **Aggiornamento.** Su 3631fd2 nessun `settings.*` e nessuna voce Settings; dopo la sostituzione e
  `php spark migrate -n RoleWarden` i due permessi esistono, sono concessi solo ad `admin`, e l'admin gia' in
  sessione apre Settings e vede la voce di menu alla richiesta successiva, senza logout.
- **Rollback.** `migrate:rollback -b 3 -f` riporta `acl_*` e `migrations` identici, riga per riga, allo stato prima
  dell'aggiornamento; l'admin viene rifiutato su Settings alla richiesta successiva; la migrazione si riapplica. Su
  installazione nuova, `-b 2` toglie tutte le tabelle e le righe di migrazione di RoleWarden.
- **Accesso.** Senza `settings.view` nessuna voce di menu e GET rifiutato (302 alla radice del sito, come il resto
  del pannello); anonimo mandato al login. Con solo `settings.view`: voce di menu, pagina con il valore, `<select>`
  disabilitato, nessun pulsante Save; il POST e' rifiutato (303 alla radice) senza scrivere nulla.
- **Impostazioni.** Sezione A "Roles", `<select name="default_role">` con "None" e tutti i ruoli non super admin;
  `super-admin` e `m1-root` non offerti. Salvataggio con toast, rilettura, "Last changed" con la data e "By" con lo
  username dell'admin (prima: "Never" e trattino). POST di `super-admin`, `m1-root`, slug inesistente e id numerico:
  303 con toast d'errore, valore invariato. Senza token CSRF o con token falso: 403, valore invariato.
- **Ruolo predefinito.** Con `v1-limited` un utente registrato da `/register` di Shield e uno creato dal pannello
  ricevono esattamente `v1-limited`; con "None" nessun ruolo. Scelto `m1-temp` e poi cancellato dal pannello (con
  toast di successo), registrazione e creazione riescono senza errori e non danno ruoli; la pagina Impostazioni si
  apre. Password di prova senza username.
- **Errori e log.** Nessun testo SQL o PHP nelle pagine catturate; log dell'app a soglia massima senza warning,
  notice, deprecation o errori tranne i rifiuti CSRF provocati (3 nel mio script, 6 nella regressione);
  stderr del server pulito.

## Regressione

`verify-v1.sh` sulla copia, con `RW_DB_PORT=3307` e `RW_DB_HOSTNAME=127.0.0.1` nell'ambiente (la porta del `.env`
era gia' 3307), nient'altro cambiato. Output in `verify-v1-m1.regression.output.txt`.

- FAIL D04 "deferred features not offered ... Settings controls": il suo pattern contiene `rolewarden/settings`,
  che ora compare nel menu perche' la schermata Impostazioni e' entrata in V1 (decisione del 2026-09-27). Il
  controllo G05 del mio script ricontrolla il resto del pattern (reset link, sign out everywhere, CSV, invito) piu'
  le righe di Settings rimandate (sessione, ricordami, blocco, 2FA, log, Reset) su utenti, utente, ruoli e
  impostazioni: nessuna resa. Il controllo D04 va aggiornato da chi mantiene `verify-v1.php`; non l'ho toccato.

## Difetti

Nessuno.

## Ambiguita' di specifica (non decise)

1. **aria-live della forma statica.** Il README Toast dice che senza JavaScript "the same markup renders"; la pila
   statica dentro `<noscript>` non ha `aria-live`, che sta solo sulla pila per JavaScript. Per un contenuto presente
   al caricamento l'effetto pratico e' nullo, ma la lettera del README e quella del mandato ("aria-live sulla pila")
   non dicono quale pila.
2. **Posizione degli errori di validazione.** La decisione V1 chiede che restino nel modulo, e cosi' e'. Il README
   Toast dice "show 'Error: ...' under the control"; il pannello mostra un blocco unico "Error:" con l'elenco sopra
   il modulo (schema gia' dell'MVP).
3. **Messaggi JSON "che dicono cosa fare".** Il messaggio sulla casella ereditata dice cosa fare; quelli per risorsa
   inesistente ("Role or permission not found.") dicono cosa e' successo, che il README Toast ammette ("what happened
   or what to do next"). Il JSON di successo non ha messaggio: la frase del toast di successo viene dal client e non
   e' verificabile senza browser.
4. **Ruolo predefinito cancellato.** Dopo la cancellazione il valore salvato resta lo slug `m1-temp` e la pagina
   mostra "None" senza avviso. Se un giorno si ricrea un ruolo con lo stesso slug, torna predefinito senza che
   nessuno lo scelga. La specifica non dice se il riferimento vada ripulito alla cancellazione.
5. **Ruolo chiamato "Super Admin".** Il gruppo Shield `superadmin` dell'app di prova, importato come ruolo ordinario
   (`is_super_admin = 0`), e' offerto come predefinito con il nome "Super Admin". Il divieto della specifica e'
   rispettato se "super admin" si intende per flag; il nome puo' trarre in inganno chi imposta il valore.
6. **POST senza il campo.** Un POST senza `default_role` salva "None" con toast di successo, invece di essere
   rifiutato. La specifica non lo copre.

## Limiti del collaudo

- Nessun browser: non verificati resa visiva, i 5 secondi di esito e attenzione, la pausa al passaggio del puntatore
  e con il focus, il massimo di tre toast, le animazioni e `prefers-reduced-motion`, il toast dopo i salvataggi
  della matrice via JavaScript, la chiusura con il pulsante e l'anello di focus, la barra delle modifiche della
  schermata Impostazioni. Il brief chiede un controllo visivo in un browser vero per le schermate nuove: resta da
  fare.
- Il tipo "warning" non ha un percorso che lo produca raggiungibile via HTTP: verificata solo l'etichetta
  "Warning:" nel JSON dei tipi.
- Un solo ambiente: PHP 8.3.11, CodeIgniter 4.7.4, MariaDB 11 in Docker; il mio script in development, la
  production solo nella parte `verify-v1.prod.php` della regressione.
- Utenti di prova scritti a database, non creati dal pannello (salvo quelli dei punti F).

## Ambiente e igiene

- Snapshot iniziale `mariadb-dump --skip-dump-date --skip-comments --databases rolewarden_test`: md5
  `7342dc7c43a82c7b84781d76de51d6d1`, 0 tabelle. Ripristino dal dump a fine lavoro: md5 identico
  `7342dc7c43a82c7b84781d76de51d6d1`, 0 tabelle.
- `rolewarden` non toccato: 14 tabelle, ultima creazione 2026-09-22 07:41:39, `update_time` NULL, come prima.
- Nessun `php.exe` attivo; junction rimossa prima della copia; copia, estrazioni del modulo, dump, cookie jar e log
  del server di `verify-v1.sh` cancellati. L'app sorella e il suo `.env` non modificati. `rolewarden-db` lasciato
  acceso.
- File scritti: `verify-v1-m1.php` (sostituisce lo script parziale `verify-v1-m1.py` lasciato da Codex, rimosso),
  `verify-v1-m1.output.txt`, `verify-v1-m1.server.log`, `verify-v1-m1.regression.output.txt`, questo report, una voce
  in `_AI-LOG.md`. Nessun commit.
