# Low-Level Design (LLD) — nezavisan code & design review

**Projekat:** Fast Corporation PHP Task
**Pregledana verzija:** `83b7612` (`main`), 2026-10-08
**Autor review-a:** ChatGPT — višedisciplinarna LLD procena
**Status:** ARCHIVED / NOT MERGED / NOT IMPLEMENTED

**Opseg:** Principal Software Architect, Staff Engineer, Principal Platform & Infrastructure Architect, Senior Software Engineer, Security Architect, Performance & Reliability Engineer, AI/Agent Infrastructure Architect, OSS Technology Evaluator.

**Važna napomena:** Ovo je zaseban izvorni LLD review koji prenosi svih **26 TODO stavki** iz razgovora. Ne potvrđuje da su zadaci rešeni. Postojeća 18-stavki [OOP lista](../OOP-ARCHITECTURE-REMEDIATION-TODO.md) i [ClaudeCode review](CLAUDE-CODE-REVIEW-2026-10-08.txt) ostaju odvojeni do buduće kanonske konsolidacije. Prioriteti su procena review-a, ne automatski usvojen razvojni plan. Potvrđene nalaze treba ponovo testirati na commitu na kojem se budu ispravljali.

**Review validation:** 86/86 PHPUnit testova (5.958 assertions), PHPStan bez prijavljenih grešaka, Deptrac 0 violations na navedenom commitu. Zeleni testovi ne dokazuju da su ovi failure scenariji zatvoreni. Nisu menjani aplikacioni kod, testovi ili MOI tokom LLD review-a.

**Format:** svaka stavka sadrži prioritet, ulogu, status dokaza, opis, rizik i konkretnu korekciju.

## 🟥 VISOK PRIORITET

### LLD-01 — Eliminisati otkrivanje korisničkih naloga kroz vreme autentikacije [Visok — Security Architect / Performance Engineer]

- [ ] **🟥 [Visok — Security Architect / Performance Engineer]**
  - **🔍 Opis problema:** U src/Accounts/Accounts.php:133–154, nepostojeći nalog vraća null bez password_verify(), dok postojeći sa pogrešnom lozinkom izvršava bcrypt. Izmereno je približno 0,012 ms prema 135 ms po proveri.
  - **💡 Obrazloženje i rizik:** Napadač može statistički razlikovati postojeća korisnička imena. Masovni pokušaji prijave istovremeno troše CPU.
  - **🛠️ Predlog rešenja:** Koristiti unapred izračunat dummy hash i proveriti lozinku i kada nalog ne postoji. Uvesti rate limiting na pouzdanoj HTTP/deployment granici, sa zajedničkim brojačima pri više instanci. Testirati obe putanje bez oslanjanja na nestabilne vremenske pragove.
  - **Dokaz/status:** Potvrđeno ranijim izolovanim testom.

### LLD-02 — Definisati i centralizovati politiku korisničkih imena i lozinki [Visok — Security Architect / Staff Software Engineer]

- [ ] **🟥 [Visok — Security Architect / Staff Software Engineer]**
  - **🔍 Opis problema:** Register i Accounts uglavnom proveravaju prazne vrednosti i bcrypt ograničenje od 72 bajta; nisu eksplicitno definisani maksimalna dužina korisničkog imena, dozvoljeni znakovi i minimalna politika lozinki.
  - **💡 Obrazloženje i rizik:** Mogu se prihvatiti slabe lozinke, Unicode konfuzni identiteti i preveliki unosi. Registracija i CLI kreiranje administratora mogu se razići u politici.
  - **🛠️ Predlog rešenja:** Definisati jedinstvenu Username invariantu (value object samo ako smanjuje dupliranje), eksplicitnu Unicode/normalization politiku, PasswordPolicy i po potrebi PasswordHasher. Fiksirati algoritam/cost uz migraciju postojećih hash-eva kroz password_verify() i rehash.
  - **Dokaz/status:** Potvrđeno čitanjem koda.

### LLD-03 — Uvesti centralni CSRF ugovor za sve state-changing HTTP rute [Visok — Security Architect / Principal Software Architect]

- [ ] **🟥 [Visok — Security Architect / Principal Software Architect]**
  - **🔍 Opis problema:** CSRF provere postoje u sadašnjim POST handlerima, ali se ručno pozivaju u bootstrap/Http/*Pages.php. Nova ruta može slučajno izostaviti Csrf::matches().
  - **💡 Obrazloženje i rizik:** Zaštita zavisi od discipline autora svakog handlera i ne može se dokazati za sve buduće unsafe rute na jednom mestu.
  - **🛠️ Predlog rešenja:** Uvesti mali centralni HTTP guard/middleware za unsafe metode i eksplicitno dokumentovane izuzetke. Sačuvati postojeći Router ako je dovoljan; dokazati testom da nezaštićena state-changing ruta ne prolazi. PSR-7/15 rewrite nije preduslov.
  - **Dokaz/status:** Potvrđeno čitanjem koda; budući fail-open rizik.

### LLD-04 — Obezbediti ispravan session lifecycle [Visok — Security Architect / Reliability Engineer]

- [ ] **🟥 [Visok — Security Architect / Reliability Engineer]**
  - **🔍 Opis problema:** SessionIdentity::signIn() menja session ID, ali ostavlja anonimni CSRF token. PhpSession se oslanja na globalni PHP session mehanizam; fizički lifetime i storage zavise od deployment okruženja.
  - **💡 Obrazloženje i rizik:** Nedovoljno eksplicitna promena autentikacionog konteksta i moguće neočekivano isticanje ili blokiranje sesija, posebno pri više instanci.
  - **🛠️ Predlog rešenja:** Definisati state machine anonymous → authenticated → expired/revoked, rotirati CSRF na loginu, uskladiti PHP session GC sa idle/absolute pravilima i testirati logout, demociju, expiry i nedostupan session storage.
  - **Dokaz/status:** Potvrđeno ranijim testom i čitanjem koda.

### LLD-05 — Sprečiti da neispravan korisnički datum postane HTTP 500 [Visok — Senior Software Engineer / Reliability Engineer]

- [ ] **🟥 [Visok — Senior Software Engineer / Reliability Engineer]**
  - **🔍 Opis problema:** Statistics::page() i DailyReports::dailyCounts() pozivaju DateTimeImmutable::createFromFormat() bez potpune preliminarne provere ulaza. NUL bajt baca ValueError, dok HTTP adapter hvata InvalidArgumentException.
  - **💡 Obrazloženje i rizik:** Pogrešan upit korisnika završava kao HTTP 500 umesto očekivanog 422; rizik nepotrebnih grešaka i loše klasifikacije incidenta.
  - **🛠️ Predlog rešenja:** Strogo validirati YYYY-MM-DD, kalendarsku ispravnost i opsege pre parsiranja; očekivane invalidne filtere mapirati na 422. UtcDay value object samo ako pojednostavljuje dve upotrebe; regresioni testovi NUL/invalidnih datuma.
  - **Dokaz/status:** Potvrđeno ranijim reprodukovanjem.

### LLD-06 — Uvesti fail-closed produkcionu konfiguraciju [Visok — Principal Platform Engineer / Security Architect]

- [ ] **🟥 [Visok — Principal Platform Engineer / Security Architect]**
  - **🔍 Opis problema:** bootstrap/config.php podrazumevano dopušta SESSION_COOKIE_SECURE=0. SqliteConnection::open() može kreirati direktorijum i bazu tokom web zahteva.
  - **💡 Obrazloženje i rizik:** Pogrešna produkciona konfiguracija može dati nebezbedne kolačiće ili novu praznu bazu umesto greške o nedostajućem provisioning-u.
  - **🛠️ Predlog rešenja:** Fail-closed produkciona konfiguracija, HTTPS i secure cookies, validacija environment vrednosti, zaseban provisioning/migrations korak i startup/readiness odbijanje neispravnog deployment-a.
  - **Dokaz/status:** Potvrđeno čitanjem koda; izloženost uslovna.

### LLD-07 — Centralizovati HTTP security headers i obradu grešaka [Visok — Security Architect / Principal Software Architect]

- [ ] **🟥 [Visok — Security Architect / Principal Software Architect]**
  - **🔍 Opis problema:** HtmlPage postavlja HTML CSP, X-Frame-Options i druge sigurnosne headere, dok tekstualni odgovori, preusmerenja, 404/405 i fallback 500 nemaju isti centralni outbound policy.
  - **💡 Obrazloženje i rizik:** Nov endpoint ili putanja greške može propustiti relevantne sigurnosne i cache-control headere.
  - **🛠️ Predlog rešenja:** Centralizovati response security policy, uz razliku između globalnih i HTML-specifičnih headera. Testirati HTML, text, redirects, download, 404/405 i nepredviđeni izuzetak.
  - **Dokaz/status:** Potvrđeno čitanjem koda.

### LLD-08 — Definisati stvarnu zaštitu i oporavak trajnih podataka [Visok — Principal Platform Engineer / Reliability Engineer]

- [ ] **🟥 [Visok — Principal Platform Engineer / Reliability Engineer]**
  - **🔍 Opis problema:** Ranije je opažen SQLite fajl sa chmod 777. Roditeljski direktorijum je imao režim 700, pa to samo po sebi nije dokaz dostupnosti drugim korisnicima. Deployment nema potvrđen minimal-permission/backup/restore ugovor.
  - **💡 Obrazloženje i rizik:** Pri drugačijem rasporedu direktorijuma može doći do neželjenog pristupa. Bez testiranog restore-a kvarovi mogu završiti gubitkom podataka.
  - **🛠️ Predlog rešenja:** Namenski servisni identitet, najmanje potrebne dozvole, provera putanja, eksplicitni RPO/RTO i test za stvarni SQLite restore, lockove, disk-full i permission-denied.
  - **Dokaz/status:** Host dozvole potvrđene ranije; produkcioni rizik uslovan.

## 🟨 SREDNJI PRIORITET

### LLD-09 — Popraviti exception safety u Renderer-u [Srednji — Senior Software Engineer / Reliability Engineer]

- [ ] **🟨 [Srednji — Senior Software Engineer / Reliability Engineer]**
  - **🔍 Opis problema:** Renderer::render() na izuzetku zatvara samo jedan output buffer. Template koji otvori dodatni ob_start() može ostaviti aktivan buffer nakon izuzetka.
  - **💡 Obrazloženje i rizik:** Globalno procesno stanje i output mogu ostati promenjeni; sledeći render može biti kontaminiran.
  - **🛠️ Predlog rešenja:** Zapamtiti početni ob_get_level(), u finally očistiti sve nove buffer-e, definisati ponašanje pri abnormalnom uspešnom povratku i testirati ugnježdenost i očuvanje izvornog izuzetka.
  - **Dokaz/status:** Potvrđeno ranijim reprodukovanjem.

### LLD-10 — Ojačati Migrator kao pouzdan persistence lifecycle komponent [Srednji — Principal Platform Engineer / Reliability Engineer]

- [ ] **🟨 [Srednji — Principal Platform Engineer / Reliability Engineer]**
  - **🔍 Opis problema:** Migrator u schema_migrations beleži identifikator i vreme, ne checksum SQL fajla. Izmenjena već primenjena migracija se tiho preskače; konkurentni migratori nemaju eksplicitno razrešen ugovor.
  - **💡 Obrazloženje i rizik:** Okruženja mogu imati divergentne šeme pod istim brojem migracije; deployment race povećava operativni rizik.
  - **🛠️ Predlog rešenja:** Ledger checksum (npr. SHA-256), fail-fast za drift i jasna politika concurrent migratora. Testirati fresh, idempotent, changed SQL, interrupted/failed migration i recovery.
  - **Dokaz/status:** Potvrđeno ranijim reprodukovanjem.

### LLD-11 — Eksplicitno definisati Transaction ugovor [Srednji — Staff Software Engineer / Reliability Engineer]

- [ ] **🟨 [Srednji — Staff Software Engineer / Reliability Engineer]**
  - **🔍 Opis problema:** Transaction::run() pretpostavlja isključivo vlasništvo nad PDO transakcijom i ne definiše nesting. Ako rollBack() baci izuzetak, prvobitni throwable može biti prikriven.
  - **💡 Obrazloženje i rizik:** Teška dijagnostika kombinovanih failure scenarija i potencijalni nedozvoljeni nested beginTransaction() pozivi.
  - **🛠️ Predlog rešenja:** Eksplicitna precondition zabrana nesting-a ili savepoints samo uz realan use case. Sačuvati oba failure konteksta kada rollback ne uspe; ciljani regresioni testovi.
  - **Dokaz/status:** Potvrđeno čitanjem koda; scenario uslovan.

### LLD-12 — Smanjiti infrastrukturno vezivanje Accounts modela [Srednji — Principal Software Architect / Staff Engineer]

- [ ] **🟨 [Srednji — Principal Software Architect / Staff Engineer]**
  - **🔍 Opis problema:** SessionIdentity poznaje konkretan PhpSession, dok Accounts objedinjuje SQL, autentikaciju, hashing i account mapping. AccountLifecycle koordinira business tok i konkretne zavisnosti.
  - **💡 Obrazloženje i rizik:** Slojevi se mogu teže menjati pojedinačno i izolovati pri testiranju; dodatne apstrakcije bez pritiska mogu isto tako pogoršati sistem.
  - **🛠️ Predlog rešenja:** Najpre izdvojiti stvarne PasswordPolicy/PasswordHasher invariante; mali session port samo ako je potreban za testabilnost/promenu skladišta; ne uvoditi repository interfejs samo radi SOLID rituala. Očuvati AccountLifecycle kao smislenu orkestraciju.
  - **Dokaz/status:** Dizajnerska procena zasnovana na kodu.

### LLD-13 — Razdvojiti authorization outcome od HTTP transporta [Srednji — Principal Software Architect / Senior Software Engineer]

- [ ] **🟨 [Srednji — Principal Software Architect / Senior Software Engineer]**
  - **🔍 Opis problema:** CowOfferPages i DownloadPages vraćaju SignedInAccount|Response iz authorizedUser(); isti union kombinuje identitet i HTTP transport.
  - **💡 Obrazloženje i rizik:** Pozivaoci moraju granati po tipu, a stil kontrole se ponavlja pri rastu broja ruta.
  - **🛠️ Predlog rešenja:** Centralni RequireSignedIn/AccessDenied tok uz jedinstveno mapiranje na 403 ili login redirect. Ne stvarati novi Result DTO ako exception/guard tok ostaje jednostavniji.
  - **Dokaz/status:** Dizajnerska procena zasnovana na kodu.

### LLD-14 — Formalizovati identitet i autoritet audit događaja [Srednji — Security Architect / Principal Software Architect]

- [ ] **🟨 [Srednji — Security Architect / Principal Software Architect]**
  - **🔍 Opis problema:** ActivityLog prima actorId i actorLabel od svojih pozivalaca. Ovo funkcioniše samo uz pouzdane klijente API-ja; append-only je trenutno aplikaciona konvencija.
  - **💡 Obrazloženje i rizik:** Buduća integracija može pripisati događaj drugom akteru ili falsifikovati istorijski label; DB administrator i dalje ima mogućnost izmene istorije.
  - **🛠️ Predlog rešenja:** Formalizovati trusted-caller ugovor i po potrebi actor context na authorization granici, sa istorijskim snapshot-om. Za compliance audit zaštitu odlučiti na osnovu threat modela i retention zahteva.
  - **Dokaz/status:** Uslovni trust-boundary rizik.

### LLD-15 — Razrešiti neatomarne cross-boundary tokove autentikacije [Srednji — Reliability Engineer / Staff Software Engineer]

- [ ] **🟨 [Srednji — Reliability Engineer / Staff Software Engineer]**
  - **🔍 Opis problema:** Login prvo uspostavlja sesiju, potom zapisuje audit; ako audit ne uspe, AccountLifecycle pokušava signOut. To je kompenzacija, ne jedna atomska transakcija.
  - **💡 Obrazloženje i rizik:** Istovremeni kvar audit upisa i revokacije sesije može ostaviti delimično izvršen login ili nejasnu grešku.
  - **🛠️ Predlog rešenja:** Formalizovati login/logout state machine i failure outcomes; testirati audit failure zajedno sa session destroy failure, očuvati root cause, definisati bezbedan odgovor. Ne uvoditi distributed transaction za ovaj lokalni tok.
  - **Dokaz/status:** Potvrđeno kodom; kombinovani failure nije reprodukovan.

### LLD-16 — Obezbediti stabilnu paginaciju audit istorije [Srednji — Performance Engineer / Senior Software Engineer]

- [ ] **🟨 [Srednji — Performance Engineer / Senior Software Engineer]**
  - **🔍 Opis problema:** Statistics::page() koristi LIMIT/OFFSET. Novi audit događaj između dve stranice može dovesti do ponovljenog ID-ja; veliki OFFSET postaje skuplji.
  - **💡 Obrazloženje i rizik:** Administrator može videti duplikate ili preskočene događaje i osećati degradaciju nad velikom tabelom.
  - **🛠️ Predlog rešenja:** Odlučiti između best-effort numerisanih stranica i stabilnog cursor/keyset ugovora (occurred_at, id); testirati simultane upise, iste timestamp-e i query plan.
  - **Dokaz/status:** Potvrđeno ranijim reprodukovanjem.

### LLD-17 — Ispraviti vremensku semantiku dnevnog grafikona [Srednji — Senior Software Engineer / Performance Engineer]

- [ ] **🟨 [Srednji — Senior Software Engineer / Performance Engineer]**
  - **🔍 Opis problema:** DailyReports vraća samo aktivne datume, dok reports.php raspoređuje X koordinate prema indeksu stavke, a ne vremenskoj razdaljini. Za mali raspon Y-axis rounding može ponavljati oznake.
  - **💡 Obrazloženje i rizik:** Korisniku se može prikazati pogrešan vremenski trend ili nejasne oznake.
  - **🛠️ Predlog rešenja:** Definisati 30 kalendarskih dana naspram 30 aktivnih datuma. Dopuniti prazne dane nulama ili vremenski razmerno pozicionirati X; prilagoditi Y tick-ove i testirati razmaknute datume i male vrednosti.
  - **Dokaz/status:** Potvrđeno čitanjem koda.

### LLD-18 — Izmeriti SQL pristup izveštajima pre optimizacije [Srednji — Performance Engineer / OSS Technology Evaluator]

- [ ] **🟨 [Srednji — Performance Engineer / OSS Technology Evaluator]**
  - **🔍 Opis problema:** DailyReports agregira događaje iz activity_events; iako expression index postoji, nema dokaza o p95/p99 performansama pri velikom obimu i konkurentnim upisima.
  - **💡 Obrazloženje i rizik:** Rast istorije može usporiti GROUP BY i povećati SQLite lock contention.
  - **🛠️ Predlog rešenja:** EXPLAIN QUERY PLAN, meriti workload i indekse; WAL evaluirati uz backup i concurrency testove. Rollup tabelu uvoditi tek kada korist nadmaši dodatnu obavezu konzistentnosti.
  - **Dokaz/status:** Uslovno, nedostaje reprezentativni benchmark.

### LLD-19 — Smanjiti session-lock contention [Srednji — Principal Platform Engineer / Reliability Engineer]

- [ ] **🟨 [Srednji — Principal Platform Engineer / Reliability Engineer]**
  - **🔍 Opis problema:** PHP session može držati lock dok traje HTTP zahtev; SessionIdentity::current() čita i upisuje last-seen timestamp.
  - **💡 Obrazloženje i rizik:** Paralelni zahtevi istog korisnika mogu se nepotrebno serijalizovati dok izveštaji ili DB operacije traju.
  - **🛠️ Predlog rešenja:** Izmeriti dva paralelna zahteva sa istom sesijom. Ako je problem stvaran, završiti session write fazu ranije (session_write_close) uz očuvanje idle-timeout semantike.
  - **Dokaz/status:** Potencijalni runtime problem zavisan od session handler-a.

### LLD-20 — Definisati sigurnost i memorijsku granicu preuzimanja fajla [Srednji — Performance Engineer / Reliability Engineer]

- [ ] **🟨 [Srednji — Performance Engineer / Reliability Engineer]**
  - **🔍 Opis problema:** DownloadFile::read() učitava kompletan fajl u string, a Response čuva kopiju tela. Metoda read() ima i audit write nuspojavu.
  - **💡 Obrazloženje i rizik:** Za velik artifact memorija raste po zahtevu; side-effect je teško predvideti iz imena metode.
  - **🛠️ Predlog rešenja:** Zadržati jednostavan string za mali fiksni sample, dokumentovati limit, streaming razmatrati za veće fajlove. Po potrebi preimenovati u prepareDownload() i objasniti da audit znači pripremljen odgovor, ne uspešnu isporuku.
  - **Dokaz/status:** Uslovno pri rastu veličine artifact-a.

### LLD-21 — Dovršiti HTTP protocol correctness [Srednji — Principal Platform Engineer / Senior Software Engineer]

- [ ] **🟨 [Srednji — Principal Platform Engineer / Senior Software Engineer]**
  - **🔍 Opis problema:** Router registruje samo tačne HTTP method/path kombinacije. Nisu definisane HEAD/OPTIONS semantike; Request::fromGlobals() može zameniti neuspešno parsiranje URI-ja sa '/'.
  - **💡 Obrazloženje i rizik:** Neki klijenti, health check-ovi i proxy integracije mogu videti neočekivane HTTP odgovore.
  - **🛠️ Predlog rešenja:** Eksplicitno definisati podržani protokol, testirati i po potrebi implementirati HEAD/OPTIONS, odbijati malformed URI bez tihog fallback-a. PSR-7/15 tek ako stvarne integracije opravdaju.
  - **Dokaz/status:** Potvrđeno čitanjem koda; zahtevi za protokol uslovni.

### LLD-22 — Testirati realne neuspehe umesto samo uspešnih putanja [Srednji — Staff Software Engineer / Reliability Engineer]

- [ ] **🟨 [Srednji — Staff Software Engineer / Reliability Engineer]**
  - **🔍 Opis problema:** 86 PHPUnit testova i 5.958 assertions prolaze, ali nisu otkrili timestamp drift, nested-buffer leak, date NUL, migration drift i pagination concurrency.
  - **💡 Obrazloženje i rizik:** Veliki broj assertions ne garantuje pokrivenost važnih failure-mode invarianti.
  - **🛠️ Predlog rešenja:** Characterization + regresioni testovi za login/session/audit kombinovane kvarove, neispravne datume, nested buffer, migracije, SQLite contention, demociju admina i paginaciju. Za potvrđene bugove test mora padati na starom kodu.
  - **Dokaz/status:** Potvrđeno nepokrivenim reprodukcijama.

## 🟩 NIZAK PRIORITET

### LLD-23 — Unaprediti javne ugovore bez novih nepotrebnih apstrakcija [Nizak — Principal Software Architect / Staff Engineer]

- [ ] **🟩 [Nizak — Principal Software Architect / Staff Engineer]**
  - **🔍 Opis problema:** Male wrapper klase i kombinacije poslovne/tehničke terminologije povremeno sugerišu shallow modules, ali CurrentAccount i AccountLifecycle trenutno štite stvarne granice.
  - **💡 Obrazloženje i rizik:** Preuranjene ili suvišne apstrakcije povećavaju coupling i otežavaju razumevanje.
  - **🛠️ Predlog rešenja:** Za svaki novi interfejs/klasu zahtevati dokaz o sakrivenoj složenosti, invariantama ili smanjenom coupling-u. Ne uvoditi DTO, repository ili servis za svaku klasu.
  - **Dokaz/status:** Dizajnerska preporuka.

### LLD-24 — Poboljšati PHPDoc ugovore, ne samo broj anotacija [Nizak — Senior Software Engineer / Principal Software Architect]

- [ ] **🟩 [Nizak — Senior Software Engineer / Principal Software Architect]**
  - **🔍 Opis problema:** Nekoliko PHPDoc opisa su generičke fraze poput 'Input value for id' ili 'Result described by the native PHP return type'.
  - **💡 Obrazloženje i rizik:** Formalna dokumentaciona pokrivenost ne objašnjava suštinski ugovor, nuspojave, failure semantiku ili ownership.
  - **🛠️ Predlog rešenja:** Zameniti generičke opise korisnim preconditions/postconditions, izuzecima, transaction i idempotency garancijama; ne brisati stvarno korisne DocBlocks i ne menjati MOI.
  - **Dokaz/status:** Potvrđeno čitanjem koda.

### LLD-25 — Usaglasiti izbor OSS komponenti sa stvarnim potrebama [Nizak — OSS Technology Evaluator / Principal Software Architect]

- [ ] **🟩 [Nizak — OSS Technology Evaluator / Principal Software Architect]**
  - **🔍 Opis problema:** Router, Request/Response, .env parser i template renderer napisani su ručno. Za Vanilla PHP zadatak to je legitimno, ali svaka takva komponenta zahteva dugoročne protokol/edge-case provere.
  - **💡 Obrazloženje i rizik:** U produkciji se povećava maintenance i security obaveza ako se postojeća rešenja drže bez testova i operativnog vlasništva.
  - **🛠️ Predlog rešenja:** ADR retain vs replace: uporediti zrela OSS rešenja po održavanju, sigurnosti, kompatibilnosti sa PHP 8.3, transitive dependencies i trošku migracije; ne uvoditi framework automatski.
  - **Dokaz/status:** Tehnološka procena.

### LLD-26 — Očuvati MOI i izvršive arhitektonske granice [Nizak — Staff Engineer / AI & Agent Infrastructure Architect]

- [ ] **🟩 [Nizak — Staff Engineer / AI & Agent Infrastructure Architect]**
  - **🔍 Opis problema:** AI-assisted promene i automatski refaktori mogu pomeriti business-owner granice, promeniti named-argument parametre ili narušiti lični MOI stil i pored zelenih testova.
  - **💡 Obrazloženje i rizik:** Raste diff noise, coupling i rizik od narušenih javnih ugovora; dodatna AI infrastruktura nije zahtev proizvoda.
  - **🛠️ Predlog rešenja:** MOI zadržati kao jedini formatter, Rector bez automatskog naming-a, Deptrac + PublicSurfaceTest kao arhitektonske gate-ove. Kratka projektna AI pravila: testovi, javni ugovori, potvrda svake nove apstrakcije.
  - **Dokaz/status:** Procesna/projektnа preporuka.

## Pravila za kasnije spajanje

- [ ] Mapirati `LLD-01` … `LLD-26` na prethodnih `OOP-01` … `OOP-18` i originalne ClaudeCode `S/A/R/P/B/T/D/X` ID-jeve; ne izgubiti nijedan izvorni identifikator.
- [ ] Pri konsolidaciji obeležiti stavke kao CONFIRMED / CONDITIONAL / DESIGN DECISION / REJECTED i dokumentovati izvor + dokaz.
- [ ] Revidirati severity prema stvarnom threat modelu, obimu zadatka i posledicama po produkciju.
- [ ] Ne započinjati big-bang rewrite zbog formalnih OOP/SOLID metrika: complexity must be earned.
- [ ] Očuvati modularni monolit, business-first boundaries, named arguments i MOI kao jedini vizuelni formatter.
