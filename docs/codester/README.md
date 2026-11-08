# Materiale per Codester

Fuori dal pacchetto (`/docs` è `export-ignore`). La scheda da incollare è `scheda-prodotto.md`.

| File | Cosa | Come si rifà |
|---|---|---|
| `preview-1600x800.png` | Immagine di anteprima | da `preview-1600x800.html` (sotto) |
| `icon-400x400.png` | Icona | copia di `assets/rolewarden-brand/png/social/avatar-400.png` |
| `screenshots/09-11-wizard-*.png` | Il wizard: ruoli, conferma, fine (1440x900) | `wizard-shots.mjs` (sotto) |
| `scheda-prodotto.md` | Titolo, descrizione, FAQ, changelog, tag, checklist | a mano |

Gli screenshot 01-08 del pannello sono in `docs/codecanyon/screenshots/`.

## Anteprima

Pagina HTML con i font IBM Plex del pacchetto e il logo vettoriale, fotografata da Chrome.
Per un'altra misura cambia `width`/`height` del `body` e `--window-size`:

```sh
cd docs/codester
"/c/Program Files/Google/Chrome/Application/chrome.exe" --headless=new --disable-gpu --hide-scrollbars \
  --force-color-profile=srgb --window-size=1600,800 \
  --screenshot="$PWD/preview-1600x800.png" "file:///$PWD/preview-1600x800.html"
```

## Screenshot del wizard

Servono un'app CodeIgniter 4 + Shield **non ancora** passata dal wizard, in inglese, in
`production`, servita su `http://localhost:8000`, con il modulo dello zip nella cartella
`modules/rolewarden` e la riga in `Autoload.php`. Quella del 2026-11-08 era una copia di
`rolewarden-wizard-demo` con i file di config originali, i gruppi di un negozio
(`admin`, `manager`, `support`, `customer`) in `AuthGroups.php` e gli utenti di
`StoreSeeder.php` (password `Shots-Pass-2026!`, un permesso diretto Shield per mostrare
l'avviso), database `rw_shots` su `rolewarden-db`:

```sh
php spark migrate -n 'CodeIgniter\Settings' && php spark migrate -n 'CodeIgniter\Shield'
php spark db:seed StoreSeeder
php spark serve --port 8000
node docs/codester/wizard-shots.mjs <cartella-app> <cartella-output>
```

Lo script entra come `maya@northwind.test`, legge il codice da
`writable/rolewarden/install-code.txt`, sceglie Adatta con `admin` come ruolo del pannello,
il super admin esistente e "solo amministratori", applica e salva una PNG per ogni passo
(più le versioni a pagina intera). Per rifarlo: rimetti i `.rolewarden-bak` al posto dei
file, cancella `writable/rolewarden/` e ricrea il database.
