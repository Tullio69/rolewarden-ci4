# M5 — casi indipendenti da eseguire

Fonte: SPEC, BRIEF-MVP, richiesta di collaudo e decisioni nel log. Questo documento
è un piano di casi, **non una suite eseguita**. `verify-m5.php` verifica soltanto
i prerequisiti; il verificatore funzionale deve ancora essere realizzato.

## Isolamento e fixture

- Copia temporanea dell'app sorella sotto `tests/Integration/`, senza `.env`,
  sessioni, cache o log originali. Dipendenze riutilizzate senza modificarle.
- Configurazione esclusivamente da ambiente; nome DB fissato a
  `rolewarden_test`, con verifica `SELECT DATABASE()` prima di ogni fase mutante.
- Prima delle migrazioni: snapshot completo di schema, righe e contatori delle
  tabelle; includere eventuali trigger e rifiutare oggetti non ripristinabili.
  Non salvare credenziali, hash password o sessioni negli output del report.
- Ripristino in `finally`, confronto esatto con lo snapshot, arresto del server,
  rimozione della sola copia temporanea previa verifica del percorso assoluto.
  Confrontare hash `.env` sorella senza leggerne il contenuto in output.
- Installazione Settings, Shield, RoleWarden e seed tramite Spark nella copia;
  provider utente e registrar pubblici come dichiarati nel log.
- Fixture: unico super admin attivo, secondo super inizialmente inattivo,
  utente senza permessi, lettore con soli permessi di visualizzazione,
  operatori con un singolo permesso di scrittura, utente ordinario di prova;
  ruoli padre/figlio/nipote, ruolo senza figli, record di sistema.
- Login reale Shield; cookie distinti per attori, mantenuti fra richieste.
  Scritture sotto test esclusivamente attraverso il pannello. SQL per fixture
  e osservazione indipendente dello stato, mai per simulare la scrittura testata.
- Individuare URL, campi e azioni da output HTTP e interfaccia pubblica;
  non aprire sorgenti di controller, model, view, asset o test esistenti.

## Casi e oracoli

| ID | Stimolo | Risultato richiesto |
| --- | --- | --- |
| A01 | GET e POST anonimi a tutte le schermate/azioni, compresa matrice | Nessun dato riservato e nessuna mutazione; autenticazione richiesta |
| A02 | Utente autenticato privo del permesso della schermata | Accesso server negato; nessun dato o effetto collaterale |
| A03 | Lettore nelle liste e nei dettagli | Azioni non autorizzate assenti dal DOM, non semplicemente disabilitate o nascoste via CSS |
| A04 | Richiesta manuale per ogni azione nascosta, con CSRF valido | Diniego e stato DB invariato |
| A05 | Operatori con un solo permesso di scrittura | Ogni azione richiede il proprio permesso; registrare separatamente eventuale ambiguità sui prerequisiti di lettura |
| U01 | Creare utente, rileggerlo, modificarlo | Dati persistenti e login Shield; nessuna password in chiaro nel DB/output |
| U02 | Disattivare e riattivare utente ordinario | Stato persistente; inattivo non autorizzato alla richiesta successiva |
| U03 | Eliminare utente ordinario | Non più utilizzabile o visibile fra utenti attivi |
| U04 | Assegnare e revocare ruolo | Stato e autorizzazioni cambiano dalla richiesta successiva nella stessa sessione |
| U05 | Concedere, negare e rimuovere override | Rispettate tutte le precedenze; rimozione ripristina il risultato del ruolo |
| U06 | Override negativo sul super admin | Nega il permesso, senza logout |
| U07 | Invio override con granted diverso da 0/1, array o valore mancante | Nessun override con dominio invalido; errore gestito senza SQL esposto |
| U08 | Ricerca, filtri ruolo/stato, paginazione | Risultati corretti, separati da utenti non corrispondenti; navigazione consistente |
| G01 | Disattivare/eliminare unico super attivo dal pannello | Rifiuto con messaggio utile e snapshot dati invariato |
| G02 | Revocare il suo ultimo ruolo super, eliminarlo o spegnerne il flag | Rifiuto; nessun aggiramento tramite richiesta manuale |
| G03 | Ripetere operazioni ammesse con secondo super attivo | Operazione consentita, almeno un super attivo rimane |
| R01 | Creare, modificare, eliminare ruolo ordinario | Persistenza, conteggio utenti e indicazione padre corretti |
| R02 | Eliminare ruolo di sistema via UI e richiesta manuale | Rifiuto senza mutazioni |
| R03 | Padre uguale a se stesso; ciclo di due/tre ruoli; padre inesistente | Rifiuto senza mutazioni |
| R04 | Eliminare padre con figli attivi | Rifiuto e messaggio che invita a spostare/eliminare prima i figli |
| R05 | Spostare/eliminare figli, poi eliminare padre | Eliminazione consentita quando non restano figli attivi |
| R06 | Riutilizzare slug di ruolo eliminato logicamente | Rifiuto con invito a ripristinare il ruolo o scegliere altro slug; nessun SQL esposto |
| M01 | Aprire dettaglio ruolo | Righe aree, colonne azioni; sole intersezioni esistenti interagibili |
| M02 | Selezionare/deselezionare riga | Modifica tutte e sole le celle modificabili della riga; persiste |
| M03 | Selezionare/deselezionare colonna | Modifica tutte e sole le celle modificabili della colonna; persiste |
| M04 | Cambiare una cella in browser reale | Salvataggio senza navigazione/refresh del documento; risposta positiva e persistenza in successiva GET indipendente |
| M05 | Salvare una cella con altri grant presenti | Gli altri grant restano invariati; salvataggio parziale effettivo |
| M06 | Aprire figlio/nipote con grant del padre/nonno | Celle ereditate selezionate, bloccate e origine indicata |
| M07 | Richiesta manuale per rimuovere grant ereditato | Permesso effettivo del figlio conservato; nessuna revoca nel padre |
| M08 | Revocare grant nel padre dal pannello | Figlio e titolare perdono il permesso dalla richiesta successiva, senza logout |
| P01 | Elenco permessi con solo permissions.view | Elenco raggruppato per area e nessun controllo di scrittura |
| P02 | Tentativi di creazione/modifica/eliminazione permessi via HTTP | Nessuna mutazione; valutazione secondo perimetro sola lettura richiesto |
| V01 | Aggiungere view ospite con marker unico nel percorso di override | Marker servito al posto della view modulo; nessuna modifica al modulo |
| V02 | Rimuovere override | View modulo nuovamente risolta |
| S01 | Ogni form mutante e richiesta fetch, token valido/assente/errato | Token presente; richieste senza token valido non mutano dati |
| S02 | Payload con apostrofi, stringhe SQL, HTML e caratteri di controllo nei campi/ID/ricerca | Nessuna estensione involontaria di letture/scritture; output escapato; niente SQL/errori interni |
| S03 | ID inesistenti, email duplicate, slug riservati, valori e tipi invalidi | Fallimenti gestiti senza diagnostica SQL, anche in development |
| S04 | Indurre errore DB recuperabile nell'isolamento temporaneo | Risposta HTTP senza SQL o dettagli connessione; ripristino stato prima dei casi successivi |
| S05 | Revoca dal pannello con cache già popolata | Stessa sessione: primo accesso consentito, revoca, richiesta immediatamente successiva negata |

Per ogni richiesta mutante: controllare risposta, stato persistito, effetto sulle
autorizzazioni e assenza di diagnostica SQL. Uno status 200/302 da solo non prova
il successo. Un rifiuto CSRF non prova il controllo di autorizzazione: A04 usa
sempre un token valido.

La verifica dell'assenza di refresh richiede un browser con JavaScript attivo e
osservazione della navigazione: un POST HTTP riuscito non soddisfa M04. I test
con payload SQL sono prove comportamentali, non certificano che **tutte** le
query siano parametrizzate. Con il divieto di lettura implementativa, dichiarare
questo limite e distinguere eventuale evidenza di strumentazione runtime.

## Questioni separate dai difetti

1. CSS scritto a mano al posto di Tailwind: deviazione dichiarata, non decidere
   se accettabile. La SPEC prevede CSS già compilato nel pacchetto e configurazione
   Tailwind per ricompilare; non impone build all'acquirente.
2. Creazione amministrativa con attivazione immediata senza verifica email:
   registrare il comportamento osservato, senza approvarlo o bocciarlo arbitrariamente.
3. SPEC: elenco permessi «sola lettura salvo i personalizzati»; richiesta attuale
   e consegna M5: sola lettura. Segnalare la differenza senza ampliare il CRUD.
4. Conservare come questioni aperte le precedenti ambiguità del log pertinenti,
   senza usare la loro interpretazione per forzare PASS o FAIL.
