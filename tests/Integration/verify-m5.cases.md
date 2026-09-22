# M5: casi indipendenti da eseguire

Fonte degli esiti attesi: SPEC, BRIEF, richiesta dell'autore e decisioni riportate nel log. Questo documento e' un piano di test, NON una suite eseguita. Nessun caso funzionale ha ancora un risultato.

## Isolamento e ripristino

- HEAD atteso 670dd97. Conservare modifiche preesistenti e hash di src, SPEC, BRIEF e .env sorella senza esporre il contenuto di quest'ultimo.
- Copiare l'app sorella in una cartella temporanea sotto tests/Integration/, senza .env, sessioni, cache o log preesistenti. Credenziali esclusivamente da ambiente. Usare writable e sessioni della copia; neutralizzare la classmap Composer che potrebbe rimandare all'app originale.
- Verificare SELECT DATABASE() = rolewarden_test prima di snapshot e di ogni fase mutante. Non usare fallback alla configurazione DB originale. Escludere email e chiamate esterne dalle fixture.
- Salvare schema, righe e contatori AUTO_INCREMENT prima del primo cambiamento; preservare anche eventuali trigger, routine ed eventi oppure fermarsi se non gestibili. Snapshot con dati sensibili solo in memoria. Ripristinare in finally anche in caso di assertion o errore, confrontando schema, righe e contatori; non dichiarare cleanup riuscito senza confronto.
- Fermare il server dedicato, rimuovere soltanto la copia creata dal runner (verifica percorso assoluto entro tests/Integration), ricontrollare hash e rilasciare lock.

## Fixture

Usare login Shield reali, password generate in memoria e sessioni separate: super admin unico, operatore ordinario senza privilegi, lettore e operatori con singolo permesso. Creare secondo super admin solo nei controlli che lo richiedono. Ruoli custom padre/figlio/nipote e foglia eliminabile; ruoli di sistema dal seed. Osservare URL, campi e richieste dal pannello eseguito, senza aprire sorgenti del modulo. Le operazioni sotto collaudo passano sempre dal pannello/HTTP; SQL diretto solo per preparazione e oracolo DB.

## Accessi e presentazione

1. Richiesta anonima a ogni pagina e azione mutante: niente contenuto protetto e nessuna mutazione DB; login Shield necessario.
2. Login senza permessi: negazione di ogni schermata e azione; navigazione e comandi non autorizzati assenti dal DOM, anche come controlli disabled.
3. Per ogni users.{view,create,update,delete,activate}, roles.{view,create,update,delete,assign}, permissions.{view,override}: permesso presente consente la propria azione, assente la nega anche con POST diretto e token CSRF valido. Separare eventuale dipendenza dal permesso di lettura da errori certi: non inventare tale contratto.
4. Lettore: nessun controllo mutante in elenco/dettagli utenti e ruoli, inclusa matrice e override. Il server rifiuta comunque richieste copiate dalla sessione amministrativa.

## Utenti

5. Creare utente da form; verificare identita' Shield, login, dati e unicita'. Annotare attivazione immediata come ambiguita' dichiarata, senza assegnarle FAIL.
6. Modificare anagrafica; verificare persistenza alla richiesta successiva e invariabilita' di altri utenti.
7. Disattivare e riattivare: sessione gia' autenticata perde e recupera accesso secondo la precedenza specificata, senza nuovo login per la verifica di autorizzazione.
8. Eliminare utente ordinario: non piu' operativo; non imporre cancellazione fisica quando il log dichiara soft delete.
9. Assegnare e revocare ruolo: permessi cambiano alla richiesta successiva della stessa sessione.
10. Override positivo, negativo e rimozione: verificare DB ed effetto sulla stessa sessione. Negativo batte anche super admin; rimozione ripristina il risultato derivante dai ruoli. Inviare granted=2, -1 e testo: nessun valore fuori 0/1 persistito.
11. Ultimo super admin: disattivazione, eliminazione e revoca ruolo rifiutate, stato DB invariato. Ripetere con secondo super admin attivo per dimostrare che la protezione non vieta indiscriminatamente le operazioni.

## Ruoli e matrice

12. Creazione, modifica, eliminazione ruolo custom; verificare dati, elenco e richieste successive. Slug riservato da ruolo eliminato: errore utile che propone ripristino o altro slug, senza SQL.
13. Ruolo di sistema non eliminabile; padre con figli attivi non eliminabile; auto-parentela e ciclo padre/figlio/nipote rifiutati senza scritture parziali.
14. Eliminazione ruolo o spegnimento flag che lasciano zero super admin attivi rifiutati anche via richiesta manipolata.
15. Matrice: righe aree, colonne azioni, celle senza permesso non interagibili; selezione per riga e colonna modifica solo celle pertinenti e consentite.
16. Browser con JavaScript: cambiare UNA cella, attendere risposta asincrona; un marcatore in memoria della pagina e l'assenza di navigazione document dimostrano nessun refresh. Nuova richiesta indipendente e DB confermano persistenza. Ripetere revoca e verificare nessuna perdita delle altre celle.
17. Figlio: celle concesse da padre e nonno selezionate, bloccate, origine visibile. Tentare revoca manipolando direttamente la richiesta di salvataggio; permesso effettivo resta concesso e dati del padre invariati.
18. Dalla matrice revocare un permesso gia' usato da un'altra sessione; richiesta successiva di quella sessione negata senza logout o invalidazione manuale. Ripetere sul discendente e tramite override da pannello.

## View, permessi e sicurezza

19. Elenco permessi leggibile e senza comandi di scrittura; richieste mutanti non modificano il catalogo. L'eccezione "salvo i personalizzati" della SPEC non va implementata nel test: la richiesta attuale prescrive sola lettura.
20. Inserire nella cartella override dell'app COPIATA una view del pannello con marcatore univoco. Richiedere la pagina e verificare precedenza; rimuovere override e verificare ritorno della view modulo. Nessun file sorgente del modulo letto o modificato.
21. Enumerare ogni form e azione mutante, inclusi fetch della matrice: token CSRF presente; token assente, invalido e token di altra sessione non devono produrre scritture. Verificare anche richieste dirette, non solo HTML.
22. Payload con apici, operatori SQL e markup in campi/ricerca/ID: nessuna modifica collaterale, nessun ampliamento dei risultati, output escaped. Sono prove comportamentali, non dimostrazione che TUTTE le query siano parametrizzate. Con il divieto di lettura implementativa tale garanzia universale resta non verificabile e va esplicitata.
23. Duplicati, ID inesistenti, input malformati, operazioni protette e fallimento DB controllato della sola copia: nessun SQL, stack o credenziale nelle risposte HTTP, anche in development e production. Ripristinare lo stato dopo ogni fault injection; E_ALL per warning/errori.

## Ambiguita' da non decidere

CSS manuale invece di Tailwind; attivazione immediata senza verifica email. Il testo SPEC prevede CSS compilato distribuito e build non obbligatoria per l'acquirente: la motivazione di Claude non costituisce una decisione dell'autore. Riportare osservazioni senza approvare autonomamente la deviazione.
