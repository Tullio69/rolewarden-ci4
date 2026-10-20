# U3 — Ricerca comandi (v1.1): collaudo indipendente

**Collaudatore ad Hoc (Claude), 2026-10-20, commit `fdb37dc` (main).**

## Esito

**FAIL. U3 non è approvata.**

- La ricerca fa ciò che la specifica chiede sui punti di sostanza: mai fuori portata (filtro sul server), completa, identificatori con «chi può farlo» coerente con Verifica e con `can()`/`explain()`, azioni che aprono la loro schermata senza scrivere nulla, overlay da tastiera con ARIA corretta, pagina senza JavaScript, inglese e italiano.
- Due difetti:
  - **D1:** una query con byte non UTF-8 (`?q=%FF%FE`) dà **500** in development e in production, con CRITICAL nel log.
  - **D2:** nelle barre Ink e Charcoal in modo chiaro l'etichetta del pulsante di ricerca è illeggibile (contrasto 1:1 – 2,1:1). Lo conferma in modo indipendente anche la suite U1 (S-*-light-4), quindi è anche una regressione di U1.
- Un'ambiguità nuova (colore del velo) e una sul conteggio dei ruoli nella risposta, da decidere.

Ho letto solo SPEC, BRIEF-v1.1, CHANGELOG (Unreleased), `documentation/` (configuration, api), il design system (README, CommandSearch, Sidebar), `_AI-LOG.md`, script e report di `tests/Integration/`, e HTML e CSS serviti. Del commit conosco solo il `--stat`. Non ho aperto controller, model, view, `src/Authorization`, `src/Settings`, `resources/css`, `src/Assets/js`, migrazioni né test unitari; `panel.js` non l'ho letto, ne ho osservato solo il comportamento e le richieste di rete in Chrome.

| Suite | development | production |
| --- | --- | --- |
| U3, fasi SRCH (HTTP) + OV (Chrome) | **156/165**, 1 AMB (FAIL = D1 ×3, D2 ×6) | **156/165**, uguale |
| U2 (CHK, ST) | **129/129** | **129/129** |
| U1 (lib adattata per U3, vedi sotto) | **220/226** (FAIL S-*-light-4 = D2) | **220/226**, uguale |
| A2 | **15/15** | **15/15** |
| V8b (L, M, P, I, G) | **116/116**, 1 AMB già nota (LA1) | **116/116**, uguale |
| S1, fase Y | **52/52** | **52/52** |
| V5 adattata | **177/177**, 1 AMB già nota (T06) | **177/177**, uguale |
| V2 principale | **151/151** | **169/169** |

## Come

- **App di prova:** copia usa e getta di `../rolewarden-app-test` col modulo da `git archive fdb37dc`, `php -S` sulla porta 8071. Porta 8000 (e 8001) dell'utente non toccate.
- **Database e posta:** solo `rolewarden_test` (127.0.0.1:3317), Mailpit. Credenziali solo come variabili di processo.
- **Riferimento:** ogni indirizzo restituito dalla ricerca viene aperto come lo stesso utente (deve dare 200: niente 403 né redirect di rifiuto); in più, per le azioni il permesso che `documentation/configuration.html` assegna loro, per gli identificatori `permissions.view`, per le schermate della barra il loro permesso, calcolati dalla precedenza della SPEC sulle tabelle. `can()`/`explain()` da un comando spark di prova (`u3:explain`) scritto solo nella copia.
- **Script:** `verify-u3.phase-srch.php`, `verify-u3.phase-ov.php`; runner `verify-u3.suite.py`, `verify-u3.harness.php`, `verify-u3.regress.py`, driver `verify-u3.all.sh`; `verify-u3.browser.mjs` (copia del browser U2 con tasti veri via CDP, testo, user agent macOS e cattura delle richieste); sonde senza controlli `verify-u3.phase-x.php`, `verify-u3.phase-x2.php` (uscita `verify-u3.x.*.txt`, `verify-u3.console.x2.production.txt`).

## 1. Mai fuori portata, e completa (REACH)

- **30 account:** super admin, admin, `user` senza permessi, senza ruolo, admin e super admin disattivati, admin disattivato a sessione aperta, super admin con override negativi (`users.view`, `permissions.view`, `roles.create`, `users.import`), admin con override negativi, ruoli con un solo permesso (`users.view`; `users.view`+`permissions.override`; solo `permissions.override`; solo `users.create`; `users.view`+`users.create`; import con e senza `users.view`; `permissions.view`; `roles.create`; `settings.view`; solo `settings.update`; `activity.view`; `appearance.update`; sessioni), ereditarietà padre e nonno, override positivi su `user`, positivo e negativo insieme, due ruoli, ruolo eliminato dal pannello.
- **41 query ciascuno:** vuota, ogni etichetta, 11 identificatori interi, parziali (`users.del`, `roles.a`, `u3zone`), parole (`users`, `role`, `e`, `perm`, `check`, `settings`).
- **RE-1 PASS:** nessun risultato restituito non si apre per quell'utente; nessuna azione senza il suo permesso (es. «Add an override for a user» solo con `permissions.override` **e** `users.view`; «Add a user» solo con `users.create`); nessun identificatore senza `permissions.view`.
- **RE-2 PASS:** ciò che l'utente raggiunge compare: con query vuota tutte le schermate e azioni attese, ogni etichetta trova la sua voce, ogni identificatore intero compare.
- **RE-3 PASS:** la risposta dell'overlay (`X-Requested-With`, solo i risultati) contiene esattamente i risultati della pagina, nello stesso ordine. Il filtro è sul server.
- **RE-4, RE-5, RE-off PASS:** senza `permissions.view` nessun identificatore; un utente senza permessi riceve solo profilo, sessioni proprie e cambio password; i disattivati entrano ma ricevono solo quelle tre voci.
- **RE-rev PASS:** permesso revocato a sessione aperta → sparisce alla richiesta successiva.
  - **Nota (non di U3):** con una scrittura diretta nel database la revoca, e anche la disattivazione, valgono solo dopo `cache:clear`; prima la ricerca e le schermate concordano tra loro. La disattivazione fatta dal pannello vale subito (sonda X2). Comportamento della cache della 1.0, che la ricerca segue.
- **RE-0, RE-cnt PASS:** per il super admin, «Go to» ha tutte le voci della barra più profilo e sessioni; i numeri accanto a Users, Roles, Permissions sono quelli dei contatori della barra.

## 2. Identificatori e «chi può farlo» (ID)

- **ID-all PASS:** ognuno dei 32 slug, scritto per intero, è il primo risultato, sotto «Permission», e porta a `permissions/check?permission=<slug>`.
- **Parziali PASS:** `users.del`, `roles.a`, `u3zone.re`, `permissions.vi` → il permesso giusto è primo, con «· who can do this» e la riga di risposta.
- **ID-open PASS:** il collegamento apre Verifica con quel permesso scelto.
- **ID-pv PASS:** `permissions.view` ereditato dal nonno basta; con solo `users.view` nessun identificatore e nulla verso Verifica.
- **ID-N PASS:** il numero di utenti della risposta coincide con i titolari secondo `can()` e con il conteggio della schermata Verifica, su 6 permessi. Ad esempio `u3zone.read`: «8 users through 4 roles, 4 denied by override», con 8 titolari = `can()` = Verifica.
- **ID-D PASS:** «denied by override» = utenti per cui `explain()` decide `override_denied`; quando sono zero non si dice.
- **ID-R:** il numero di ruoli coincide con `explain()` sotto una sola definizione: i ruoli **assegnati** ai titolari da cui il permesso arriva (primo elemento delle catene di `via`). Non coincide con i ruoli che lo concedono direttamente. Vedi A2.

## 3. Azioni (ACT, RO)

- **Azioni (5):** Add a user, Import users, Add a role, Add an override for a user, Change your password.
  - Sono collegamenti GET che aprono la loro schermata (200, un modulo da compilare). Nessuna punta a un endpoint che agisce, nessuna è un interruttore.
  - Aprirle tutte non cambia il database.
- **Ricerca (pagina e overlay, 10 GET, più POST/PUT/PATCH/DELETE con token valido):**
  - database invariato, fuori da `users.last_active` e `acl_sessions.last_seen_at`;
  - nessun 2xx per metodi diversi da GET.
- **Chrome (K-db):** aprire, scrivere, muoversi e scegliere una schermata, un'azione e un identificatore non scrive nulla, fuori dalla contabilità dell'accesso.
- **Pagina:** solo il form GET (`role="search"`, campo `q`) oltre a quelli della shell.

## 4. Overlay in Chrome (OV)

**Tastiera e ARIA (Console, chiaro, Paper), tutto PASS.**
- Ctrl K apre un `<dialog>` modale col campo a fuoco. Esc chiude e restituisce il fuoco al collegamento che lo aveva.
- Il pulsante della barra apre la ricerca con Invio da tastiera e col clic, senza cambiare pagina; Esc riporta il fuoco al pulsante.
- Su e Giù spostano l'evidenza 0 → 1 → 2 → 1, con un solo `aria-selected` e `aria-activedescendant` del campo su quella opzione; Invio apre l'indirizzo evidenziato (200).
- Su un'azione Invio apre la sua schermata («Add a role» → modulo); su `users.del` Invio apre Verifica su `users.delete`.
- Ctrl K funziona anche su Verifica e sulla pagina di ricerca. Il clic sul velo chiude. Nessun errore JavaScript.
- Albero di accessibilità di Chrome: `dialog` «Search or run a command», `combobox` «Search screens, actions and permissions», `listbox` «Results», `option` per ogni riga.
- L'overlay chiede `GET /rolewarden/search?q=…` con `X-Requested-With`, mai altri metodi.
- Su macOS (user agent e piattaforma emulati) il pulsante mostra «⌘ K» e Cmd K apre.
- Nella barra compressa la ricerca è un pulsante a icona (etichetta nascosta) con nome accessibile; Invio e Ctrl K aprono.

**Aspetto: 3 temi × chiaro/scuro × 3 stili della barra (18 combinazioni, 108 controlli).**
- Pannello di 600px, centrato, a 72px dall'alto, `surface-raised`, `shadow-float`, `radius-md` del tema, velo al 45%.
- Riga evidenziata in `accent-tint` con il suggerimento Enter, le altre senza.
- Campo a fuoco con l'anello del pannello (`focus`, stessa larghezza e scarto dei pulsanti del contenuto).
- Pulsante della barra raggiunto da tastiera: stesso anello delle voci della barra in quello stile.
- Ogni colore del dialogo e del pulsante è il valore di un token.
- Etichette dei gruppi `ink-muted`, piede su `surface`.
- Contrasto del testo nel dialogo ≥ 5,16:1 ovunque.
- **FAIL solo il contrasto del pulsante nella barra in Ink e Charcoal, modo chiaro (D2).**

## 5. Senza JavaScript

- Il pulsante della barra è un vero `<a href=".../rolewarden/search">`.
- La pagina (200, H1 «Search», form GET) dà gli stessi risultati dell'overlay (RE-3) per tutti i 30 account.
- Anonimo: la pagina manda al login; la richiesta dell'overlay non dà risultati né identificatori.

## 6. Ingressi ostili (HOS)

- **Ingressi provati (22, pagina e overlay):** `q` array semplice e annidato, 6000 caratteri, markup, SQL, `%`, `_`, `users.*`, NUL, a capo, emoji, `.`, `..`, `\`, entità, parametri estranei, nessun `q`.
- **Risultati:**
  - nessuna eco grezza: il campo conserva il testo con escape;
  - `%` e `_` sono testo, non caratteri jolly;
  - ogni risposta è UTF-8 valido;
  - nessun testo SQL.
- **FAIL H-1/K-1/K-2 per la sola sequenza non UTF-8 (D1).**

## 7. Lingue

- Tutte le stringhe hanno un testo italiano diverso da quello inglese: titolo, H1, introduzione, etichetta, pulsante, pulsante della barra, nome del dialogo, etichetta del campo, nome della lista, piede, gruppi, risultati, «chi può farlo» e stato vuoto.
- Nessuna frase inglese nelle risposte italiane.
- In italiano ogni schermata e azione si trova con la sua etichetta italiana.

## 8. Regressione

- **U2:** passa senza adattamenti in entrambi gli ambienti, compresa la ricerca dei titolari spostata in comune con Verifica (WHO, paginazione, 120 coppie).
- **U1:** adattata una copia, `verify-u3.u1lib.adapted.php`.
  - **Motivo:** il pulsante di ricerca sta in un contenitore con la stessa classe dei gruppi, `rw-side-group`, ma senza intestazione né voci. Il lettore della barra lo contava come un gruppo vuoto, e N-*-2/3 e N-grp fallivano (39 FAIL).
  - **Adattamento:** ora lo salta, perché il design system lo mette tra il nome e i gruppi, fuori dalla navigazione. Le aspettative non cambiano.
  - **Copie:** la prima uscita, non adattata, resta in `verify-u3.console.u1.development.unadapted-sb.txt`; in production è girata già adattata.
  - **Esito:** restano i 6 FAIL S-*-light-4, che sono D2.
- **V5 adattata:** 177/177 ×2.
- **A2, V8b, S1 Y, V2:** PASS ×2.

## Difetti

- **D1 — Una query non UTF-8 manda la ricerca in errore (500).**
  - **Riproduzione:** `GET /rolewarden/search?q=%FF%FE`, anche `%C3%28`, con o senza `X-Requested-With`, in development e production.
  - **Log:**
    - `CRITICAL Laminas\Escaper\Exception\RuntimeException: String to be escaped was not valid UTF-8`;
    - `ERROR Invalid message format: "Nothing you may reach matches "{0}"." … U_INVALID_CHAR_FOUND`;
    - in development anche `TypeError … Toolbar::run()`.
  - **Confronto:** le altre schermate con lo stesso ingresso (`permissions/check?permission=%FF`, `users?q=%FF%FE`) rispondono 200.
  - **Regole violate:** «nessun 5xx» e log puliti.
  - **Controlli:** H-1, K-1, K-2.
- **D2 — Pulsante della ricerca illeggibile nelle barre Ink e Charcoal in modo chiaro.**
  - **Comportamento:** «Search or run a command» (e l'icona) usa il colore d'accento della pagina, non i token della barra.
  - **Contrasto sulla barra scura:**

    | Tema | Ink | Charcoal |
    | --- | --- | --- |
    | Console | 1,84:1 | 1,99:1 |
    | Clarity | 1,73:1 | 2,11:1 |
    | Contrast | 1:1 (testo `rgb(11,61,145)` su `rgb(11,61,145)`) | 1,8:1 |

  - **Modi e stili non toccati:** in modo scuro e in Paper è corretto (≥ 6,8:1).
  - **Regole violate:** README (WCAG AA) e definizione di fatto della v1.1 («I tre stili della barra, nei tre temi in chiaro e in scuro, rendono…»).
  - **Prove:** schermate `verify-u3.ov-side-*-light.u3.fdb37dc.*.png`.
  - **Controlli:** LK-*-{ink,charcoal}-light-5 e, indipendentemente, U1 S-*-light-4.

## Ambiguità di specifica (all'utente, non decise)

- **A1 — Colore del velo.**
  - Il README dice «a 45% `ink` scrim»; il velo servito è sempre `rgba(23, 25, 28, 0.45)`, come scritto nel preview, in tutti i temi e modi.
  - Il token `ink` cambia per tema e in scuro è quasi bianco, quindi il velo non è il token `ink` in Clarity, in Contrast e in tutti i modi scuri.
  - Non è detto se il velo debba seguire `ink` del tema e del modo o essere un velo scuro fisso.
- **A2 — Cosa conta «N users through M roles».**
  - **Utenti:** N sono tutti i titolari, compresi super admin e override positivi, ed è giusto rispetto a Verifica.
  - **Ruoli:** M conta i ruoli **assegnati** ai titolari attraverso cui il permesso arriva, figli ed eredi compresi, non i ruoli che lo concedono. Per `u3zone.read`, concesso da 2 ruoli, la risposta dice «through 4 roles»; per `users.delete`, «5 users through 1 role» dove 3 dei 5 sono super admin.
  - Né il README né la SPEC definiscono il conteggio. Il design system mostra solo l'esempio «8 users through 2 roles, 1 denied by override».
- **Ancora aperta da U2:** «Access» = «Accesso» nella barra e «Autorizzazioni» nell'occhiello.

## Pulizia

- **Database:** `rolewarden_test` uguale allo snapshot `f7f37c04…e21e64` dopo ognuna delle 22 esecuzioni, sonde e prove comprese (`verify-u3.environment.txt`).
- **Mailpit:** vuoto.
- **Processi:** nessun processo php/python/node/chrome mio residuo.
- **Scratchpad:** restano in `scratchpad/u3/` 7 cartelle con soli profili Chrome bloccati (nessun `.env`, nessuna credenziale), da cancellare a mano.
- Nessun commit.
