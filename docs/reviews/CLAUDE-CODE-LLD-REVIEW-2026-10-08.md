# LLD Review ToDo: Fast Corporation PHP Task

Oct 8, 2026 · @stankela

## 🟥 Visok prioritet

- [ ] **🟥 \[Visok - Security Architect\] 1. CSRF, autentikacija i autorizacija su opt-in po handleru**

🔍 **Opis:** `Router` mapira samo (metoda, putanja) na closure. Provera sesije, CSRF, admin uloge i beleženje prikaza su ručno ponovljeni u svakoj `*Pages` klasi: `csrf->matches()` na 5 mesta, a `authorizedUser()` je doslovno kopiran u `CowOfferPages` i `DownloadPages`.

💡 **Rizik:** Svaki novi POST handler koji zaboravi poziv je CSRF ranjiv, a nova admin ruta bez `requireAdministrator()` je javna. Greška se ne vidi ni u review-u ni u testovima, jer ne postoji ugovor "svaka ne-GET ruta ima CSRF".

🛠️ **Rešenje:** Ruta nosi svoje middleware-e, a sigurnost je podrazumevana (fail-closed).

```php
interface Middleware { public function process(Request $r, callable $next): Response; }

final readonly class VerifyCsrf implements Middleware {
    public function __construct(private Csrf $csrf) {}
    public function process(Request $r, callable $next): Response {
        if (!in_array($r->method(), ['GET', 'HEAD', 'OPTIONS'], true)
            && !$this->csrf->matches($r->post('csrf_token'))) {
            return Response::text('Forbidden', 403);
        }
        return $next($r);
    }
}

$router->post('/buy-cow', $cow->buy(...), [Authenticate::class, TrackClick::class]); // CSRF globalno
$router->get('/statistics', $stats->page(...), [Authenticate::class, Authorize::admin()]);
```

Dodati test koji prolazi kroz route tabelu i pada ako ne-GET ruta nema `VerifyCsrf` u lancu. Token rotirati pri prijavi i dodati proveru `Origin` / `Sec-Fetch-Site`.

- [ ] **🟥 \[Visok - Security Architect\] 2. Nema throttlinga prijave i registracije (brute force i CPU DoS)**

🔍 **Opis:** `POST /login` i `/register` nemaju nikakvo ograničenje; README to prepušta edge-u. CSRF token se dobija bez naloga, pa napadač ima neograničen broj pokušaja.

💡 **Rizik:** Pogađanje lozinki bez ograničenja; svaki zahtev košta jedan bcrypt hash (izmereno oko 59 ms na cost 10, oko 4x više na cost 12), pa nekoliko desetina zahteva u sekundi zagušuje PHP workere. NIST 800-63B traži rate limiting.

🛠️ **Rešenje:** Port `Throttle` sa SQLite implementacijom; edge limit ostaje druga linija odbrane.

```php
interface Throttle {
    /** @throws TooManyAttempts sa retryAfter */
    public function hit(string $key): void;   // npr. "login:" . $canonicalUsername . ":" . $ip
    public function clear(string $key): void; // posle uspešne prijave
}
// SQLite: auth_attempts(key TEXT, at INTEGER); backoff = min(2^(n-5), 900) s -> 429 + Retry-After
```

- [ ] **🟥 \[Visok - Security Architect\] 3. Nema politike lozinke i korisničkog imena (potvrđeno probom)**

🔍 **Opis:** Probom su prihvaćeni: lozinka `x` (1 karakter), korisničko ime od 100.000 karaktera, `аdmin` sa ćiriličnim "а", ime sa RTL override znakom U+202E, i `Ünï` kao poseban nalog od `ÜNÏ` (`COLLATE NOCASE` poznaje samo ASCII). Postoji jedino gornja granica lozinke od 72 bajta.

💡 **Rizik:** Lažno predstavljanje admina u audit tabeli (homoglif, bidi spoofing na Statistics stranici), slabe lozinke, naduvavanje baze jer se ime kopira kao snapshot u svaki događaj, i nepoverljiv slobodan tekst koji kasnije stiže u logove ili AI alate.

🛠️ **Rešenje:** Value objekti koji ne dozvoljavaju nevalidno stanje.

```php
final readonly class Username {
    private function __construct(public string $display, public string $canonical) {}
    public static function from(string $raw): self {
        $n = \Normalizer::normalize(trim($raw), \Normalizer::FORM_KC) ?: '';
        $c = mb_strtolower($n, 'UTF-8');
        if (!preg_match('/^[a-z0-9._-]{3,32}$/', $c)) throw new InvalidUsername();
        return new self($n, $c);   // UNIQUE nad username_canonical
    }
}
final readonly class PlainPassword {
    public function __construct(#[\SensitiveParameter] private string $v) {
        if (mb_strlen($v) < 12 || mb_strlen($v) > 128) throw new PasswordRejected();
    }
}
```

- [ ] **🟥 \[Visok - Security Architect\] 4. Hash algoritam, cost i timing enumeracija korisnika**

🔍 **Opis:** `PASSWORD_BCRYPT` bez opcija daje cost 10 na PHP 8.3 i cost 12 na 8.4+ (CI matrica 8.3/8.4/8.5 se ponaša različito). Za nepostojećeg korisnika `authenticate()` vraća odmah (izmereno 0,13 ms naspram 59 ms), a limit od 72 bajta je zaobilaznica za bcrypt.

💡 **Rizik:** Napadač razlikuje postojeća imena po vremenu odgovora (enumeracija pred brute force); slabiji cost na 8.3; hash-evi se tiho razlikuju između okruženja.

🛠️ **Rešenje:** Interfejs `PasswordHasher`, Argon2id (`ext-sodium` je dostupan) sa eksplicitnim parametrima i dummy verifikacija.

```php
interface PasswordHasher { public function hash(string $p): string; public function verify(string $p, string $h): bool; public function needsRehash(string $h): bool; }

final class Argon2idHasher implements PasswordHasher {
    private const OPTS = ['memory_cost' => 65536, 'time_cost' => 3, 'threads' => 1];
    public function hash(string $p): string { return password_hash($p, PASSWORD_ARGON2ID, self::OPTS); }
    /* verify, needsRehash: password_verify / password_needs_rehash(..., self::OPTS) */
}
// Authenticator: kad nalog ne postoji, verify($p, self::DUMMY_HASH) pa vrati null (isti trošak).
// Postojeći bcrypt hash-evi se migriraju prirodno kroz needsRehash() pri sledećoj prijavi.
```

- [ ] **🟥 \[Visok - Principal Platform Architect\] 5. SQLite u `delete` journal modu i web zahtev koji može da napravi bazu**

🔍 **Opis:** `SqliteConnection` ne postavlja `journal_mode` (probom: `delete`, `synchronous=2`). Svaki GET na Page A/B je upis (activity event + sesija), a Statistics čita istu bazu. `PRAGMA foreign_keys = ON` ne radi ništa jer šema nema nijedan strani ključ. Pored toga `open()` pravi direktorijum (0775) i prazan fajl ako ne postoje.

💡 **Rizik:** Pisac blokira čitaoce, pa pod opterećenjem sledi `SQLITE_BUSY` i HTTP 500 posle 5 s. Bez migracija web proces tiho napravi praznu bazu sa pogrešnim vlasnikom i dozvolama, a greška izgleda kao "no such table".

🛠️ **Rešenje:** WAL pri provisioning-u, u web kontekstu samo otvaranje postojeće baze, provera šeme pri bootu.

```php
// bin/migrate.php (jedino mesto koje sme da kreira)
$pdo->exec('PRAGMA journal_mode=WAL; PRAGMA synchronous=NORMAL;');

// web: bez CREATE flaga
$pdo = new PDO('sqlite:' . $path, null, null, [
    PDO::SQLITE_ATTR_OPEN_FLAGS => PDO::SQLITE_OPEN_READWRITE,
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$pdo->exec('PRAGMA busy_timeout=5000');
// SchemaGuard: SELECT max(migration) mora da se poklopi sa očekivanim, a /healthz vraća 503
// Dozvole: direktorijum 0750, fajl 0640, vlasnik servisni nalog
```

- [ ] **🟥 \[Visok - Performance Engineer\] 6. Reports je O(N), a Statistics filteri skeniraju celu tabelu (potvrđeno query planom)**

🔍 **Opis:** `EXPLAIN QUERY PLAN` daje: Reports `SCAN activity_events USING INDEX idx_activity_day` (agregira sve redove pa zadrži 30 dana); filter po korisniku i po akciji `SCAN ... idx_activity_recent` (nema indeksa na `actor_label` ni `action`); filter po datumu dodaje `USE TEMP B-TREE FOR ORDER BY`. Paginacija je `LIMIT/OFFSET`, a autor je sam pokazao ponovljen red pri paralelnom upisu.

💡 **Rizik:** Vreme odgovora raste linearno sa istorijom; pri milion i više događaja stranice za administratore postaju spore i drže SQLite lock. `LIMIT 30` ograničava veličinu odgovora, ne cenu. Testovi sa 5.000 redova ovo ne mogu da uhvate.

🛠️ **Rešenje:** Dnevni rollup u istoj transakciji, odgovarajući indeksi i keyset paginacija.

```sql
CREATE TABLE activity_daily (day TEXT PRIMARY KEY, page_a_views INT NOT NULL DEFAULT 0,
  page_b_views INT NOT NULL DEFAULT 0, buy_cow_clicks INT NOT NULL DEFAULT 0, download_clicks INT NOT NULL DEFAULT 0);
-- pri upisu događaja, u istoj transakciji:
INSERT INTO activity_daily(day, page_a_views) VALUES (:d, 1)
  ON CONFLICT(day) DO UPDATE SET page_a_views = page_a_views + 1;

CREATE INDEX idx_act_actor  ON activity_events(actor_id, occurred_at DESC, id DESC);
CREATE INDEX idx_act_action ON activity_events(action,   occurred_at DESC, id DESC);
-- keyset umesto OFFSET:
WHERE (occurred_at, id) < (:t, :id) ORDER BY occurred_at DESC, id DESC LIMIT 51
-- datum kao opseg: occurred_at >= :d AND occurred_at < :d_next
```

Dodati test koji pada ako `EXPLAIN QUERY PLAN` sadrži `SCAN`, i `rebuild-daily` komandu.

- [ ] **🟥 \[Visok - Principal Software Architect\] 7. Domen zavisi od infrastrukture, nema portova**

🔍 **Opis:** `SessionIdentity` (Accounts) uvozi `Infrastructure\Session\PhpSession` i `Infrastructure\Time\SystemClock`; opcioni `?ClockInterface $clock = null` tiho pravi `new SystemClock()`. `Accounts` i `ActivityLog` rade direktno nad `PDO`, a `CowOffer` i `Downloads` zavise od `final` klase `ActivityLog`. `architecture.yaml` ovu zavisnost (Accounts → Infrastructure) izričito dozvoljava. Kontroleri žive u `Bootstrap\Http` i dohvataju templejte stringom `'src/Accounts/Register/form.php'`.

💡 **Rizik:** Narušeno Dependency Inversion: domen se ne može testirati bez `$_SESSION` i prave baze (testovi sada ručno rade `session_destroy()` i `ini_set`), a zamena sesije (Redis, DB) ili baze (PostgreSQL) znači izmenu poslovnog koda. Opcioni klok sakriva grešku u wiring-u.

🛠️ **Rešenje:** Portovi u modulu, adapteri u `Infrastructure`, obrnut smer u Deptrac-u; kontroleri u `src/Web`.

```php
// src/Accounts/Port/*.php  (modul poseduje interfejse)
interface SessionStore { public function get(string $k): mixed; public function put(string $k, mixed $v): void; public function regenerate(): void; public function destroy(): void; }
interface AccountRepository { public function findById(int $id): ?Account; public function findByUsername(Username $u): ?StoredCredentials; public function add(Account $a, string $hash): void; }
// src/ActivityTracking/Port/ActivityRecorder.php
interface ActivityRecorder { public function record(ActivityEvent $e): void; }

// deptrac: Infrastructure -> Accounts|ActivityTracking (portovi); NE obrnuto.
// SessionIdentity::__construct(SessionStore $s, AccountRepository $r, ClockInterface $clock) // klok obavezan
```

- [ ] **🟥 \[Visok - Staff Software Engineer\] 8. `Accounts` je god-klasa, a `CowOffer` je prazna ljuska**

🔍 **Opis:** `Accounts` meša politiku lozinke (72 bajta), hash-ovanje, SQL, autentikaciju, mapiranje redova i rehash upis. `trim($username)` je ponovljen u `Register`, `Login` i `CreateAdmin`, od kojih je svaka klasa sa jednom metodom. `CowOffer::view()/buy()` samo prosleđuju poziv u `ActivityLog`, `DownloadFile::view()` je isti kod, a `buy()` vraća magični string `'thankYou'` koji kontroler poredi.

💡 **Rizik:** Sve promene (nova pravila, drugi hasher, druga baza) pogode jednu klasu, pa regresije u autentikaciji nastaju lako; ceremonija bez poslovne vrednosti poskupljuje svaku izmenu. Praćenje prikaza je cross-cutting briga duplirana u dva modula.

🛠️ **Rešenje:** Razdvojiti odgovornosti, a praćenje prikaza prebaciti u middleware.

```php
final class Account {                                   // ima ponašanje, ne samo podatke
    private function __construct(public readonly int $id, public readonly Username $name, public readonly Role $role) {}
    public static function register(Username $n, PlainPassword $p, PasswordHasher $h, AccountRepository $r): self { /* ... */ }
    public function isAdmin(): bool { return $this->role === Role::Admin; }
}
final readonly class Authenticator { /* findByUsername + dummy verify + needsRehash */ }

// ruta deklariše praćenje, modul CowOffer nestaje:
$router->get('/page-a', $pages->pageA(...), [Authenticate::class, TrackPageView::of(TrackedPage::PageA)]);
```

## 🟨 Srednji prioritet

- [ ] **🟨 \[Srednji - Security Architect\] 9. Sigurni default-i su fail-open, a konfiguracija je neprovereno polje**

🔍 **Opis:** `SESSION_COOKIE_SECURE` je podrazumevano 0, `.env.example` ima `APP_DEBUG=1`, a `APP_ENV`, `APP_DEBUG` i `APP_URL` se nigde ne koriste. Sigurnosni headeri se dodaju samo u `HtmlPage` (403/404/405/500, redirecti i download ih nemaju), CSP koristi `style-src 'unsafe-inline'` (7 templejta ima kopiran `<style>`), nema HSTS ni Permissions-Policy. Konfiguracija je netipiziran niz, a `.env` se ručno parsira na svaki zahtev i puni preko `putenv` (nije thread-safe).

💡 **Rizik:** Deploy sa zaboravljenom promenljivom radi bez Secure kolačića preko HTTP-a, bez greške i upozorenja; odgovori bez `nosniff` otvaraju MIME sniffing; `unsafe-inline` slabi odbranu od XSS-a.

🛠️ **Rešenje:** Tipizirana konfiguracija sa fail-fast validacijom i globalni middleware za headere.

```php
final readonly class AppConfig {
    public function __construct(public Env $env, public string $dbPath, public bool $secureCookie) {
        if ($env === Env::Production && !$secureCookie) {
            throw new ConfigError('Production zahteva SESSION_COOKIE_SECURE=1 (HTTPS).');
        }
    }
}
// SecurityHeaders middleware na SVE odgovore:
// X-Content-Type-Options: nosniff, Referrer-Policy: no-referrer, Permissions-Policy: ()
// Strict-Transport-Security (kad je secure), CSP: default-src 'none'; style-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'
// CSS u public/assets/app.css; klase umesto style="..." atributa
```

- [ ] **🟨 \[Srednji - Reliability Engineer\] 10. Sesije: istek, skladište, anonimne sesije i lock**

🔍 **Opis:** Idle limit aplikacije je 1800 s, a PHP `session.gc_maxlifetime` je podrazumevano 1440 s. `save_path` je deljeni tmp. Svaki anonimni GET na `/` ili `/login` otvara sesiju i fajl (zbog CSRF tokena). Sesija drži lock tokom celog zahteva, čak i kada samo čita. Nisu postavljeni `use_only_cookies` ni `use_trans_sid=0`.

💡 **Rizik:** Sesije se brišu pre limita (korisnik se odjavljuje "bez razloga"); botovi prave neograničen broj fajlova; spora Statistics stranica blokira sve ostale zahteve istog korisnika; deljeni tmp izlaže sesije drugim PHP pool-ovima.

🛠️ **Rešenje:**

```php
ini_set('session.gc_maxlifetime', '43200');        // >= absolute lifetime
ini_set('session.save_path', '/var/lib/app/sessions'); // 0700, vlasnik servisni nalog
ini_set('session.use_only_cookies', '1');
ini_set('session.use_trans_sid', '0');
session_start(['read_and_close' => true]);         // za zahteve koji samo čitaju
// Pre-auth forme (login/register): stateless CSRF = HMAC(secret, cookie_nonce), bez sesije.
// Cookie ime "__Host-fast_task" (zahteva Secure, Path=/, bez Domain).
```

- [ ] **🟨 \[Srednji - Reliability Engineer\] 11. Audit nije append-only, a vreme je string (potvrđeno probom)**

🔍 **Opis:** README tvrdi "append-only", ali `UPDATE activity_events SET actor_label='tampered'` i `DELETE` prolaze. Datum se izvodi sa `substr(occurred_at, 1, 10)` nad ISO stringom, a `accounts.created_at` koristi `format('c')` bilo kog klok-a pa ne-UTC klok upiše lokalni ofset.

💡 **Rizik:** Audit koji svako sa pristupom bazi (ili ranjivost u budućem kodu) može da menja nema dokaznu vrednost; miješanje ofseta kvari filtere po datumu i izveštaje.

🛠️ **Rešenje:**

```sql
CREATE TRIGGER activity_no_update BEFORE UPDATE ON activity_events BEGIN SELECT RAISE(ABORT, 'append-only'); END;
CREATE TRIGGER activity_no_delete BEFORE DELETE ON activity_events BEGIN SELECT RAISE(ABORT, 'append-only'); END;
-- vreme: INTEGER epoch (UTC) ili kolona  occurred_on TEXT GENERATED ALWAYS AS (substr(occurred_at,1,10)) STORED  + indeks
-- retencija/arhiviranje kroz eksplicitan job koji privremeno koristi poseban DB nalog
```

- [ ] **🟨 \[Srednji - Reliability Engineer\] 12. `Migrator` nema checksum, lock ni stabilan redosled**

🔍 **Opis:** Izmenjena već primenjena migracija se tiho preskoči (autor je to proverio: `migrate()` vraća `[]`). Dva paralelna pokretanja nisu zaštićena. Redosled je po ključu `Owner:fajl`, pa Accounts uvek ide pre ActivityTracking bez obzira na vremensku liniju. Nema `--status`.

💡 **Rizik:** Shema se razilazi između okruženja bez ikakvog upozorenja; dva deploy čvora mogu istovremeno da primene istu migraciju.

🛠️ **Rešenje:**

```php
$pdo->exec('BEGIN IMMEDIATE');                       // lock protiv paralelnog pokretanja
$hash = hash('sha256', $sql);
if (isset($applied[$id]) && $applied[$id] !== $hash) {
    throw new MigrationDrift("$id je izmenjena posle primene");
}
// ledger: schema_migrations(migration, checksum, applied_at); imena fajlova 20261008_1200_accounts_create.sql
```

- [ ] **🟨 \[Srednji - Staff Software Engineer\] 13. Dvostruki klik i osvežavanje naduvavaju izveštaj**

🔍 **Opis:** `POST /buy-cow` i `POST /download` odmah renderuju odgovor (bez Post/Redirect/Get). Ponovno slanje forme (refresh, dvoklik, replay zahteva) upisuje novi `button-click`, a Reports ga broji.

💡 **Rizik:** Metrike koje su smisao cele aplikacije su netačne, a browser pri osvežavanju prikazuje dijalog "Confirm Form Resubmission".

🛠️ **Rešenje:** Jednokratni nonce po formi i PRG.

```php
$nonce = $this->nonces->issue('buy-cow');            // upisuje se u formu
// pri POST-u:
if (!$this->nonces->consume('buy-cow', $request->post('nonce'))) { return Response::redirect('/page-a'); }
$this->recorder->record(ActivityEvent::click(TrackedButton::BuyCow, $actor));
$this->flash->put('thankYou');                       // prikazuje se jednom na sledećem GET-u
return Response::redirect('/page-a');                // 303
```

- [ ] **🟨 \[Srednji - Principal Software Architect\] 14. Validacija preko `InvalidArgumentException` i nizovi umesto objekata**

🔍 **Opis:** Greške korisničkog unosa se signaliziraju sa `InvalidArgumentException` (tip za programersku grešku), a `ActivityPages` prikazuje `$e->getMessage()` korisniku. Provera `page` je u kontroleru, provera datuma u domenu, a parsiranje datuma je dupliran kod u `Statistics` i `DailyReports`; NUL bajt u datumu daje `ValueError` i HTTP 500 (autorov OOP-01). `Statistics::page()` vraća `array{events, hasNext}`, a templejti kastuju `(int) $day[$key]`.

💡 **Rizik:** Svaki `InvalidArgumentException` iz dubljeg sloja postaje 422 sa internom porukom (curenje informacija); stringly-typed nizovi kriju greške koje PHPStan ne vidi; ista logika na dva mesta se razilazi.

🛠️ **Rešenje:** Tipizirani objekti sa jednom validacijom.

```php
final readonly class UtcDay {
    private function __construct(public string $value) {}
    public static function parse(string $s): self {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $s)) throw ValidationFailed::field('date', 'Format YYYY-MM-DD');
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $s, new \DateTimeZone('UTC'));
        if (!$d || $d->format('Y-m-d') !== $s) throw ValidationFailed::field('date', 'Nepostojeći datum');
        return new self($s);
    }
}
final readonly class ActivityPage { /** @param list<ActivityEvent> $items */ public function __construct(public array $items, public ?Cursor $next) {} }
```

- [ ] **🟨 \[Srednji - Senior Software Engineer\] 15. Grafik na Reports obmanjuje i ima netačne oznake (potvrđeno probom)**

🔍 **Opis:** X osa je kategorijska (ravnomeran razmak) i prikazuje samo dane sa aktivnošću: za događaje 2026-01-01, 2026-01-02 i 2026-03-15 treća tačka je nacrtana odmah pored druge, a linija povezuje dva i po meseca bez podataka. Dani sa nulom nisu ni u tabeli iako zadatak traži "daily counts". Y oznake: `round($max * $i / 4)` za `max=1` daje 0, 0, 1, 1, 1 (isto za svako `max < 4`).

💡 **Rizik:** Administrator izvlači pogrešne zaključke o trendu; duplirane oznake izgledaju kao greška u aplikaciji. Serije se razlikuju samo bojom (nepristupačno), a zelena `#16a34a` kao tekst ima oko 3,3:1 kontrast.

🛠️ **Rešenje:** Kontinuiran kalendar sa nulama i `LineChart` presenter sa unit testovima.

```php
$days = new \DatePeriod($from, new \DateInterval('P1D'), $to->modify('+1 day'));
foreach ($days as $d) { $row = $byDay[$d->format('Y-m-d')] ?? DailyActivity::zero($d); }   // nule uključene
$step = max(1, (int) ceil($max / 4));                    // "nice" korak
$ticks = range(0, (int) ceil($max / $step) * $step, $step);   // npr. max=1 -> [0, 1]
// X pozicija proporcionalna vremenu: x = left + (dayIndex / (totalDays - 1)) * width
```

- [ ] **🟨 \[Srednji - Principal Platform Architect\] 16. HTTP sloj: HEAD/OPTIONS, greške, logovanje, eager wiring**

🔍 **Opis:** `HEAD` i `OPTIONS` na postojeće GET rute vraćaju 405 (probano). `Response` ne može da nosi više headera istog imena niti stream. Jedan `catch (Throwable)` u `index.php` sa `error_log((string) $error)`, bez request id-a. `routes.php` pri svakom zahtevu otvara PDO (i za 404), parsira `.env` i gradi ceo graf. Nema `/healthz`. `parse_url('//page-a')` vraća `null` pa se zahtev tiho preusmerava na `/`.

💡 **Rizik:** Load balancer i uptime monitori lome se na HEAD; u produkciji se greška ne može povezati sa zahtevom; nepotreban trošak na svakom zahtevu; neispravni URI-ji se maskiraju.

🛠️ **Rešenje:**

```php
// Router: HEAD = GET bez tela; OPTIONS vraća 204 + Allow
if ($method === 'HEAD' && isset($routes['GET'][$path])) { return $dispatchGet()->withoutBody(); }
// ErrorBoundary middleware + PSR-3 log sa request id
$log->error('unhandled', ['request_id' => $rid, 'exception' => $e]);   // Monolog JSON handler
// GET /healthz: liveness bez baze; GET /readyz: SchemaGuard + SELECT 1
// 400 Bad Request kada parse_url vrati false/null za putanju
// Lazy servisi: ServiceFactory::pdo() otvara bazu tek kad zatreba
```

- [ ] **🟨 \[Srednji - OSS Technology Evaluator\] 17. Ponovo izmišljen točak na pet mesta**

🔍 **Opis:** Sopstveni `Request`/`Response`/`Router`, ručni `.env` parser, `error_log` umesto PSR-3, sirovi PHP templejti sa kopiranim CSS-om i `$escape` magijom, ručno SVG računanje u templejtu.

💡 **Rizik:** Svaki od njih je već rešen i testiran u malim bibliotekama; sopstvene verzije ne podržavaju HEAD, višestruke headere, stream, dotenv sintaksu; održavanje pada na tim. Zadatak preporučuje vanilla PHP, pa se preporučuju **interfejsi i male biblioteke, ne framework**.

🛠️ **Rešenje:** (verzije proveriti na Packagist-u pre usvajanja)

```bash
composer require nyholm/psr7 nikic/fast-route monolog/monolog league/plates
composer require --dev symfony/dotenv vimeo/psalm infection/infection roave/security-advisories:dev-latest
```

| Oblast | Preporuka | Odluka |
| --- | --- | --- |
| HTTP poruke i middleware | PSR-7 (`nyholm/psr7`) + PSR-15 oblik, ruter sopstveni ili `fast-route` | Usvojiti |
| Templejti | `league/plates` (nativni PHP, layout) ili Twig | Usvojiti jedno |
| Logovanje | `psr/log` + `monolog/monolog` | Usvojiti |
| Lozinke | Nativni Argon2id, HIBP k-anonymity iza porta | Nativno |
| Migracije | Zadržati sopstveni, očvrsnuti (stavka 12); Phinx je previše za 3 fajla | Zadržati |
| Framework (Slim, Symfony, Laravel) | Protivreči duhu zadatka | Ne |

- [ ] **🟨 \[Srednji - Staff Software Engineer\] 18. Testovi i CI ne štite ono što je bitno**

🔍 **Opis:** Testovi ručno rade `session_destroy()`, `$_SESSION = []`, `ini_set('session.use_cookies')`, a `HttpFlowTest` ima 610 linija nad globalnim stanjem. E2E smoke test je napisan u Pythonu. Ne postoje testovi za: CSRF na svakoj ruti, headere na 4xx/5xx, HEAD, politiku imena i lozinke, throttling, WAL, trigere, geometriju grafika, `EXPLAIN QUERY PLAN`, paralelni Migrator, dvostruki POST. CI koristi pokretne tagove (`actions/checkout@v5`, `setup-php@v2`), nema pokrivenosti (`coverage: none`) ni mutation testiranja. Dev zavisnosti nisam mogao da instaliram u sandbox-u, pa autorovih 86 testova nisam sam pokrenuo.

💡 **Rizik:** Zeleni testovi ne dokazuju sigurnost; refaktori iz stavki 1 do 8 bez mreže mogu tiho da promene ponašanje; kompromitovan tag akcije izvršava tuđ kod u CI-ju.

🛠️ **Rešenje:**

```php
// Ugovor ruta: svaka ne-GET ruta mora imati VerifyCsrf u lancu
foreach ($router->routes() as $r) {
    if (!in_array($r->method, ['GET', 'HEAD'], true)) self::assertContains(VerifyCsrf::class, $r->middleware);
}
```

Prvi korak: `composer quality` i smoke test na čistom checkout-u kao polazna linija, pa characterization testovi za sve rute. Zatim `InMemorySessionStore`, Psalm taint analiza, Infection (MSI ≥ 80% nad `Accounts` i `ActivityTracking`), pcov sa pragom, akcije pinovane na commit SHA uz Dependabot, `composer audit` po rasporedu, gitleaks i Semgrep.

## 🟩 Nizak prioritet

- [ ] **🟩 \[Nizak - Staff Software Engineer\] 19. Dokumentacija je veća od proizvoda, a docblock-ovi su šum**

🔍 **Opis:** Oko 2.700 linija dokumenata (`ARCHITECTURE-GOVERNANCE.md` sam ima 1.873) naspram oko 2.300 linija aplikacije, sedam konfiguracija alata i sopstveni `check-phpdocs.php` koji nameće boilerplate: `@param AccountLifecycle $lifecycle Input value for lifecycle.`, `@return Response Result described by the native PHP return type.`

💡 **Rizik:** Docblock koji ponavlja tip ne daje informaciju, razilazi se od koda i skriva one retke koji su važni (`@throws`, oblik niza, invarijanta). Recenzent prvo čita šest dokumenata umesto koda. Autor to u OOP-15 i sam priznaje.

🛠️ **Rešenje:** Obrisati tautološke docblock-ove, gate svesti na `@throws` i oblike nizova, governance dokument zameniti jednostranim ADR-om (Deptrac je izvršiva istina), a README skratiti na jedan ekran.

```php
// Pre: /** @param Request $request Raw HTTP request; validate client-supplied values before use. @return Response Result described by the native PHP return type. */
// Posle (jedino što tip ne kaže):
/** @throws ValidationFailed kada filter nije u formatu YYYY-MM-DD */
public function page(ActivityFilter $f, ?Cursor $after): ActivityPage
```

- [ ] **🟩 \[Nizak - Principal Software Architect\] 20. Template sloj: kopiran CSS, bez layout-a, logika u templejtu**

🔍 **Opis:** Sedam templejta ima isti `<style>` blok; nema layout-a ni partiala. `$data` i `$escape` su magične lokalne promenljive (PHPStan ih ne vidi), `require` unutar metode daje templejtu `$this` (Renderer), a `reports.php` računa SVG geometriju i `statistics.php` radi `array_filter` za paginaciju.

💡 **Rizik:** Izmena izgleda znači sedam izmena; logika u templejtu se ne može unit testirati (vidi stavku 15, grafik); `$this` u templejtu otvara nepotrebnu površinu.

🛠️ **Rešenje:** Layout + ViewModel, presenter za grafik, izolovan scope.

```php
$render = static function (string $file, array $vars, callable $e): string {   // static: nema $this
    $escape = $e; extract($vars, EXTR_SKIP); ob_start(); require $file; return (string) ob_get_clean();
};
final readonly class LineChart { public function __construct(/* serije, dani */) {} public function ticks(): array {} public function points(): array {} }
```

- [ ] **🟩 \[Nizak - Security Architect\] 21. Download artefakt: ceo fajl u memoriji, binarni `.exe` u repou**

🔍 **Opis:** `DownloadFile::read()` radi `file_get_contents` pa `strlen` i vraća ceo sadržaj kao string. `assets/sample.exe` je binarni fajl u repou (SHA-256 `31a97b0f...cb80`). Ime fajla je "sample.exe" u `DownloadPages`, a putanja u `routes.php`, dakle na dva mesta.

💡 **Rizik:** Veći artefakt troši memoriju po zahtevu; AV/EDR i korporativni skeneri označavaju `.exe` u repou; nema provere integriteta pa zamenjen fajl isporučuju svi korisnici. Isporuka izvršnih fajlova je supply-chain rizik.

🛠️ **Rešenje:**

```php
final readonly class Artifact { public function __construct(public string $path, public string $name, public string $sha256) {} }
// pri slanju:
if (!hash_equals($artifact->sha256, hash_file('sha256', $artifact->path))) throw new ArtifactUnavailable();
header('Content-Disposition: attachment; filename="sample.exe"; filename*=UTF-8\'\'sample.exe');
header('Content-Length: ' . filesize($artifact->path));
readfile($artifact->path);                 // stream; ili X-Accel-Redirect / X-Sendfile iza web servera
```

Artefakt generisati deterministički u buildu (ili čuvati checksum u konfiguraciji); u produkciji potpisati (Authenticode) i objaviti checksum.

- [ ] **🟩 \[Nizak - Senior Software Engineer\] 22. Higijena koda i mrtva logika**

🔍 **Opis:** `Statistics::events()` postoji samo zbog testova; `Response::html()` se nigde ne koristi; `reportsPage(?Request $request = null)` ima nepotreban null; `Migrator` ima suvišan `private readonly` u `final readonly class`; provere `$statement === false` su mrtve pod `ERRMODE_EXCEPTION`; `PhpSession`, `Csrf`, `SystemClock`, `Router`, `Renderer` su konkretne `final` klase bez interfejsa na granici koja se menja.

💡 **Rizik:** Mrtav kod zbunjuje, privlači pogrešne zavisnosti i daje lažnu pokrivenost.

🛠️ **Rešenje:** `shipmonk/dead-code-detector` i Rector dead-code set u CI, interfejsi samo na granicama koje se zamenjuju (sesija, klok, hasher, repozitorijumi).

```bash
composer require --dev shipmonk/dead-code-detector phpstan/phpstan-strict-rules
```

- [ ] **🟩 \[Nizak - Reliability Engineer\] 23. Rubni slučajevi transakcija i sesije**

🔍 **Opis:** `Transaction::run()` ne podržava ugnježdavanje (`beginTransaction()` u aktivnoj transakciji baca izuzetak), a neuspeo `rollBack()` može da zamaskira originalni izuzetak. Prijava menja sesiju pre upisa audit događaja pa se kompenzuje sa `signOut()`; ako i on baci, gubi se uzrok. `SessionIdentity` uništava sesiju ako je `issuedAt > now`, bez tolerancije.

💡 **Rizik:** Na više čvorova sa malim odstupanjem sata korisnici se nasumično odjavljuju; dijagnostika greške u kompenzaciji je teška.

🛠️ **Rešenje:**

```php
try { $this->identity->signOut(); } catch (\Throwable $cleanup) { throw new \RuntimeException('audit failed', 0, $original); }
private const CLOCK_SKEW = 5;                       // sekundi
if ($issuedAt > $now + self::CLOCK_SKEW) { /* destroy */ }
// Transaction: depth counter + SAVEPOINT sp_N za ugnježene pozive, ili fail-fast sa jasnom porukom
```

- [ ] **🟩 \[Nizak - Security Architect\] 24. Upravljanje nalozima i sigurnosni log**

🔍 **Opis:** Nema statusa naloga (disabled), promene ni reseta lozinke; izgubljena admin lozinka znači novi admin preko CLI-ja. `bin/create-admin.php` koristi `shell_exec('stty')` (ne radi na Windows-u), ima zamršeno parsiranje `argv` i ne ostavlja trag. Neuspele prijave, CSRF odbijanja, 403 i pristup admin stranicama (sa korisničkim imenima) ne beleži niko. Zadatak dozvoljava samo uspešne akcije u `activity_events`, i to treba da ostane.

💡 **Rizik:** Nema načina da se kompromitovan nalog blokira bez brisanja iz baze; napad se ne vidi dok ne postane incident.

🛠️ **Rešenje:** Kolona `status` sa proverom u repozitorijumu, `bin/reset-password.php`, i odvojen strukturisan sigurnosni log.

```php
$security->warning('login_failed', ['username' => $canonical, 'ip_hash' => hash('sha256', $ip . $salt), 'request_id' => $rid]);
// ALTER TABLE accounts ADD COLUMN status TEXT NOT NULL DEFAULT 'active' CHECK (status IN ('active','disabled'));
```

- [ ] **🟩 \[Nizak - AI / Agent Infrastructure Architect\] 25. Spremnost za rad sa agentima i nepoverljivi tekst**

🔍 **Opis:** Proizvod nema AI površinu i ne treba je dodavati. Ali repo nema `AGENTS.md` ni jedinstvenu komandu koja pokreće sve gate-ove, a korisnička imena su slobodan tekst u audit tabeli.

💡 **Rizik:** Agent (ili novi čovek) menja kod bez poznavanja invarijanti (bez uvoza između modula, CSRF kroz middleware) i bez determinističke provere; ako se kasnije doda AI asistent nad Statistics podacima, korisnička imena su vektor prompt injection-a.

🛠️ **Rešenje:** `AGENTS.md` sa komandama, invarijantama i definicijom "gotovo"; jedan `composer ci`; allowlist imena iz stavke 3 zatvara injection kanal; izvršiva arhitektura (Deptrac + `PublicSurfaceTest`) ostaje najbolja zaštita od agentskih refaktora.

```json
"scripts": { "ci": ["@check:composer", "@check:psr4", "@security:audit", "@stan", "@architecture", "@test", "python3 tests/Flow/live_smoke.py"] }
```

- [ ] **🟩 \[Nizak - Staff Software Engineer\] 26. Imenovani argumenti na ugrađenim PHP funkcijama (mišljenje)**

🔍 **Opis:** `trim(string: ..., characters: ...)`, `ini_set(option: ..., value: ...)`, `session_regenerate_id(delete_old_session: true)` svuda, uz poseban dokument koji to brani.

💡 **Rizik:** Imena parametara internih funkcija nisu deo BC garancije i menjala su se kroz verzije (CI matrica 8.3 do 8.5); kod postaje duži i teži za čitanje. Ovo je stilska odluka vlasnika, pa je niskog prioriteta.

🛠️ **Rešenje:** Imenovane argumente zadržati za sopstvene API-je sa 3 ili više parametara ili boolean zastavicama, a za ugrađene funkcije koristiti pozicione.

```php
ini_set('display_errors', '0');                 // umesto ini_set(option: 'display_errors', value: '0')
$pageA = $recorder->record(event: $event, actor: $actor, at: $clock->now());   // sopstveni API: imenovano
```
