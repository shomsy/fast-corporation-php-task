# ClaudeCode LLD Review — originalni nalazi i indeks

**Datum izvornog izveštaja:** 8. oktobar 2026. · **Autor izvora:** ClaudeCode (`@stankela` u originalu).

**Status:** ARCHIVED / UNVERIFIED / NOT MERGED / NOT IMPLEMENTED. Ovaj dokument je indeks tvrdnji nezavisnog pregleda, ne potvrda da su svi nalazi bugovi. Zadržavamo originalnu težinu i oznake autora; predloženi kod i arhitektonske odluke nisu automatski usvojeni.

**Verbatim original:** [CLAUDE-CODE-LLD-REVIEW-2026-10-08.md](CLAUDE-CODE-LLD-REVIEW-2026-10-08.md) (izvorni upload `Untitled.md`, 33.151 UTF-8 bajtova / 481 tekstualni red).

**Original SHA-256:** `ccc6b3fad1861e6a0bf62aa52ff2facc2ba5c0bab9387e42296e87bfefa83028`.

## Pregled nalaza

| Originalni prioritet | Broj | ID-jevi |
|---|---:|---|
| 🟥 Visok | 8 | CLLD-01 … CLLD-08 |
| 🟨 Srednji | 10 | CLLD-09 … CLLD-18 |
| 🟩 Nizak | 8 | CLLD-19 … CLLD-26 |
| **Ukupno** | **26** | **26 izvornih zadataka** |

ID `CLLD-##` je samo trajni identifikator za buduću deduplikaciju: u originalnom dokumentu stavke su označene brojevima 1–26.

## Potpuni indeks (bez procene opravdanosti)

| ID | Autorov prioritet i uloga | Naziv nalaza |
|---|---|---|
| `CLLD-01` | 🟥 Visok - Security Architect | CSRF, autentikacija i autorizacija su opt-in po handleru |
| `CLLD-02` | 🟥 Visok - Security Architect | Nema throttlinga prijave i registracije (brute force i CPU DoS) |
| `CLLD-03` | 🟥 Visok - Security Architect | Nema politike lozinke i korisničkog imena (potvrđeno probom) |
| `CLLD-04` | 🟥 Visok - Security Architect | Hash algoritam, cost i timing enumeracija korisnika |
| `CLLD-05` | 🟥 Visok - Principal Platform Architect | SQLite u `delete` journal modu i web zahtev koji može da napravi bazu |
| `CLLD-06` | 🟥 Visok - Performance Engineer | Reports je O(N), a Statistics filteri skeniraju celu tabelu (potvrđeno query planom) |
| `CLLD-07` | 🟥 Visok - Principal Software Architect | Domen zavisi od infrastrukture, nema portova |
| `CLLD-08` | 🟥 Visok - Staff Software Engineer | `Accounts` je god-klasa, a `CowOffer` je prazna ljuska |
| `CLLD-09` | 🟨 Srednji - Security Architect | Sigurni default-i su fail-open, a konfiguracija je neprovereno polje |
| `CLLD-10` | 🟨 Srednji - Reliability Engineer | Sesije: istek, skladište, anonimne sesije i lock |
| `CLLD-11` | 🟨 Srednji - Reliability Engineer | Audit nije append-only, a vreme je string (potvrđeno probom) |
| `CLLD-12` | 🟨 Srednji - Reliability Engineer | `Migrator` nema checksum, lock ni stabilan redosled |
| `CLLD-13` | 🟨 Srednji - Staff Software Engineer | Dvostruki klik i osvežavanje naduvavaju izveštaj |
| `CLLD-14` | 🟨 Srednji - Principal Software Architect | Validacija preko `InvalidArgumentException` i nizovi umesto objekata |
| `CLLD-15` | 🟨 Srednji - Senior Software Engineer | Grafik na Reports obmanjuje i ima netačne oznake (potvrđeno probom) |
| `CLLD-16` | 🟨 Srednji - Principal Platform Architect | HTTP sloj: HEAD/OPTIONS, greške, logovanje, eager wiring |
| `CLLD-17` | 🟨 Srednji - OSS Technology Evaluator | Ponovo izmišljen točak na pet mesta |
| `CLLD-18` | 🟨 Srednji - Staff Software Engineer | Testovi i CI ne štite ono što je bitno |
| `CLLD-19` | 🟩 Nizak - Staff Software Engineer | Dokumentacija je veća od proizvoda, a docblock-ovi su šum |
| `CLLD-20` | 🟩 Nizak - Principal Software Architect | Template sloj: kopiran CSS, bez layout-a, logika u templejtu |
| `CLLD-21` | 🟩 Nizak - Security Architect | Download artefakt: ceo fajl u memoriji, binarni `.exe` u repou |
| `CLLD-22` | 🟩 Nizak - Senior Software Engineer | Higijena koda i mrtva logika |
| `CLLD-23` | 🟩 Nizak - Reliability Engineer | Rubni slučajevi transakcija i sesije |
| `CLLD-24` | 🟩 Nizak - Security Architect | Upravljanje nalozima i sigurnosni log |
| `CLLD-25` | 🟩 Nizak - AI / Agent Infrastructure Architect | Spremnost za rad sa agentima i nepoverljivi tekst |
| `CLLD-26` | 🟩 Nizak - Staff Software Engineer | Imenovani argumenti na ugrađenim PHP funkcijama (mišljenje) |

## Tačke za buduće nezavisno proveravanje

- [ ] **Security:** centralni fail-closed CSRF/authorization; throttling na login/register; politika username/password; autentikacioni timing i session lifecycle.
- [ ] **Persistence:** SQLite provisioning i izbor journal režima; migration drift/lock; append-only audit ugovor i istorijski timestamp; realni backup/restore test.
- [ ] **Performance:** `EXPLAIN QUERY PLAN` pod reprezentativnim podacima; korisnički i action filteri; keyset pagination; eventualni daily rollup tek nakon merenja.
- [ ] **HTTP/UI:** HEAD/OPTIONS/URI; statusne i sigurnosne header-e; stvarna vremenska osa i nulte dane na grafikonu; dupli POST i idempotentnost.
- [ ] **Architecture:** granice domen/infrastruktura i stvarna potreba za interfejsima; eventualni middleware/PSR-7 portovi nasuprot riziku nepotrebnog rewrite-a.
- [ ] **Conventions:** autorski MOI i named arguments su postojeće odluke; predlozi da se menjaju nisu automatski prihvaćeni. PHPDoc politika ostaje posebno vlasničko pitanje.
- [ ] **Validation:** tvrdnje koje Claude opisuje kao probane treba ponoviti u izolovanom testu na tadašnjem commitu; proveriti konkretne verzije i API-je predloženih OSS biblioteka.

## Ostali nezavisni izvori (ne spajati još)

- [Naš OOP Architecture Remediation TODO — 18 stavki](../OOP-ARCHITECTURE-REMEDIATION-TODO.md)
- [Raniji ClaudeCode review — 62 nalaza](CLAUDE-CODE-REVIEW-INDEX.md)
- [Naš LLD code review — 26 stavki](LLD-CODE-REVIEW-2026-10-08.md)

**Zbir pre deduplikacije:** 18 + 62 + 26 + 26 = **132 izvorne stavke**. Ovaj zbir nije broj jedinstvenih problema. Konsolidacija će čuvati mapiranje svakog ID-ja na original i status CONFIRMED / CONDITIONAL / DESIGN DECISION / REJECTED.
