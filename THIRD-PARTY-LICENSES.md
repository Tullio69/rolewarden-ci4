# Third-party licences

RoleWarden itself is proprietary, licensed through CodeCanyon. It ships or depends on the
open-source works below, each under its own licence. The full text of every licence is in
the work's own folder (`LICENSE` under `vendor/` in the complete application) or at the
address given.

## Shipped inside the module

| Work | Version | Licence | Copyright | Where |
| --- | --- | --- | --- | --- |
| [Alpine.js](https://alpinejs.dev) | 3.17.4 | MIT | © 2019-2025 Caleb Porzio and contributors | `src/Assets/js/vendor/alpine.min.js`, licence in `LICENSE-alpine.md` beside it |
| [IBM Plex](https://github.com/IBM/plex) Sans, Serif, Mono | Latin subset | SIL Open Font License 1.1 | © 2017 IBM Corp., Reserved Font Name "Plex" | `src/Assets/fonts/`, licence in `LICENSE.txt` beside them |
| [Tailwind CSS](https://tailwindcss.com) | 4.3.3 | MIT | © Tailwind Labs, Inc. | compiled into `src/Assets/css/panel.css` (build tool only; the header of that file keeps its notice) |

## Required by the module, included in the complete application

| Package | Version in the complete application | Licence | Copyright |
| --- | --- | --- | --- |
| [codeigniter4/framework](https://github.com/codeigniter4/framework) | 4.7.5 | MIT | © 2014-2019 British Columbia Institute of Technology; © 2019-present CodeIgniter Foundation |
| [codeigniter4/shield](https://github.com/codeigniter4/shield) | 1.4.1 | MIT | © 2020-2022 Lonnie Ezell; © 2022-present CodeIgniter Foundation |
| [codeigniter4/settings](https://github.com/codeigniter4/settings) | 2.4.0 | MIT | © 2021 Lonnie Ezell; © 2021-present CodeIgniter Foundation |
| [codeigniter4/translations](https://github.com/codeigniter4/translations) | 4.7.4 | MIT | © 2014-2019 British Columbia Institute of Technology; © 2019-present CodeIgniter Foundation |
| [laminas/laminas-escaper](https://github.com/laminas/laminas-escaper) | 2.18.0 | BSD-3-Clause | © 2020 Laminas Project a Series of LF Projects, LLC |
| [psr/log](https://github.com/php-fig/log) | 3.0.2 | MIT | © 2012 PHP Framework Interoperability Group |
| [Composer](https://getcomposer.org) autoloader (`vendor/autoload.php`, `vendor/composer/`) | 2.x | MIT | © Nils Adermann, Jordi Boggiano |

The complete application starts from CodeIgniter's
[app starter](https://github.com/codeigniter4/appstarter) (MIT, © CodeIgniter Foundation).

## The MIT licence

The works above marked MIT are distributed under these terms, with their own copyright line:

> Permission is hereby granted, free of charge, to any person obtaining a copy of this
> software and associated documentation files (the "Software"), to deal in the Software
> without restriction, including without limitation the rights to use, copy, modify, merge,
> publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons
> to whom the Software is furnished to do so, subject to the following conditions:
>
> The above copyright notice and this permission notice shall be included in all copies or
> substantial portions of the Software.
>
> THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED,
> INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR
> PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE
> FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR
> OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER
> DEALINGS IN THE SOFTWARE.

The BSD-3-Clause text of laminas-escaper is in `vendor/laminas/laminas-escaper/LICENSE.md`;
the SIL Open Font License 1.1 is in `src/Assets/fonts/LICENSE.txt`.
