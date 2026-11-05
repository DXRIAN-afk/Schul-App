# Schul-App

## Login-System

Die App ist jetzt nur nach Anmeldung erreichbar.

- **Registrieren:** `/register.php` · **Anmelden:** `/login.php` · **Abmelden:** `/logout.php`
- **Admin:** Mit Benutzername `Admin` und dem Passwort aus `config.php` anmelden.
  Im Admin-Fenster (`/admin.php`) siehst du, wie viele Nutzer registriert sind,
  wer gerade online ist und wie viele sich heute / in den letzten 7 Tagen angemeldet haben.
  In der App öffnet der Link „Admin“ oben rechts das Fenster als kleines Popup.

### Einrichtung

1. Webserver mit **PHP 8+** und **Apache** (`mod_rewrite` aktiv, `.htaccess` erlaubt).
2. In `config.php` das Admin-Passwort und ggf. die Datenbank einstellen.
   Standard ist **SQLite** (keine Einrichtung nötig, Datei `data/schulapp.sqlite`).
   Für MySQL `DB_DRIVER` auf `'mysql'` setzen und Zugangsdaten eintragen.
3. Der Ordner `data/` muss für PHP beschreibbar sein. Die Tabellen werden automatisch angelegt.
