# ClaudeCode code review — independent source index

Source date: **2026-10-08**. Source title: *Fast Corporation PHP Task: Code Review ToDo*.

## Status

**ARCHIVED / NOT MERGED / NOT IMPLEMENTED.** This document indexes an independently authored ClaudeCode review. Its findings and severities are *the source's claims*, not newly verified facts. The original text is preserved without edits in [`CLAUDE-CODE-REVIEW-2026-10-08.txt`](CLAUDE-CODE-REVIEW-2026-10-08.txt).

The existing [`OOP-ARCHITECTURE-REMEDIATION-TODO.md`](../OOP-ARCHITECTURE-REMEDIATION-TODO.md) remains the separate prior review. Do not deduplicate, override priorities or mark tasks completed until the later consolidation pass explicitly decides **CONFIRMED / CONDITIONAL / DESIGN DECISION / REJECTED** and maps each Claude ID to a canonical task.

Verbatim source SHA-256: `2ffd5e863cec58cd46d106e24a2be8b035780f06f7456abd54d133e66a9e1782`.

Source provenance: uploaded user-supplied `Pasted text(20261008-214011).txt` (31,778 UTF-8 bytes, 244 lines). ClaudeCode states that it could not run the project's PHPUnit/PHPStan/Deptrac suite inside its sandbox. It distinguishes `[probano]` direct probes, `[kod]` static reading and `[uslovno]` environment-dependent claims.

## Category inventory

| Prefix | Area | Distinct IDs |
|---|---|---:|
| `S` | Security | 15 |
| `A` | Architecture & OOP | 13 |
| `R` | Reliability & data | 9 |
| `P` | Performance | 6 |
| `B` | Correctness / bugs | 7 |
| `T` | Testing / CI | 6 |
| `D` | Documentation & process | 3 |
| `X` | AI / agent infrastructure | 3 |
| **Total** | | **62** |

## Original task identifiers (not yet reconciled)

| Claude ID | Claude severity | Effort | Claude evidence label | Source description (opening fragment) |
|---|---|---|---|---|
| `S-01` | P1 | M | kod | Throttling prijave i registracije u aplikaciji. README to prepušta edge-u, ali aplikacija nema nikakvu odbranu. Svaki POST na /l… |
| `S-02` | P1 | S | probano | Nema minimalne dužine lozinke. Lozinka x je prihvaćena; postoji samo maksimum od 72 bajta. Fix: PasswordPolicy (min 12, max 128… |
| `S-03` | P1 | M | probano | Hash algoritam i cost. PASSWORD_BCRYPT bez opcija daje $2y$10$ na PHP 8.3, a cost 12 na 8.4+, pa se ponašanje razlikuje kroz CI… |
| `S-04` | P1 | M | probano | Nema politike korisničkog imena. Prihvaćeni su: ime od 100.000 karaktera, аdmin sa ćiriličnim «а», ime sa RTL override znakom U+… |
| `S-05` | P1 | S | probano | Timing enumeracija korisnika. Nepostojeći korisnik: 0,13 ms; postojeći sa pogrešnom lozinkom: 59 ms. Autor ovo već vodi kao OOP-… |
| `S-06` | P1 | M | kod | CSRF treba da bude podrazumevan, ne opt-in. csrf->matches() je ručno ponovljen u pet handlera (register, login, logout, buy-cow,… |
| `S-07` | P2 | M | kod | Sigurni default-i su fail-open. SESSION_COOKIE_SECURE je podrazumevano 0, .env.example ima APP_DEBUG=1, a APP_ENV, APP_DEBUG i A… |
| `S-08` | P2 | M | kod | Sesije. (a) session.gc_maxlifetime je po defaultu 1440 s, a idle limit aplikacije je 1800 s: sesija može biti obrisana pre istek… |
| `S-09` | P2 | S | kod | Sigurnosni headeri su nedosledni. Dodaje ih samo HtmlPage; 403/404/405/500, redirecti i download odgovori nemaju nosniff. CSP ko… |
| `S-10` | P2 | M | kod | Ništa za upravljanje nalozima. Nema statusa naloga (disabled), promene ili reseta lozinke, a izgubljena admin lozinka znači novi… |
| `S-11` | P2 | M | kod | Nema sigurnosnog audit traga. Zadatak kaže da se beleže samo uspešne akcije, i to treba da ostane u activity_events. Ali neuspel… |
| `S-12` | P3 | S | kod | Download artefakt. Ceo fajl se čita u memoriju (file_get_contents + strlen), a binarni sample.exe je u repou (AV/EDR i secret sc… |
| `S-13` | P3 | S | probano | Neispravan URI se tiho tretira kao /. parse_url('//page-a', PHP_URL_PATH) vraća null, pa Request::fromGlobals preusmerava na /.… |
| `S-14` | P3 | S | kod | bin/create-admin.php. Koristi shell_exec('stty') (ne radi na Windows-u), zamršeno parsiranje argv, neuhvaćen InvalidArgumentExce… |
| `S-15` | P3 | S | kod | Ručni .env parser. Ne podržava export, citirane vrednosti sa #, escape sekvence; koristi putenv koji nije thread-safe (ZTS, Fran… |
| `A-01` | P1 | L | kod | HTTP kernel sa middleware pipeline-om. Router mapira samo (metoda, putanja) na closure; auth, CSRF, admin provera i tracking su… |
| `A-02` | P1 | M | kod | Modul CowOffer je prazna ljuska. CowOffer::view() i buy() samo prosleđuju poziv u ActivityLog, a DownloadFile::view() je isti ko… |
| `A-03` | P1 | M | kod | Kontroleri žive u Bootstrap namespace-u. bootstrap/Http/*Pages parsiraju ulaz, mapiraju izuzetke u statuse i renderuju, a Deptra… |
| `A-04` | P1 | M | kod | Domen zavisi od infrastrukture. SessionIdentity (Accounts) uvozi Infrastructure\Session\PhpSession i Infrastructure\Time\SystemC… |
| `A-05` | P1 | L | kod | Accounts je god-repozitorijum. U jednoj klasi su politika lozinke (72 bajta), hash-ovanje, SQL, autentikacija, mapiranje redova… |
| `A-06` | P2 | M | kod | Stringly-typed nizovi umesto objekata. Statistics::page() vraća array{events, hasNext}, DailyReports vraća redove stringova koje… |
| `A-07` | P2 | S | kod | Validacija ulaza preko InvalidArgumentException. To je tip za programersku grešku, a ActivityPages prikazuje $e->getMessage() ko… |
| `A-08` | P2 | M | kod | Portovi za activity. CowOffer i Downloads zavise od final ActivityLog nad PDO-om, pa testovi moraju prava baza. Pet metoda recor… |
| `A-09` | P2 | M | kod | Kompozicioni koren gradi sve eager. routes.php pri svakom zahtevu otvara PDO (uključujući 404 i /login), proverava direktorijum… |
| `A-10` | P2 | M | probano | HEAD i OPTIONS vraćaju 405. Monitoring, curl -I i prefetch lome se na rutama koje postoje kao GET. Response ne podržava više hea… |
| `A-11` | P3 | M | kod | Template sloj. Sedam templejta sa istim kopiranim <style> blokom, bez layout-a i partiala; $data i $escape su magične lokalne pr… |
| `A-12` | P3 | S | kod | Greške i logovanje. Jedan catch (Throwable) u index.php sa error_log((string) $error), bez request id-a i bez PSR-3. Mapiranje i… |
| `A-13` | P3 | S | kod | Higijena. Statistics::events() postoji samo zbog testova; Response::html() se nigde ne koristi; reportsPage(?Request $request =… |
| `R-01` | P1 | S | probano | SQLite radi u journal_mode=delete. Svaki GET na Page A ili B je upis (activity event plus upis sesije), a Statistics čita istu b… |
| `R-02` | P1 | S | kod | Web zahtev može da stvori praznu bazu. SqliteConnection::open pravi direktorijum (0775) i fajl ako ne postoje; bez migracija sle… |
| `R-03` | P2 | M | kod | Migrator. Nema checksum-a (izmenjena primenjena migracija se tiho preskoči; autorov OOP-08 potvrđuje), nema lock-a za paralelne… |
| `R-04` | P2 | S | probano | "Append-only" audit nije zaštićen. README tvrdi append-only, ali UPDATE i DELETE nad activity_events rade bez greške. Fix: SQLit… |
| `R-05` | P2 | M | kod | Vreme kao string. occurred_at je ISO string, a datum se izvodi sa substr(occurred_at, 1, 10); accounts.created_at koristi format… |
| `R-06` | P2 | S | kod | Backup i restore. Nema skripte ni testa vraćanja (autorov OOP-17 to već traži). Fix: sqlite3 .backup ili VACUUM INTO u cron, tes… |
| `R-07` | P3 | S | kod | Login nije atomičan sa sesijom. Sesija se menja pre upisa audit događaja, pa se kompenzuje signOut(); ako signOut baci, gubi se… |
| `R-08` | P3 | S | kod | Transaction ne podržava ugnježdenje. beginTransaction() unutar aktivne transakcije baca PDOException, a neuspeli rollBack() može… |
| `R-09` | P3 | S | kod | Observability. Nema /healthz, request id-a, strukturisanih logova ni slow-query loga. Fix: vidi A-12; dodati liveness i readines… |
| `P-01` | P1 | L | probano | Reports je O(N). Plan je SCAN activity_events USING INDEX idx_activity_day: svaki prikaz agregira celu tabelu pa zadrži 30 dana.… |
| `P-02` | P1 | M | probano | Statistics filteri skeniraju sve. Filter po korisniku i po akciji daju SCAN ... USING INDEX idx_activity_recent (nema indeksa na… |
| `P-03` | P2 | S | kod | Ponovljeni upiti po zahtevu. SessionIdentity::current() radi findById na svaki poziv; ActivityPages na odbijanju zove admin->acc… |
| `P-04` | P2 | S | kod | Fiksni trošak po zahtevu. Parsiranje .env, dvostruki require konfiguracije, mkdir/is_dir i otvaranje PDO-a na svaki zahtev, čak… |
| `P-05` | P2 | M | uslovno | Nema opterećenja ni budžeta. Fix: skripta koja seed-uje 1M događaja, wrk ili k6 scenario sa ciljem p95 za /statistics i /reports… |
| `P-06` | P3 | S | uslovno | Izlazni kriterijum za SQLite. SQLite je ispravan za jedan čvor. Fix: ADR sa pragom (više čvorova, upisi/s, veličina baze) pri ko… |
| `B-01` | P2 | S | probano | Grafik na Reports obmanjuje. X osa je kategorijska (ravnomeran razmak), a prikazuju se samo dani sa aktivnošću. Probom sa događa… |
| `B-02` | P3 | S | kod | Duplirane oznake na Y osi. round($max * $i / 4) za max=1 daje 0, 0, 1, 1, 1 (PHP zaokružuje .5 na gore), pa se isti brojevi i li… |
| `B-03` | P2 | M | kod | Dvostruki klik i osvežavanje naduvavaju brojeve. POST /buy-cow i POST /download odmah renderuju odgovor bez PRG-a, pa ponovno sl… |
| `B-04` | P3 | S | kod | view-page znači "stigao je GET". Reload, prefetch, link preview i botovi se broje; GET ima sporedni efekat. Fix: dokumentovati d… |
| `B-05` | P3 | S | kod | Klok skew uništava sesiju. issuedAt > now ili lastSeen > now odmah uništava sesiju; na više čvorova sa malim odstupanjem sata ko… |
| `B-06` | P3 | S | kod | Pristupačnost. Serije na grafiku se razlikuju samo bojom, a zelena #16a34a kao tekst nema WCAG AA kontrast (oko 3,3:1). Fix: raz… |
| `B-07` | P3 | S | kod | Potvrđeni autorovi nalazi. OOP-01 (NUL bajt u datumu daje ValueError i HTTP 500) i OOP-02 (Renderer ostavlja otvoren output buff… |
| `T-01` | P1 | S | not labeled | · Prvi korak: neutralna polazna linija. Pre bilo kog refaktora pokrenuti composer quality i live_smoke.py na čistom checkout-u i… |
| `T-02` | P2 | M | kod | Testovi su vezani za superglobale. setUp ručno radi session_destroy(), $_SESSION = [], session_id(''), ini_set('session.use_cook… |
| `T-03` | P1 | M | not labeled | · Nedostaju sigurnosni i regresioni testovi: ugovor ruta (svaka ne-GET ruta ima CSRF), headeri na svakom odgovoru uključujući 4x… |
| `T-04` | P2 | S | not labeled | · Dodatna statika: phpstan/phpstan-strict-rules, phpstan-deprecation-rules, shipmonk/dead-code-detector, tomasvotruba/type-cover… |
| `T-05` | P2 | S | not labeled | · CI higijena. Akcije su vezane za pokretne tagove (actions/checkout@v5, shivammathur/setup-php@v2); fix: pin na commit SHA plus… |
| `T-06` | P3 | S | not labeled | · Pravilo isporuke. Svaka stavka iz ove liste se zatvara samo uz test koji pada na starom kodu, kao što autor već traži u svom p… |
| `D-01` | P2 | S | kod | Proces je veći od proizvoda. Oko 2.700 linija dokumenata (ARCHITECTURE-GOVERNANCE.md sam ima 1.873) naspram oko 2.300 linija apl… |
| `D-02` | P3 | S | mišljenje | Imenovani argumenti na ugrađenim funkcijama (trim(string: ..., characters: ...), ini_set(option: ..., value: ...)). Imena parame… |
| `D-03` | P3 | S | not labeled | · README. Skratiti na jedan ekran (zahtevi, pet komandi, linkovi); odbrambeni odeljci o sigurnosti i paginaciji prebaciti u docs… |
| `X-01` | P3 | S | not labeled | Dodati AGENTS.md (ili CLAUDE.md) sa komandama, invarijantama (Deptrac, bez uvoza između modula, A-xx redosled) i definicijom "go… |
| `X-02` | P3 | S | not labeled | Jedan composer ci skript koji radi sve gate-ove deterministički (agenti i ljudi pokreću istu komandu). |
| `X-03` | P3 | S | not labeled | Ako se ikada doda AI asistent nad Statistics podacima, korisnička imena i ostali slobodan tekst iz audit tabele su nepoverljiv u… |

## Later consolidation checklist

- [ ] Independently reproduce high-impact findings against the then-current commit (not merely against the archived review).
- [ ] Cross-map all 62 Claude IDs with existing OOP-01 … OOP-18 without discarding contrary perspectives.
- [ ] Separate confirmed bugs from environment-dependent claims and optional architectural rewrites.
- [ ] Reconcile disagreements (middleware/PSR-7 scope, SQLite WAL, documentation, named arguments) under Compass evidence and project constraints.
- [ ] Preserve **MOI as sole PHP formatter**, business-owner module boundaries, regression tests and named-argument compatibility.
- [ ] Build one ordered canonical remediation list with acceptance criteria, scope, owners and explicit non-goals.

**This commit is documentation-only; it does not implement or approve ClaudeCode recommendations.**
