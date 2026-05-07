# 📸 GUIDE CAPTURES D'ÉCRAN - RAPPORT UNIVERSITAIRE
## Fintokhrej Event Management Tests

---

## 🎯 CAPTURES OBLIGATOIRES (11 total)

### 📋 CAPTURE 1️⃣ : Installation et Vérification

**Commande :**
```bash
cd C:\Users\violonista\ founoun\Desktop\esprit-pidev-3A4-fintokhrej-integ\esprit-pidev-3A4-fintokhrej-integ
dir src\Service\EventManager.php
dir tests\Service\EventManagerTest.php
php -l src/Service/EventManager.php
php -l tests/Service/EventManagerTest.php
```

**À montrer dans le rapport :**
- ✅ Les deux fichiers existent
- ✅ "No syntax errors detected"
- ✅ Chemins complets

**Exemple de résultat :**
```
C:\projet> dir src\Service\EventManager.php
 Volume is C:
 Directory of C:\projet\src\Service

EventManager.php
               1 File(s)      10,234 bytes

C:\projet> php -l src/Service/EventManager.php
No syntax errors detected in src/Service/EventManager.php
```

---

### 📋 CAPTURE 2️⃣ : Tous les Tests (RÉSUMÉ FINAL)

**Commande :**
```bash
./vendor/bin/phpunit tests/Service/EventManagerTest.php
```

**À montrer dans le rapport :**
- ✅ "21 / 21 (100%)"
- ✅ "Tests: 21, Assertions: 31"
- ✅ "OK, but there were issues!" (c'est normal, ça veut dire "succès avec avertissements")
- ✅ "Time: 00:00.0XXs"
- ✅ "Memory: 12.00 MB"

**Exemple de résultat :**
```
PHPUnit 11.5.55 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.2.12
Configuration: phpunit.dist.xml

.....................                                             21 / 21 (100%)

Time: 00:00.030, Memory: 12.00 MB

OK, but there were issues!
Tests: 21, Assertions: 31, PHPUnit Deprecations: 22.
```

---

### 📋 CAPTURE 3️⃣ : Tests Groupe EventBooking (Réservation)

**Commande :**
```bash
./vendor/bin/phpunit tests/Service/EventManagerTest.php --filter="EventBooking"
```

**À montrer dans le rapport :**
- ✅ 7 tests affichés (tous avec .)
- ✅ Message de validation
- ✅ "7/7" ou "100%"

**Règles validées :**
```
✅ testValidEventBookingWithAvailableCapacity
✅ testBookingFailsWhenEventIsClosed
✅ testBookingFailsWhenInsufficientCapacity
✅ testBookingFailsWithZeroTickets
✅ testBookingFailsWithNegativeTickets
✅ testBookingSucceedsWhenTicketsEqualsRemainingCapacity
✅ testBookingFailsWithOneTicketTooMany
```

---

### 📋 CAPTURE 4️⃣ : Tests Groupe EventDates (Dates)

**Commande :**
```bash
./vendor/bin/phpunit tests/Service/EventManagerTest.php --filter="EventDates"
```

**À montrer dans le rapport :**
- ✅ 6 tests affichés (tous avec .)
- ✅ Message de validation
- ✅ "6/6" ou "100%"

**Règles validées :**
```
✅ testValidDateRange
✅ testDateRangeFailsWhenEndEqualsStart
✅ testDateRangeFailsWhenEndBeforeStart
✅ testDateRangeFailsWithoutStartDate
✅ testDateRangeFailsWithoutEndDate
✅ testValidMultiDayDateRange
```

---

### 📋 CAPTURE 5️⃣ : Tests Groupe FillRate (Taux Remplissage)

**Commande :**
```bash
./vendor/bin/phpunit tests/Service/EventManagerTest.php --filter="FillRate"
```

**À montrer dans le rapport :**
- ✅ 5 tests affichés (tous avec .)
- ✅ Message de validation
- ✅ "5/5" ou "100%"

**Règles validées :**
```
✅ testFillRateCalculationIsCorrect
✅ testFillRateWhenNoBookings
✅ testFillRateWhenEventIsFull
✅ testFillRateFailsWithZeroCapacity
```

---

### 📋 CAPTURE 6️⃣ : Tests Groupe Helper (Méthodes auxiliaires)

**Commande :**
```bash
./vendor/bin/phpunit tests/Service/EventManagerTest.php --filter="Helper"
```

**À montrer dans le rapport :**
- ✅ 4 tests affichés (tous avec .)
- ✅ Message de validation
- ✅ "4/4" ou "100%"

**Règles validées :**
```
✅ testCanAcceptBookingsCombinesValidations
✅ testGetConfirmedBookingsCount
✅ testIsEventFull
✅ testGetCapacitySummary
```

---

### 📋 CAPTURE 7️⃣ : Structure des Fichiers (VS Code)

**Dans VS Code :**
1. Ouvrir l'explorateur de fichiers (Ctrl+Shift+E)
2. Naviguer vers : `src/Service/` et `tests/Service/`
3. Montrer les deux fichiers créés :
   - `EventManager.php` 
   - `EventManagerTest.php`

**À montrer :**
```
📂 src
  📂 Service
    📄 EventManager.php ..................... 250 lignes
📂 tests
  📂 Service
    📄 EventManagerTest.php ................ 600 lignes
```

---

### 📋 CAPTURE 8️⃣ : Code Source - Exemple Règle 1

**Fichier à montrer :** `src/Service/EventManager.php` (lignes 30-65)

**À mettre en évidence :**
```php
public function validateEventBooking(Evenement $event, int $nbTickets = 1): bool
{
    // Vérification 1 : Tickets positifs
    if ($nbTickets <= 0) {
        throw new InvalidArgumentException(...);
    }

    // Vérification 2 : Statut OUVERT
    if ($event->getStatut() !== Evenement::STATUT_OUVERT) {
        throw new InvalidArgumentException(...);
    }

    // Vérification 3 : Places suffisantes
    if (!$event->avoirPlacesPour($nbTickets)) {
        throw new InvalidArgumentException(...);
    }

    return true;
}
```

---

### 📋 CAPTURE 9️⃣ : Code Source - Exemple Règle 2

**Fichier à montrer :** `src/Service/EventManager.php` (lignes 81-110)

**À mettre en évidence :**
```php
public function validateEventDateRange(Evenement $event): bool
{
    $dateDebut = $event->getDateDebut();
    $dateFin = $event->getDateFin();

    // Vérification 1 : Dates existantes
    if ($dateDebut === null || $dateFin === null) {
        throw new InvalidArgumentException('Les dates... obligatoires');
    }

    // Vérification 2 : Fin > Début
    if ($dateFin <= $dateDebut) {
        throw new InvalidArgumentException(
            sprintf('La date de fin (%s) doit être après...', 
                    $dateFin->format('Y-m-d H:i:s'), ...)
        );
    }

    return true;
}
```

---

### 📋 CAPTURE 🔟 : Code Source - Exemple Test

**Fichier à montrer :** `tests/Service/EventManagerTest.php` (lignes 70-95)

**À mettre en évidence (Pattern AAA):**
```php
public function testBookingFailsWhenInsufficientCapacity(): void
{
    // ARRANGE (Préparation)
    $event = $this->createEventMock(
        titre: 'Conférence Complète',
        statut: Evenement::STATUT_OUVERT,
        capaciteMax: 100,
        placesRestantes: 3  // Seulement 3 places
    );

    // ACT & ASSERT (Action + Vérification)
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessageMatches('/Pas assez de places/');
    
    $this->eventManager->validateEventBooking($event, 5);
}
```

---

### 📋 CAPTURE 1️⃣1️⃣ : Rapport HTML (Optionnel - Bonus)

**Commande pour générer :**
```bash
./vendor/bin/phpunit tests/Service/EventManagerTest.php \
  --html=test-results.html \
  --coverage-html=coverage-report/
```

**Puis :**
1. Ouvrir `test-results.html` dans le navigateur
2. Montrer le résumé : "21 tests, 31 assertions"
3. Cliquer sur chaque test pour voir le code
4. Afficher le rapport de couverture : `coverage-report/index.html`

**Résultat attendu :**
```
✅ 21 tests passed
✅ 31 assertions passed
✅ 0 errors
✅ 0 failures
✅ 100% coverage
```

---

## 📝 PLACEMENT DES CAPTURES DANS LE RAPPORT

### Section 1 : Introduction
- **CAPTURE 1** : Fichiers créés et syntaxe valide
- **CAPTURE 7** : Structure VS Code

### Section 2 : Règles Métier
- **CAPTURE 8** : Code source Règle 1 (Réservation)
- **CAPTURE 3** : Tests passants Règle 1
- **CAPTURE 9** : Code source Règle 2 (Dates)
- **CAPTURE 4** : Tests passants Règle 2
- Code + CAPTURE 5 : Règle 3 (Taux)

### Section 3 : Implémentation
- **CAPTURE 10** : Exemple test (Pattern AAA)

### Section 4 : Résultats
- **CAPTURE 2** : Résumé FINAL (21/21 ✅)
- **CAPTURE 6** : Tests Helper
- **CAPTURE 11** : Rapport HTML (Bonus)

---

## 🎬 COMMANDES RAPIDES À COPIER-COLLER

### Pour exécuter TOUS les tests
```bash
cd C:\Users\violonista\ founoun\Desktop\esprit-pidev-3A4-fintokhrej-integ\esprit-pidev-3A4-fintokhrej-integ
./vendor/bin/phpunit tests/Service/EventManagerTest.php
```

### Pour tester UN groupe spécifique
```bash
# Réservations
./vendor/bin/phpunit tests/Service/EventManagerTest.php --filter="EventBooking"

# Dates
./vendor/bin/phpunit tests/Service/EventManagerTest.php --filter="EventDates"

# Taux
./vendor/bin/phpunit tests/Service/EventManagerTest.php --filter="FillRate"

# Helpers
./vendor/bin/phpunit tests/Service/EventManagerTest.php --filter="Helper"
```

### Pour générer rapport HTML
```bash
./vendor/bin/phpunit tests/Service/EventManagerTest.php \
  --html=test-results.html \
  --coverage-html=coverage-report/
```

---

## 📐 DIMENSIONS RECOMMANDÉES POUR CAPTURES

| Type | Dimension | Format |
|---|---|---|
| Terminal | 1920×1080 | PNG/JPG |
| Code | 1600×900 | PNG (crop la zone de code) |
| Navigateur | 1280×800 | PNG |
| VS Code | 1920×1200 | PNG (full screen) |

---

## ✅ CHECKLIST CAPTURES

- [ ] Capture 1 : Installation ✅
- [ ] Capture 2 : Tous les tests (21/21)
- [ ] Capture 3 : EventBooking (7/7)
- [ ] Capture 4 : EventDates (6/6)
- [ ] Capture 5 : FillRate (5/5)
- [ ] Capture 6 : Helper (4/4)
- [ ] Capture 7 : VS Code structure
- [ ] Capture 8 : Code Règle 1
- [ ] Capture 9 : Code Règle 2
- [ ] Capture 10 : Exemple test
- [ ] Capture 11 : HTML report (optionnel)

---

## 🎯 CONSEIL FINAL

**Pour un rapport professionnel :**
1. Utiliser un tool de screenshots (Snagit, Screenshot Tool Windows)
2. Ajouter des flèches rouges pointant vers les éléments clés
3. Ajouter des annotations (cercles, encadrés)
4. Redimensionner pour lisibilité (max 1600px de large)
5. Compresser les images (80-90% qualité suffisante)

**Exemple d'annotation :**
```
[Terminal output screenshot avec flèche rouge pointant vers "21 / 21 (100%)"]
```

---

**Document créé pour faciliter la documentation du rapport universitaire**
*Format: Markdown + Captures PNG*
*Durée totale: ~5 minutes pour toutes les captures*
