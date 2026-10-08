## Visok Prioritet

---

### 1. 🟥 [Visok - Security Architect] Ranjivost na Arbitrary File Read / Path Traversal u modulima za preuzimanje (`DownloadFile.php`)

🔍 **Opis problema:**
Putanja do fajlova unutar `src/Downloads/DownloadFile.php` konstruiše se spajanjem ulaznih parametara bez rigorozne kanonizacije i provere granica dozvoljenog direktorijuma.

💡 **Obrazloženje i Rizik:**
Napadač slanjem relativnih putanja sa sekvencama za navigaciju kroz direktorijume (npr. `?file=../../.env` ili `../../../../etc/passwd`) može pročitati osetljive konfiguracione fajlove, privatne ključeve ili sistemske fajlove servera. U produkcionom okruženju ovo dovodi do potpune kompromitacije sistema i curenja tajni.

🛠️ **Predlog rešenja:**
Obavezno validirajte putanju pomoću `realpath()` i uverite se da se razrešena putanja nalazi unutar predviđenog korenskog direktorijuma pre bilo kakvog čitanja ili slanja fajla:

```php
public function resolvePath(string $filename): string
{
    $baseDir = realpath('/path/to/storage/downloads');
    $filePath = realpath($baseDir . DIRECTORY_SEPARATOR . $filename);

    if ($filePath === false || !str_starts_with($filePath, $baseDir)) {
        throw new ArtifactUnavailable('Access denied or file not found.');
    }

    return $filePath;
}

```

---

### 2. 🟥 [Visok - Security Architect] Neodgovarajuća provera CSRF tokena i tajming ranjivost (`infrastructure/Session/Csrf.php`)

🔍 **Opis problema:**
Poređenje CSRF tokena u `Csrf.php` koristi standardne operatore poređenja (`!==` / `!=`) umesto vremenski konstantne funkcije. Takođe, nedostaju sigurna podešavanja parametara kolačića sesije (`SameSite`, `HttpOnly`, `Secure`).

💡 **Obrazloženje i Rizik:**
Upotreba `!==` prekida poređenje na prvom karakteru koji se ne poklapa. Napadač može merenjem preciznog vremena odziva servera (Side-Channel Timing Attack) rekonstruisati validan CSRF token karakter po karakter i izvršiti neovlašćene izmene u ime legitimnog korisnika.

🛠️ **Predlog rešenja:**
Koristite ugrađenu PHP funkciju `hash_equals()` i konfigurišite bezbedne atribute sesijskih kolačića:

```php
// infrastructure/Session/Csrf.php
public function validate(?string $providedToken): bool
{
    $storedToken = $this->session->get('csrf_token');

    if (!$storedToken || !$providedToken) {
        return false;
    }

    return hash_equals($storedToken, $providedToken);
}

// infrastructure/Session/PhpSession.php
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax',
]);

```

---

### 3. 🟥 [Visok - Performance & Reliability Engineer] Concurrency usko grlo i rizik od "Database Locked" u SQLite drajveru (`SqliteConnection.php`)

🔍 **Opis problema:**
SQLite konekcija se inicijalizuje sa podrazumevanim `DELETE` journal režimom i bez podešenog timeout-a za zauzetost baze (`busy_timeout`).

💡 **Obrazloženje i Rizik:**
U višenitnom/višeprocesnom okruženju (npr. PHP-FPM sa više radnih procesa), svako upisivanje u SQLite bazu zaključava čitavu bazu. Bez `WAL` (Write-Ahead Logging) moda i odgovarajućeg timeout-a, paralelni čitajući i pišući zahtevi će masovno padati sa izuzetkom `PDOException: database is locked`.

🛠️ **Predlog rešenja:**
Aktivirajte `WAL` režim i podesite `busy_timeout` od 5000ms odmah po otvaranju PDO konekcije:

```php
// infrastructure/Database/SqliteConnection.php
public function connect(): PDO
{
    $pdo = new PDO($this->dsn, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $pdo->exec('PRAGMA journal_mode = WAL;');
    $pdo->exec('PRAGMA busy_timeout = 5000;');
    $pdo->exec('PRAGMA synchronous = NORMAL;');

    return $pdo;
}

```

---

### 4. 🟥 [Visok - Security Architect & Performance Engineer] Nedostatak Rate Limiting mehanizma na autentičnim i osetljivim rutama (`Login.php`, `Register.php`)

🔍 **Opis problema:**
Sistem ne ograničava broj uzastopnih neuspešnih pokušaja prijave ili registracije na rutama za autentifikaciju.

💡 **Obrazloženje i Rizik:**
Omogućava neometane Brute-Force i Credential Stuffing napade. Pored toga, izračunavanje argon2id ili bcrypt heševa lozinki je namerno CPU-intenzivno, pa masovni neautorizovani POST zahtevi mogu dovesti do DoS-a (Denial of Service) iscrpljivanjem CPU resursa servera.

🛠️ **Predlog rešenja:**
Uvedite Middleware za ograničavanje protoka (Token Bucket ili Leaky Bucket algoritam) zasnovan na IP adresi i korisničkom imenu:

```php
final class RateLimiter
{
    public function check(string $key, int $maxAttempts = 5, int $decaySeconds = 60): bool
    {
        $attempts = $this->cache->get($key, 0);
        if ($attempts >= $maxAttempts) {
            return false;
        }
        $this->cache->set($key, $attempts + 1, $decaySeconds);
        return true;
    }
}

```

---

## Srednji Prioritet

---

### 5. 🟨 [Srednji - Principal Software Architect & Staff Engineer] Kršenje Dependency Inversion principa i direktno sprezanje sa konkretnim klasama

🔍 **Opis problema:**
Domenski servisi (`Accounts`, `ActivityLog`, `DownloadFile`) direktno instanciraju infrastructure zavisnosti (`new SqliteConnection()`, `new SystemClock()`) umesto da primaju apstrakcije (interfejse) kroz konstruktor.

💡 **Obrazloženje i Rizik:**
Direktno sprezanje onemogućava pisanje brzih i izolovanih jedinica testova (Unit Tests) bez stvarne baze, otežava zamenu infrastrukture u budućnosti i direktno krši SOLID principe (Dependency Inversion Principle).

🛠️ **Predlog rešenja:**
Uvedite interfejse i primenite Dependency Injection:

```php
interface ClockInterface
{
    public function now(): DateTimeImmutable;
}

final class ActivityLog
{
    public function __construct(
        private PDO $connection,
        private ClockInterface $clock
    ) {}
}

```

---

### 6. 🟨 [Srednji - Performance & Reliability Engineer] Nekoordinisano učitavanje kompletnih skupova podataka u memoriju (`DailyReports.php`, `Statistics.php`)

🔍 **Opis problema:**
Metode za izveštaje učitavaju sve zapise iz `activity_events` tabele u PHP memoriju pomoću `fetchAll()` kako bi naknadno vršile filtriranje i grupsanja u samom PHP kodu.

💡 **Obrazloženje i Rizik:**
Kako broj zapisa u produkciji raste na stotine hiljada ili milione događaja, izvođenje ovih upita će prekoračiti `memory_limit` PHP procesa i izazvati `Fatal Error: Allowed memory size exhausted`.

🛠️ **Predlog rešenja:**
Agregacije vršite direktno u bazi podataka pomoću SQL agregatnih funkcija ili koristite PDO kurzore za strimovanje podataka:

```php
// Izvršavanje agregacije u samoj bazi
public function getDailySummary(string $date): array
{
    $stmt = $this->pdo->prepare('
        SELECT action, COUNT(*) as total 
        FROM activity_events 
        WHERE DATE(created_at) = :date 
        GROUP BY action
    ');
    $stmt->execute(['date' => $date]);
    return $stmt->fetchAll();
}

```

---

### 7. 🟨 [Srednji - Security Architect] Propust u regeneraciji ID-a sesije prilikom prijave i izlogovanja (`AccountLifecycle.php`)

🔍 **Opis problema:**
Nakon uspešne autentifikacije korisnika ne poziva se `session_regenerate_id(true)`, niti se pri izlogovanju eksplicitno poništavaju sesijski kolačići na strani klijenta.

💡 **Obrazloženje i Rizik:**
Sistem je ranjiv na Session Fixation napad, gde napadač može podmetnuti poznati ID sesije žrtvi pre prijave i preuzeti njen identifikator nakon što se ona uspešno autentifikuje.

🛠️ **Predlog rešenja:**
Uvek regenerišite ID sesije pri svakoj promeni stanja autentifikacije:

```php
public function login(string $username, string $password): SignedInAccount
{
    // ... autentifikacija uspešna ...
    
    session_regenerate_id(true);
    $_SESSION['user_id'] = $account->id();

    return new SignedInAccount($account);
}

```

---

### 8. 🟨 [Srednji - OSS Technology Evaluator] Reinvencija točka: Prilagođeni HTTP Runtime, Router i Templating engine

🔍 **Opis problema:**
Sistem implementira sopstveni HTTP sloj (`Request`, `Response`, `Router`) i sablonski mehanizam umesto usvajanja proverenih industrijskih standarda (PSR-7, PSR-15, PSR-11).

💡 **Obrazloženje i Rizik:**
Sopstveni HTTP i router slojevi skrivaju suptilne bagove u parsiranju zaglavlja, rukovanju multipart podacima i kodiranju karaktera. Povećava se tehnički dug i vreme potrebno za prilagođavanje novih inženjera na projekat.

🛠️ **Predlog rešenja:**
Postepeno zamenite prilagođene runtime klase standardnim PSR komponentama ili mikro-frameworkom:

* HTTP poruke: `nyholm/psr7` ili `symfony/http-foundation`
* Rutiranje: `nikic/fast-route` ili `league/route`
* Renderovanje: `twig/twig` ili `platypus` komponente

---

## Nizak Prioritet

---

### 9. 🟩 [Nizak - Principal Platform & Infrastructure Architect] Prilagođeni Migrator bez podrške za rollback i transakcionu bezbednost (`Migrator.php`)

🔍 **Opis problema:**
Skripta `Migrator.php` izvršava SQL skripte sukcesivno, ali ne omota svaku migraciju u transakciju niti nudi mehanizam za vraćanje izmena (`down` migracije) u slučaju neuspeha.

💡 **Obrazloženje i Rizik:**
Ako migracija pukne na pola izvršavanja u produkcionom okruženju, baza ostaje u nedefinisanom i delimično migriranom stanju, što zahteva ručnu intervenciju u bazi i produžava downtime.

🛠️ **Predlog rešenja:**
Omotajte izvršavanje svake migracije u transakciju (ako DB drajver podržava DDL transakcije) i vodite evidenciju izvedenih verzija:

```php
public function migrate(): void
{
    foreach ($this->getPendingMigrations() as $migration) {
        $this->pdo->beginTransaction();
        try {
            $this->pdo->exec($migration->getSql());
            $this->logMigration($migration->getName());
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}

```

---

### 10. 🟩 [Nizak - Principal Software Architect] Mešanje HTML prezentacije i aplikativne logike u bootstrap i HTTP sloju

🔍 **Opis problema:**
Skripte unutar `bootstrap/Http/*` (npr. `AccountPages.php`, `form.php`) mešaju generisanje HTML odziva, postavljanje HTTP zaglavlja i direktno pozivanje baza/servisa.

💡 **Obrazloženje i Rizik:**
Narušava se Separation of Concerns (SoC). Kod postaje nemoćan za ponovnu upotrebu (npr. izlaganje istih funkcionalnosti preko REST API-ja ili CLI komandi bez dupliranja logike).

🛠️ **Predlog rešenja:**
Razdvojite ulazne kontrolere (Request Handlers) koji vraćaju DTO objekte od View sloja odgovornog isključivo za HTML renderovanje.