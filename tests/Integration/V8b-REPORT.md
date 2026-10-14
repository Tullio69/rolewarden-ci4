# V8b — Modifiche dopo la V8: collaudo indipendente

## Pacchetti rc.v8c — 2026-10-14, Collaudatore ad Hoc (Claude), commit `b1cca14`, zip `build/*-1.0.0-rc.v8c.zip`

**Esito: PASS. Pacchetti rc.v8c approvati per il collaudo.** Non ho trovato nessun difetto.

- **Cosa ho eseguito.** Le suite che sugli zip non avevo potuto eseguire: le fasi P ed E di V8, la suite V7 e il controllo dei pacchetti V7, una alla volta.
- **I FAIL che restano.** Il secondo e il terzo riquadro sotto: un controllo già giudicato corretto in V8 e un'aspettativa superata da V8.
- **Il primo giro della suite V7 è falsato.** Dava 21 FAIL, ma la causa è un'aspettativa dello strumento superata dal selettore della lingua (vedi sotto), non il prodotto.

| Suite sugli zip rc.v8c | Risultato |
| --- | ---: |
| V8, fasi P ed E (`verify-v8b.v8zip.b1cca14.rc.v8c.output.txt`) | 35/36 (P06d, già giudicato corretto in V8, osservazione O7) |
| V7, adattata (`verify-v8b.v7.b1cca14.rc.v8c.adapted.output.txt`) | **181/181**, 1 AMB (l'A1 di V7, ancora aperta) |
| V7, così com'era (`verify-v8b.v7.b1cca14.rc.v8c.output.txt`) | 160/181: tutti i 21 FAIL vengono dall'aspettativa superata descritta sotto |
| Pacchetti V7 (`verify-v8b.v7package.b1cca14.rc.v8c.output.txt`) | 15/16 (P04, superato da V8 come prima) |

### Dettaglio

**Fase P di V8 (zip): 22 controlli, come in V8.**
- Lo zip del modulo è identico a `git archive b1cca14` e lo zip della demo a `git archive b1cca14:demo`: stessi file, stessi byte (P01, P02). Il testo dei due controlli nomina ancora `f06a29d`, ma il confronto è fatto su `b1cca14`.
- L'applicazione completa contiene il modulo in `modules/rolewarden`, identico allo zip, e la demo in `modules/rolewarden-demo`, con le righe di aggancio.
- Ci sono CHANGELOG e THIRD-PARTY-LICENSES.
- Ogni pacchetto di `vendor/` ha il suo file di licenza, con la versione elencata.
- Nessuna credenziale, chiave, `.env` o percorso personale.
- La documentazione si apre offline.
- **P06d.** Fallisce come in V8 e per la stessa frase: il README alla radice nomina `documentation/design-guide.html` e dice di aprirlo «from the module folder». In V8 è stato giudicato corretto (osservazione O7) e non lo riconto.

**Fase E di V8 (applicazione completa dallo zip): 9/9.**
- `/demo` non risponde finché la demo non è installata.
- L'installer va dal codice al primo super admin, con il file di blocco.
- `demo:install`, l'uso e `demo:uninstall` sono puliti, con il `.env` scritto dall'installer.
- Nessun 500.

**Suite V7.**
- **Così com'era.** Dà 21 FAIL, tutti nella sezione E (lingue), più le email che ne dipendono. La causa è nello strumento: `set_lang()` di `verify-v7.php` usa il **primo** form che invia a `rolewarden/profile/language`. Da `6d21875` quel form è il selettore EN/IT della barra in alto, che non ha la select, quindi la lingua non veniva mai inviata.
- **Adattata.** `verify-v8b.v7adapted.php` è identico tranne che per quella riga, che ora prende il form del profilo con la select. Passa 181/181 su questi zip. La scelta della lingua dal profilo funziona: toast «Lingua salvata.», `lang="it"`, persistenza, valori non validi ignorati, email nella lingua del destinatario, terza lingua.
- È un'aspettativa superata come G07 e G07v5, non un difetto.
- L'ambiguità A1 di V7 (dati seminati in inglese) resta aperta.

**Pacchetti V7: 15/16.** P04 vuole nel modulo solo README, composer.json, `documentation/` e `src/`. Ora ci sono anche CHANGELOG.md e THIRD-PARTY-LICENSES.md, che la SPEC V8 richiede: è un'aspettativa superata, come già in V8.

### Fallimenti noti e ambiguità, non ricontati

- G07 e i 25 controlli V4 sulla data ISO. Queste suite non le ho rieseguite qui: lo zip non le tocca, e sul codice `e99fe1b`, uguale a `b1cca14` fuori da test e log, erano già chiuse.
- A1, A3, A15c, G03 e l'A1 di V7 restano aperte all'autore.

### Pulizia

- **Database e Mailpit.** `rolewarden_test` uguale allo snapshot `f7f37c04…e21e64` in tutte le 61 esecuzioni registrate in `verify-v8b.environment.txt`, senza nessuna differenza, e di nuovo a mano alla fine. Mailpit vuoto.
- **Processi.** Nessun processo mio attivo.
- **Cartelle e file.** Le cartelle di lavoro delle tre suite sono rimosse, con i `.env`.
- **Da cancellare a mano.** Restano solo i profili Chrome già segnalati in `scratchpad/`: da `v8breg` a `v8breg4`.

Nessun commit, nessun push.

---

## Terza riverifica — 2026-10-14, Collaudatore ad Hoc (Claude), commit `e99fe1b` (locale)

**Esito: PASS. V8b approvata per il collaudo.** D4 è corretto e non resta nessun difetto aperto. Le ambiguità A1 e A3 restano aperte all'autore.

Tra `0943a60` ed `e99fe1b` cambia solo `src/Controllers/AccountController.php` (3 righe in più, 2 in meno, da `git diff --stat`; contenuto non letto).

| Suite su `e99fe1b` | development | production |
| --- | ---: | ---: |
| V8b (`verify-v8b.php`, fasi L, M, P, Y, I, G; 132 controlli con L20e e L20f) | 130/132, 1 AMB | 130/132, 1 AMB |
| V8 (`verify-v8.php`, fasi A-D, F, G) | 195/195, 2 AMB già aperte (A15c, G03) | |
| V5 | 176/177: solo G07v5, superato (L17) | 176/177: solo G07v5 |

- **I due FAIL di V8b** in ciascun ambiente sono Y03-1280 e Y03-1366, cioè l'ambiguità A3, non contata come difetto. L'AMB è A1.
- **Le altre suite** (V2, V3, V4, V6) le ho lasciate ai risultati di `0943a60`, come concordato col coordinatore: il diff tocca solo la condizione del selettore della lingua, che V8b esercita per intero.
- **Esecuzione.** Ogni suite è girata da sola e in un solo ambiente, senza job paralleli.
- **Uscite:**
  - `verify-v8b.e99fe1b.{development,production}.output.txt`;
  - `verify-v8b.v8.e99fe1b.output.txt`;
  - `verify-v8b.regression.v5.*.e99fe1b.console.txt`.

### D4 e i controlli collegati

| Controllo | development | production |
| --- | --- | --- |
| L20d: `back` interno con query costruite a mano, compreso CR/LF grezzo (che in `0943a60` dava il 500) | PASS | PASS |
| L20e (nuovo): `back` con CR, LF, CRLF, NUL, TAB o DEL grezzi nella query, nel percorso o in fondo (18 casi). Atteso: nessun 500, redirect dentro l'applicazione senza caratteri di controllo, nessun header iniettato, lingua salvata | PASS | PASS |
| L20f (nuovo): negli stessi 18 casi il campo è ignorato e si torna alla pagina da cui il selettore è stato usato | PASS | PASS |
| P12: nessun 500, SQL o errore PHP nella fase P | PASS | PASS |
| G01: log a soglia 9 senza righe WARNING, ERROR o CRITICAL oltre ai rifiuti CSRF provocati dalla suite | PASS | PASS |
| G03: nessun 500, SQL o errore PHP su 290 risposte | PASS | PASS |
| L08, L11-L11c, L19, L19b, L20-L20c: query string conservata, due schede, nessun redirect esterno con Referer, Host, X-Forwarded-Host o campo costruito a mano | PASS | PASS |

In tutti i 18 casi di L20e il redirect è un 303 a `http://127.0.0.1:8071/index.php/rolewarden/permissions`, la pagina di partenza. Il valore con i caratteri di controllo viene scartato e la lingua viene comunque salvata.

### Stato dei difetti

| Difetto | Stato |
| --- | --- |
| D1, due schede | Corretto in `57f6f09`, confermato qui (L19, L19b) |
| D2, override in sola lettura | Corretto in `57f6f09`, confermato qui (M19, M19e) |
| D3, query string persa | Corretto in `0943a60`, confermato qui (L08) |
| D4, 500 con caratteri di controllo | **Corretto** in `e99fe1b` (L20d, L20e, L20f, P12, G01, G03) |
| A2, «Not granted» nella pagina utente in sola lettura | Corretto in `57f6f09`, confermato qui (M19f) |

### Aperte all'autore

- **A1.** Come si ottiene una sola lingua disponibile.
- **A3.** Lo scorrimento orizzontale a 1280 e 1366 px, invariato, con la causa nel contenuto di `main`.
- **Da V8 e V7.** A15c e G03.
- **Dovuto all'autore.** Il controllo visivo in un browser vero.
- **Non provati.** Gli zip: `build/` è ancora a `rc.v8b`, quindi la fase P e la fase E di V8, la suite V7 e i pacchetti vanno ripetuti dopo la ricostruzione.

### Pulizia

- **Database e Mailpit.** `rolewarden_test` uguale allo snapshot `f7f37c04…e21e64` in tutte le 58 esecuzioni registrate in `verify-v8b.environment.txt`, senza nessuna differenza, e a mano alla fine. Mailpit vuoto.
- **Processi.** Nessun processo mio attivo.
- **File.** `.env` cancellati.
- **Da cancellare a mano.** Restano solo profili Chrome che Windows rifiuta di cancellare, in `scratchpad/`: `v8breg`, `v8breg2`, `v8breg3` e `v8breg4`.

Nessun commit, nessun push.

---

## Seconda riverifica — 2026-10-14, Collaudatore ad Hoc (Claude), commit `0943a60` (locale)

**Esito: FAIL. V8b non approvata.**
- **D3 corretto.** Si torna alla pagina con la query string.
- **Regressione chiusa** in development e production, con i soli fallimenti noti.
- **D4, nuovo e minore.** Il campo `back` con un a capo grezzo (CR/LF) nella query string costruita a mano dà un 500. È un effetto della correzione di D3. Nessun header viene iniettato e nulla esce dall'applicazione.

| Suite su `0943a60` | development | production |
| --- | ---: | ---: |
| V8b (`verify-v8b.php`, fasi L, M, P, Y, I, G; 130 controlli con il nuovo L20d) | 124/130, 1 AMB | 124/130, 1 AMB |
| V8 (`verify-v8.php`, fasi A-D, F, G) | 195/195, 2 AMB già aperte (A15c, G03) | |
| V6 | 151/151 | 151/151 |
| V5 | 176/177: G07v5 superato (L17) | 176/177: G07v5 |
| V4 | 207/232: i 25 FAIL noti della data ISO, nient'altro | 207/232: gli stessi 25 |
| V2 `test` / `extra` / `adapt` | 150/151 (G07, noto) + 41 + 16 | 168/169 (G07, noto) + 54 + 16 |
| V3 principale / supplementare / filtri array | 139 + 81 + 55 | 139 + 81 + 55 |

**Totali della regressione V2-V6.**
- Grezzi: development 1016/1043, production 1047/1074, uguali a V8 e al primo giro V8b.
- Tolti i fallimenti noti (G07 e le 25 date ISO di V4) resta solo G07v5. È un'aspettativa superata: i submit in più sono il selettore della lingua.

**I 6 FAIL della suite V8b, uguali nei due ambienti.**
- L20d, P12, G01 e G03 sono tutti D4: lo stesso 500 contato nella fase L, nel controllo delle risposte della fase P, nel log e nel controllo globale.
- Y03-1280 e Y03-1366 sono l'ambiguità A3, non contata come difetto.

**Esecuzione e uscite.**
- Ogni suite è girata da sola e in un solo ambiente, una chiamata alla volta: nessun job in parallelo e nessuna interruzione per memoria.
- La suite V8 crea da sé le copie dell'applicazione; l'ho eseguita una volta, come in V8.
- Uscite:
  - `verify-v8b.0943a60.{development,production}.output.txt`;
  - `verify-v8b.v8.0943a60.output.txt`;
  - `verify-v8b.regression.*.0943a60.console.txt`.

### D3 e redirect

| Controllo | Esito |
| --- | --- |
| L08: da `/rolewarden/users?q=v8b&page=1` il selettore torna esattamente a quell'indirizzo | **PASS**: D3 corretto |
| L19, L19b: due schede, nei due ordini | PASS |
| L11-L11c: Referer esterno, Host e X-Forwarded-Host falsificati | PASS |
| L20, L20b, L20c: 19 valori esterni nel campo `back`; indirizzo locale seguito; campo e Referer esterni insieme | PASS |
| L20d (nuovo): `back` interno con query costruite a mano | **FAIL**: D4 |

**Valori provati in L20d**, tutti dopo un `back` interno:
- `?x=//evil.example.com`
- `?next=http://evil.example.com/&q=a`
- `?x=%0d%0aX-Injected:%201`
- `?x=%0d%0aLocation:%20http://evil.example.com/`
- `#//evil.example.com`
- markup codificato
- 3000 caratteri
- CR/LF grezzo

Tutti tranne l'ultimo tornano con 303 a `http://127.0.0.1:8071/index.php/rolewarden/users…` e la query intatta. Le sequenze `%0d%0a` restano codificate e non generano nessun header in più.

### Difetto nuovo

**D4 — minore. Un a capo grezzo nel campo `back` dà un 500** (L20d, ripreso da P12, G01 e G03).

Riproduzione:
1. Da utente collegato, inviare a `rolewarden/profile/language` una POST con CSRF valido, `language=en` e `back=http://<app>/index.php/rolewarden/users?x=` seguito da CR LF e `X-Injected: 1`, con i caratteri grezzi e non codificati.
2. Il form lo farebbe solo se modificato a mano.

Osservato:
- La risposta è un 500 sia in development sia in production, con `CRITICAL … InvalidArgumentException: The header value is not valid as per RFC 7230.` nel log.
- Nessun header iniettato: il framework rifiuta il valore.
- Nessun testo SQL a schermo.
- Su `57f6f09` L20d non esisteva ancora. Lì la query del campo veniva scartata (D3), quindi con ogni probabilità il caso non arrivava al redirect: non l'ho verificato.

Il valore andrebbe rifiutato, o ricondotto alla pagina di partenza, come succede per un `back` esterno: un input costruito a mano non deve produrre un 500 né una riga CRITICAL. È minore perché non apre nessun redirect né iniezione, e solo l'utente collegato può provocarlo, contro sé stesso.

### Ambiguità aperte all'autore

- **A1.** Come si ottiene una sola lingua disponibile.
- **A3.** Lo scorrimento orizzontale a 1280 e 1366 px: invariato, con la causa nel contenuto di `main`.
- **Già aperte da V8 e V7.** A15c (voci del log sugli oggetti della demo) e G03 (dati della demo seminati in inglese).

### Pulizia

- **Database e Mailpit.** `rolewarden_test` uguale allo snapshot `f7f37c04…e21e64`. Il confronto è riuscito in tutte le 53 esecuzioni registrate in `verify-v8b.environment.txt`, senza nessuna differenza, e di nuovo a mano alla fine. Mailpit vuoto.
- **Processi.** Nessun processo mio attivo.
- **File.** `.env` cancellati.
- **Da cancellare a mano.** Restano solo profili Chrome che Windows rifiuta di cancellare, in `scratchpad/`: `v8breg`, `v8breg2` e `v8breg3`.

Nessun commit, nessun push.

---

## Riverifica — 2026-10-14, Collaudatore ad Hoc (Claude), commit `57f6f09` (main, locale)

**Esito: FAIL. V8b non approvata.**
- **D1, D2 e l'ambiguità A2 sono corretti.**
- **D3, nuovo e minore.** La correzione di D1 fa perdere la query string quando si torna alla pagina.
- **Regressione incompleta.** È stata fermata da Claude Code per memoria di sistema insufficiente dopo V6: vedi «Cosa ho rifatto».

Mandato e regole sono quelli del primo giro. Dei file cambiati dal commit ho visto solo i nomi, mai il contenuto: `AccountController`, il partial della lingua, le viste del ruolo e dell'utente.

| Suite su `57f6f09` | development | production |
| --- | ---: | ---: |
| V8b (`verify-v8b.php`, fasi L, M, P, Y, I, G; ora 129 controlli) | 126/129, 1 AMB | 126/129, 1 AMB |
| V6 | 151/151 | 151/151 |
| V5, V4, V2, V3, suite V8 | **non rieseguite** (vedi sotto) | **non rieseguite** |

I 3 FAIL di ciascun ambiente sono:
- L08, che è D3;
- Y03-1280 e Y03-1366, che sono l'ambiguità A3, non contata come difetto.

L'altra ambiguità aperta è A1. A2 non è più un'ambiguità: ora è il controllo M19f e passa.

Uscite:
- suite V8b: `verify-v8b.57f6f09.{development,production}.output.txt`;
- regressione: `verify-v8b.regression.v6.*.57f6f09.console.txt`.

### Difetti del primo giro

| Difetto | Stato | Evidenza |
| --- | --- | --- |
| D1, due schede | **Corretto** | L19, la riproduzione originale: si torna a Permissions anche dopo aver caricato Activity in un'altra scheda. L19b, ordine inverso: si torna all'elenco utenti anche dopo aver caricato Settings. Il form ora porta un campo nascosto `back` con l'indirizzo della propria pagina |
| Sicurezza del redirect | **Tiene** | L11-L11c: Referer esterni, Host e X-Forwarded-Host falsificati non vengono mai seguiti. L20: il campo `back` è stato riempito a mano con 19 valori (sotto) e il redirect è sempre rimasto sull'applicazione, senza 500. L20b: un indirizzo locale nel campo viene seguito, quindi il campo è davvero usato. L20c: con campo e Referer esterni insieme il redirect resta interno e la lingua viene salvata |
| D2, override in sola lettura | **Corretto** | M19 e M19e: le matrici in sola lettura, del ruolo e dell'utente, hanno in ogni cella lo stesso marchio della versione modificabile. Gli override di concessione hanno (✓) |
| A2, «Not granted» nella pagina utente in sola lettura | **Corretto** | M19f, nuovo controllo: il quadratino tratteggiato con il tooltip «· Not granted» c'è su ogni permesso esistente non concesso, esattamente le stesse celle della pagina modificabile. Nulla dove il permesso non esiste |

I 19 valori provati in L20 sono:
- un altro host, in http e in https;
- `//host` e `///host`;
- `/\host` e `\\host`;
- lo stesso prefisso con un altro host (`127.0.0.1:8071.evil…`, `127.0.0.1:8071@evil…`);
- lo stesso host con un'altra porta (`127.0.0.1:80719`);
- un altro schema (`https://127.0.0.1:8071/`);
- `http:evil…`;
- spazio o tab iniziale;
- `javascript:` e `JaVaScRiPt:`;
- `data:`;
- `/%2F%2Fevil…`;
- `evil%2F@127.0.0.1`;
- `../` dentro l'indirizzo dell'app.

### Difetto nuovo

**D3 — minore. Tornando alla pagina, il selettore perde la query string** (L08).

Riproduzione:
1. Aprire `/rolewarden/users?q=v8b&page=1`, cioè una ricerca o una pagina dell'elenco.
2. Premere EN o IT.

Osservato:
- Su `57f6f09` il redirect va a `/rolewarden/users`: ricerca, filtri e numero di pagina vanno persi.
- Su `6d21875` lo stesso controllo passava: si tornava esattamente all'indirizzo con la query.
- Dal comportamento sembra che il campo `back` porti l'indirizzo della pagina senza la query string.

Il comportamento dichiarato è «torna alla pagina da cui è stato usato». Qualunque correzione deve conservare il risultato di L20: nessun indirizzo esterno, in nessuna forma.

### Cosa ho rifatto e cosa no

- **Rifatto.**
  - Le fasi V8b complete in development e production, con i controlli nuovi L19b, L20, L20b, L20c e M19f.
  - L17, l'aspettativa aggiornata di G07/G07v5: in Settings in sola lettura gli unici submit sono quelli del selettore della lingua e del chiaro/scuro. Passa in entrambi gli ambienti.
  - V6 in development e production: 151/151.
- **Non rieseguito.**
  - Il job della regressione (V6 → V5 → V4 → V2 → V3, poi la suite V8 A-D/F/G) è stato fermato da Claude Code per memoria di sistema insufficiente durante V5 development.
  - Il driver era rimasto vivo: l'ho fermato per PID, insieme ai suoi figli, poi ho ripristinato il database dallo snapshot (hash `f7f37c04…e21e64` identico) e svuotato Mailpit.
  - Come da istruzioni non l'ho rilanciato. L'uscita parziale è `verify-v8b.regression.v5.development.57f6f09.interrupted.console.txt`.
  - Le modifiche toccano il partial della lingua, presente su ogni pagina, e le viste del ruolo e dell'utente, che V2, V3 e V6 attraversano. Per chiudere la regressione basta, una suite alla volta e con le variabili di processo impostate:

    `RW_V8_NEW=57f6f09 RW_V8_SCRATCH=<scratch> bash verify-v8b.regression-all.sh v5:development v5:production v4:development v4:production test:development test:production extra:development extra:production adapt:development adapt:production v3:development v3:production edges:development edges:production arrays:development arrays:production`

    Poi `python verify-v8b.run.py v8 57f6f09 A,B,C,D,F,G`, con `RW_V8B_WORK` impostata.

### Pulizia della riverifica

- **Database e Mailpit.** Snapshot `f7f37c04…e21e64` identico dopo le due esecuzioni V8b, le due V6 e il ripristino manuale dopo l'interruzione. Mailpit vuoto.
- **Processi.** Nessun processo mio attivo; il driver interrotto e i suoi figli sono stati fermati per PID.
- **File.** `.env` cancellati, compreso quello del giro interrotto.
- **Da cancellare a mano.** Restano solo profili Chrome che Windows rifiuta di cancellare, in `scratchpad/`:
  - `v8breg` e `v8breg2`;
  - `v8b-locked-development` e `v8b-locked-production`.

Nessun commit, nessun push.

---

## Collaudo — 2026-10-14, Collaudatore ad Hoc (Claude), commit `6d21875` (baseline `be64b53`, V8 PASS)

**Esito: FAIL. V8b non approvata.** Due difetti minori:

- **D1**, nuovo. Con due schede aperte il selettore della lingua torna all'ultima pagina caricata, non a quella da cui è stato usato.
- **D2**, già presente in `be64b53`. Nelle matrici in sola lettura una concessione per singolo utente ha il marchio di una concessione del ruolo.

Tre ambiguità di specifica, separate dai difetti e non decise:

- **A1**: come si ottiene una sola lingua disponibile;
- **A2**: il quadratino «Not granted» nella pagina utente in sola lettura;
- **A3**: lo scorrimento orizzontale a 1280 e 1366 px, peggiorato rispetto a `be64b53`.

Tutto il resto delle cinque modifiche passa, così come la regressione: restano solo i fallimenti già noti, più G07v5, superato dal selettore.

| Suite | development | production |
| --- | ---: | ---: |
| V8b (`verify-v8b.php`, fasi L, M, P, Y, I, G) | 119/124, 2 AMB | 119/124, 2 AMB |
| V8 (`verify-v8.php`, fasi A, B, C, D, F, G) | 195/195, 2 AMB già aperte (un'esecuzione, l'ambiente lo sceglie la suite) | |
| V6 | 151/151 | 151/151 |
| V5 | 176/177: G07v5, superato (vedi sotto) | 176/177: G07v5 |
| V4 | 207/232: i 25 FAIL noti della data ISO | 207/232: gli stessi 25 |
| V2 `test` / `extra` / `adapt` | 150/151 (G07, noto) + 41 + 16 | 168/169 (G07, noto) + 54 + 16 |
| V3 principale / supplementare / filtri array | 139 + 81 + 55 | 139 + 81 + 55 |

Uscite:

- Suite V8b: `verify-v8b.6d21875.{development,production}.output.txt`.
- Suite V8: `verify-v8b.v8.6d21875.output.txt`.
- Regressione: `verify-v8b.regression.*.6d21875.console.txt` e `verify-v8b.*.6d21875.output.txt`.
- Confronto sulla baseline: `verify-v8b.baseline-be64b53.output.txt`.
- Attribuzione dello scorrimento: `verify-v8b.6d21875.production.layout-attribution.output.txt`.
- Snapshot `f7f37c04…e21e64` identico prima e dopo **tutte** le 28 esecuzioni (`verify-v8b.environment.txt`).

### Perimetro e regole

- **Letto.** SPEC, BRIEF v1.0, `_AI-LOG.md`, README, CHANGELOG, `documentation/`, `docs/design-system/README.md` (marchi), `components/PermissionMatrix/README.md`, i contratti, gli script di `tests/Integration/`. I file di lingua en/it li ho letti solo per traduzione e chiavi; ho guardato le righe cambiate, che sono contenuto di traduzione e non implementazione. Il resto è HTML, CSS e JS così come li riceve Chrome.
- **Non aperto.** Nessun controller, model, vista PHP, adapter, test unitario, né il diff del codice. Non ho aperto il repo `../rolewarden-demo`.
- **Ambiente.**
  - Copie di `../rolewarden-app-test` nello scratchpad, con il modulo da `git archive 6d21875` agganciato come junction e la demo da `git archive 6d21875:demo`.
  - Database solo `rolewarden_test` su 127.0.0.1:3317. Credenziali solo come variabili di processo.
  - Mailpit su 1026, indirizzi `@v8b.test`/`@example.com`.
  - Un server PHP alla volta (`error_reporting=-1`). Chrome headless con profilo nella cartella di lavoro, chiuso per PID.
  - Docker Desktop era spento: l'ho avviato e ho fatto ripartire solo i container `rolewarden-db` e `rolewarden-mail`.
  - Nessun accesso ai server remoti.

### Esito per punto

| # | Punto | Esito | Controlli |
| --- | --- | --- | --- |
| 1 | Selettore lingua | **FAIL** minore (D1), più A1 | L00-L19 |
| 2 | Matrici e marchio «Not granted» | **FAIL** minore (D2, già presente), più A2 | M00-M19e |
| 3 | Permissions, «Granted by» | PASS | P00-P12 |
| 4 | Installer, decisione A2 di V7 | PASS | I00-I09 |
| 5 | Layout a 1280, 1366, 1920 | PASS per gutter, numeri, allineamento e intestazioni; scorrimento orizzontale in A3 | Y00-Y05 |
| 6 | Regressione | PASS (solo i fallimenti noti, più G07v5 superato) | tabella sopra |
| 7 | Log | PASS | G01-G03 |

**1. Selettore lingua.**
- **Presenza.** Il form è in POST verso `rolewarden/profile/language` con CSRF, bottoni EN e IT, `lang` e nome nella propria lingua. C'è:
  - su 15 pagine del pannello per il super admin;
  - su profilo e sessioni per un utente senza permessi;
  - sulle pagine della demo per l'autore demo e per il super admin (L01-L03b).
- **Andata e ritorno.** Il cambio a IT dalla pagina Permissions torna a quella pagina. Il titolo diventa «Permessi», `lang="it"` e IT risulta premuto (`aria-pressed`). Anche l'etichetta del form è tradotta (L04-L04d).
- **Persistenza.** La scelta è salvata nelle impostazioni dell'utente. Il selettore del profilo la mostra (L05, L06). Vale dopo un nuovo accesso da un browser pulito (L07).
- **Ritorno all'inglese.** Dall'elenco utenti con query string si torna esattamente a quell'indirizzo, e il profilo segue (L08-L08c).
- **Valori non validi ignorati senza errori.** `xx`, `EN`, `it;`, `../en`, `it_IT`, 300 caratteri, markup e un array (L09, L09b).
- **Rifiuti.** Senza CSRF, con un CSRF sbagliato e via GET: nulla cambia (L10-L10c).
- **Nessun open redirect.** Referer verso un altro host, verso un'altra porta, scheme-relative o `javascript:`: il redirect resta sull'app. Lo stesso con Host o X-Forwarded-Host falsificati sulla richiesta precedente (L11-L11c). Senza Referer il redirect resta interno (L12).
- **Ospite.** Rimandato al login, nulla salvato (L13). Il valore vuoto del profilo («Same as the application») riporta alla lingua dell'app (L14).
- **Demo.** Si torna alla lista, tradotta («Documenti»), e poi indietro (L15, L15b). La scelta di un utente non tocca quella degli altri (L16).
- **Terza lingua.** Aggiunta come da documentazione (`app/Language/fr/RoleWarden.php`), compare nel selettore e funziona (L18, L18b).
- **Una sola lingua: A1.**
- **Due schede: D1** (L19).

**2. Matrici.**
- **Ruolo a tre livelli** (Grandparent M ← Parent M ← Kid M, override di concessione e di negazione su due utenti; L01-M05b):
  - c'è una cella per ogni `area.azione` esistente, e le combinazioni inesistenti restano vuote, senza marchio;
  - i marchi sono esatti: ✓ proprio, 〃 ereditato da entrambi i livelli con l'origine nel tooltip e la cella bloccata, (✓) override, ✓ barrato per la negazione con il ruolo e l'utente nominati;
  - tutte le altre celle esistenti hanno il quadratino tratteggiato visibile, il tooltip «`slug` · Not granted» e `aria-pressed="false"`;
  - riga di istruzioni sopra la matrice dal file di lingua, legenda a cinque marchi.
- **Interazioni** (M06-M12):
  - clic su una cella ereditata: nulla cambia;
  - clic su «Not granted»: diventa una concessione, segnata come modificata; dopo Save e ricarica il database la contiene;
  - Discard riporta allo stato salvato;
  - la casella Row di un'area e la casella in testa a una colonna concedono tutte le celle modificabili, senza toccare quelle bloccate;
  - il database alla fine contiene esattamente le concessioni attese: le ereditate non vengono copiate e i padri restano intatti.
- **Pagina utente** (M13-M17):
  - quadratino tratteggiato sulle celle non concesse, riga di aiuto con il nome dell'utente;
  - il clic su «Not granted» seguito da «Save overrides» crea un override di concessione persistente (database e ricarica);
  - la negazione mostra il marchio barrato.
- **Italiano** (M18, M18b): istruzioni, aiuto, legenda e tooltip «Non concesso» tradotti.
- **Sola lettura** (roles.view e users.view, senza diritti di modifica; M19-M19e):
  - nessuna cella cliccabile, nessuna riga di istruzioni, nessun Save;
  - clic e tentativi di salvataggio non cambiano nulla nel database;
  - il quadratino «Not granted» c'è nella matrice del ruolo.
- **Difetto D2. Ambiguità A2.**
- **Sensibilità.** Gli stessi controlli su `be64b53` falliscono come atteso: niente quadratini (M02, M03).

**3. Permissions, «Granted by»** (P00-P12):
- **Confronto con il database** su tutti i 31 permessi, calcolando i ruoli non cancellati che concedono il permesso direttamente o tramite un antenato. Il controllo copre:
  - la catena di tre livelli della fase M;
  - una catena di quattro livelli: concessione in cima ereditata da tutti, concessione in fondo non risalita;
  - «no role» su un permesso che nessuno concede.
- **Escluso.** Un ruolo con soft delete non compare mai.
- **Super admin.** Il ruolo super admin non è elencato e l'introduzione dice che li ha tutti.
- **Link.** Ogni nome punta a `rolewarden/roles/{id}` del ruolo giusto.
- **Escape.** Markup e script nel nome del ruolo sono escapati.
- **Rinomina.** Un ruolo rinominato dal suo form compare subito col nuovo nome.
- **Concessione tolta in cima.** Sparisce da tutta la catena.
- **Italiano.** «Concesso da», «nessun ruolo» e l'introduzione sono tradotti.
- **Solo `permissions.view`.** L'utente vede l'elenco con i nomi e senza link: le pagine dei ruoli non gli sono aperte (302).
- **Nessun errore a schermo.** Nessun 500, testo SQL o errore PHP.
- **Osservazione O1.**

**4. Installer** (I00-I09), su una copia propria dell'app:
- **Super admin attivo, senza lock.** L'installer rifiuta («Already installed»), non crea il codice e le POST costruite a mano non cambiano nulla.
- **Unico super admin disattivato, senza lock** (l'admin normale resta attivo):
  - l'installer riparte dal passo del codice; il codice è salvato nel file e non compare nella pagina;
  - i passi successivi inviati prima del codice non scrivono nulla, né nel database né nel `.env`;
  - codice sbagliato e CSRF mancante sono rifiutati;
  - con il codice giusto si arriva al passo 4 e si crea un nuovo super admin attivo, senza perdere i dati esistenti;
  - poi vengono scritti il lock e tolto il codice, il nuovo account accede e l'installer rifiuta di nuovo.
- **Lock presente, nessun super admin attivo.** Rifiuta.
- **Nessun utente con un ruolo super admin, senza lock.** Riparte.
- **Utente attivo su un ruolo personalizzato con il flag super admin.** Rifiuta: conta il flag, non lo slug.

**5. Layout** (Chrome headless, 12 schermate, 1280, 1366 e 1920 px):
- **Y01.** In ogni tabella numerata la colonna dei numeri è larga esattamente un gutter (40 px), in 29 tabelle. Su `be64b53` la stessa misura falliva: 26-56 px.
- **Y02.** Nessun numero tagliato.
- **Y04.** Nella pagina Permissions le colonne delle 11 tabelle partono alla stessa x. Su `be64b53` la colonna Notes era sfasata di 21 px.
- **Y05.** Le intestazioni della matrice restano dentro la propria colonna.
- **Y03, scorrimento orizzontale:** A3.
- Screenshot: `verify-v8b.layout-*.png`, `verify-v8b.matrix-{role,user,readonly}.png`.

**6. Regressione.**
- **Fallimenti già noti.** G07 di V2 e i 25 controlli V4 sulla data ISO.
- **G07v5, nuovo nella V5.** «View-only settings: no submit button outside the top-bar mode switch» trova 2 submit. L17 prova che sono i bottoni EN/IT del nuovo selettore nella barra in alto: nella pagina Settings in sola lettura non c'è nessun altro submit. È un'aspettativa superata dalla modifica, non un difetto, come G07.
- **Controlli in più in V2 `test`.** Rispetto a V8 ci sono 8 controlli in più (J01/J02, ripetuti per i dati), tutti PASS.
- **Suite V8.** Le fasi A-D, F e G girano sul modulo e sulla demo di `6d21875`: 195/195.
- **Suite non eseguite.** Le fasi P ed E di V8, la suite V7 e i pacchetti V7 controllano gli zip di `build/`, fermi a `rc.v8b` (`50894c9`) e non ricostruiti dopo `be64b53`. Non le ho eseguite: vedi «Cosa non ho potuto provare».

**7. Log.** Su development e production nei percorsi toccati:
- a soglia 9 nessuna riga WARNING/ERROR/CRITICAL, tolti i 4 rifiuti CSRF provocati di proposito (L10, L10b) in development; in production zero;
- nessun warning PHP nel server;
- nessun 500, SQL o errore PHP a schermo su 154 risposte.

### Difetti

**D1 — minore. Con due schede il selettore torna all'ultima pagina caricata, non a quella da cui è stato usato** (L19).

Riproduzione:
1. Accedere al pannello.
2. Aprire `/rolewarden/permissions` (scheda A).
3. Aprire `/rolewarden/activity` in un'altra scheda (B).
4. Tornare alla scheda A e premere IT.

Osservato: redirect a `/rolewarden/activity`, anche se il browser invia come Referer la pagina Permissions. La lingua cambia, ma si finisce su una pagina diversa.

Il comportamento dichiarato è «torna alla pagina da cui è stato usato». Con una sola scheda è così (L04, L08, L15). Dal comportamento sembra che il ritorno segua l'ultima pagina vista dalla sessione e non la pagina del form. Il Referer da un host estraneo invece non è mai seguito (L11): qualunque correzione deve conservare questa proprietà.

**D2 — minore, già presente in `be64b53`. Nelle matrici in sola lettura una concessione per singolo utente ha il marchio della concessione del ruolo** (M19, M19e).

Riproduzione:
1. Ruolo Kid M; l'utente v8bkid1 ha un override di concessione su `activity.view`.
2. Aprire la matrice del ruolo da un utente con `roles.view` e senza `roles.update`.

Osservato:
- La cella `activity.view` ha il marchio ✓ (`rw-mark--role`, «Granted by role» in legenda), con il tooltip «Override for 1 user: v8bkid1».
- Per lo stesso stato, nella versione modificabile ha (✓) (`rw-mark--grant`).
- Lo stesso succede nella pagina utente in sola lettura («Override: granted to v8bkid1 only» disegnato come ✓ del ruolo).

Il design system vuole che i cinque stati si distinguano «by shape alone». Il problema è già presente in `be64b53` (`verify-v8b.baseline-be64b53.output.txt`, M19 e M19e): non lo ha introdotto la V8b, ma rientra nel punto «versione di sola lettura coerente».

### Ambiguità di specifica (non decise)

**A1 — Come si ha una sola lingua disponibile** (LA1).
- Quello che dice la documentazione:
  - il modulo esce in inglese e italiano;
  - una terza lingua si aggiunge con un file in `app/Language`;
  - nulla dice come restare con una lingua sola.
- `Config\App::$supportedLocales = ['en']` nell'app ospite lascia il selettore con EN e IT.
- Togliere `src/Language/it` significherebbe modificare il modulo, cosa vietata all'acquirente.
- Non ho quindi potuto verificare «con una sola lingua il selettore non compare» come la documentazione lo renderebbe configurabile.

**A2 — Quadratino «Not granted» nella pagina utente in sola lettura** (MA1).
- La pagina utente vista con `users.view`, senza diritti sugli override, mostra «Effective permissions». Le 19 celle di permessi esistenti e non concessi restano vuote come le combinazioni inesistenti.
- Il CHANGELOG lo promette per la matrice del ruolo. Il design system dice che ogni cella di matrice mostra uno dei cinque marchi.
- La specifica non dice se questa vista riassuntiva debba mostrarlo.
- Nella matrice del ruolo in sola lettura il quadratino c'è.

**A3 — Scorrimento orizzontale a 1280 e 1366 px** (Y03, contati come FAIL nello strumento ma non come difetti).
- Il design system fissa il disegno a 1440 px e la specifica non dice nulla sulle larghezze minori.

| Pagina | Scorrimento con `6d21875` | Scorrimento con `be64b53` |
| --- | ---: | ---: |
| Pagina di un ruolo, 1280 px | 186-200 px | 69-82 px |
| Pagina di un ruolo, 1366 px | 100-114 px | assente |
| Pagina utente, 1280 px | 64 px | assente |
| Elenco utenti con 13 utenti, 1280 px | 163 px | assente (con meno dati) |
| Elenco utenti con 13 utenti, 1366 px | 77 px | assente (con meno dati) |

- **Causa: il contenuto, non la barra in alto.** Nascondendo il selettore della lingua, o tutti i controlli della barra in alto, lo scorrimento non cambia; nascondendo `main` sparisce. In `main` sporgono la colonna delle note da 200 px e la barra Save.
- **Larghezze verificate.** A 1920 px nessuno scorrimento, a 1440 px non ho misurato.
- **Da decidere.** Se le larghezze sotto 1440 px debbano stare senza scorrimento.

**Ancora aperte da V8 e V7, ritrovate dalla suite V8.**
- A15c: voci del log di un admin vero sugli oggetti della demo.
- G03: dati della demo seminati in inglese.

### Osservazioni (non contate)

- **O1.** Con un ruolo cancellato (soft delete) in mezzo a una catena, i figli sotto di lui non ereditano più dai ruoli sopra. Con Level Two cancellato, `permissions.override` concesso a Level One non risulta più per Level Three e Level Four. La specifica non dice nulla del caso. Il dato serve all'autore per confermarlo.
- **O2.** Nella matrice modificabile della pagina utente le celle sono bottoni senza `aria-pressed` né `aria-disabled`. In quella del ruolo ci sono.
- **O3.** Dopo il codice, l'installer che riparte passa direttamente al passo 4 («First super admin»), senza database e tabelle. È coerente con un'app già configurata.
- **O4.** Nella pagina utente la riga d'aiuto «Click a cell to add or remove an override…» sta nella barra della legenda e non sopra la tabella, come invece nella matrice del ruolo.

### Cosa non ho potuto provare

- **Controllo visivo in un browser vero**, dovuto all'autore. Qui c'è solo Chrome headless con misure e screenshot.
- **Gli zip.** La fase P e la fase E di V8, la suite V7 e i pacchetti V7: `build/` contiene ancora `rc.v8b` di `50894c9` e ricostruirlo vuol dire scrivere fuori da `tests/Integration/`. Restano da ripetere dopo la ricostruzione.
- **Altri ambienti.** 1440 px; ultima PHP 8.x e CI 4.x (solo PHP 8.3.11); Apache, sottocartella e reverse proxy.
- **Online.** Staging e demo online sono fuori perimetro.
- **Email nella lingua scelta dalla barra in alto.** Coperte solo indirettamente dalla regressione V4 (lingua del destinatario).

### Pulizia

- **Database.** `rolewarden_test` uguale allo snapshot iniziale, SHA256 `f7f37c04…e21e64`. È verificato dopo ognuna delle 28 esecuzioni e alla fine, a mano.
- **Mailpit.** Vuoto.
- **Processi.** Nessun php, python, node o Chrome miei attivi (verificato per riga di comando).
- **File.** I `.env` temporanei sono cancellati e le cartelle di lavoro tolte, junction per prime. Quello dell'installer conteneva le credenziali scritte dall'installer stesso.
- **Strumento.** Il probe esplorativo (`verify-v8b.phase-x.php`) e le esecuzioni di messa a punto `try1`-`try3` sono cancellati; restano le esecuzioni finali.
- **Da cancellare a mano.** Resta `scratchpad/v8breg`: 2211 file, solo profili Chrome della regressione V5/V6 che Windows rifiuta di cancellare. Restano anche le cartelle già segnalate in V8.
- **Docker.** Docker Desktop e i due container `rolewarden-db`/`rolewarden-mail` restano accesi.

Nessun commit, nessun push.
