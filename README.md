# Delegacje + Flota

System obsługi delegacji służbowych i floty z interfejsem WWW oraz PWA.

## Architektura
- jedna aplikacja PHP 8.x + MySQL/MariaDB
- `public/` jest jedynym document rootem i punktem wejścia aplikacji
- `src/` zawiera usługi domenowe, a `database/migrations/` wersjonowane zmiany schematu
- PWA dla pracowników, kierowców i przełożonych
- panel WWW dla administracji i księgowości
- modułowa Flota
- branding i konfiguracja per wdrożenie
- bez wymogu Node.js na produkcji

## Etap 1 — fundament PWA
- manifest aplikacji
- service worker
- ekran offline
- instalacja na Android/iOS/Windows
- bezpieczna polityka cache
- struktura pod Flotę i powiadomienia

## Bezpieczeństwo
Repozytorium nie powinno zawierać plików .env, haseł, kluczy API, dumpów produkcyjnej bazy ani danych osobowych.

## Quality workflow

Zmiany funkcjonalne wykonujemy na osobnych gałęziach i łączymy przez Pull Request.
Przed merge należy sprawdzić co najmniej:
- brak sekretów i danych osobowych w repozytorium,
- podstawowy smoke test na telefonie i desktopie,
- instalację PWA i zachowanie offline,
- formularze delegacji, floty, kosztów, akceptacji i Super Admin,
- regresję istniejących funkcji po zmianach.

### Audyt 2026-09-18
Rozpoczęto systematyczny hardening aplikacji: service worker, PWA manifest, QR quick-start, walidacja wejścia oraz eliminacja zależności runtime od zewnętrznego CDN.

## Zintegrowany fundament produkcyjny

Kanoniczna aplikacja znajduje się w `public/`, `src/`, `config/` i `database/`. Katalog `server/` jest starszym szkieletem referencyjnym i nie powinien być uruchamiany równolegle. Aktualny backend zapewnia:
- sesje HttpOnly/Secure/SameSite,
- wspólne nagłówki CSP, HSTS (dla HTTPS), anty-framing i ograniczenie uprawnień przeglądarki,
- limit bezczynności i maksymalny czas sesji,
- logowanie z `password_hash/password_verify`,
- samodzielną zmianę hasła z odnowieniem identyfikatora sesji,
- RBAC dla pracownika, przełożonego, księgowości, floty i Super Admina,
- API delegacji, decyzji przełożonego, floty, rezerwacji, usterek i kosztów,
- administrację pojazdami, przypisaniami, przebiegiem oraz terminami OC i badań,
- dokumenty kosztów poza document rootem, pobierane po autoryzacji,
- serwerowy audit log z podglądem dla Super Admina oraz osobnym rejestrem prób logowania,
- zarządzanie użytkownikami, rolami, przełożonymi i aktywnością kont,
- przygotowane migracje bazy `001`–`009`,
- konfigurację poza repozytorium.

Instalacja i kontrola przedwdrożeniowa są opisane w `deploy/PRODUCTION-RUNBOOK.md`. Nie należy wykonywać migracji na produkcji bez backupu i wcześniejszego testu na oddzielnej bazie.
