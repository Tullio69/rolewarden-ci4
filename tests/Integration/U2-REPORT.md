# U2 — Verifica di un permesso (v1.1): collaudo indipendente

**Collaudatore ad Hoc (Claude), 2026-10-19, commit `b921f97` (main).**

## Esito

**FAIL. U2 non è approvata.**

- La schermata è corretta su tutti i punti 1-7: accesso, esito e regola, chi lo ha, ingressi ostili, sola lettura, lingue e regressione.
- Ci sono tre difetti:
  - D1: un contratto di `explain()` non è rispettato;
  - D2 e D3: riguardano lo stato utente del commit `6f3922a`, punto 8.

Ho letto solo SPEC, BRIEF-v1.1, CHANGELOG (Unreleased), `documentation/api.html`, il design system (README, Sidebar, UserStatus, `bundle.css`), `_AI-LOG.md`, gli script e i report di `tests/Integration`, e HTML e CSS serviti. Del commit conosco solo il `--stat`. Non ho aperto controller, model, view, `src/Authorization`, `src/Settings`, `resources/css`, migrazioni né test unitari.

| Suite | development | production |
| --- | --- | --- |
| U2, fase CHK (schermata, `explain()`) | **82/83**, 0 AMB (FAIL O-6 = D1) | **82/83**, uguale |
| U2, fase ST (stato utente, Chrome, 3 temi × chiaro/scuro) | **38/44** (FAIL ST-*-7 = D2) | **38/44**, uguale |
| U1 (NAV e RAIL adattate) | **226/226** | **226/226** |
| A2 | **15/15** | **15/15** |
| V8b (L, M, P, I, G) | **116/116**, 1 AMB già nota (LA1) | **116/116**, uguale |
| S1, fase Y (layout 1280-1920) | **52/52** | **52/52** |
| V5 adattata | **176/177** (FAIL T05 = D3), 1 AMB già nota (T06) | **176/177**, uguale |
| V2 principale | **151/151** | **169/169** |

## Come

- **App di prova:** è la copia di `../rolewarden-app-test` con il modulo da `git archive b921f97`, servita da `php -S` sulla porta 8071. La porta 8000 non è stata toccata.
- **Database e posta:** solo `rolewarden_test` (127.0.0.1:3317), Mailpit.
- **Riferimento dell'esito:** la precedenza della SPEC calcolata dalle tabelle (`rule_model`). La confronto con `service('rolewarden')->can()` ed `explain()`, chiamati da un comando spark di prova (`u2:explain`) scritto solo nella copia usa e getta, e con la schermata.
- **Script:**
  - `verify-u2.phase-chk.php` e `verify-u2.phase-st.php`;
  - runner `verify-u2.suite.py`, `verify-u2.harness.php`, `verify-u2.regress.py` e driver `verify-u2.all.sh`;
  - `verify-u2.browser.mjs`: copia del browser U1, con `prefers-reduced-motion`;
  - `verify-u2.phase-x.php`: sonda esplorativa, senza controlli.

## 1. Accesso (ACC)

- **Account provati (12):**
  - super admin, admin, utente senza permessi;
  - ruolo con `permissions.view`, ereditato da padre e da nonno;
  - override positivo su `user`, override negativo su admin e su super admin;
  - admin disattivato;
  - ruolo con il permesso eliminato dal pannello;
  - ruolo con solo `users.view`.
- **Per ognuno:**
  - la voce «Check a permission» (Access, dopo Permissions) compare solo con il permesso;
  - la rotta `rolewarden/permissions/check` risponde 200 solo con il permesso;
  - senza il permesso il server la rifiuta senza mostrare nulla della risposta;
  - gli anonimi vanno al login.
- **Precedenza:** quella della SPEC coincide con `can()` per tutti i 12 account.
- **Schermata:** `aria-current="page"` è solo sulla voce nuova.
- **Elenco dei permessi:** ogni slug porta alla sua verifica.
- **Selettore:** offre esattamente i permessi del database.

## 2. Esito per utente e permesso (OUT)

**Copertura:** 20 utenti × 6 permessi, 120 coppie. Tutte le sei regole sono esercitate: `inactive` 18, `override_denied` 4, `override_granted` 4, `super_admin` 16, `role` 12, `none` 72.

**Casi:**
- disattivato con ruolo, con super admin e con override positivo;
- override negativo su ruolo e su super admin;
- override positivo, senza ruolo e sopra un ruolo che già concede;
- super admin;
- ruolo diretto, ereditato dal padre e ereditato dal nonno;
- due ruoli;
- nessuno;
- unico ruolo eliminato dal pannello;
- ruolo con padre eliminato.

**Risultati:**
- O-1/O-2: `explain()` ha la forma documentata, con `allowed` uguale a `can()`. `can()` ed `explain()['rule']` coincidono con la precedenza della SPEC in tutte le 120 coppie.
- O-3: la schermata dà l'esito di `can()` e marca esattamente una regola, quella di `explain()`, nell'elenco ordinato delle sei.
- O-5: i ruoli che la schermata nomina coincidono con `explain()['via']`.
- O-gp, O-p, O-sd, O-og, O-del, O-multi (due catene separate da virgola): PASS.
- **O-6 FAIL (D1).** Vedi sotto.
- **Ruolo con padre eliminato:**
  - il pannello rifiuta di eliminare un ruolo che ha figli (303, non elimina);
  - ho quindi segnato `deleted_at` a mano;
  - `can()` nega, e la schermata concorda;
  - nessuna ambiguità aperta.

## 3. Chi lo ha (WHO)

**Permessi provati (7):** `u2zone.read/write/none/page`, `users.view`, `permissions.view`, `settings.update`.

**Per ciascuno:**
- l'elenco è esattamente l'insieme degli utenti con `can()` vero, ognuno una volta, senza utenti eliminati (eliminato dal pannello) e senza disattivati;
- l'insieme coincide con la precedenza della SPEC;
- la colonna Why (Super admin / Override: granted / Role: catena) corrisponde a `explain()`;
- il link Check porta alla coppia giusta;
- il conteggio nell'intestazione è giusto su ogni pagina.

**Paginazione:**
- 47 titolari in 2 pagine da 25, numeri di riga continui;
- `page` = 999, 0, -3, abc, 1e3, 2.5: nessun errore e nessun estraneo.

**Elenco vuoto:** con un super admin attivo, obbligatorio per la SPEC, nessun permesso ha zero titolari, quindi lo stato vuoto non è raggiungibile.

## 4. Ingressi ostili (HOS), 26 casi

- **Casi provati:**
  - permesso inesistente, maiuscolo, jolly, senza punto, XSS, SQL, 3000 caratteri, NUL, array semplice e annidato;
  - utente inesistente, 0, -1, SQL, `%`, `_`, XSS, array, 5000 caratteri, 1.5, 0x1, email e id di un utente eliminato;
  - solo utente; entrambi array.
- **Risultati:**
  - nessun 5xx, nessun testo SQL o PHP, nessuna eco grezza;
  - mai un esito per un altro utente;
  - messaggi «There is no permission…» e «No user matches…».
- **Ricerca dell'utente:**
  - si trova per id, email e username;
  - un username parziale non trova nulla;
  - con un username fatto delle stesse cifre dell'id di un altro utente vince l'id.
- **Escape:** un nome di ruolo `<img src=x onerror=…>` e un username `<b>…</b>` creati ad arte sono stampati con escape nell'esito e nell'elenco.
- **`explain()` diretto:**
  - jolly e slug malformati danno `InvalidArgumentException`, come `can()`;
  - utente o permesso inesistente: diniego coerente con `can()`.

## 5. Sola lettura (RO)

- **Database:** identico prima e dopo 20 richieste della schermata con ogni tipo di ingresso. Unica eccezione la contabilità di ogni richiesta autenticata: `users.last_active` di Shield e `acl_sessions.last_seen_at`.
- **Metodi:** POST, PUT, PATCH e DELETE con un token valido danno 404.
- **Form:** la schermata ha solo il form GET.

## 6. Lingue

- **Confronto:** tutte le stringhe della schermata in 11 stati, inglese contro italiano: titolo, intestazioni, introduzione, etichette, placeholder, sei regole, esito, «Roles that hold it», tabella, messaggi e voce della barra.
- **Risultato:** ogni stringa ha la sua traduzione e nessuna frase inglese resta nelle pagine italiane. Uguale nelle due lingue solo «Super admin».
- Nota, vedi A1.

## 7. Regressione

- **U1:** girata con due copie adattate, che non cambiano le aspettative nella sostanza (226/226 ×2):
  - `verify-u2.u1lib.adapted.php`: `NAVMAP` con la voce nuova su `permissions.view`;
  - `verify-u2.phase-nav.adapted.php`: N-grp con Access a quattro voci, N-mid con 7 voci;
  - `verify-u2.phase-rail.adapted.php`: R-5 con 7 voci.
- **V8b:** passa senza adattamenti nuovi (L17 già adattato in U1). Non controlla l'insieme delle voci per permesso.
- **Altre suite:** A2, S1 Y e V2 PASS in development e production.

## 8. Stato utente (`6f3922a`)

**Conforme, in Chrome, 3 temi × chiaro/scuro:**
- puntino attivo `granted` (riempimento e bordo), 8px, tondo, parola `ink`;
- alone `granted-tint` di 3px a riposo;
- respira con un'animazione da 1.6s × 3 (4.8s), che anima solo `box-shadow`;
- dopo 5.6s è ferma e l'alone resta;
- disattivato: anello vuoto `ink-muted`, senza alone né animazione, mai `denied`.

**Contrasto del puntino sullo sfondo:** sempre ≥ 3:1.

| Tema e modo | Puntino/sfondo | Puntino/alone |
| --- | --- | --- |
| Console chiaro | 6.26:1 | 5.63:1 |
| Console scuro | 8.2:1 | 6.55:1 |
| Clarity chiaro | 5.02:1 | 4.5:1 |
| Clarity scuro | 9.87:1 | 7.45:1 |
| Contrast chiaro | 8.06:1 | 6.72:1 |
| Contrast scuro | 11.36:1 | 8.25:1 |

**Non conforme:** D2 (riduzione del movimento) e D3 (V5 T05).

## Difetti

- **D1 — `explain()['via']` è vuoto per un account disattivato.**
  - **Contratto:** `documentation/api.html` dice che `via` elenca le catene «even when another rule decides».
  - **Comportamento:** per un utente disattivato con ruolo `u2-grand`, che concede `u2zone.read` direttamente, `explain()` dà `{"allowed":false,"rule":"inactive","via":[]}` invece di `[[u2-grand]]`. Di conseguenza la schermata non mostra «Roles that hold it».
  - Con le altre regole che decidono (override negativo, override positivo sopra un ruolo) `via` è presente.
  - **Riproduzione:** `verify-u2.all.sh u2.development`, controllo O-6.
  - **Correzione:** o il resolver, o la documentazione. Lo decide l'autore, ma oggi i due sono in contrasto.
- **D2 — Con `prefers-reduced-motion: reduce` il puntino attivo si anima lo stesso.**
  - Il README (Motion, UserStatus) dice «never under `prefers-reduced-motion`».
  - **CSS servito:**

    ```css
    .rw-status:not(.rw-status--disabled):before { animation: 1.6s ease-in-out 3 rw-alive }
    ```

    che ha specificità (0,2,1). Il blocco `@media (prefers-reduced-motion: reduce) { .rw-status:before { animation: none } }` ha specificità (0,1,1) e perde.
  - **Verificato in Chrome:** `matchMedia(reduce)` vero, eppure l'animazione `rw-alive` gira, in tutti i 3 temi × 2 modi.
  - Lo stesso errore è nel riferimento `docs/design-system/components/bundle.css`.
  - **Riproduzione:** controlli ST-*-7.
- **D3 — Regressione V5 T05: valori di movimento grezzi.**
  - **Valori:** `1.6s` e `ease-in-out` nella regola dell'animazione dello stato. Le altre animazioni, come il toast, leggono `--motion-*` e `--ease-out`.
  - **Come per D2 di U1:** token o eccezione dichiarata, lo decide l'autore.
  - **Riproduzione:** `verify-u2.all.sh v5.development`.

## Ambiguità di specifica (all'utente, non decise)

- **A1 — «Access» in italiano.**
  - Il gruppo della barra (U1) è «Accesso», come nella SPEC.
  - L'occhiello della schermata nuova, come quello della schermata Permessi della 1.0, è «Autorizzazioni».
  - Stesso testo inglese, due traduzioni diverse. Non è detto quale valga per l'occhiello delle schermate di Access.

## Pulizia

- **Database:** `rolewarden_test` uguale allo snapshot `f7f37c04…e21e64` dopo ognuna delle 18 esecuzioni e alla fine (`verify-u2.environment.txt`).
- **Mailpit:** vuoto.
- **Processi:** nessun processo php/python/node/chrome mio residuo.
- **Scratchpad:** restano `u2w-dev2/` e `u2r-*` con soli profili Chrome che il sandbox non lascia cancellare (nessun `.env`, nessuna credenziale). Vanno cancellati a mano.
- Nessun commit.
