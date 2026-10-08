# Gemini code review — original i nezavisan indeks

**Izvor:** korisnički priložen `Pasted text(20261008-220925).txt` (datum je preuzet iz naziva fajla).
**Sačuvani original:** [GEMINI-REVIEW-2026-10-08.md](GEMINI-REVIEW-2026-10-08.md) — **10 nalaza**, **280 redova**, **10.787 UTF-8 bajtova**.
**SHA-256 originala:** `c39664d5d28c11b8d1500bf104def6fdfb2dbb4f456206673f4229b231954802`.

**Status:** ARCHIVED / PARTIALLY FACT-CHECKED / NOT MERGED / NOT IMPLEMENTED.

Original je sačuvan **bez izmena**. Tabela ispod je zasebna početna provera tvrdnji u izvornom kodu, a ne odobrenje rešenja. Statusi se odnose na trenutno pregledani `main`; pri kasnijoj konsolidaciji svaku stavku ponovo proveriti na tadašnjem commitu. Osporeni nalazi ostaju u indeksu radi sledljivosti.

## Prioriteti iz originalnog izveštaja

| 🟥 Visok | 🟨 Srednji | 🟩 Nizak | Ukupno |
|---:|---:|---:|---:|
| 4 | 4 | 2 | **10** |

## Nalazi i preliminarni fact-check

| ID | Originalni prioritet / uloga | Naziv | Preliminarni status | Kratak razlog |
|---|---|---|---|---|
| `GEM-01` | 🟥 Visok - Security Architect | Ranjivost na Arbitrary File Read / Path Traversal u modulima za preuzimanje (`DownloadFile.php`) | **Osporeno kodom** | `DownloadFile` prima server-owned `artifactPath` kroz konstruktor; `?file=` nije deo rute/poziva čitanja. |
| `GEM-02` | 🟥 Visok - Security Architect | Neodgovarajuća provera CSRF tokena i tajming ranjivost (`infrastructure/Session/Csrf.php`) | **Osporeno kodom** | `Csrf::matches()` već koristi `hash_equals()`; `PhpSession` postavlja `HttpOnly`, `SameSite=Lax` i konfigurabilan `Secure`. |
| `GEM-03` | 🟥 Visok - Performance & Reliability Engineer | Concurrency usko grlo i rizik od "Database Locked" u SQLite drajveru (`SqliteConnection.php`) | **Delimično tačno** | WAL nije eksplicitno uključen, ali `PRAGMA busy_timeout = 5000` već postoji. WAL je odluka uz concurrency/backup test. |
| `GEM-04` | 🟥 Visok - Security Architect & Performance Engineer | Nedostatak Rate Limiting mehanizma na autentičnim i osetljivim rutama (`Login.php`, `Register.php`) | **Relevantno, uslovno** | Aplikacija nema in-app limiter; README traži edge/reverse-proxy rate limit pre javnog deployment-a. |
| `GEM-05` | 🟨 Srednji - Principal Software Architect & Staff Engineer | Kršenje Dependency Inversion principa i direktno sprezanje sa konkretnim klasama | **Osporeno kodom** | `Accounts` prima `PDO`, `ActivityLog` prima `PDO` i PSR-20 `ClockInterface`; nije tačno da sami instanciraju navedeni DB/clock. |
| `GEM-06` | 🟨 Srednji - Performance & Reliability Engineer | Nekoordinisano učitavanje kompletnih skupova podataka u memoriju (`DailyReports.php`, `Statistics.php`) | **Osporeno kodom** | `DailyReports` radi `SUM/CASE` i `GROUP BY` u SQL-u; `fetchAll()` učitava najviše 30 agregiranih dana. `Statistics` paginira. |
| `GEM-07` | 🟨 Srednji - Security Architect | Propust u regeneraciji ID-a sesije prilikom prijave i izlogovanja (`AccountLifecycle.php`) | **Osporeno kodom** | `PhpSession::regenerate()` koristi `session_regenerate_id(true)`, a `destroy()` invalidira session cookie. |
| `GEM-08` | 🟨 Srednji - OSS Technology Evaluator | Reinvencija točka: Prilagođeni HTTP Runtime, Router i Templating engine | **Dizajnerski predlog** | Procena sopstvenog Request/Response/Router/Renderer sloja zahteva upoređivanje koristi i troška zamene; Vanilla PHP je zahtev zadatka. |
| `GEM-09` | 🟩 Nizak - Principal Platform & Infrastructure Architect | Prilagođeni Migrator bez podrške za rollback i transakcionu bezbednost (`Migrator.php`) | **Osporeno kodom** | `Migrator::apply()` već pokreće SQL migraciju i ledger upis kroz `Transaction::run()`; checksum i concurrency kontrola ostaju druga pitanja. |
| `GEM-10` | 🟩 Nizak - Principal Software Architect | Mešanje HTML prezentacije i aplikativne logike u bootstrap i HTTP sloju | **Dizajnerski predlog** | HTTP adapteri i `Renderer` već su fizički razdvojeni; zahtev za dodatnim DTO/view slojem nije dokazan kao neophodan. |

## Smernice za kasniju konsolidaciju

- [ ] Uključiti sve izvorne `GEM-01`–`GEM-10` reference uz postojeće OOP, ClaudeCode i ChatGPT LLD nalaze.
- [ ] Svaku tvrdnju nezavisno ponoviti na konkretnom commitu, uz dokaz iz koda i regresioni test gde je relevantno.
- [ ] Ostaviti kao **odbijene / osporene** tvrdnje koje su već rešene u kodu (npr. `hash_equals`, session ID rotacija, transakcije), osim ako se pojavi nov dokaz.
- [ ] Uključiti samo opravdane preostale zahteve (npr. stvarni rate limiting, SQLite WAL odluka) u jednu kanonsku listu bez duplikata.
- [ ] Sačuvati korisnikov **MOI** kao jedini autoritet za vizuelni PHP stil; ne uvoditi nepotrebne SOLID slojeve.

## Ostali nezavisni review-i

- [ChatGPT OOP review — 18 TODO stavki](../OOP-ARCHITECTURE-REMEDIATION-TODO.md)
- [ClaudeCode general review — 62 nalaza](CLAUDE-CODE-REVIEW-INDEX.md)
- [ChatGPT LLD review — 26 nalaza](LLD-CODE-REVIEW-2026-10-08.md)
- [ClaudeCode LLD review — 26 nalaza](CLAUDE-CODE-LLD-REVIEW-INDEX.md)

**Ukupno pre deduplikacije:** 18 + 62 + 26 + 26 + 10 = **142 izvorne stavke** iz pet review-a. Ovaj broj nije broj jedinstvenih problema.
