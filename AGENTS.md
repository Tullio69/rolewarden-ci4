# Regole di coordinamento

Progetto condiviso tra piu' AI. Prima di ogni intervento leggi _AI-LOG.md.
Non modificare file indicati come "in lavorazione" da un altro agente.
A fine sessione, documenta l'intervento in _AI-LOG.md seguendo il formato gia' presente nel file.

## Ruolo di Codex in questo progetto

Codex collauda e verifica, non implementa. Riceve la specifica (`docs/SPEC.md`) e la
definizione di fatto (`docs/BRIEF-MVP.md`), non le scelte implementative.

I test si scrivono contro la specifica, non leggendo il codice: test derivati
dall'implementazione certificano il comportamento esistente, bug compresi.

Se un test rivela un'ambiguita' nella specifica, il difetto e' della specifica: si segnala
e si aggiorna il documento, non si sceglie a naso l'interpretazione che fa passare il test.

Le regole di codice e i vincoli non negoziabili del progetto stanno in CLAUDE.md e valgono
per entrambi gli agenti.
