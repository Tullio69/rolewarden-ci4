# Piano screenshot e immagine di anteprima - RoleWarden 1.1.0

Note per Fabio. Ogni screenshot riprende una schermata che esiste nella 1.1 (rotte in
`src/Config/RouteRegistrar.php`). Le didascalie da sovrapporre sono in inglese, le
istruzioni in italiano. Scaletta decisa il 2026-10-28: 8 immagini, la dashboard per prima
(decisione del 24/10, confermata il 28/10).

Formati Envato: anteprima 590x300, miniatura 80x80, screenshot tutti della stessa
larghezza: 1440x900, tema Console chiaro salvo dove indicato.

## Come si scattano

- App di prova locale (`../rolewarden-app-test`) con il modulo di `main`, **in production**
  (`CI_ENVIRONMENT = production`): niente debug toolbar di CodeIgniter.
- Chrome headless a 1440x900, `--lang=en-US` (i campi data di Activity seguono la lingua del
  browser), zoom 100%, movimento ridotto (`prefers-reduced-motion: reduce`) per avere ogni
  elemento già al suo posto. Lo script è `shoot.mjs` della sessione del 28/10: va portato in
  `bin/` o tenuto fuori dal pacchetto, decisione da prendere.
- Pannello in inglese (EN nella barra in alto), modalità chiara salvata dall'utente che
  scatta, tranne lo screenshot 8.
- Showcase della demo spento (la barra di cambio account non deve comparire).

## Dati da preparare (prima di ogni scatto)

Partire da un database pulito, mai da quello di sviluppo:

1. Installazione come da `documentation/installation.html` (migrazioni di Settings, Shield
   e RoleWarden, `RoleWardenSeeder`, oppure l'installer da browser), poi `php spark demo:install`
   (ruoli Reader, Author con padre Reader, Approver con padre Author; permessi
   `documents.*`; tre account; documenti di esempio). Il blocco "Demo documents" della
   dashboard non deve più dire "Run php spark demo:install".
2. Un super admin con un nome credibile, ad esempio `Maya Lindqvist`, non "admin".
3. 10-12 utenti con nomi realistici e ruoli misti, importati con l'import CSV (genera anche
   le righe `user.imported` del log): due disattivati, uno senza ruolo (mostra "No role").
   Nessun ruolo duplicato.
4. Override su `author@example.com`: nega `documents.delete` (rosso barrato), concedi
   `users.view` (cerchio verde).
5. Attività degli ultimi 30 giorni: login riusciti da più utenti in giorni e ore diversi
   (riempie grafico e mappa oraria), 3-4 login falliti, una modifica alle impostazioni,
   assegnazioni di ruolo, concessioni e revoche di permessi. Le righe `auth.login` vengono
   da `auth_logins` di Shield: servono login veri, oppure righe inserite con date
   all'indietro solo nel database di prova.
6. Tema: Console, nessuna personalizzazione salvata, barra Paper.

## Screenshot, in ordine

Ogni voce: schermata e rotta; cosa deve vedersi; didascalia (banda in alto, Plex Sans 500,
20px, `--ink` su `--surface-raised`, mai sopra i dati); cosa evidenziare (riquadro 2px
`--accent`, raggio 4px, oppure niente).

1. **Dashboard** - `/rolewarden`, periodo 30 giorni. Cifre chiave piene, grafico degli
   accessi con picchi e qualche fallimento, mappa oraria, utenti per ruolo con 4-5 ruoli,
   ultime modifiche ai permessi (leggono "granted documents.view to Reader"). Didascalia:
   `See who signs in, who holds which role and what changed, on one screen.` Evidenziare:
   niente.
2. **Matrice di un ruolo** - `/rolewarden/roles/{id}` di `Author`. Con più di 8 azioni la
   matrice è densa (etichette verticali): deve entrare intera a 1440. Righe `documents` con
   spunte proprie e segni di ereditarietà da Reader. Didascalia: `One matrix per role.
   Inherited permissions are marked; select a whole row or column at once.` Evidenziare:
   una cella ereditata.
3. **Dettaglio utente con override** - `/rolewarden/users/{id}` di `author@example.com`.
   Ruolo Author, matrice con `documents.delete` negato (rosso) e `users.view` concesso
   (verde). Didascalia: `Grant or deny a single permission to one user. A denial beats
   every role, super admin included.` Evidenziare: le due celle di override.
4. **Check a permission** -
   `/rolewarden/permissions/check?permission=documents.delete&user=author@example.com`.
   Verdetto negato col segno rosso, regola "A denial on this user" marcata DECIDES, sotto
   l'elenco di chi ha il permesso e perché. Didascalia: `Ask why: who holds a permission,
   and which rule decides for one user.` Evidenziare: la regola che decide.
5. **Elenco utenti** - `/rolewarden/users`. 10-12 utenti, ruoli misti, un "No role", due
   disattivati, filtro per ruolo visibile. Didascalia: `Search, filter by role and status,
   sort. Create, activate, deactivate, import from CSV.` Evidenziare: i filtri.
6. **Activity** - `/rolewarden/activity`, ultimi 7 giorni. Almeno 15 righe miste, con 2-3
   login falliti (segno rosso) e slug dei permessi in mono. Didascalia: `Every change from
   the panel and every sign-in attempt, filtered by user, action and period.` Evidenziare:
   un login fallito.
7. **Appearance** - `/rolewarden/appearance`. Tema Clarity selezionato, barra Ink, accento
   cambiato con il controllo WCAG che segnala un colore illeggibile, anteprima D visibile.
   Non salvare: lo scatto è sullo stato non salvato. Didascalia: `Three themes, light and
   dark, three sidebar styles. Live customiser with a WCAG AA contrast check.`
   Evidenziare: l'avviso di contrasto.
8. **Ricerca comandi, tema scuro** - Ctrl K sopra la dashboard in modalità scura, query
   `docu`: permessi `documents.*` con "who can do this", pagine e azioni. Didascalia:
   `Ctrl K finds pages, users, roles and permissions, filtered by what you may do.`
   Evidenziare: niente.

Riserve, se servono 10 immagini: Impostazioni (blocco dopo 5 tentativi, limite per
indirizzo, conservazione del log), Sessioni dell'utente, Import CSV con anteprima, Login
Shield ristilizzato in tema Contrast scuro.

## Immagine di anteprima 590x300

Scelta da Fabio il 2026-11-02: l'anteprima del kit del logo,
`docs/brand/png/codecanyon/inline-preview-590x300.png` (sorgente
`docs/brand/svg/codecanyon/inline-preview-590x300.svg`, generata da `docs/brand/build.py`).
Fondo navy, logo Warden Shield con il nome, titolo `Roles and permissions` /
`for CodeIgniter 4 Shield` e tre righe numerate: `Roles and permissions in the database`,
`Inheritance and per-user overrides`, `Admin panel built on Shield`. Si carica così com'è,
senza passare da Claude Design. I testi si cambiano solo in `build.py`, e devono restare
coperti da `inventario.md`.

Sostituisce la composizione a registro (ledger) del canvas del 2026-10-31, che nominava il
prodotto solo come testo.

Miniatura 80x80: il marchio Warden Shield, logo ufficiale dal 2026-11-02, file
`docs/brand/png/codecanyon/thumbnail-80.png`. Sostituisce il monogramma `RW` provvisorio.

## Tavole con didascalia

Gli 8 screenshot vanno montati in Claude Design come tavole 1440 di larghezza con la banda
della didascalia in alto (alta circa 64px, testo come sopra) e lo screenshot sotto, senza
cornici di browser. Gli evidenziatori sono rettangoli 2px `--accent` raggio 4px, mai
frecce.
