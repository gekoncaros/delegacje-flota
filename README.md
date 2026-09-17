# Delegacje + Flota

System obsługi delegacji służbowych i floty z interfejsem WWW oraz PWA.

## Architektura
- jedna aplikacja PHP 8.x + MySQL/MariaDB
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
