# Brief v1.0 - RoleWarden

Esportato il 2026-09-26 dall'originale su Claude: https://claude.ai/code/artifact/795055ee-64e6-4f32-90e7-7e8be2dc3d3b

## Cosa costruiamo

La v1.0 è la prima versione in vendita su CodeCanyon. Parte dall'MVP verificato e aggiunge due cose: quello che un compratore cerca per primo in un pannello admin, e la confezione che la review della piattaforma pretende. La specifica, **RoleWarden - Specifiche e Roadmap**, resta la fonte di verità: questo brief è l'ordine di lavoro.

**Dentro la v1.0.**

1. Ricordami e throttling di Shield, esposti e configurabili dal pannello
2. Sessioni e remember token, con revoca singola o totale
3. Log attività, filtrabile per utente, azione e periodo
4. Profilo utente con cambio password
5. Toast e riscontri nel pannello
6. Email sugli eventi di sicurezza: accesso da nuovo dispositivo o IP, password cambiata, troppi tentativi falliti
7. Importazione utenti da CSV
8. Impostazioni: opzioni del modulo modificabili a runtime
9. Tre temi (Console, Clarity, Contrast) con variante chiara e scura, personalizzatore con anteprima dal vivo e controllo del contrasto, design guide con i token documentati
10. Confezione: installer guidato via browser, app demo con modalità vetrina, documentazione HTML completa, interfaccia tradotta anche in italiano, pacchetto installabile senza Composer

**Fuori dalla v1.0, e non si anticipa.** Tutto ciò che la specifica assegna a v1.5 e v2.0: 2FA, login social, centro notifiche persistente e preferenze di notifica, API token, inviti via email, policy password configurabili, impersonazione, esportazione e importazione del tema, tipografia personalizzata. Gli eventi di sicurezza che la specifica manda anche al centro notifiche, in v1.0 partono solo per email.

## Punto di partenza

L'MVP è chiuso dal 26 settembre 2026: tutte le 13 voci della sua definizione di fatto sono verificate da collaudi indipendenti. Della v1.0 esiste già una parte, il pannello rifatto con il design system "editorial ledger".

| Già fatto | Stato |
| --- | --- |
| Resolver, aggancio a Shield, protezioni, innesto su app esistente (M0-M6) | Approvati |
| Pannello sul design system: Login, Utenti, Dettaglio utente, Ruoli, Matrice permessi | Approvato |
| Token CSS in `panel.css`, un solo tema chiaro, tema scuro nei token senza controllo per attivarlo | Parziale |
| Compatibilità: PHP 8.3 e 8.5, CodeIgniter 4.7.4 fino all'ultima 4.x, Shield `^1.4` | Approvata |
| Repository su GitHub, branch `main` | Attivo |

**Vincoli ereditati, invariati.** Si estende Shield, non lo si sostituisce. Il codice controlla permessi, mai ruoli. Lo strato di autorizzazione resta PHP puro. Nessun file del modulo si modifica dall'acquirente, e ogni view resta sovrascrivibile dall'applicazione ospite. Precedenza di risoluzione e slug `area.azione` come nell'MVP.

**Regola in più per la v1.0.** Ogni funzione che Shield già offre (ricordami, throttling, sessioni, registro dei tentativi) si integra e si espone nel pannello, non si riscrive. Se Shield non offre il pezzo che serve, si segnala prima di scriverlo.

## Decisioni da prendere

Nessuna blocca la prima tappa. Ognuna va chiusa prima della tappa indicata, e la decisione si scrive nella specifica prima del codice.

| Decisione | Opzioni sul tavolo | Blocca |
| --- | --- | --- |
| Hosting e accessi per rolewarden.com | Server, database, invio email sostituito a livello di sistema, sottodominio di test | V0 |
| Tailwind o CSS a mano | La specifica prevede Tailwind; CLAUDE.md vieta script di generazione e il pannello è già in CSS a mano con token. I temi leggono variabili CSS in entrambi i casi | V5 |
| Lingue dell'interfaccia | La specifica dice inglese più italiano: da confermare, e se servono altre lingue al lancio | V7 |
| Pacchetto senza Composer | Come riceve Shield e le sue dipendenze chi copia una cartella: vendor incluso nello zip, oppure Composer obbligatorio e la voce si toglie dalla specifica | V7 |
| Prezzo e struttura della licenza | Da fissare guardando gli item analoghi in vetrina | Pubblicazione |

**Già chiuse, da riportare nella specifica.** Il nome è RoleWarden, la tabella delle decisioni aperte lo dà ancora da scegliere. Lo strumento di design di partenza è di fatto deciso: esiste `docs/design-system/`, e i tre temi partono da lì.

## Ordine di lavoro

Nove tappe, in quest'ordine. Come nell'MVP, ognuna si chiude prima di aprire la successiva e passa dal collaudo indipendente prima di dirsi chiusa.

| Tappa | Cosa consegna |
| --- | --- |
| V0 Ambiente | Ambiente di test separato e demo su rolewarden.com: reset periodico dei dati, nessuna email in uscita, `noindex`, avanzamento solo per merge fast-forward da un branch di test |
| V1 Riscontri e impostazioni | Componente toast (esito, errore, attenzione) usato da tutte le schermate esistenti; schermata Impostazioni con le opzioni del modulo modificabili a runtime |
| V2 Account | Profilo con cambio password; sessioni e remember token con revoca singola o totale; ricordami e throttling di Shield configurabili dalle Impostazioni |
| V3 Log attività | Registro degli eventi di accesso e delle modifiche a ruoli, permessi e utenti, filtrabile per utente, azione e periodo |
| V4 Email di sicurezza | Accesso da nuovo dispositivo o IP, password cambiata, troppi tentativi falliti; template sovrascrivibili come le view, invio a fine richiesta se l'ospite non ha una coda |
| V5 Temi | Token su tre livelli, temi Console, Clarity e Contrast in chiaro e scuro, personalizzatore con anteprima dal vivo e controllo del contrasto, file tema salvato nell'applicazione ospite, design guide |
| V6 Importazione CSV | Importazione utenti con anteprima, errori per riga e nessuna scrittura parziale in caso di file non valido |
| V7 Confezione | Installer guidato via browser (requisiti, database, primo super admin), traduzione italiana dell'interfaccia, pacchetto installabile senza Composer |
| V8 Demo e documentazione | App demo con tre ruoli e modalità vetrina, messa online su rolewarden.com, documentazione HTML completa, changelog e licenze delle dipendenze |

**Perché quest'ordine.** V0 viene prima perché la specifica lo chiede: gli aggiornamenti non si provano sulla demo che i compratori guardano. I toast vengono prima delle schermate nuove, che altrimenti nascerebbero mute. Il log viene prima delle email, perché "nuovo dispositivo o IP" si decide confrontando gli accessi passati. I temi vengono dopo le schermate nuove, così il personalizzatore si collauda su tutto il pannello e non su metà. Demo e documentazione chiudono perché descrivono il prodotto finito.

**Sulla V2.** Ogni revoca deve avere effetto alla richiesta successiva, senza attendere la scadenza della sessione: è lo stesso criterio che l'MVP applica ai permessi.

**Sulla V5.** I temi toccano solo il livello semantico dei token. Una personalizzazione non modifica mai un file del modulo, e un aggiornamento la lascia intatta.

## Definizione di fatto

La v1.0 è finita quando questa lista è interamente spuntata dal collaudo indipendente. Sono verifiche, non opinioni.

**Funzionalità.**

- [ ] Una sessione o un remember token revocati dal pannello non danno più accesso alla richiesta successiva
- [ ] Un utente cambia la propria password dal profilo solo confermando quella attuale, e riceve l'email di avviso
- [ ] Il throttling configurato dalle Impostazioni blocca il tentativo oltre la soglia, e l'email "troppi tentativi" arriva all'utente e agli admin
- [ ] Un accesso da dispositivo o IP mai visto per quell'utente produce l'email; un accesso da uno già visto no
- [ ] Ogni modifica a ruoli, permessi e utenti compare nel log attività con autore, oggetto e ora, e i filtri per utente, azione e periodo la ritrovano
- [ ] Ogni azione del pannello che salva o fallisce produce un toast; gli errori restano finché non si chiudono
- [ ] Un CSV con una riga non valida viene rifiutato per intero, con l'errore riportato per riga; uno valido crea gli utenti
- [ ] I tre temi, in chiaro e in scuro, rendono ogni schermata senza valori scritti fuori dai token
- [ ] Il personalizzatore salva un file tema nell'applicazione ospite, segnala le combinazioni sotto WCAG AA, e il tema sopravvive a un aggiornamento del modulo
- [ ] L'installer guidato porta da zero a un primo super admin funzionante, e si rifiuta di ripartire a installazione avvenuta
- [ ] L'app demo si installa e si disinstalla con un comando senza lasciare residui, e la modalità vetrina non si attiva in production
- [ ] Ogni stringa dell'interfaccia esiste in inglese e in italiano

**Pubblicazione (dalla specifica).**

- [ ] Installazione pulita su PHP 8.3 e sull'ultima 8.x, su CodeIgniter 4.7.4 e sull'ultima 4.x
- [ ] Nessun errore o warning con il report degli errori al massimo livello
- [ ] Nessun errore SQL a schermo in nessun percorso, compresi quelli di fallimento
- [ ] Nessuna credenziale, chiave o URL personale nel pacchetto
- [ ] CSRF su ogni form, escape su ogni output, hash delle password lasciato a Shield
- [ ] Funziona in sottocartella e dietro reverse proxy
- [ ] Tutte le voci della definizione di fatto dell'MVP restano vere
- [ ] Demo online raggiungibile, con reset dei dati, nessuna email in uscita e `noindex` verificati

## Protocollo e cosa fermarsi a chiedere

Il protocollo è quello dell'MVP, invariato: Claude Code scrive, Codex collauda contro la specifica e questo brief, mai leggendo il codice. Se Codex non è disponibile lo sostituisce il Collaudatore ad Hoc, con lo stesso perimetro. Registro condiviso in `_AI-LOG.md`, un commit per unità di lavoro, push solo su richiesta.

**In più per la v1.0.**

- Ogni schermata nuova passa anche da un controllo visivo in un browser vero: il collaudo via HTTP legge l'HTML, non la resa.
- Il collaudo delle email usa una cattura locale dei messaggi, mai un invio reale.
- Gli script di collaudo si fermano da soli se il database di destinazione non è `rolewarden_test`.

**Cosa fermarsi a chiedere.** Le voci dell'MVP restano: scostamenti dai vincoli, dipendenze oltre CI4 e Shield, modifiche allo schema non previste, anticipi di funzionalità di v1.5 o successive, requisiti che restringono chi può installare il modulo. Si aggiungono:

1. Qualunque funzione che Shield già offre e che si pensa di riscrivere
2. Tabelle nuove, anche quando la specifica le implica (sessioni, log, impostazioni): lo schema si approva prima delle migrazioni
3. Qualunque cosa che parta dal server verso l'esterno: email reali, chiamate a servizi di geolocalizzazione IP, analytics
