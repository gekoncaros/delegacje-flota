# Roadmap PWA

## Etap 1
- manifest i service worker
- instalowalność
- offline fallback
- bezpieczna polityka cache

## Etap 2
- mobilny dashboard
- rozpoczęcie/zakończenie delegacji
- aparat i upload dokumentów

## Etap 3
- pojazdy
- przejazdy
- tankowania

## Etap 4
- QR pojazdów
- szkody/usterki
- dokumenty pojazdów

## Zrealizowana integracja backendu
- logowanie, sesje i RBAC
- delegacje: utworzenie, akceptacja, start, zakończenie i księgowość
- flota: lista pojazdów, rezerwacje i lifecycle usterek
- administracja floty: dane pojazdu, przypisanie pracownika oraz terminy OC i badań
- koszty: dokumenty za autoryzacją, akceptacja i wypłata
- Super Admin: zaproszenia, role, przełożeni, aktywność kont i podgląd zdarzeń bezpieczeństwa
- serwerowy audit log dla operacji modyfikujących dane i prób logowania
- profil użytkownika: bezpieczna zmiana hasła i odnowienie sesji

## Etap 5
- centrum powiadomień
- Web Push

## Warunek integracji
Przed modyfikacją produkcji należy zaimportować aktualny kod aplikacji i schemat bazy.
Migracja 001 jest szkieletem i wymaga dopasowania do istniejących tabel użytkowników i delegacji.
