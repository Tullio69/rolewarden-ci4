# RoleWarden - istruzioni per Claude Code

Modulo CodeIgniter 4 per ruoli e permessi in database, con pannello di amministrazione,
costruito come estensione di Shield. Destinazione: vendita su CodeCanyon.

La specifica completa sta in `docs/SPEC.md`, l'ordine di lavoro dell'MVP in
`docs/BRIEF-MVP.md`. Sono la fonte di verita' e vanno letti prima di iniziare: questo
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
- HTML, CSS e JavaScript scritti a mano, nessuno script di generazione

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

### Lancio di Codex per il collaudo di una tappa

Non usare l'agente `codex:codex-rescue` e non usare `task --background`: tornano subito e nessuno
avvisa a fine lavoro, quindi l'utente dovrebbe chiedere ogni volta se Codex ha finito.
Procedura obbligatoria:

1. Scrivi il prompt di collaudo in un file nella cartella scratchpad (mai inline nel comando,
   i virgolettati rompono la shell). Il prompt segue il protocollo del brief: Codex legge
   solo specifica, definizione di fatto, `_AI-LOG.md` e i contratti pubblici, non
   l'implementazione; scrive solo in `tests/Integration/` e nel log; nessun commit.
2. Lancia con lo strumento Bash e `run_in_background: true`, senza `--background`, cosi' il
   comando resta in primo piano nel proprio processo e il sistema ti risveglia quando termina:
   `node "C:/Users/fabio/.claude/plugins/cache/openai-codex/codex/1.0.6/scripts/codex-companion.mjs" task --write "$(cat <file-prompt>)"`
   (se la versione del plugin nel percorso non esiste piu', usa quella presente in
   `~/.claude/plugins/cache/openai-codex/codex/`).
3. Dillo all'utente in una riga e continua o fermati. Non fare polling e non chiedere
   all'utente se ha finito.
4. Alla notifica di fine, leggi l'output e `tests/Integration/M<n>-REPORT.md`, correggi i
   difetti che sono tuoi, rilancia Codex per la riverifica e riporta l'esito all'utente
   senza aspettare che lo chieda. Le ambiguita' di specifica non si decidono: si riportano.

### Se Codex non e' disponibile

Quota esaurita, ambiente che rifiuta l'avvio dei processi, o qualunque altro blocco che
impedisce a Codex di collaudare: non si salta il collaudo e non si dichiara chiusa una
tappa senza di esso. Si sostituisce Codex con un subagente Claude fresco (tipo
`general-purpose`), il "Collaudatore ad Hoc", che segue lo stesso identico protocollo,
in tutto e per tutto:

1. Legge solo specifica, definizione di fatto, `_AI-LOG.md` e i contratti pubblici. Non
   apre controller, model, view, adapter ne' i test unitari esistenti: se leggesse
   l'implementazione certificherebbe il comportamento esistente, bug compresi, esattamente
   il rischio che il protocollo con Codex esiste per evitare.
2. Collauda in nero (richieste HTTP, query dirette al database) contro un'app CI4 di prova
   isolata, usando solo `rolewarden_test`, mai `rolewarden`; snapshot e ripristino esatto
   dei dati a fine collaudo; nessuna credenziale nei file.
3. Scrive solo in `tests/Integration/` (stesso schema di file di Codex: `verify-m<n>.*`,
   `M<n>-REPORT.md`, output) e una voce in `_AI-LOG.md` nello stesso formato, con esito
   PASS/FAIL, stato di approvazione e le ambiguita' di specifica separate dai difetti, mai
   decise a naso. Nessun commit: lo fa chi lo lancia, dopo aver letto l'esito.
4. Chi lo lancia non gli fornisce scorciatoie che Codex non avrebbe: stesso perimetro,
   stessa cecita' sull'implementazione, stesso rigore. L'unica differenza accettabile e' il
   nome nella voce di log.

Il collaudo del Collaudatore ad Hoc vale come chiusura della tappa alla pari di quello di
Codex. Quando Codex torna disponibile, non serve ripetere una tappa gia' chiusa cosi'.

Inizio sessione: aggiorna "In lavorazione" con nome, orario, scope previsto.
Fine sessione: riporta "In lavorazione" a "nessuno" e aggiungi una voce nel log.

Per ogni vista del pannello RoleWarden segui docs/design-system/CLAUDE.md