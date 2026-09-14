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
