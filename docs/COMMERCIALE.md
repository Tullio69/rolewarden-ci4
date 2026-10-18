# RoleWarden - lato commerciale e immagine

Punto di partenza per la chat dedicata a marketing, prezzo, vetrina e immagine.
Lo sviluppo tecnico va avanti in un'altra chat: questa non tocca codice, pacchetti
ne' server. Fonte di verita' del prodotto: `docs/SPEC.md` (sezione "Requisiti di
pubblicazione") e `docs/BRIEF-v1.0.md`.

## Il prodotto in breve

- Modulo CodeIgniter 4 per ruoli e permessi in database, con pannello di
  amministrazione, costruito come estensione di Shield (la libreria di
  autenticazione ufficiale di CI4). In vendita su CodeCanyon.
- Funzioni v1.0:
  - matrice dei permessi per ruolo, override per singolo utente, gerarchia di ruoli;
  - utenti, sessioni revocabili, profilo con cambio password;
  - log attivita' filtrabile, email di sicurezza;
  - tre temi in chiaro e scuro con personalizzatore e controllo del contrasto;
  - importazione CSV degli utenti;
  - installer da browser, app completa senza Composer;
  - interfaccia in inglese e italiano;
  - documentazione HTML; app demo con tre ruoli e modalita' vetrina.
- Requisiti: PHP 8.3-8.5, CodeIgniter 4.7.4 o successiva, Shield 1.4.
- Si installa in un'app esistente e si aggiorna sostituendo una cartella, senza
  toccare il codice dell'acquirente.

## Dove si vede

- Demo pubblica: https://demo.rolewarden.com/ (reset orario dei dati, nessuna email in
  uscita, `noindex`). Credenziali pubbliche: admin@example.com / demo-admin-2026,
  viewer@example.com / demo-viewer-2026; reader@, author@, approver@example.com /
  demo-docs-2026.
- Staging (versioni nuove prima della demo): https://staging.rolewarden.com/
- Sito: https://www.rolewarden.com/ (oggi una pagina statica). Documentazione online:
  https://www.rolewarden.com/docs/ (versione 1.0.0).

## Stato della roadmap di pubblicazione (2026-10-18)

1. Decisioni del prodotto: fatto.
2. Verifiche tecniche: fatto.
3. Versione 1.0.0 definitiva: fatto (tag `v1.0.0`, demo e documentazione online alla 1.0.0).
4. **Vetrina: compito di questa chat.**
5. Invio a CodeCanyon e post-lancio.

## Da fare qui (passo 4)

1. **Prezzo e licenza** (Regular ed Extended): unica decisione del brief ancora aperta,
   da fissare guardando gli item analoghi in vetrina.
2. **Ricerca marchi** su EUIPO eSearch plus, classi 9 e 42, per il nome RoleWarden.
3. **Immagini**: screenshot con la matrice dei permessi come prima immagine; immagine
   di anteprima e miniatura nei formati Envato.
4. **Video** di due minuti che parte dall'installazione.
5. **Pagina prodotto** CodeCanyon: testo, punti di forza, credenziali della demo in
   chiaro, requisiti.
6. **Supporto**: risposte pronte alle domande prevedibili (innesto su una tabella
   utenti esistente, convivenza con Shield, personalizzazione delle view) e cosa il
   supporto non copre (sviluppo su misura, debug dell'app dell'acquirente).

## Concorrenti gia' analizzati

**"CodeIgniter 4 Login with Ion Auth and User Management System"** (Code_Master11),
https://codecanyon.net/item/codeigniter-login-with-ion-auth/19878790. Analisi del
2026-10-18:
- **Numeri:**
  - prezzo: Regular $16, Extended $65;
  - vendite: 356 in 9 anni;
  - voto: 3,86 su 29 recensioni;
  - base tecnica: CI 4.7.3, Ion Auth 4, Argon Dashboard 2 PRO.
- **Punti forti:**
  - prezzo d'impulso;
  - "tutto incluso": registrazione, login social Google/Facebook/GitHub, dashboard,
    task manager, email a gruppi;
  - installer;
  - anzianita' in vetrina;
  - supporto percepito cortese.
- **Punti deboli:**
  - molte recensioni sui bug ("Poorly coded", due "scam");
  - ruoli = gruppi Ion Auth, senza permessi granulari;
  - non basato su Shield;
  - app da cui partire, non modulo;
  - documentazione assente;
  - aggiornamenti lenti;
  - credenziali della demo che non funzionavano;
  - scheda trascurata (IE9, MySQL 5.x, supporto via Skype);
  - possibile tema PRO a pagamento ridistribuito (da verificare).
- **Per RoleWarden:**
  - puntare su permessi granulari, "built on Shield", modulo drop-in, documentazione,
    collaudo;
  - non inseguirli sul prezzo;
  - dire nella scheda che registrazione e recupero password li da' Shield;
  - mancano rispetto a loro: login social e dashboard.

Da analizzare ancora: 3-4 item analoghi (pannelli RBAC per CodeIgniter e Laravel) prima
di fissare il prezzo.

## Regole per questa chat

- Non modificare codice, pacchetti, server, la demo ne' lo staging: per quello si passa
  dalla chat tecnica.
- Il lavoro di questa chat vive in `docs/` (questo file e quelli nuovi che servono) o
  fuori dal repository. Nessun commit o push senza richiesta dell'utente.
- Prima di iniziare leggi `_AI-LOG.md`. Se "In lavorazione" indica la chat tecnica, non
  toccare i suoi file: questa chat scrive solo in `docs/COMMERCIALE*` e simili.
