# ⚡ COMMANDES RAPIDES - COPY/PASTE

**Copier-coller ces commandes directement dans le terminal PowerShell**

---

## 🎯 COMMANDE PRINCIPALE (EXÉCUTER TOUS LES TESTS)

```powershell
cd 'C:\Users\violonista founoun\Desktop\esprit-pidev-3A4-fintokhrej-integ\esprit-pidev-3A4-fintokhrej-integ' ; ./vendor/bin/phpunit tests/Service/EventManagerTest.php
```

**Résultat attendu:**
```
PHPUnit 11.5.55

✓ 21 / 21 (100%)
✓ Tests: 21, Assertions: 31
✓ OK
```

---

## 📋 EXÉCUTER UN GROUPE SPÉCIFIQUE

### Tests de Réservation (7 tests)
```powershell
./vendor/bin/phpunit tests/Service/EventManagerTest.php --filter="EventBooking"
```

### Tests de Dates (6 tests)
```powershell
./vendor/bin/phpunit tests/Service/EventManagerTest.php --filter="EventDates"
```

### Tests de Taux Remplissage (5 tests)
```powershell
./vendor/bin/phpunit tests/Service/EventManagerTest.php --filter="FillRate"
```

### Tests Helpers (4 tests)
```powershell
./vendor/bin/phpunit tests/Service/EventManagerTest.php --filter="Helper"
```

---

## 🔍 VÉRIFIER LA SYNTAXE PHP

```powershell
php -l src/Service/EventManager.php
```

```powershell
php -l tests/Service/EventManagerTest.php
```

---

## 📊 AFFICHER LA COUVERTURE

```powershell
./vendor/bin/phpunit tests/Service/EventManagerTest.php --coverage-text
```

---

## 🌐 GÉNÉRER RAPPORT HTML

```powershell
./vendor/bin/phpunit tests/Service/EventManagerTest.php --html=test-results.html --coverage-html=coverage-report/
```

---

## 📁 VÉRIFIER LES FICHIERS

```powershell
dir src\Service\EventManager.php
```

```powershell
dir tests\Service\EventManagerTest.php
```

---

## 🚀 SCRIPT COMPLET (Tout en une fois)

```powershell
cd 'C:\Users\violonista founoun\Desktop\esprit-pidev-3A4-fintokhrej-integ\esprit-pidev-3A4-fintokhrej-integ' ; `
Write-Host "=== VÉRIFICATION SYNTAXE ===" ; `
php -l src/Service/EventManager.php ; `
php -l tests/Service/EventManagerTest.php ; `
Write-Host "=== EXÉCUTION TOUS LES TESTS ===" ; `
./vendor/bin/phpunit tests/Service/EventManagerTest.php ; `
Write-Host "=== COUVERTURE ===" ; `
./vendor/bin/phpunit tests/Service/EventManagerTest.php --coverage-text --no-coverage-on-modify | Select-Object -First 30
```

---

## 🎬 COMMANDES PAR CAS D'USAGE

### Pour voir rapidement si ça marche
```powershell
./vendor/bin/phpunit tests/Service/EventManagerTest.php
```

### Pour tester UNE règle métier
```powershell
# Changer "EventBooking" par "EventDates" ou "FillRate"
./vendor/bin/phpunit tests/Service/EventManagerTest.php --filter="EventBooking"
```

### Pour générer les rapports pour le rapport académique
```powershell
./vendor/bin/phpunit tests/Service/EventManagerTest.php --html=test-results.html --coverage-html=coverage-report/
```

### Pour un résumé court
```powershell
./vendor/bin/phpunit tests/Service/EventManagerTest.php | tail -5
```

### Pour un résumé détaillé
```powershell
./vendor/bin/phpunit tests/Service/EventManagerTest.php --testdox
```

---

## 📝 EXPORT EN FICHIER

### Exporter en XML
```powershell
./vendor/bin/phpunit tests/Service/EventManagerTest.php --log-junit=test-results.xml
```

### Exporter en JSON (si supporté)
```powershell
./vendor/bin/phpunit tests/Service/EventManagerTest.php --log-json=test-results.json
```

---

## 🔧 DÉPANNAGE

### Si les tests ne trouvent pas le fichier
```powershell
# Vérifier le chemin courant
pwd

# Ou aller au bon dossier
cd 'C:\Users\violonista founoun\Desktop\esprit-pidev-3A4-fintokhrej-integ\esprit-pidev-3A4-fintokhrej-integ'
```

### Si PHPUnit n'est pas trouvé
```powershell
# Réinstaller les dépendances
composer install

# Puis réessayer
./vendor/bin/phpunit tests/Service/EventManagerTest.php
```

### Si erreur de syntaxe
```powershell
# Vérifier la syntaxe PHP
php -l src/Service/EventManager.php
php -l tests/Service/EventManagerTest.php
```

---

## 💡 COMMANDES BONUS

### Voir la structure des fichiers
```powershell
tree /F /L 2 src/Service
tree /F /L 2 tests/Service
```

### Compter les lignes de code
```powershell
(Get-Content src/Service/EventManager.php | Measure-Object -Line).Lines
(Get-Content tests/Service/EventManagerTest.php | Measure-Object -Line).Lines
```

### Voir le contenu du test (premier 50 lignes)
```powershell
Get-Content tests/Service/EventManagerTest.php -Head 50
```

---

## 🎯 LISTE COMPLÈTE - COPIE POUR VOTRE RAPPORT

```powershell
# 1. Vérifier installation
composer show | grep phpunit

# 2. Tous les tests
./vendor/bin/phpunit tests/Service/EventManagerTest.php

# 3. Tests groupe 1 (Réservation)
./vendor/bin/phpunit tests/Service/EventManagerTest.php --filter="EventBooking"

# 4. Tests groupe 2 (Dates)
./vendor/bin/phpunit tests/Service/EventManagerTest.php --filter="EventDates"

# 5. Tests groupe 3 (Taux)
./vendor/bin/phpunit tests/Service/EventManagerTest.php --filter="FillRate"

# 6. Tests groupe 4 (Helpers)
./vendor/bin/phpunit tests/Service/EventManagerTest.php --filter="Helper"

# 7. Rapport HTML
./vendor/bin/phpunit tests/Service/EventManagerTest.php --html=test-results.html

# 8. Couverture
./vendor/bin/phpunit tests/Service/EventManagerTest.php --coverage-text
```

---

## 🎓 POUR VOTRE DOCUMENTATION

Vous pouvez copier ces commandes directement dans votre rapport académique :

```markdown
### Exécution des tests

Pour exécuter les tests du service EventManager :

\`\`\`bash
./vendor/bin/phpunit tests/Service/EventManagerTest.php
\`\`\`

Résultat :
\`\`\`
✓ 21 / 21 (100%)
✓ OK
\`\`\`
```

---

## ⏱️ TEMPS ESTIMÉ

| Commande | Temps | Résultat |
|---|---|---|
| Tous les tests | 1 sec | 21/21 PASS |
| Un groupe | 0.5 sec | 4-7/X PASS |
| Coverage | 2 sec | % coverage |
| Rapport HTML | 5 sec | Fichier HTML |

---

**Créé pour copie-coller rapide - Mai 2026**
