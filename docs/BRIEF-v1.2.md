# Brief v1.2 - RoleWarden (bozza)

Aperto il 2026-11-02, prima dell'invio della 1.1.0. Per ora contiene solo le decisioni prese con Fabio quel giorno; il resto dell'ordine di lavoro si scrive dopo la pubblicazione della 1.1.0. Le decisioni vanno riportate in `docs/SPEC.md` prima di iniziare.

## Logo nel pannello e white-label

Deciso il 2026-11-02. Il logo ufficiale è Warden Shield (`docs/brand/`), e il nome si scrive RoleWarden. Il pannello lo mostra di serie; chi installa può sostituirlo col nome e il marchio del suo cliente (white-label) senza modificare file del modulo.

1. Sidebar: il marchio accanto al nome, la variante del marchio scelta in base alla dimensione (`docs/brand/README.md`), colori dai token, così da seguire i tre stili della sidebar e i temi. Con la barra a icone resta solo il marchio.
2. Favicon del pannello, della pagina di accesso e dell'installer, servita dal modulo (rotta degli asset, SVG), mai scritta in `public/`.
3. Due parametri in `Config\RoleWarden`, sovrascrivibili in `app/Config/RoleWarden.php` come gli altri:
   - `$brandName = 'RoleWarden'`: nome nella sidebar, nel titolo della finestra, nella pagina di accesso e nell'installer.
   - `$brandMark = null`: `null` usa Warden Shield; un URL usa il marchio del cliente, anche come favicon; `''` nessun marchio.
   Con un nome diverso da RoleWarden sparisce anche la riga "Access managed by RoleWarden" della pagina di accesso.
4. Nessuna impostazione nel pannello (niente caricamento di file): scelto il 2026-11-02 tra le due forme proposte.

Da rifare dopo: gli screenshot della scheda, perché la sidebar cambia; la demo pubblica; la documentazione di configurazione.
