# Aynku Forum

Ein schlankes, modernes Community-Forum in nativem PHP. **Keine externen Abhängigkeiten, kein Composer, kein Framework.**

```
Aktuelle Version: 0.18.55 Beta
Live unter:       https://www.aynku.de
```

> Versionsgeschichte → [CHANGELOG.md](CHANGELOG.md)

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

5. **Installer freigeben** – eine leere Datei anlegen:
   ```bash
   touch install/.unlock          # oder install/UNLOCK.txt, falls der
                                  # FTP-Client Punkt-Dateien versteckt
   ```
   Ohne diese Datei antwortet der Installer mit 403. Grund: zwischen dem
   Hochladen und dem Einrichten steht ein unfertiges Forum offen im Netz —
   wer dann als Erster `/install/` aufruft, kann es auf seine eigene Datenbank
   einrichten und sich selbst als Inhaber anlegen. Die Datei ist kein
   Geheimnis, sie beweist nur Dateizugriff.

6. **Installer starten**: `https://deine-domain.tld/install/`

   Der Wizard führt durch 4 Schritte:
   - System-Check (PHP-Version, Extensions)
   - Datenbank-Verbindung
   - Admin-Account anlegen → wird automatisch zum **Owner**
   - Fertig

7. **`install/`-Ordner löschen** (Wichtig!)
   ```bash
   rm -rf install/
   ```

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
# Aynku Forum – nginx-Routing + Schutz
# ════════════════════════════════════════════════════════════════

# Sensible Einzeldateien blockieren
location = /config.php           { deny all; return 403; }
location = /bootstrap.php        { deny all; return 403; }
location = /install.sql          { deny all; return 403; }
location = /README.md            { deny all; return 403; }
location = /CHANGELOG.md         { deny all; return 403; }
location = /.htaccess            { deny all; return 403; }

# Verzeichnisse, die nie über HTTP erreichbar sein dürfen.
#
# Warum `^~` und kein regulärer Ausdruck: ein `^~`-Präfix schlägt bei nginx
# JEDEN regulären Ausdruck — unabhängig von der Reihenfolge im File. Mit
# `location ~ ^/(lib|cache)/` hinge die Wirkung davon ab, ob dieser Block
# vor oder nach der `location ~ \.php$` des Panels landet. Und `cache/`
# enthält übersetzte Templates als lauffähiges PHP, `backup/` die DB-Dumps.
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

# Hochgeladenes wird ausgeliefert, aber niemals ausgeführt.
#
# Verschachtelt statt als einzelner regulärer Ausdruck: das äussere `^~`
# fängt alles unter /uploads/ ein und schaltet damit die regulären
# Ausdrücke der Server-Ebene ab — die innere Regel gilt nur noch hier.
# Bilder gehen weiter raus, PHP nie, und die Reihenfolge im File ist egal.
# (Die flache Variante `location ~* ^/uploads/.*\.php$` funktioniert auch,
# aber nur solange sie VOR der allgemeinen `location ~ \.php$` steht.)
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

Nicht in die vhost-Datei schreiben — die wird bei jeder Änderung an der Domain neu erzeugt und überschreibt Handarbeit. Der Block gehört ins Feld **Domain → nginx-Direktiven**. Von dort setzt ihn die Vorlage an genau die Stelle oberhalb der PHP-`location`, an der die Reihenfolge stimmt.

Das Panel bringt einen **.htaccess-Konverter** mit: die vorhandene `.htaccess` einlesen lassen, das Ergebnis übernehmen und um den Block oben ergänzen. Was der Konverter nicht sicher übersetzen kann, markiert er als `# [BITTE PRÜFEN]` statt still etwas Falsches zu erzeugen.

Gespeichert wird erst, wenn `nginx -t` die Konfiguration annimmt — eine kaputte Direktive kann die Domain nicht abschießen.

---

### Prüfen, ob es greift

Nach dem Einbau von außen messen, nicht auf der Platte nachsehen. Erwartet wird durchgehend `403`:

```bash
for u in /views_new/thread.tpl.html /modules/faq/install.sql \
         /cache/templates/error.php /backup/index.html \
         /lib/db.php /lang/de.php /uploads/avatars/test.php; do
  printf '%-38s ' "$u"
  curl -s -o /dev/null -w '%{http_code}\n' "https://deine-domain.tld$u"
done
```

Und die Gegenprobe — das hier muss weiter `200` liefern, sonst ist zu viel zu:

```bash
for u in / /login.php /members.php /assets/style.css /api/quicksearch.php?q=te; do
  printf '%-38s ' "$u"
  curl -s -o /dev/null -w '%{http_code}\n' "https://deine-domain.tld$u"
done
```

> **Vorsicht bei der Auswertung:** Eine nicht existierende Datei liefert wegen `try_files` ebenfalls `200` — nämlich die Startseite, nicht die Datei. Wer `/backup/irgendwas.sql` testet und `200` sieht, hat nicht zwingend ein Leck gefunden. Immer mit `%{size_download}` und `%{content_type}` gegenprüfen: 17 KB `text/html` ist die Startseite.
>
> Umgekehrt ist bei `/uploads/…/test.php` der Wechsel von `404` auf `403` der eigentliche Beweis: der nackte `404` kommt von PHP-FPM („Skript nicht da") — die Anfrage war also bei PHP. Erst `403` heißt, sie endet vorher.

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

Zusätzlich liegen eigene `.htaccess` in `lib/`, `views/`, `uploads/` und `backup/`. Die bleiben bewusst im Paket, damit eine Apache-Installation ohne Zutun geschützt ist.

> **Unter reinem nginx (Setup B) tun diese Dateien nichts.** nginx liest keine `.htaccess` — weder im Root noch in den Unterverzeichnissen, und es gibt auch keine Warnung. Ein `Deny from all` in `backup/` sieht nach Schutz aus und ist unter nginx wirkungslos. Wer von Apache umzieht, muss jede dieser Dateien im nginx-Block nachbilden; genau dafür ist der Block oben gedacht.

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
├── 📂 uploads/              ← User-Uploads
│   ├── avatars/
│   ├── attachments/
│   └── banners/
│
└── 📂 install/              ← Installer (nach Setup löschen!)
    ├── index.php
    └── install.sql
```

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

1. **Backup ziehen** (DB + `uploads/` + `config.php`)
2. **Neue Dateien hochladen** – alte überschreiben
   - **NICHT überschreiben:** `config.php`, `uploads/`
3. **Beliebige Seite im Forum aufrufen** – das war's

Beim ersten Request nach dem Upload macht sich das Forum selbst zurecht:
- Versionsnummer in der DB wird automatisch hochgesetzt (Self-Healing)
- Footer zeigt sofort die neue Version

Falls eine Migration nötig wäre (bei MINOR-Updates), liegt sie in `install/migrations/` und wird im Admin unter **System → Updates** angeboten.

### Details pro Version → [CHANGELOG.md](CHANGELOG.md)

---

## 🖼️ Logo / Branding

- **Standard:** `assets/logo_default.svg`
- **Eigenes Logo:** als `assets/logo_custom.svg` (oder .png) hochladen → wird automatisch verwendet
- **Theme-Farben:** im Admin unter **Design** anpassbar

---

## 🔐 Sicherheits-Tipps

### Nach der Installation
- ✅ `install/`-Ordner löschen
- ✅ Owner-Account: starkes Passwort + 2FA aktivieren
- ✅ DB-User nur auf das eine Schema berechtigt
- ✅ `config.php` darf nicht öffentlich lesbar sein (siehe nginx-Block)
- ✅ `backup/` von außen prüfen, **bevor** das erste Backup erzeugt wird — der Dateiname enthält nur einen Zeitstempel und ist damit erratbar; ein erreichbarer Dump ist die komplette Datenbank inklusive Passwort-Hashes und privater Nachrichten

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

## 📝 Lizenz

Privates Projekt. Bei Interesse an Wiederverwendung: Kontakt aufnehmen.

---

## 🙏 Danksagung

Geschrieben von **Jürgen Garske** und seinem KI-Sparringspartner. Inspiration von alten phpBB-/WBB-Forengenerationen, aber ohne deren Ballast.
