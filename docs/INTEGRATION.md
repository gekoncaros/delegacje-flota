# Integracja z istniejącym systemem

Repozytorium zawiera zintegrowaną aplikację PWA oraz backend PHP/MySQL. Kanoniczny runtime znajduje się w `public/` i `src/`.

## Przed połączeniem z produkcją
1. Zaimportować aktualny kod aplikacji działającej na serwerze.
2. Wykonać eksport samego schematu bazy danych bez danych osobowych.
3. Na osobnej bazie testowej uruchomić migracje `001`–`009`.
4. Przygotować kontrolowany import istniejących identyfikatorów użytkowników, delegacji i ról do kanonicznych tabel.
5. Wdrożenie wykonać najpierw na środowisku testowym i przejść pełny scenariusz ról.

## Nie wykonywać
- nie uruchamiać migracji na produkcji przed weryfikacją schematu,
- nie kopiować produkcyjnego pliku .env do GitHub,
- nie commitować haseł SMTP, DB, API ani dumpów danych osobowych.

## PWA
Manifest, service worker, ekran offline i ikona są podłączone w katalogu `public/`. Service worker nie zapisuje w cache stron uwierzytelnionych ani odpowiedzi API.
