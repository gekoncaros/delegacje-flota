# Paczka wdrożeniowa — Delegacje + Flota

Celem katalogu `deploy` jest przygotowanie systemu do powtarzalnych wdrożeń dla kolejnych firm bez kopiowania danych produkcyjnych.

## Minimalne wymagania

- PHP 8.1+
- MySQL 8 / MariaDB 10.6+
- rozszerzenia PHP: PDO, pdo_mysql, json, fileinfo, mbstring
- HTTPS
- zapisywalny katalog `storage` poza publicznym document rootem

## Bezpieczna kolejność wdrożenia

1. Utwórz nową bazę danych i użytkownika DB o ograniczonych uprawnieniach.
2. Skopiuj `.env.example` do `.env` poza repozytorium i uzupełnij dane środowiska.
3. Uruchom `php scripts/preflight.php`.
4. Zaimportuj migracje po kolei dopiero po weryfikacji istniejącego schematu.
5. Skonfiguruj nazwę firmy, branding, role i routing e-maili w Super Adminie.
6. Sekrety SMTP/API zapisuj wyłącznie po stronie serwera — nigdy w GitHub ani PWA.
7. Włącz `APP_MODE=production` dopiero po testach na kopii bazy.

## Co można przenosić między firmami

Eksport konfiguracji Super Admina może zawierać:
- dane firmy,
- nazwę aplikacji i branding,
- role użytkowników,
- routing e-maili,
- ustawienia workflow,
- treści RODO i regulaminu.

Nie należy przenosić:
- haseł i tokenów,
- danych delegacji,
- dokumentów i paragonów,
- produkcyjnych danych osobowych,
- dumpów bazy danych.

## Kontrola przed publikacją

- HTTPS działa,
- `.env` nie jest dostępny z WWW,
- katalog `storage/expenses` znajduje się poza `public/` i nie jest dostępny bez autoryzowanego endpointu,
- backup bazy został wykonany,
- role i uprawnienia przetestowane,
- e-maile testowe trafiają do właściwych odbiorców,
- RODO i regulamin zostały zaakceptowane przez firmę wdrażającą.
