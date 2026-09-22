# Brief di apertura - MVP RoleWarden

## Cosa costruiamo

Un modulo CodeIgniter 4 che estende Shield portando ruoli e permessi dal file di configurazione al database, con un pannello per governarli. Destinazione finale: vendita su CodeCanyon. Questo brief copre solo l'MVP, che non si vende: serve a dimostrare che l'architettura regge su un progetto reale prima di costruirci sopra mesi di lavoro.

La specifica completa vive nel documento **RoleWarden - Specifiche e Roadmap**, che e' la fonte di verita'. Questo brief e' l'ordine di lavoro, non la sostituisce.

**Dentro l'MVP.**

1. Installazione via Composer, comando di setup, migrazioni e seed dei ruoli di sistema
2. Ruoli e permessi in database, con gerarchia fra ruoli
3. Override del singolo permesso sul singolo utente, concesso o negato
4. Aggancio a Shield: entita' utente propria, `can()` che passa dal nostro resolver
5. Filtro di rotta per permesso e helper nelle view
6. Pannello su utenti, ruoli e permessi, con la matrice permessi

**Fuori dall'MVP, e non si anticipa.** Niente 2FA, niente login social, niente notifiche, niente log attivita', niente app demo, niente installer grafico, niente temi multipli ne' personalizzatore. Login, registrazione, recupero password e throttling restano quelli di Shield: non li riscriviamo, al massimo ne vestiamo le viste.

Il pannello dell'MVP puo' essere spartano. La grafica si rifa' in v1.0, l'architettura no.

## Vincoli non negoziabili

Decisioni gia' prese. Non si cambiano nel codice: se una sembra sbagliata, si segnala e si aspetta, non si aggira.

1. **Si estende Shield, non lo si sostituisce.** Shield resta padrone di utenti, credenziali, sessioni, login, remember-me, token e registro dei tentativi. Nessuna tabella utenti nostra, nessuna logica di autenticazione riscritta.
2. **Il codice controlla permessi, mai ruoli.** `can('users.delete')` ovunque, mai `hasRole('admin')`. E' questa regola che rende il sistema governabile dal pannello.
3. **Lo strato di autorizzazione non dipende dal framework.** Le classi che risolvono i permessi sono PHP puro, raggiunte da CI4 attraverso filtro ed helper. Non e' un core multi-framework: e' un confine tenuto pulito, in vista di un'eventuale versione Laravel.
4. **Progetto nuovo solo per CI4.** Per tutto il resto si parla direttamente al framework: modelli, filtri e query builder sono quelli di CI4, senza astrazioni inventate per un secondo framework che oggi non esiste.
5. **Nessun file del modulo va modificato dall'acquirente.** Ogni personalizzazione vive nella sua applicazione. Un aggiornamento deve essere una sostituzione di cartella.
6. **Precedenza nella risoluzione:** utente disattivato nega tutto; poi override negativo dell'utente, che batte anche il super admin; poi override positivo; poi super admin; poi permesso da un ruolo; altrimenti nega. La prima regola che risponde vince.
7. **Permessi identificati per slug testuale**, formato `area.azione`, mai per id nelle chiamate del codice.

## Stack e struttura

| Voce | Valore |
| --- | --- |
| PHP | 8.3 minimo, collaudato fino a 8.5 |
| CodeIgniter | 4.5 minimo, collaudato fino all'ultima 4.x |
| Shield | `^1.4`, fissato il 20 settembre 2026 sviluppando su 1.4.1 |
| Database | MySQL o MariaDB, InnoDB |
| Pannello | View CI4 server-side, CSS scritto a mano nell'MVP (Tailwind arriva con il design system in v1.0), Alpine.js |
| Prefisso tabelle | `acl_`, configurabile, distinto da `auth_` di Shield |
| Distribuzione | Composer, piu' cartella da copiare per chi non lo usa |

**Deciso il 2026-09-22.** La riga sul CSS del pannello e' cambiata rispetto alla stesura iniziale: CLAUDE.md vieta script di generazione per HTML/CSS/JS, e una pipeline Tailwind e' esattamente questo. Per l'MVP il pannello usa CSS scritto a mano con un primo livello di token in variabili CSS (colori, spaziatura, forma), spartano di proposito. Tailwind e il design system completo (tre livelli di token, tre temi, personalizzatore) restano pianificati per la v1.0, dove la grafica si rifa' comunque da zero.

**Struttura attesa.** Il modulo e' un pacchetto Composer con un namespace unico, che si installa in un'applicazione CI4 ospite. Dentro, la separazione segue gli strati della specifica: le classi di autorizzazione senza dipendenze dal framework da una parte, gli agganci a CI4 e a Shield dall'altra, le view e gli asset del pannello in una terza.

Tre punti di ingresso lato acquirente, tutti previsti da Shield e nessuno che tocchi il suo codice:

1. Entita' utente propria, dichiarata nella configurazione di Shield, che estende la sua e sovrascrive `can()`
2. Filtro di rotta registrato per la verifica dei permessi
3. Helper per le view

**Risoluzione delle view.** Ogni view del pannello passa prima dall'applicazione ospite: se esiste un file con lo stesso percorso nella sua cartella di override, vince quello. Va costruito nell'MVP, non aggiunto dopo, perche' condiziona come sono scritte tutte le view.

## Ordine di lavoro

Sette tappe, in quest'ordine. Ognuna si chiude prima di aprire la successiva, e ognuna passa da Codex prima di essere considerata chiusa. L'ordine non e' arbitrario: il resolver viene prima dell'aggancio a Shield, e l'aggancio prima del pannello, perche' ogni tappa si collauda su quella sotto.

| Tappa | Cosa consegna |
| --- | --- |
| M0 Impalcatura | Pacchetto Composer, namespace, configurazione, Shield installato su un'app CI4 di prova, `_AI-LOG.md` creato |
| M1 Migrazioni | Le cinque tabelle dell'MVP con migrazioni reversibili, seed dei ruoli e permessi di sistema, prefisso configurabile |
| M2 Resolver | Classi di autorizzazione senza dipendenze dal framework: risoluzione, precedenza, gerarchia, cache con invalidazione |
| M3 Aggancio Shield | Entita' utente propria, `can()` instradato al resolver, filtro di rotta, helper per le view, lettura dei gruppi Shield per compatibilita' |
| M4 Protezioni | Ultimo super admin non eliminabile, cicli nella gerarchia rifiutati, record di sistema non cancellabili |
| M5 Pannello | Layout, risoluzione delle view sovrascrivibili, CRUD utenti e ruoli, elenco permessi, matrice permessi |
| M6 Innesto | Installazione su un'app CI4 esistente con utenti gia' presenti, piu' la migrazione che importa i gruppi da `AuthGroups.php` |

**Sulla M2.** E' il cuore e va scritta con i test davanti, perche' e' l'unica parte dove un errore non si vede finche' non diventa una falla di sicurezza. La cache si invalida a ogni scrittura su ruoli, permessi o assegnazioni: un permesso revocato deve negare l'accesso alla richiesta successiva, senza logout.

**Sulla M5.** La matrice permessi e' la schermata che poi vendera' il prodotto: righe le aree, colonne le azioni, selezione per riga e per colonna, salvataggio parziale senza ricaricare. I permessi ereditati dal ruolo padre appaiono selezionati ma bloccati, con l'origine indicata. Anche in versione spartana, questo comportamento ci deve essere.

**Sulla M6.** E' la tappa che dice se l'MVP ha centrato l'obiettivo. Se l'innesto su un progetto esistente richiede di toccare file del framework o del modulo, qualcosa nelle tappe precedenti e' sbagliato e si torna indietro.

## Regole di codice

Valgono sempre, non solo quando conviene.

**Convenzioni dell'autore.**

1. Mai usare il percorso `public/` direttamente: si passa dagli helper del framework
2. Mai mostrare errori SQL a schermo, in nessun ambiente e in nessun percorso
3. Codice modulare, estendibile, riusabile in contesti diversi; layout pensati per essere personalizzati
4. Componenti JS: dichiarazione e controller nello stesso file, con il controller come funzione separata
5. Per i riferimenti visivi del pannello si guarda merakiui.com e tailblocks.cc
6. HTML, CSS e JavaScript si scrivono a mano, nessuno script di generazione

**Sicurezza, non negoziabile.** Escape su ogni output, protezione CSRF su ogni form, query sempre parametrizzate, hash delle password lasciato a Shield senza varianti nostre. Nessuna credenziale, chiave o URL personale nel codice: tutto in variabili d'ambiente.

**Traduzioni.** Ogni stringa dell'interfaccia sta nei file di lingua fin dall'inizio, non ci si ripassa dopo. Lingua di sviluppo e documentazione in inglese, dato che il prodotto va su CodeCanyon.

**Messaggi di errore.** Sono parte del prodotto. Chi compra legge quelli prima della documentazione, quindi devono dire cosa fare, non cosa e' fallito.

**Commit.** Uno per unita' di lavoro comprensibile, con un messaggio che dica cosa cambia e perche'. Servono anche a Codex per capire cosa verificare.

## Definizione di fatto

L'MVP e' finito quando questa lista e' interamente spuntata da Codex. Sono verifiche, non opinioni: ognuna si esegue e da' un esito.

- [ ] Installazione su un progetto CI4 esistente, con utenti gia' presenti, senza modificare file del framework ne' del modulo
- [ ] Un permesso revocato da pannello nega l'accesso alla richiesta successiva, senza logout
- [ ] Un override negativo sull'utente batte il ruolo super admin
- [ ] Il sistema rifiuta l'operazione che eliminerebbe l'ultimo super admin attivo
- [ ] Un ciclo nella gerarchia dei ruoli viene rifiutato al salvataggio
- [ ] Un ruolo figlio non puo' revocare un permesso concesso dal padre
- [ ] Rollback completo delle migrazioni senza residui in database
- [ ] Una view sovrascritta nell'applicazione ospite ha precedenza su quella del modulo
- [ ] `inGroup()` di Shield continua a rispondere sui gruppi mappati sui nostri ruoli
- [ ] La migrazione di importazione trasforma i gruppi di `AuthGroups.php` in ruoli e permessi in database
- [ ] Nessun errore ne' warning con il report degli errori al massimo livello
- [ ] Nessun errore SQL visibile a schermo in nessun percorso, compresi quelli di fallimento
- [ ] Funziona su PHP 8.3 e sull'ultima versione collaudata, su CI4 4.5 e sull'ultima 4.x

## Protocollo con Codex

Claude Code scrive, Codex collauda e verifica. Chi scrive non giudica il proprio lavoro.

**Come funziona il passaggio.** A fine tappa si segnala che e' pronta. Codex riceve la specifica e la definizione di fatto, non le scelte implementative: scrive i test contro il documento, non leggendo il codice. Se leggesse il codice certificherebbe il comportamento esistente, bug compresi. I difetti tornano indietro, si correggono, Codex riverifica. Nessuna tappa si dichiara chiusa saltando questo passaggio.

**Registro condiviso.** Un file `_AI-LOG.md` nella radice del progetto, letto all'inizio e aggiornato alla fine di ogni sessione, da entrambi. Contiene cosa e' stato toccato, cosa e' verificato, cosa e' in sospeso e ogni scelta presa che non fosse gia' nella specifica.

**Se un test rivela un'ambiguita' nella specifica**, il difetto e' della specifica. Si segnala, si aggiorna il documento, poi si riprende. Non si sceglie a naso l'interpretazione che fa passare il test.

## Cosa fermarsi a chiedere

Su questi punti non si decide da soli: si segnala e si aspetta risposta.

1. Qualunque scostamento dai vincoli non negoziabili, anche se il codice ne guadagnerebbe
2. Aggiunta di una dipendenza esterna oltre CI4 e Shield
3. Modifiche allo schema delle tabelle diverse da quelle previste
4. Anticipo di funzionalita' rimandate alle versioni successive, anche se sembrano gratuite adesso
5. Scelte che restringono chi puo' installare il modulo, per esempio requisiti di estensioni PHP o di configurazione del server
6. Qualsiasi cosa che costringa l'acquirente a modificare un file del modulo

**Due decisioni restano aperte** e non servono per iniziare: prezzo e licenza, strumento di design di partenza. Il nome e' RoleWarden, e l'intervallo di versioni Shield supportate e' fissato a `^1.4`, sviluppando su 1.4.1.
