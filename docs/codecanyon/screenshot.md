# Piano screenshot e immagine di anteprima - RoleWarden 1.0.0

Note per Fabio. Ogni screenshot nomina una schermata che esiste nel codice della 1.0.0
(rotte in `src/Config/RouteRegistrar.php`). Le didascalie da sovrapporre sono in inglese,
le istruzioni in italiano. Formati Envato: anteprima 590x300, miniatura 80x80, screenshot
a piacere ma tutti della stessa larghezza (consiglio 1440x900, Console chiaro per la
maggior parte, qualche variante scura e Clarity/Contrast per mostrare i temi).

Dove farli: staging (`staging.rolewarden.com`) o un'app di prova locale con la demo
installata (`php spark demo:install`) e la modalità showcase spenta. Browser a 1440 di
larghezza, zoom 100%, nessuna estensione visibile, cursore fuori dall'inquadratura.

## Immagine di anteprima 590x300

Concetto: un "registro" (ledger) di permessi, come la pagina stampata di un libro mastro.
Niente mockup del browser, niente screenshot rimpicciolito illeggibile: il messaggio deve
leggersi in un secondo nella griglia di CodeCanyon.

Composizione (sfondo `--surface` Console chiaro, graphite-25, bordo sottile `--rule`):
- Colonna sinistra (circa 55% della larghezza), allineata a una linea di base:
  - Riga 1, IBM Plex Mono 11px maiuscoletto, colore `--ink-muted`: `CODEIGNITER 4 · SHIELD`
  - Riga 2, IBM Plex Serif 400, 40px, `--ink`: `RoleWarden`
  - Riga 3, IBM Plex Sans 500, 17px, `--ink-muted`, su due righe:
    `Roles & permissions in the database,`
    `with an admin panel. Built on Shield.`
  - Riga 4, tre "timbri" in linea, IBM Plex Mono 12px, bordo 1px `--rule-strong`,
    raggio piccolo: `PHP 8.3+` `CI 4.7.4+` `Shield 1.4+`
- Colonna destra (circa 45%): un frammento della matrice permessi, ritagliato e leggibile,
  disegnato a mano (non screenshot): 4 righe x 3 colonne. Intestazioni colonna in Plex
  Sans 500 (`Reader`, `Author`, `Approver`), identificatori riga in Plex Mono
  (`documents.view`, `documents.create`, `documents.approve`, `documents.delete`), celle con
  il segno di spunta verde `--granted` su fondo `--granted-tint` dove concesso e un trattino
  `--ink-faint` dove no. Sulla cella `documents.delete` / `Author` un segno rosso `--denied`
  su `--denied-tint` con l'etichetta mono `override` sotto: è l'unico elemento rosso e
  racconta la feature degli override.
- Angolo in basso a destra, Plex Mono 10px `--ink-faint`: `v1.0.0`.
- Niente gradienti, niente ombre, un solo accento (ink blue `--accent`) usato solo per
  sottolineare la parola `Shield` nella riga 3.

Miniatura 80x80: solo il monogramma `RW` in Plex Serif su fondo `--accent` ink blue, testo
bianco `--on-accent`. [DA VERIFICARE] se esiste già un logo deciso in chat commerciale; in
caso contrario questo è coerente con il resto.

## Screenshot, in ordine

Ogni voce: schermata e rotta; dati da avere a schermo; didascalia da sovrapporre (banda in
alto, Plex Sans 500, 20px, `--ink` su `--surface-raised`, mai sopra i dati); cosa evidenziare
(riquadro 2px `--accent`, raggio 4px, oppure niente).

1. **Matrice permessi di un ruolo** - `/rolewarden/roles/{id}` del ruolo `Author` della
   demo. Dati: permessi `documents.*` e `users.view` con alcune spunte; almeno una riga con
   il segno di ereditarietà (la legenda in cima alla matrice lo spiega, il riepilogo dice
   "N own + M inherited permissions") per far vedere l'eredità da Reader; una voce non
   concessa con il suggerimento su come concederla. Didascalia: `One matrix per role. Inherited
   permissions are marked, missing ones tell you how to grant them.` Evidenziare: la cella
   ereditata.
2. **Dettaglio utente con override** - `/rolewarden/users/{id}` di `author@example.com`.
   Dati: ruolo Author assegnato, matrice utente con `documents.delete` negato (override
   rosso) e `users.view` concesso (override verde), sessioni attive in fondo. Didascalia:
   `Grant or deny a single permission to one user. A denial beats every role, super admin
   included.` Evidenziare: le due celle di override.
3. **Elenco utenti** - `/rolewarden/users?role=…&status=active`. Dati: 12-15 utenti con
   nomi credibili (nessun "test1"), ruoli misti, due disattivati visibili se togli il
   filtro; filtro per ruolo applicato, barra di ricerca vuota, paginazione a 2 pagine.
   Didascalia: `Search, filter by role and status, sort. Create, activate, deactivate,
   import.` Evidenziare: i filtri.
4. **Elenco ruoli** - `/rolewarden/roles`. Dati: `super-admin`, `admin`, `user` di sistema
   più Reader, Author (parent Reader), Approver (parent Author) e un ruolo in più, ad esempio
   `Support`, per mostrare la gerarchia; marcatori di sistema visibili. Didascalia: `Roles
   inherit from a parent. System roles cannot be deleted.` Evidenziare: colonna del padre.
5. **Log attività con filtri** - `/rolewarden/activity?action=…`. Dati: almeno 20 righe
   miste: `role.updated`, `user.role_assigned`, `user.deactivated`, `settings.updated`,
   `auth.login` fallito e riuscito, `user.imported`; filtro per periodo "ultimi 7 giorni"
   applicato. Didascalia: `Every change from the panel and every sign-in attempt, filtered
   by user, action and period.` Evidenziare: una riga di tentativo fallito.
6. **Impostazioni** - `/rolewarden/settings`. Dati: ruolo predefinito `user`, sessione
   2 ore, remember me attivo, blocco dopo 5 tentativi per 15 minuti, 10 tentativi al minuto
   per IP, conservazione 1 anno. Didascalia: `Default role, session lifetime, remember-me,
   lockout and rate limits, log retention. No config file edits.` Evidenziare: niente.
7. **Sessioni dell'utente** - `/rolewarden/sessions` dell'admin. Dati: 3 sessioni (desktop
   Windows/Chrome, iPhone/Safari, Linux/Firefox) con IP diversi e "last seen" diversi, una
   marcata come corrente, un browser "ricordato". Didascalia: `Every browser signed in to an
   account. Sign out one, all, or forget a remembered device.` Evidenziare: il pulsante
   "sign out all".
8. **Import CSV, anteprima** - `/rolewarden/users/import` dopo l'upload. Dati: file di
   10 righe con 1 riga in errore (email già usata) così si vede che il file viene rifiutato
   con l'errore sulla riga; oppure due screenshot, uno con errore e uno valido pronto alla
   conferma. Didascalia: `CSV import with a row-by-row preview: nothing is written unless
   every row is valid.` Evidenziare: la riga in errore.
9. **Personalizzatore tema** - `/rolewarden/appearance`. Dati: tema Clarity selezionato,
   accento cambiato, un colore di testo che fa scattare l'avviso WCAG AA (così si vede il
   controllo), anteprima live visibile accanto. Didascalia: `Three themes, light and dark.
   Live customiser with a WCAG AA contrast check. Saved as a file of your app.` Evidenziare:
   l'avviso di contrasto.
10. **Login e tema scuro Contrast** - Shield login restyled (`/login`) in Contrast scuro,
    oppure la matrice del punto 1 in Console scuro. Dati: campo email precompilato
    `admin@example.com`. Didascalia: `Restyled Shield sign-in. Registration and recovery
    stay Shield's own.` Evidenziare: niente.

Opzionale 11: **Installer** `/rolewarden/install` al passo dei requisiti, tutti verdi. Va
fatto su un'app vergine: didascalia `Browser installer: requirements, setup code, database,
first super admin.` Utile per il video più che per la scheda.

Opzionale 12: **Elenco permessi** `/rolewarden/permissions` con i ruoli che concedono
ciascuno: sostituisce il 4 se vuoi tenere 10 immagini.

## Pagine e stati da preparare prima

- App con `demo:install` eseguito (ruoli Reader/Author/Approver, 5 permessi
  `documents.*`, 3 account). Showcase spento.
- Un ruolo extra (`Support`, padre `user`) e 12-15 utenti con nomi realistici e ruoli misti;
  2 disattivati. Si possono creare con l'import CSV, che popola anche il log con
  `user.imported`.
- Override su `author@example.com`: nega `documents.delete`, concedi `users.view`.
- Log attività: generare almeno 20 righe con azioni diverse negli ultimi 7 giorni, inclusi
  2-3 login falliti (sbagliare la password da un browser in incognito) e un lock se vuoi
  mostrare l'email di sicurezza. Le righe `auth.login` arrivano da `auth_logins` di Shield,
  quindi vanno fatti login veri.
- Sessioni: accedere con lo stesso account da 3 browser/dispositivi diversi, uno con
  "remember me".
- Impostazioni salvate una volta (genera `settings.updated` nel log).
- Tema: salvare una personalizzazione su Clarity, poi `appearance/reset` prima degli
  screenshot in Console, così il resto resta sul tema di default.
- Lingua: tutti gli screenshot in inglese; uno solo in italiano è opzionale, per la
  didascalia `English and Italian included`.
- Emails: se vuoi uno screenshot dell'email "new sign-in", configura `fromEmail` e un
  Mailpit/Mailhog locale: i template sono in `src/Views/emails/`.
- [DA VERIFICARE] la decisione del 24/10 metteva la dashboard della 1.1 come prima
  immagine: con la 1.0.0 la prima immagine è la matrice (punto 1), coerente con
  `docs/COMMERCIALE.md`. Se la scheda esce con la 1.1.0, aggiungere la dashboard in testa e
  rifare la barra in tutti gli screenshot.
