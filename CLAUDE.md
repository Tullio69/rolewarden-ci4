# RoleWarden - istruzioni per Claude Code

Modulo CodeIgniter 4 per ruoli e permessi in database, con pannello di amministrazione,
costruito come estensione di Shield. Destinazione: vendita su CodeCanyon.

La specifica completa sta in `docs/SPEC.md`, l'ordine di lavoro dell'MVP (chiuso) in
`docs/BRIEF-MVP.md`, quello della v1.0 (in corso) in `docs/BRIEF-v1.0.md`. Sono la fonte di verita' e vanno letti prima di iniziare: questo
file riassume solo cio' che serve avere sempre sotto mano. Le copie originali vivono
come documenti su Claude; se una decisione cambia si aggiorna prima l'originale e poi
si riesporta in `docs/`.

## Vincoli non negoziabili

1. Si estende Shield, non lo si sostituisce. Shield resta padrone di utenti, credenziali,
   sessioni, login, remember-me, token e registro dei tentativi.
2. Il codice controlla permessi, mai ruoli: `can('users.delete')`, mai `hasRole('admin')`.
3. Lo strato di autorizzazione (`src/Authorization/`) non dipende dal framework. CI4 lo
   raggiunge tramite filtro ed helper.
4. Per tutto il resto si parla direttamente a CI4, senza astrazioni multi-framework.
5. Nessun file del modulo va modificato dall'acquirente: le personalizzazioni vivono nella
   sua applicazione, e un aggiornamento deve essere una sostituzione di cartella.
6. Precedenza nella risoluzione: utente disattivato nega tutto; poi override negativo
   dell'utente, che batte anche il super admin; poi override positivo; poi super admin;
   poi permesso da un ruolo; altrimenti nega.
7. I permessi si identificano per slug testuale (`area.azione`), mai per id.

## Regole di codice

- Mai usare il percorso `public/` direttamente
- Mai mostrare errori SQL a schermo, in nessun ambiente
- Escape su ogni output, CSRF su ogni form, query sempre parametrizzate
- Hash delle password lasciato a Shield, nessuna variante propria
- Nessuna credenziale o chiave nel codice: tutto in variabili d'ambiente
- Ogni stringa dell'interfaccia nei file di lingua fin da subito
- Codice e documentazione in inglese
- Componenti JS: dichiarazione e controller nello stesso file, controller come funzione separata
- HTML e JavaScript scritti a mano. Il CSS del pannello si compila con la CLI standalone di
  Tailwind v4 (niente Node ne' npm) da un sorgente scritto a mano; il CSS compilato si committa,
  chi installa il modulo non esegue build. Le classi componente `rw-*` restano, definite nel
  sorgente Tailwind sui token. Nessun altro script di generazione (deciso il 2026-09-30, V5)

## Repository

- Remoto `origin`: `git@github.com:Tullio69/rolewarden-ci4.git`
- Branch principale e predefinito: `main` (rinominato da `master` il 2026-09-26; `master`
  non esiste piu', ne' in locale ne' sul remoto)
- Push solo su richiesta dell'utente

## Cosa fermarsi a chiedere

Scostamenti dai vincoli, nuove dipendenze oltre CI4 e Shield, modifiche allo schema non
previste, anticipi di funzionalita' rimandate alle versioni successive, requisiti che
restringono chi puo' installare il modulo.

## Coordinamento con altri agenti AI

Prima di iniziare qualsiasi modifica, leggi _AI-LOG.md nella root del progetto.
Se la sezione "Stato corrente" indica un altro agente al lavoro su file che si sovrappongono al tuo scope, fermati e avvisa l'utente invece di procedere.

### Collaudo di una tappa

Dal 2026-10-14 il collaudo indipendente lo fa solo un subagente Claude fresco (tipo
`general-purpose`), il "Collaudatore ad Hoc". Codex non si usa piu', nemmeno quando e'
disponibile. Non si salta il collaudo e non si dichiara chiusa una tappa senza di esso.

1. Il collaudatore legge solo specifica, definizione di fatto, `_AI-LOG.md` e i contratti
   pubblici. Non apre controller, model, view, adapter ne' i test unitari esistenti: se
   leggesse l'implementazione certificherebbe il comportamento esistente, bug compresi.
2. Collauda in nero (richieste HTTP, query dirette al database) contro un'app CI4 di prova
   isolata, usando solo `rolewarden_test`, mai `rolewarden`; snapshot e ripristino esatto
   dei dati a fine collaudo; nessuna credenziale nei file.
3. Scrive solo in `tests/Integration/` (`verify-<tappa>.*`, `<TAPPA>-REPORT.md`, output) e una
   voce in `_AI-LOG.md` con esito PASS/FAIL, stato di approvazione e le ambiguita' di
   specifica separate dai difetti, mai decise a naso. Nessun commit: lo fa chi lo lancia,
   dopo aver letto l'esito.
4. Chi lo lancia scrive il prompt in un file nella cartella scratchpad, non gli fornisce
   scorciatoie sull'implementazione, lo avvia in background e lo dice all'utente in una
   riga, senza polling. Alla notifica di fine legge il report, corregge i difetti suoi,
   rilancia il collaudatore per la riverifica e riporta l'esito all'utente senza aspettare
   che lo chieda. Le ambiguita' di specifica non si decidono: si riportano.

Le tappe chiuse in passato da Codex restano valide e non vanno ripetute.

Inizio sessione: aggiorna "In lavorazione" con nome, orario, scope previsto.
Fine sessione: riporta "In lavorazione" a "nessuno" e aggiungi una voce nel log.

Per ogni vista del pannello RoleWarden segui docs/design-system/CLAUDE.md