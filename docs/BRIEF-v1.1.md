# Brief v1.1 - RoleWarden

Scritto il 2026-10-18, dopo la pubblicazione della 1.0.0 (tag `v1.0.0`). La fonte di verità resta `docs/SPEC.md`, sezione "Pannello admin", decisioni del 2026-10-18 (v1.1); il disegno è nel design system "RoleWarden" su Claude, riesportato in `docs/design-system/`. Questo brief è l'ordine di lavoro.

## Cosa costruiamo

La v1.1 è il primo aggiornamento: dà al pannello una pagina d'ingresso e una navigazione all'altezza degli screenshot, senza toccare autorizzazione né schema.

**Dentro la v1.1.**

1. Barra laterale nuova: voci a gruppi, contatori, pannello "Oggi", compressione a icone, tre stili (Paper, Ink, Charcoal) scelti nel personalizzatore
2. Verifica di un permesso: chi lo ha e perché, e l'esito per un utente
3. Ricerca comandi con Ctrl K
4. Dashboard con cinque blocchi, ognuno col suo permesso `dashboard.*`, e grafici SVG lato server
5. Personalizzazione della dashboard (predefinito globale e scelte per utente) e blocchi aggiunti dall'applicazione

**Fuori dalla v1.1, e non si anticipa.** Predefiniti della dashboard per ruolo; fonti di dati oltre a `auth_logins`, utenti e `acl_activity_log`; tutto ciò che la specifica assegna a v1.5 e v2.0.

## Punto di partenza

| Già fatto | Stato |
| --- | --- |
| RoleWarden 1.0.0: pannello, temi, installer, demo, documentazione | Pubblicato, tag `v1.0.0`, collaudo S3 PASS |
| Design system con Dashboard, Sidebar, CommandSearch e regole dei grafici (direzione B) | Approvato il 2026-10-18 |
| Valori dei token `side-*`, `charcoal-*`, `heat-*` nei tre temi | Approvati il 2026-10-18 su anteprima |
| Decisioni della 1.1 nella specifica | Scritte il 2026-10-18 |

**Vincoli ereditati, invariati.** Si estende Shield. Il codice controlla permessi, mai ruoli. Lo strato di autorizzazione resta PHP puro: la verifica di un permesso legge il resolver, non ne copia la logica. Nessun file del modulo si modifica dall'acquirente; ogni view nuova è sovrascrivibile.

**Regole in più per la v1.1.**

- **Aggiornamento senza sorprese.** Chi sostituisce la cartella della 1.0 e lancia le migrazioni trova il pannello com'era (barra Paper) più la dashboard per i ruoli che vedono il log. Nessuna view sovrascritta dall'acquirente smette di funzionare: se una view esistente deve cambiare, il CHANGELOG lo dice.
- **Nessuna tabella nuova.** Preferenze e contatori stanno nella libreria Settings.
- **Nessuna libreria JavaScript nuova.** Grafici in SVG dal server, interazioni con Alpine come oggi.

## Decisioni da prendere

| Decisione | Opzioni sul tavolo | Blocca |
| --- | --- | --- |
| Pannello "Oggi" | Acceso o spento sulle installazioni nuove e su quelle aggiornate dalla 1.0 | U1 |
| Forma della API dei blocchi | Interfaccia o classe astratta del modulo; come si registra (array nella config `RoleWarden` dell'applicazione o evento); cosa riceve la view (periodo, utente) | U5 |
| Dati della demo | La demo azzera i dati ogni ora: per avere grafici pieni serve uno storico di accessi finto nel seeder della demo, marcato come esempio | U6 |

**Già chiuse il 2026-10-18.** Migrazione che concede `dashboard.*` ai ruoli con `activity.view`; personalizzazione con Settings, senza predefiniti per ruolo; API dei blocchi nella 1.1; barra Paper predefinita, demo in Ink; mappa di calore di Console invariata.

## Ordine di lavoro

Sei tappe, in quest'ordine. Ognuna si chiude prima di aprire la successiva e passa dal collaudo indipendente prima di dirsi chiusa.

| Tappa | Cosa consegna |
| --- | --- |
| U1 Navigazione | Barra laterale a gruppi (Panoramica, Accesso, Sicurezza, Sistema), voci rese solo con il loro permesso, contatori, voci nuove del log dall'ultima visita, pannello "Oggi" con il suo interruttore nelle Impostazioni, compressione per utente; stili Paper, Ink e Charcoal nel personalizzatore e nel file di tema, con i token nei tre temi |
| U2 Verifica di un permesso | Schermata in sola lettura con `permissions.view`: per un permesso chi lo ha e per quale via; per un utente e un permesso l'esito e la regola di precedenza che lo decide |
| U3 Ricerca comandi | Ctrl K (Cmd K) e campo nella barra: schermate, azioni e permessi raggiungibili dall'utente, filtrati lato server; un identificatore porta alla verifica |
| U4 Dashboard | Pagina di `/rolewarden` e prima schermata dopo l'accesso; cinque blocchi con i loro permessi; dati da `auth_logins`, utenti e log; grafici SVG con etichetta e tabella; periodo 7, 30, 90 giorni; seeder e migrazione dei permessi `dashboard.*` |
| U5 Personalizzazione e blocchi | Predefinito globale con `settings.update`, scelte per utente; API pubblica per i blocchi dell'applicazione, documentata, con un blocco d'esempio nella demo |
| U6 Rilascio 1.1.0 | Documentazione, CHANGELOG, design guide aggiornata, demo con storico d'esempio e barra Ink, prova d'aggiornamento dallo zip 1.0.0, zip 1.1.0, collaudo finale |

**Perché quest'ordine.** La barra viene prima perché ogni schermata nuova ci si appoggia. La verifica di un permesso precede la ricerca, che ci porta. La dashboard viene dopo la navigazione, così il suo collaudo vede il pannello vero; la personalizzazione e la API vengono dopo i blocchi che riordinano. Il rilascio chiude perché descrive il prodotto finito.

**Su U4.** Ogni numero della dashboard deve coincidere con quello che si ottiene interrogando le tabelle: il collaudo lo verifica con query dirette sullo stesso periodo.

## Definizione di fatto

La v1.1 è finita quando questa lista è interamente spuntata dal collaudo indipendente.

**Funzionalità.**

- [ ] Ogni voce della barra compare solo a chi ha il suo permesso; nessuna voce disabilitata
- [ ] I tre stili della barra, nei tre temi in chiaro e in scuro, rendono senza valori fuori dai token, con la voce attiva almeno a 3:1 sulla barra
- [ ] Un file di tema senza stile della barra dà Paper; il personalizzatore salva lo stile scelto
- [ ] La verifica di un permesso dà, per ogni coppia utente e permesso provata, lo stesso esito del resolver, con la regola giusta (disattivato, override negativo, override positivo, super admin, ruolo, nessuno)
- [ ] La ricerca non restituisce mai una schermata, un'azione o un permesso fuori dalla portata dell'utente
- [ ] Ogni blocco della dashboard compare solo con il suo permesso; senza nessuno, l'utente arriva alla prima schermata che può vedere
- [ ] Le cifre e i grafici della dashboard coincidono con le tabelle per 7, 30 e 90 giorni
- [ ] Le scelte di un utente sulla dashboard non cambiano quella degli altri; il predefinito globale vale per chi non ha scelto
- [ ] Un blocco registrato dall'applicazione compare, rispetta il suo permesso e si riordina come gli altri
- [ ] Ogni stringa nuova esiste in inglese e in italiano

**Aggiornamento e pubblicazione.**

- [ ] Da una 1.0.0 installata: sostituzione della cartella e migrazioni portano alla 1.1.0; i ruoli con `activity.view` ricevono `dashboard.*`; il rollback della migrazione toglie solo quelli
- [ ] Una view della 1.0 sovrascritta nell'applicazione continua a funzionare
- [ ] Tutte le voci di pubblicazione della v1.0 restano vere (PHP 8.3-8.5, CI 4.7.4 e ultima 4.x, sottocartella, reverse proxy, nessun errore SQL a schermo in production, nessuna credenziale nel pacchetto)
- [ ] Demo e staging alla 1.1.0, con reset, nessuna email in uscita e `noindex` verificati

## Protocollo e cosa fermarsi a chiedere

Il protocollo è quello di CLAUDE.md: Claude Code scrive, il Collaudatore ad Hoc collauda in nero contro la specifica e questo brief, su `rolewarden_test`, senza leggere il codice. Ogni schermata nuova passa anche da un controllo visivo dell'utente sullo staging. Registro in `_AI-LOG.md`, un commit per unità di lavoro, push solo su richiesta.

**Cosa fermarsi a chiedere.** Le voci di CLAUDE.md restano. Si aggiungono:

1. Qualunque modifica a una view o a una classe pubblica della 1.0 che un acquirente può aver sovrascritto o esteso
2. Qualunque dato della dashboard che richieda una tabella, una colonna o un indice nuovi
3. La forma definitiva della API dei blocchi, prima di scriverla: dopo la 1.1.0 non cambia fino alla 2.0
