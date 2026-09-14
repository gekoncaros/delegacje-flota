# Uruchomienie produkcyjne — Delegacje + Flota

## Wymagania

- PHP 8.1+ z rozszerzeniami `pdo_mysql`, `mbstring`, `fileinfo`
- MySQL/MariaDB
- HTTPS
- katalog `public/` jako document root
- prywatne repozytorium GitHub

## Kroki

1. Pobierz kod z prywatnego repozytorium na serwer.
2. Skopiuj `.env.example` do `.env` i ustaw:
   - `APP_MODE=production`
   - `APP_URL=https://delegacje.callit.pl`
   - dane bazy `DB_*`
   - `SESSION_SECURE=true`
3. Utwórz pustą bazę danych i użytkownika DB z minimalnymi wymaganymi uprawnieniami do tej bazy.
4. Wykonaj migracje SQL kolejno `001`–`007` po wcześniejszym backupie.
5. Uruchom `php scripts/preflight.php`.
6. Utwórz pierwszego Super Admina:
   `php scripts/create-super-admin.php admin@firma.pl "Imię Nazwisko"`
   Hasło zostanie pobrane bez wyświetlania go w historii poleceń.
7. Skonfiguruj serwer WWW tak, aby publicznie dostępny był wyłącznie katalog `public/`.
8. Wejdź na `https://delegacje.callit.pl/login.php`.
9. Po zalogowaniu otwórz `https://delegacje.callit.pl/mobile.php`.
10. Na iOS: Safari → Udostępnij → Dodaj do ekranu początkowego. Na Androidzie: Chrome → menu → Zainstaluj aplikację / Dodaj do ekranu głównego.

## Kontrola przed udostępnieniem pracownikom

- repo pozostaje private
- `.env` nigdy nie trafia do Git
- HTTPS działa bez ostrzeżeń
- ciasteczko sesyjne ma Secure + HttpOnly + SameSite=Lax
- migracje 005–007 wykonane
- Super Admin loguje się
- utworzone role i przełożeni
- test: pracownik tworzy delegację → przełożony akceptuje → pracownik start/koniec → księgowość widzi zakończoną delegację
- katalog uploadów nie pozwala wykonywać skryptów
- backup bazy działa

## Ważne

GitHub nie jest środowiskiem produkcyjnym tej aplikacji. Prywatne repo służy do przechowywania kodu. Aplikacja PWA powinna być uruchomiona z własnej domeny/hostingu, np. `delegacje.callit.pl`.
