# Forumix

Ein vollständiges Community-Forum in nativem PHP. **Keine externen Abhängigkeiten, kein Composer, kein Framework, kein Build-Schritt.**

```
Aktuelle Version: 1.9.2
Projektseite:     https://forumix.de
Zum Ausprobieren: https://forum.forumix.de   ← läuft selbst mit Forumix
Download:         https://forumix.de/#download
```

Das Forum unter **forum.forumix.de** ist keine Demo-Installation neben der
Software, sondern dieselbe Fassung, die hier im Repository liegt. Dort laufen
auch Fragen und Fehlermeldungen auf.

> Versionsgeschichte → [CHANGELOG.md](CHANGELOG.md)
> Häufige Fragen und der Aufbau eigener Module → https://forumix.de/faq.html

---

## ✨ Features

### Inhalte & Kommunikation
- 💬 Kategorien, Foren, Threads, Beiträge mit BBCode
- 🧵 Sticky-Threads, geschlossene Threads, passwort-geschützte Foren
- 📊 Umfragen (mit Mehrfach-Auswahl optional)
- 🏷️ Tag-System für Threads
- 🔖 Lesezeichen + Thread-Abonnements
- 📌 @Mention-Autocomplete
- 💬 Verschachtelte Zitate mit Sprung zum Original
- 😊 45+ eingebaute Smileys
- 📎 Datei-Anhänge
- 📨 Private Nachrichten

### Mitglieder
- 👥 Profil-Seiten mit Banner, Avatar, Signatur, Bio, Location
- 🏅 Automatisches Rang-System (Beitragszahl-basiert)
- 🏆 20 Standard-Achievements
- 🟢 Live-Anzeige wer online ist (Bots erkennbar)
- 📊 Aktivitäts-Heatmap pro User
- 🔐 2FA (TOTP-basiert)
- 🍪 „Angemeldet bleiben" mit sicheren Remember-Tokens
- 🗑️ Selbstlöschung (DSGVO-konform)

### Moderation
- 👑 **Owner**-Rolle (vor versehentlichem Entzug geschützt)
- 🛡️ Globale Admins/Moderatoren
- 🛡️ Forum-spezifische Moderatoren
- 🚦 Freigabe-System pro Forum (Themen/Antworten optional)
- ✏️ Mod-Edit-Marker mit Pflicht-Begründung
- 🚨 Meldungs-System
- ⚠️ Warnungs-System mit Auto-Ban-Schwelle
- 🚫 IP-Banns
- 📜 Mod-Log (alle Mod-Aktionen nachvollziehbar)

### Sicherheit
- 🔒 PHP-Sessions mit CSRF-Schutz
- 🔐 BCrypt-Passwörter
- 🚦 Rate-Limiting für Login, Registrierung, Passwort-Reset
- 📧 E-Mail-Verifikation (optional)
- 🛡️ Auto-Bann bei zu vielen Warnungs-Punkten
- 🔍 IP-Verlauf pro User (für Mods sichtbar)

### Technik
- 🎨 **Design-System** mit Theme-Tokens (Farben, Schrift, Layout im Admin änderbar)
- 🌙 Light/Dark Mode (User-wählbar)
- 🎯 **Template-Engine** mit Auto-Escape (Twig-inspirierte Syntax)
- 🧩 **Modul-System** für Erweiterungen
- 🔄 **Self-Healing Versionsanzeige** (Version aus Code, nicht aus DB-Drift)
- 📱 Responsive Design
- ⚡ Native PHP, kein Build-Step

---

## 🚀 Installation

### Voraussetzungen
- PHP 8.1+
- MySQL 5.7+ oder MariaDB 10.3+
- nginx oder Apache mit mod_rewrite
- Schreibrechte für `cache/`, `uploads/`, `config.php`

### Schritt-für-Schritt

1. **Datenbank anlegen** (im Hosting-Panel oder via SQL)
   ```sql
   CREATE DATABASE forum CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'forum_user'@'localhost' IDENTIFIED BY 'starkes_passwort';
   GRANT ALL ON forum.* TO 'forum_user'@'localhost';
   ```

2. **Dateien hochladen** (alle Inhalte aus dem ZIP)

3. **Verzeichnis-Rechte setzen**
   ```bash
   chmod 775 cache/templates uploads uploads/avatars uploads/attachments
   ```

4. **Web-Server konfigurieren** (siehe nächster Abschnitt)

5. **Installer starten**: `https://deine-domain.tld/install/`

   > ⏱️ Zügig durchziehen: bis der Installer durch ist, könnte ein Fremder das
   > Forum auf seiner eigenen Datenbank einrichten.

   Der Wizard führt durch 4 Schritte:
   - System-Check (PHP-Version, Extensions)
   - Datenbank-Verbindung
   - Admin-Account anlegen → wird automatisch zum **Owner**
   - Fertig

6. **Installer entfernen** (Wichtig!)
   ```bash
   rm install/index.php install/cli.php install/lib.php install/install.sql install/.installed
   ```

   > ⚠️ Den Unterordner **`install/migrations/` stehen lassen.** Daraus holt der
   > Adminbereich die Datenbank-Anpassungen späterer Updates. Ist er weg, zeigt
   > die Update-Seite einfach nichts an — ohne Fehlermeldung — und die
   > Anpassungen laufen nie.

### Ohne Browser: Installer auf der Kommandozeile

Wer SSH hat, spart sich die vier Formularseiten. Die Angaben kommen als JSON,
nicht als Argumente — Argumente stehen in `ps` und wären damit für jeden
anderen Benutzer der Maschine lesbar, samt beider Passwörter.

```bash
cat > setup.json <<'JSON'
{
  "db":    {"host":"localhost","name":"forum","user":"forum_user","pass":"…"},
  "forum": {"name":"Mein Forum","url":"https://forum.example.com"},
  "owner": {"user":"Chef","email":"chef@example.com","pass":"…"},
  "cleanup": true
}
JSON
chmod 600 setup.json
php install/cli.php --config=setup.json && rm setup.json
```

`"cleanup": true` räumt den Installer nach Erfolg selbst weg (und lässt
`migrations/` stehen). Über `"extra"` lassen sich zusätzliche Konstanten in die
`config.php` schreiben, etwa ein fest verankerter Mail-Absender:

```json
"extra": {
  "FORUM_MAIL_FROM": "noreply@example.com",
  "FORUM_MAIL_MAX_PER_HOUR": 50
}
```

Ist die Konstante gesetzt, gewinnt sie immer und das Feld im Adminbereich ist
gesperrt. Ohne sie bleibt alles wie gehabt. Gedacht für Installationen, die
jemand anderes betreibt als der Forum-Admin: dort hängt der Versand an fremder
Reputation.

---

## 🌐 Webserver-Konfiguration

### Welches Setup hast du?

Webserver-Setups gibt es typischerweise in drei Varianten:

| Setup | Beschreibung | Was greift? |
|---|---|---|
| **A) Hybrid** (nginx + Apache) | nginx vorne als Reverse-Proxy, Apache hinten für PHP. Häufig bei HestiaCP/cPanel als Default. | **beides:** nginx-Custom + `.htaccess` |
| **B) Pures nginx** (mit PHP-FPM) | nginx macht alles allein, kein Apache. | nur **nginx-Custom** (`.htaccess` wird ignoriert) |
| **C) Pures Apache** | Kein nginx, Apache macht alles. | nur **`.htaccess`** |

**Wie du dein Setup prüfst (per SSH):**
```bash
systemctl status nginx apache2 | grep "Active:"
```
- Beide aktiv → Setup A (Hybrid)
- Nur eines aktiv → Setup B oder C entsprechend

### Empfehlung

**Du solltest die nginx-Custom-Config einrichten — auch bei Setup A**, weil:

1. Bei Setup A oder B: nginx steht vorne, fängt Anfragen zuerst ab — schneller, sicherer
2. Bei Setup A bleibt `.htaccess` als zweite Schutzschicht aktiv (Defense in Depth)
3. Bei späterem Umstieg auf reines nginx (Setup B) muss nichts angepasst werden

Bei reinem Setup C (nur Apache): Die `.htaccess` im Forum-Root ist bereits vorbereitet, dort musst du nichts manuell konfigurieren — `mod_rewrite` muss nur aktiv sein.

---

### nginx-Konfiguration (für Setup A oder B)

#### Variante 1: Bei HestiaCP/ähnlichen Panels

Custom-Config-Datei anlegen (für SSL-Variante):

```bash
nano /home/[USER]/conf/web/deinedomain.tld/nginx.ssl.conf_custom
```

Folgenden Block einfügen:

```nginx
# ════════════════════════════════════════════════════════════════
# Forumix – nginx-Routing + Schutz
# ════════════════════════════════════════════════════════════════

# Sensible Einzeldateien blockieren
location = /config.php           { deny all; return 403; }
location = /bootstrap.php        { deny all; return 403; }
location = /install.sql          { deny all; return 403; }
location = /README.md            { deny all; return 403; }
location = /CHANGELOG.md         { deny all; return 403; }
location = /.htaccess            { deny all; return 403; }

# Verzeichnisse, die nie über HTTP erreichbar sein dürfen.
# `^~` statt regulärem Ausdruck: schlägt jeden Regex, egal in welcher
# Reihenfolge der Block eingesetzt wird.
location ^~ /lib/       { deny all; }
location ^~ /views/     { deny all; }
location ^~ /views_new/ { deny all; }   # Vorlagen im Klartext
location ^~ /pages/     { deny all; }
location ^~ /lang/      { deny all; }
location ^~ /cache/     { deny all; }   # übersetzte Templates = PHP
location ^~ /backup/    { deny all; }   # DB-Dumps
location ^~ /install/migrations/ { deny all; }
location = /install/install.sql  { deny all; }

autoindex off;

location ~ /\. { return 404; }
location ~ ~$  { return 404; }
location ~ \.(bak|sql|log|sqlite|sqlite3|db|tpl\.html|orig|dist|ini)$ { return 404; }

# Hochgeladenes wird ausgeliefert, aber nie ausgeführt.
# Verschachtelt, damit die Reihenfolge im File egal ist.
location ^~ /uploads/ {
    location ~* \.(php|phtml|phar|phps|php[0-9]|cgi|pl|py|rb|sh)$ {
        deny all;
    }
}

# Routing: /xyz.php → /index.php?_page=xyz
error_page 404 = @forum_router;

location @forum_router {
    rewrite ^/([a-zA-Z0-9_-]+)\.php$ /index.php?_page=$1 last;
    rewrite ^ /index.php last;
}

# Caching für statische Assets
location ~* \.(jpg|jpeg|png|gif|webp|svg|ico|css|js|woff|woff2)$ {
    expires 1w;
    add_header Cache-Control "public";
    access_log off;
}

# Security Headers
add_header X-Content-Type-Options "nosniff" always;
add_header X-Frame-Options "SAMEORIGIN" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;

# Upload-Größe
client_max_body_size 10M;
```

Auch für HTTP-Variante übernehmen:

```bash
cp /home/[USER]/conf/web/deinedomain.tld/nginx.ssl.conf_custom \
   /home/[USER]/conf/web/deinedomain.tld/nginx.conf_custom
```

Testen und Reload:

```bash
nginx -t && systemctl reload nginx
```

#### Variante 2: Eigener Server ohne Panel

Bei einem eigenen nginx ohne Panel: gleicher Block in den `server { }`-Block der Domain. Datei meist unter `/etc/nginx/sites-available/deinedomain.tld`.

#### Variante 3: Orbitalpanel

Block ins Feld **Domain → nginx-Direktiven**, nicht in die vhost-Datei — die wird bei jeder Domain-Änderung neu erzeugt.

- Der eingebaute **.htaccess-Konverter** übernimmt die vorhandene `.htaccess`; was er nicht sicher übersetzen kann, markiert er als `# [BITTE PRÜFEN]`
- Gespeichert wird erst, wenn `nginx -t` zustimmt

---

### Prüfen, ob es greift

Von außen messen, nicht auf der Platte nachsehen:

```bash
DOM=https://deine-domain.tld
# muss 403 sein
for u in /views_new/thread.tpl.html /modules/faq/install.sql /cache/templates/error.php \
         /backup/index.html /lib/db.php /lang/de.php /uploads/avatars/test.php; do
  printf '%-38s ' "$u"; curl -s -o /dev/null -w '%{http_code}\n' "$DOM$u"
done
# muss 200 bleiben
for u in / /login.php /members.php /assets/style.css "/api/quicksearch.php?q=te"; do
  printf '%-38s ' "$u"; curl -s -o /dev/null -w '%{http_code}\n' "$DOM$u"
done
```

Zwei Stolperfallen beim Auswerten:

| Beobachtung | Bedeutung |
|---|---|
| `200` bei einer *nicht existierenden* Datei | `try_files` liefert die Startseite, nicht die Datei — mit `%{size_download}` gegenprüfen |
| `404` statt `403` bei `/uploads/…/test.php` | kommt von PHP-FPM, die Anfrage war also **bei PHP**. Erst `403` heißt: vorher gestoppt |

---

### Apache-Konfiguration (für Setup A als Fallback, oder C als Hauptkonfig)

Eine vorbereitete `.htaccess` liegt im Forum-Root. Voraussetzung: `mod_rewrite` ist aktiv.

```bash
a2enmod rewrite
systemctl reload apache2
```

Inhalt der `.htaccess` im Root:
- Routing `/xyz.php` → `/index.php?_page=xyz`
- Schutz für `lib/`, `views/`, `pages/`, `lang/`, `cache/`
- PHP-Sperre in `uploads/`

Zusätzlich liegen eigene `.htaccess` in `lib/`, `views/`, `uploads/` und `backup/` — damit eine Apache-Installation ohne Zutun geschützt ist.

> ⚠️ **Unter reinem nginx tun diese Dateien nichts** — auch die in den Unterverzeichnissen, und ohne Warnung. Ein `Deny from all` in `backup/` sieht dann nach Schutz aus und ist keiner. Dafür ist der nginx-Block oben da.

**Bei Setup A:** Die `.htaccess` ist deine zweite Sicherheitsschicht — sie greift falls die nginx-Custom-Config mal fehlerhaft oder weg ist. Einfach drin lassen, sie schadet nicht.

**Bei Setup C (nur Apache):** Die `.htaccess` ist deine **einzige** Konfiguration. Nichts weiter zu tun, sofern `mod_rewrite` aktiv ist.

---

## 📁 Verzeichnisstruktur

```
forum/
├── 📄 index.php             ← Einstiegspunkt
├── 📄 bootstrap.php         ← Lädt Config, Libs, Self-Healing-Version
├── 📄 config.php            ← (vom Installer angelegt, DB-Zugang)
├── 📄 .htaccess             ← Apache-Routing
├── 📄 CHANGELOG.md          ← Versions-Historie
├── 📄 README.md             ← Diese Datei
│
├── 📂 admin/                ← Admin-Panel
├── 📂 api/                  ← AJAX-Endpunkte
│
├── 📂 lib/                  ← Bibliotheken
│   ├── auth.php             ← Login/Logout/Sessions
│   ├── db.php               ← PDO-Wrapper
│   ├── functions.php        ← Helpers (bbcode, e, ...)
│   ├── template.php         ← Template-Engine (Auto-Escape)
│   ├── view.php             ← View-Renderer
│   ├── approval.php         ← Freigabe-System
│   ├── user_deletion.php    ← DSGVO-Löschung
│   └── ...
│
├── 📂 pages/                ← Seiten-Controller
├── 📂 views/                ← Templates (PHP-basiert, klassisch)
├── 📂 views_new/            ← Templates (.tpl.html mit Auto-Escape)
├── 📂 modules/              ← Optionale Module
├── 📂 lang/                 ← Sprachdateien (de/en)
├── 📂 assets/               ← CSS, JS, Logo, Icons
│
├── 📂 cache/
│   └── templates/           ← Kompilierte Templates (auto-generiert)
│
├── 📂 modules/              ← Erweiterungen, im Admin ein-/ausschaltbar
│   ├── faq/                 ← Häufige Fragen
│   ├── shoutbox/            ← Mini-Chat auf der Startseite
│   ├── termine/             ← Terminliste
│   ├── downloads/           ← Dateiablage
│   ├── kontakt/             ← Kontaktformular
│   ├── loesung/             ← Antwort als Lösung markieren
│   └── banner/              ← Ankündigungsband
│
├── 📂 uploads/              ← User-Uploads
│   ├── avatars/
│   ├── attachments/
│   └── banners/
│
└── 📂 install/
    ├── index.php            ← Installer im Browser (nach Setup löschen)
    ├── cli.php              ← Installer auf der Kommandozeile (nach Setup löschen)
    ├── lib.php              ← gemeinsamer Kern beider Wege (nach Setup löschen)
    ├── install.sql          ← Schema (nach Setup löschen)
    └── 📂 migrations/       ← BLEIBT: DB-Anpassungen späterer Updates
```

### Module

Sieben Module liegen bei, **alle standardmäßig aus**. Einschalten unter
Adminbereich → *Module*; erst dann legt ein Modul seine Tabellen an. Wieder
Ausschalten löscht keine Daten.

Ein Modul ist ein Ordner unter `modules/` mit einer `module.php`. Es kann
eigene Seiten und Ansichten mitbringen, eigene Tabellen anlegen, sich über
Einhängepunkte (`layout_top`, `home_top`, `thread_top`, `post_footer`) ins
Layout hängen und einen Menüpunkt anmelden — ohne dass am Kern etwas geändert
werden muss.

Wie man eins baut, steht mit vollständigem Beispiel auf
[forumix.de/faq.html#module](https://forumix.de/faq.html#module).

---

## 🔄 Updates

### Versionierung

Das Forum nutzt [SemVer](https://semver.org) – die Versionsnummer sagt dir was sich geändert hat:

```
MAJOR . MINOR . PATCH
  0   .  18   .   0
```

- **PATCH** (`0.17.6 → 0.17.7`): Bug-Fixes, kleine Verbesserungen
- **MINOR** (`0.17.x → 0.18.0`): Neues Feature
- **MAJOR** (`0.x.x → 1.0.0`): Stabile Veröffentlichung / Breaking Changes

### Wie ein Update einspielen

Auf [forumix.de](https://forumix.de/#download) liegen zu jeder Version zwei
Archive: das **Vollpaket** für eine neue Installation und das **Update-Paket**
mit nur den geänderten Dateien.

**Mit dem Update-Paket** (empfohlen): Adminbereich → **Updates** → Archiv
hochladen. Es wird vor dem Einspielen geprüft, `config.php` und `uploads/`
bleiben unberührt. Kein FTP nötig.

**Von Hand:**

1. **Backup ziehen** (DB + `uploads/` + `config.php`)
2. **Neue Dateien hochladen** – alte überschreiben
   - **NICHT überschreiben:** `config.php`, `uploads/`
3. **Beliebige Seite im Forum aufrufen** – das war's

Beim ersten Request nach dem Upload macht sich das Forum selbst zurecht:
- Versionsnummer in der DB wird automatisch hochgesetzt (Self-Healing)
- Footer zeigt sofort die neue Version

Falls eine Migration nötig wäre (bei MINOR-Updates), liegt sie in `install/migrations/` und wird im Admin unter **Wartung → Updates** angeboten.

### Details pro Version → [CHANGELOG.md](CHANGELOG.md)

---

## 🖼️ Logo / Branding

- **Standard:** wird aus dem Forum-Namen erzeugt, es gibt keine Bilddatei dafür.
  Ein angehängtes „Forum" oder „Community" fällt im Schriftzug weg, die Farbe
  kommt aus der Akzentfarbe des Themes.
- **Eigenes Logo:** `assets/logo.svg` (oder .png/.jpg/.webp) ablegen → wird
  automatisch genommen. Ein **lokales SVG wird eingebettet**, nicht verlinkt —
  `currentColor` darin geht damit in hell und dunkel mit.
- **Über eine Adresse:** Adminbereich → **Design** → Logo-URL. Das wird als
  Bild eingebunden, dann greift `currentColor` nicht.
- **Theme-Farben:** im Admin unter **Design** anpassbar

---

## 🔐 Sicherheits-Tipps

### Nach der Installation
- ✅ Installer löschen (`install/index.php`, `cli.php`, `lib.php`, `install.sql`) — `install/migrations/` stehen lassen
- ✅ Owner-Account: starkes Passwort + 2FA aktivieren
- ✅ DB-User nur auf das eine Schema berechtigt
- ✅ `config.php` darf nicht öffentlich lesbar sein (siehe nginx-Block)
- ✅ `backup/` von außen prüfen, **bevor** das erste Backup läuft — der Dateiname ist nur ein Zeitstempel, also erratbar

### Regelmäßig
- 🔄 PHP-Version aktuell halten
- 🔄 Backups (DB + uploads/) verifizieren
- 📋 Mod-Log im Admin sichten

### Owner-Account
- Owner ≠ normaler Admin. Owner kann **nicht versehentlich entzogen** werden.
- Bei mehreren Owners: einer kann den anderen nur per Bestätigung degradieren.
- Wenn der Owner verlorengeht: SQL-Notlauf
  ```sql
  UPDATE users SET is_owner = 1, is_admin = 1 WHERE username = 'DEIN_NAME';
  ```

---

## 🐛 Problembehandlung

### „403 Forbidden" auf allen Seiten außer Startseite
nginx-Routing fehlt. Den Block aus dem Kapitel **Webserver-Konfiguration → nginx** einbauen.

### „404 auf jeder Seite"
Bei Apache: `mod_rewrite` nicht aktiv. Bei nginx: error_page-Routing fehlt.

### Footer zeigt alte Version
Falls die Self-Healing-Logik nicht greift (z.B. uralter Stand): einmalig per SQL setzen
```sql
UPDATE settings SET `value` = '0.18.0 Beta' WHERE `key` = 'forum_version';
```
Ab dann pflegt sich's selbst.

### Forum lässt sich nicht installieren
- PHP-Version < 8.1? → updaten
- Schreibrechte auf `config.php`? → `chmod 664` oder per Owner-User schreiben lassen
- DB-Verbindung schlägt fehl? → Host meist `localhost`, manchmal `127.0.0.1`

### „Install bereits vorhanden"
`config.php` existiert schon. Entweder benutzen (Forum ist installiert), oder Datei löschen und neu installieren.

---

## 🧪 Mitentwickeln

Im Repository liegt ein Prüflauf ohne Fremdpakete — über Rechte, Papierkorb,
Suche, Kontolöschung, Zwei-Faktor und die Datenbank-Anpassungen. Er gehört
nicht ins Auslieferungspaket, wohl aber ins Repository: wer den Code ändert,
soll sehen können, ob er etwas kaputt gemacht hat.

```sh
# Eine eigene Datenbank, nie die des laufenden Forums:
FORUM_TEST_DB=forumix_test php tools/pruefen.php

# Nur eine Gruppe:
php tools/pruefen.php suche
```

Ein Teil der Prüfungen braucht eine zweite, vollständige Installation als
Prüfstand — etwa um zu sehen, wie sich das Forum verhält, wenn eine
Datenbank-Anpassung noch nicht gefahren ist. Ohne die Angabe überspringen sie
sich und sagen es:

```sh
FORUM_PRUEFSTAND=/pfad/zur/instanz php tools/pruefen.php
```

Einzelheiten und wie eine neue Prüfung aussieht:
[`tools/pruefungen/README.md`](tools/pruefungen/README.md).

## 📝 Lizenz

**GNU Affero General Public License v3.0** — vollständiger Text in [LICENSE](LICENSE).

Kurzfassung, ohne juristischen Anspruch:

- Du darfst die Software benutzen, ändern und weitergeben — auch geschäftlich.
- Gibst du eine geänderte Fassung weiter **oder betreibst sie als Dienst im Netz**, musst du deinen Quelltext unter derselben Lizenz herausgeben. Genau das unterscheidet die AGPL von der GPL: bei Web-Software greift die GPL nicht, weil Betreiben keine Weitergabe ist.
- Wer das Forum nur installiert und betreibt, ohne etwas zu ändern, muss gar nichts tun.

> **Pflicht für Betreiber geänderter Fassungen (§ 13):** Nutzer müssen den Quelltext angeboten bekommen. Im Fußbereich steht dafür das Forumix-Zeichen, und das ist ein Link — ohne eigene Einstellung zeigt er auf forumix.de, wo der Quelltext liegt. Wer den Code **ändert**, trägt unter Adminbereich → **Design** → *Quelltext-URL* sein eigenes Repository ein; dann zeigt das Zeichen dorthin.

### Mitgelieferte Fremdbestandteile

| Bestandteil | Lizenz |
|---|---|
| CodeMirror 5.65.16 (`assets/codemirror/`) | MIT — [LICENSE](assets/codemirror/LICENSE) |
| Inter, Open Sans, Lato, Nunito, Poppins, Chakra Petch (`assets/fonts/`) | SIL Open Font License 1.1 |
| Roboto (`assets/fonts/`) | Apache License 2.0 |

Alle drei sind mit der AGPLv3 verträglich. Einzelheiten in [assets/fonts/LICENSE.txt](assets/fonts/LICENSE.txt).

---

## 🙏 Danksagung

Geschrieben von **Jürgen Garske** und seinem KI-Sparringspartner. Inspiration von alten phpBB-/WBB-Forengenerationen, aber ohne deren Ballast.
