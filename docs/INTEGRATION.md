# Integracja z istniejącym systemem

Repozytorium zawiera obecnie fundament nowej warstwy PWA.

## Przed połączeniem z produkcją
1. Zaimportować aktualny kod aplikacji działającej na serwerze.
2. Wykonać eksport samego schematu bazy danych bez danych osobowych.
3. Zmapować istniejące identyfikatory użytkowników, delegacji i ról.
4. Dopiero wtedy dostosować migrację `001_pwa_fleet_foundation.sql`.
5. Wdrożenie wykonać najpierw na środowisku testowym.

## Nie wykonywać
- nie uruchamiać migracji na produkcji przed weryfikacją schematu,
- nie kopiować produkcyjnego pliku .env do GitHub,
- nie commitować haseł SMTP, DB, API ani dumpów danych osobowych.

## PWA
Do istniejącego layoutu należy dołączyć:
- `<link rel="manifest" href="/manifest.webmanifest">`
- `/assets/js/pwa.js`
- meta tagi iOS
- ikonę aplikacji po przygotowaniu brandingu.
