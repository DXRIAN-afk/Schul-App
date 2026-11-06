# Schul-App

## Login-System

Die App ist jetzt nur nach Anmeldung erreichbar.

- **Registrieren:** `/register.php` · **Anmelden:** `/login.php` · **Abmelden:** `/logout.php`
- **Admin:** Mit Benutzername `Admin` und dem Passwort aus `config.php` anmelden.
  Im Admin-Fenster (`/admin.php`) siehst du, wie viele Nutzer registriert sind,
  wer gerade online ist und wie viele sich heute / in den letzten 7 Tagen angemeldet haben.
  In der App öffnet der Link „Admin“ oben rechts das Fenster als kleines Popup.

### Mit dartx account anmelden

Auf der Login- und der Registrierungsseite gibt es den Knopf **«Mit dartx account anmelden»**
(gemeinsames Konto von `account.da-rtx.ch`, wie bei Hexa Chat).

- Wer sich zum ersten Mal mit dartx account anmeldet, bekommt automatisch ein Schul-App-Konto.
- Bestehende Schul-App-Konten lassen sich oben rechts über **«Mit dartx verbinden»** verknüpfen.
- Der Administrator von dartx account ist auch in der Schul-App Administrator.
- Alle 5 Minuten fragt die Schul-App bei dartx account nach: Wird ein Konto dort **gesperrt**,
  gelöscht oder der Zugriff entfernt, wird man auch hier abgemeldet.
- Einstellungen in `config.php` (`KONTO_URL`, `KONTO_CLIENT_ID`). `KONTO_URL` leer = Knopf ausgeblendet.
- Bei dartx account muss in `lib/config.php` unter `schul-app` die Rücksprung-Adresse
  `https://schul-app.da-rtx.ch/sso.php` eingetragen sein (andere Domain → entsprechend anpassen).

### Einrichtung

1. Webserver mit **PHP 8+** und **Apache** (`mod_rewrite` aktiv, `.htaccess` erlaubt).
2. In `config.php` das Admin-Passwort und ggf. die Datenbank einstellen.
   Standard ist **SQLite** (keine Einrichtung nötig, Datei `data/schulapp.sqlite`).
   Für MySQL `DB_DRIVER` auf `'mysql'` setzen und Zugangsdaten eintragen.
3. Der Ordner `data/` muss für PHP beschreibbar sein. Die Tabellen werden automatisch angelegt.
