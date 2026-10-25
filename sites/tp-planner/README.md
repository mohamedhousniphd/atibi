# TP Planner

Application web PHP pour planifier, organiser et suivre les travaux pratiques (TP) en laboratoire : classes, fiches de séance, matériel, checklists, mini-quiz et export PDF.

Interface disponible en **français**, **anglais** et **arabe**.

---

## Prérequis

| Composant | Version minimale | Remarque |
|-----------|------------------|----------|
| **PHP** | 7.4+ (8.0+ recommandé) | Extensions : `mysqli`, `mbstring`, `json`, `fileinfo`, `gd` (images) |
| **MySQL / MariaDB** | 5.7+ / 10.4+ | Base `tp_planner` |
| **Apache** (XAMPP) | — | Module `mod_rewrite` optionnel ; projet utilisable en sous-dossier |
| **Composer** | 2.x | Obligatoire pour l’export PDF |
| **Navigateur** | Récent | Chrome, Firefox, Edge |

Dépendances PHP (via Composer) :

- `tecnickcom/tcpdf` — génération PDF
- `propa/tcpdi` — fusion / import PDF
- `smalot/pdfparser` — extraction de texte depuis PDF importés

Front-end (chargé via CDN, pas d’installation npm) :

- Bootstrap 5.3
- Bootstrap Icons
- Chart.js (tableau de bord admin)
- Quill (éditeur riche sur la fiche TP)

---

## Installation (XAMPP sous Windows)

### 1. Placer le projet

Copier le dossier dans `C:\xampp\htdocs\TP PLANNER` (ou autre nom sans espaces si vous préférez).

### 2. Créer la base de données

1. Démarrer **Apache** et **MySQL** dans le panneau XAMPP.
2. Ouvrir **phpMyAdmin** : `http://localhost/phpmyadmin`
3. Importer le fichier complet :

   ```
   database/database.sql
   ```

   Ce script crée la base `tp_planner`, toutes les tables et des données d’exemple (admin, classe, stagiaire, séances TP).

   *Alternative manuelle :*

   ```sql
   CREATE DATABASE tp_planner CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

   Puis importer `database/database.sql` dans cette base.

### 3. Configurer la connexion MySQL

Éditer `config/database.php` :

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'tp_planner');
define('DB_USER', 'root');      // utilisateur XAMPP par défaut
define('DB_PASS', '');          // mot de passe XAMPP (souvent vide)
```

### 4. Installer les dépendances Composer

À la racine du projet (PowerShell ou CMD) :

```bash
cd "C:\xampp\htdocs\TP PLANNER"
composer install
```

Sans Composer installé globalement, télécharger [Composer](https://getcomposer.org/) puis relancer la commande.

### 5. Dossiers d’écriture

Vérifier que PHP peut écrire dans (créés automatiquement à l’usage si besoin) :

- `uploads/` — schémas et documents importés des TP

### 6. Accéder au site

URL typique avec XAMPP :

```
http://localhost/TP%20PLANNER/
```

Les liens internes s’adaptent au chemin du script (`config/config.php` calcule `BASE_PATH`).

---

## Comptes et rôles

Deux types d’utilisateurs :

| Rôle | Table | Accès |
|------|-------|--------|
| **Administrateur** (staff) | `users` | Tableau de bord, classes, séances TP, gestion des comptes, export PDF |
| **Professeur stagiaire** | `students` | Espace laboratoire : TP de sa classe, checklists, quiz, ses résultats |

Connexion par **adresse e-mail** et mot de passe.

### Comptes d’exemple (après import de `database.sql`)

| Profil | E-mail | Mot de passe |
|--------|--------|--------------|
| Administrateur | `admin@gmail.com` | `admin123` |
| Stagiaire | `hasnaeelbahraoui@gmail.com` | `hasna123` |

> **Production :** dans `config/config.php`, passer `DEV_PLAIN_PASSWORD` à `false` et n’utiliser que des mots de passe hashés (`password_hash`).

---

## Utilisation rapide

### Administrateur

1. Se connecter → **Tableau de bord** (statistiques, graphique des scores, alertes checklists).
2. **Classes** — créer les groupes / niveaux (ex. « 2 BAC SM 1 »).
3. **Séances TP** — créer une séance ou **Modifier** pour la fiche complète :
   - titre, unité, objectifs, compétences, sécurité, durée, classe ;
   - étapes, matériel, checklist (avant / pendant / après), questions du mini-quiz ;
   - schéma ou import PDF/Word.
4. **Voir** une séance — consultation, checklist, résultats des quiz.
5. **Export PDF** — document imprimable de la fiche.
6. **Stagiaires** — créer / modifier / supprimer comptes admin et stagiaires.

### Professeur stagiaire

1. **S’inscrire** (`register.php`) en choisissant une classe existante, ou utiliser un compte créé par l’admin.
2. **Espace laboratoire** — liste des TP de sa classe.
3. Ouvrir un TP — cocher la checklist, répondre au mini-quiz.
4. **Mes résultats de quiz** — détail question par question et bonnes réponses.

### Langue de l’interface

Sélecteur FR / EN / AR dans la barre de navigation (ou sur la page d’accueil). La préférence est enregistrée en session et cookie (`pages/set_lang.php`).

---

## Structure du projet

```
TP PLANNER/
├── assets/
│   ├── css/          # style.css, landing.css
│   ├── js/           # app.js, landing.js
│   └── img/          # images page d'accueil
├── config/
│   ├── config.php    # session, chemins, i18n
│   └── database.php  # identifiants MySQL
├── database/
│   ├── database.sql        # schéma + données (à importer)
│   └── schema_reference.sql
├── includes/
│   ├── functions.php     # auth, CSRF, helpers
│   ├── i18n.php          # traductions FR/EN/AR
│   ├── header.php, navbar.php, footer.php
│   ├── tp_document.php   # import PDF / upload
│   └── tp_sections.php   # affichage sections TP
├── pages/                # pages applicatives
├── uploads/              # fichiers uploadés
├── vendor/               # Composer (TCPDF, etc.)
├── index.php             # accueil ou redirection si connecté
├── login.php             # alias → pages/login.php
├── register.php          # alias → pages/register.php
├── composer.json
└── README.md
```

---

## Fonctionnalités principales

- **Page d’accueil** publique (présentation, contact, liens connexion / inscription).
- **Classes** : CRUD, recherche, lien vers les TP par classe.
- **Séances TP** : CRUD, filtres (classe, recherche), tri par titre / date / durée.
- **Fiche TP** : objectifs riches (Quill), étapes, matériel, checklist par phase, mini-quiz QCM (A–D).
- **Checklists** : cocher / décocher avec badges d’état.
- **Quiz** : enregistrement des réponses et scores par stagiaire ; synthèse pour l’admin.
- **Export PDF** : fiche complète via TCPDF.
- **Sécurité** : `password_hash` / `password_verify`, jeton CSRF, requêtes préparées, accès par rôle et par classe.

---

## Dépannage

| Problème | Solution |
|----------|----------|
| « Database connection failed » | Vérifier MySQL démarré et `config/database.php` |
| « Please run: composer install » | Exécuter `composer install` à la racine |
| Page blanche / erreurs PHP | Vérifier les logs Apache ; en dev, `display_errors` est activé dans `config.php` |
| Session / langue instable | Le cookie de session utilise le chemin `/` (compatible sous-dossier avec espaces) |
| Import PDF échoue | Vérifier `uploads/` accessible en écriture et extension `fileinfo` activée |

---

## Documentation des pages

Un guide détaillé en français pour chaque écran (prêt pour captures d’écran) :

**`docs/Guide_des_pages_TP_Planner.docx`**

---

## Licence

Projet pédagogique / usage interne laboratoire — adapter selon votre contexte.
