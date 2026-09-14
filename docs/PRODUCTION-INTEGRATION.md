# Integracja z produkcją

Aplikacja posiada dwa tryby:

- APP_MODE=demo — dane testowe w sesji PHP,
- APP_MODE=production — dane przez PDO i mapowanie istniejącej bazy.

## Bezpieczna kolejność

1. Wykonaj backup produkcji.
2. Skopiuj aktualny kod produkcyjny do osobnej gałęzi Git.
3. Nie commituj .env, haseł, kluczy ani dokumentów użytkowników.
4. Na kopii bazy uruchom skrypt scripts/inspect-schema.php.
5. Na podstawie schema.json ustaw mapowanie tabel i kolumn w .env.
6. Przetestuj odczyt delegacji i pojazdów.
7. Dopiero potem ustaw APP_MODE=production.

## Inspekcja schematu

Uruchom z CLI:

php scripts/inspect-schema.php > schema.json

Skrypt eksportuje wyłącznie nazwy tabel, kolumn i ich typy. Nie eksportuje rekordów ani danych osobowych.

## Mapowanie

Przykład:

APP_MODE=production
DB_DELEGATIONS_TABLE=business_trips
DB_DELEGATION_USER_ID_COLUMN=employee_id
DB_DELEGATION_DESTINATION_COLUMN=destination
DB_DELEGATION_PURPOSE_COLUMN=purpose
DB_DELEGATION_DATE_FROM_COLUMN=date_from
DB_DELEGATION_DATE_TO_COLUMN=date_to
DB_DELEGATION_STATUS_COLUMN=status

## Ważne

Warstwa PDO używa prepared statements dla danych wejściowych. Nazwy tabel i kolumn są dopuszczane tylko, jeśli składają się z liter, cyfr i znaku podkreślenia.

Aktualny moduł Flota zakłada strukturę nowych tabel z migracji projektu. Jeśli produkcja ma już własną tabelę pojazdów, mapowanie rozszerzymy po analizie schematu.
