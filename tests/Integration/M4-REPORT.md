# M4 - Riverifica indipendente dopo fix D3

**M4 approvata nel perimetro collaudato: 422 PASS, 0 FAIL. D3 chiuso.**

Esecuzione del 2026-09-22 su HEAD `52d8726874e4ae9f52a9eb0b00d323615c59ff62`, contenente il fix dichiarato `4ce25ad`. Il precedente tentativo bloccato e' stato ripreso con il database disponibile. Exit code del verificatore: **0**.

## Risultati

| Perimetro | Esito |
| --- | --- |
| Casi M4 immediati, protezioni e cache | 278 PASS, 0 FAIL |
| Altri controlli del runner: isolamento, regressione M3, HTTP M4/D3 e ripristino | 143 PASS, 0 FAIL |
| Cleanup PowerShell: app rimossa e hash `.env` sorella invariato | 1 PASS, 0 FAIL |
| Totale | **422 PASS, 0 FAIL** |

Il footer PHP riporta 421 PASS / 0 FAIL; il wrapper aggiunge il PASS di cleanup. Output completo in [m4-output.txt](m4-output.txt). Runtime: PHP 8.3.11, CodeIgniter 4.7.4, Shield 1.4.1, E_ALL. Nessuna diagnostica PHP osservata. Lint dei due file PHP e `git diff --check` superati.

## D3: rinomina ed eliminazione permesso

Verificate entrambe le operazioni per titolari tramite ruolo, override positivo diretto e override negativo diretto. I casi immediati includono titolari diretti e discendenti del ruolo. I casi HTTP verificano il risultato nella risposta alla scrittura e nella richiesta successiva, conservando ID autenticato e cookie di sessione.

| Titolare | Prima: vecchio / nuovo slug | Dopo rinomina | Dopo eliminazione |
| --- | --- | --- | --- |
| Ruolo che concede il permesso | true / false | false / true | false / false |
| Solo override positivo | true / false | false / true | false / false |
| Super admin con override negativo diretto | false / true | true / false | true / true |

Il ruolo super admin della fixture negativa non detiene il permesso: il caso rende osservabile l'invalidazione del solo override, senza beneficiare dell'invalidazione dei ruoli titolari. Le righe override restano collegate dopo rinomina e scompaiono dopo eliminazione.

Tutte le attese sono superate. I quattro FAIL storici di D3 risultano chiusi; aggiunti i controlli di eliminazione e di override negativo. Nessun logout o `forget` manuale esterno tra riscaldamento cache, scrittura e lettura nei casi D3. I precedenti `forgetUser` diagnostici sono sostituiti da letture ripetute senza invalidazione; i test M3 dell'API esplicita `forget` restano separati.

## Regressione

Conservati ed eseguiti i controlli funzionali del verificatore precedente:

- Cicli diretti, indiretti e su se stesso; padre inesistente; spostamento valido e rimozione del padre.
- Ruoli e permessi di sistema non cancellabili.
- Matrice ultimo super admin: eliminazione/disattivazione utente, revoca ruolo, eliminazione ruolo, spegnimento flag; altro utente attivo, inattivo, ruolo eliminato e secondo ruolo sullo stesso utente.
- Rifiuto eliminazione padre con figli attivi, logica e fisica, con database invariato; eliminazione consentita dopo spostamento o eliminazione figli; figli misti, padre di sistema, soli figli eliminati logicamente.
- D1/D2: scritture senza chiave primaria rifiutate senza effetti, incluse where(id), assenza di where e delete logico/fisico.
- Cache: eliminazione ruolo foglia, cambio padre, rinomina/eliminazione permesso, revoca assegnazione e disattivazione utente; propagazione ai discendenti dove prevista, controlli immediati e HTTP senza logout.
- Regressione M3: provider Shield, precedenza, gruppi diretti, slug non validi, filtro/helper, invalidazione esplicita tra richieste, soft delete e tabella utenti configurabile.

Nessun nuovo difetto trovato. Non sono stati letti i sorgenti per proporre o verificare una correzione.

## Indipendenza e sicurezza

Nella ripresa sono stati letti SPEC, BRIEF-MVP, log, contratti pubblici Authorization e il verificatore/report di integrazione preesistente. Nessuna lettura di Resolver, Guard, adapter, model o test unitari. I controlli Git di uguaglianza non mostrano ne' analizzano i corpi implementativi. Le fixture e le attese seguono specifica, contratti e decisioni autore gia' registrate.

Usato esclusivamente `rolewarden_test` su 127.0.0.1:3306, verificando il database selezionato sia nella connessione iniziale sia nei processi CI4 e HTTP. Snapshot prima delle modifiche e ripristino finale con confronto esatto di DDL e righe riusciti. `rolewarden` mai selezionato o modificato.

Credenziale caricata dalla configurazione host esistente nell'ambiente del processo, senza stamparla o scriverla in nuovi file. Server di test terminato e assenza di listener sulla porta 18973 verificata; app temporanea rimossa; hash `.env` dell'app sorella invariato. HEAD, src/, SPEC e BRIEF invariati. Nessun commit.

## Ambiguita' di specifica mantenute, non decise ne' conteggiate come FAIL

1. Flag super admin diretto o ereditato oltre il contratto pubblico di assegnazione diretta.
2. Confine delle protezioni rispetto a SQL diretto, query builder e cascata delle chiavi esterne.
3. Ripristino e cambio padre dei ruoli eliminati logicamente, oltre la regola gia' decisa sui figli attivi.
4. API di scrittura e invalidazione delle assegnazioni ruolo-permesso e degli override, rinviate a M5; rinomina/eliminazione dei permessi con override esistenti sono invece coperte qui.
5. Questioni pregresse del resolver: liste vuote per inattivi, enumerazione dei permessi super admin, espansione jolly in assegnazione, dominio ID e rappresentazione di override confliggenti.

Nessuna nuova ambiguita' emersa e nessuna decisione arbitraria. La regola di rifiuto eliminazione padre con figli attivi e' una decisione autore gia' acquisita. L'approvazione riguarda M4 nel perimetro richiesto, non le funzionalita' M5 o l'intera matrice di versioni supportate.

## Riproduzione

Con `RW_DB_PASSWORD` nell'ambiente e database di test disponibile:

```powershell
& .\tests\Integration\verify-m4.ps1 *> .\tests\Integration\m4-output.txt
$LASTEXITCODE
```
