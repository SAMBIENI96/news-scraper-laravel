# Kaktus News — Documentation technique & Architecture

> Agrégateur d'actualités basé sur le scraping de [kaktus.media](https://kaktus.media).
> Projet réalisé dans le cadre du test technique **Polaris** (poste développeur PHP Laravel junior).
> Auteur : **SAMBIENI Ebenezer**

---

## 1. Objectif du projet

Construire une application web qui **agrège les actualités de kaktus.media** :

- Affichage des actualités du jour au chargement de la page.
- Filtrage par date (consultation d'un jour choisi).
- Recherche par titre **côté backend** (le frontend reçoit une liste déjà filtrée).
- Extraction automatique (scraping) des données depuis le site source.
- Affichage des images, titres et liens de chaque article.

Le site kaktus.media **ne propose ni API publique ni flux RSS** : toute la donnée est donc
extraite par **scraping HTML** puis parsée en objets métier.

---

## 2. Stack technique

| Élément | Technologie | Rôle |
|---|---|---|
| Langage | PHP `^8.2` (exécuté ici en 8.5.11) | Langage principal |
| Framework | Laravel `^12.0` | Backend, routing, validation, vues |
| Client HTTP | GuzzleHTTP | Requêtes HTTP vers kaktus.media |
| Parsing HTML | Symfony DomCrawler + CssSelector `^7.4` | Extraction des articles |
| Authentification | Laravel Sanctum `^4.0` | Tokens (non utilisé dans le flux applicatif) |
| REPL | Laravel Tinker `^2.10` | Debug / expérimentation |
| Frontend | Blade + JavaScript natif (fetch API) | Affichage et interactions |
| CSS | CSS embarqué + polices Google Fonts | Design type « journal » |
| Base de données | SQLite | Sessions, cache, queues |
| Outils dev | PHPUnit `^11.5`, Pint, Pail, Collision | Tests & qualité |
| Build front | Vite `^7` + TailwindCSS `^4` | Outillage des assets |

---

## 3. Architecture applicative

Le projet suit l'architecture **MVC de Laravel** enrichie d'une couche **Service** et d'un **DTO**.

```
app/
├── Classes/
│   └── News.php                  # Ancienne classe News (legacy, non utilisée par le flux)
├── DTO/
│   └── News.php                  # Data Transfer Object immuable représentant un article
├── Services/
│   └── NewsParserService.php     # Cœur métier : scraping + parsing + filtrage
├── Http/
│   └── Controllers/
│       └── NewsController.php    # index() (vue) + search() (JSON)
├── Models/
│   └── User.php                  # Modèle User par défaut de Laravel
└── Providers/
    └── AppServiceProvider.php    # Provider vide (aucun bind personnalisé)

routes/
├── web.php                       # GET /  + routes de debug
├── api.php                       # GET /api/news
└── console.php                   # Commandes console

bootstrap/
└── app.php                       # Configuration de l'application (Laravel 12)

resources/views/news/
└── index.blade.php               # Vue principale (HTML + CSS + JS)
```

### Couches

1. **Présentation** — `resources/views/news/index.blade.php` : rendu serveur initial + JS client.
2. **Contrôleur** — `NewsController` : valide l'entrée, appelle le service, retourne vue ou JSON.
3. **Service (métier)** — `NewsParserService` : toute la logique de scraping, parsing, filtrage.
4. **DTO** — `App\DTO\News` : objet de transfert immuable (`readonly`), découplé de la vue.
5. **Persistance** — SQLite via Eloquent (sessions/cache/queues ; **aucune donnée métier n'est stockée**).

> Le parsing est **stateless** : chaque requête re-scrape le site. Aucune table `news` n'existe.

---

## 4. Flux de fonctionnement

```
1. GET /                       → Le contrôleur index() scrape kaktus.media et affiche les news du jour (rendu Blade)
2. L'utilisateur change la date → JS envoie GET /api/news?date=YYYY-MM-DD
3. L'utilisateur saisit un mot  → JS envoie GET /api/news?date=YYYY-MM-DD&search=mot
4. Backend filtre               → NewsParserService::getNews($date, $search)
5. Frontend reçoit              → JSON filtré → rendu dynamique des cartes via JS
```

Détail du flux serveur :

```
NewsController::index/search
        │
        ▼
NewsParserService::getNews(date, search?)
        │
        ├── fetchNewsByDate(date)  ──► fetchHtml(BASE_URL)  [Guzzle GET https://kaktus.media/]
        │                                   │
        │                                   ▼
        │                            parse(html, date)                  [DomCrawler]
        │                                   │
        │                                   ├── filtre a[href*="/doc/"]
        │                                   ├── extraction titre (texte du lien)
        │                                   ├── recherche image (remontée 3 niveaux parents)
        │                                   ├── extraction date depuis URL image (regex)
        │                                   ├── filtrage par date
        │                                   └── deduplicate() par URL
        │
        └── filterBySearch(news, search?)  ──► filtre insensible à la casse sur le titre
```

---

## 5. Composants détaillés

### 5.1 `App\DTO\News`

Objet métier immuable représentant un article.

```php
class News
{
    public function __construct(
        public readonly string  $date,
        public readonly string  $title,
        public readonly string  $url,
        public readonly ?string $image = null,
    ) {}

    public static function fromArray(array $data): self;  // hydratation
    public function toArray(): array;                     // sérialisation JSON
}
```

- `declare(strict_types=1)` actif.
- Propriétés `readonly` (immutabilité).
- `fromArray()` / `toArray()` pour la conversion vers/depuis les tableaux.
- **Note :** `app/Classes/News.php` est une version **legacy** identique (sans `fromArray`),
  non référencée par le flux actuel (code mort conservé).

### 5.2 `App\Services\NewsParserService`

Cœur technique de l'application.

**Client HTTP (constructeur) :**

| Option | Valeur | Raison |
|---|---|---|
| `timeout` | 20 s | Limite globale de requête |
| `connect_timeout` | 10 s | Limite de connexion |
| `User-Agent` | Chrome 120 (desktop) | Se faire passer pour un navigateur |
| `Accept-Language` | `ru-RU,ru;q=0.9,en;q=0.8` | Site russophone |
| `verify` | `false` | **Ignore la vérification SSL** (évite les erreurs de certificat) |

**Méthodes :**

| Méthode | Visibilité | Rôle |
|---|---|---|
| `getNews(string $date, ?string $search)` | public | Point d'entrée ; orchestre fetch + filtre ; capture les exceptions et log |
| `fetchNewsByDate(string $date)` | private | Télécharge la page d'accueil et lance le parsing |
| `parse(string $html, string $date)` | private | Sélectionne et transforme les liens en objets `News` |
| `filterBySearch(array $news, string $search)` | private | Filtre par titre en `mb_strtolower` + `str_contains` |
| `deduplicate(array $news)` | private | Déduplique par URL |
| `fetchHtml(string $url)` | private | GET Guzzle, retourne le corps HTML ou `null` |
| `buildAbsoluteUrl(string $href)` | private | Normalise les liens relatifs/protocol-relative en URL absolue |

**Logique de parsing (`parse`) :**

1. Sélection de tous les liens `a[href*="/doc/"]` (URL des articles sur kaktus.media).
2. Nettoyage du titre (`preg_replace` sur les espaces, `trim`) ; ignore les titres < 10 caractères.
3. Pour chaque lien, remontée jusqu'à **3 niveaux de parents** pour trouver une `<img>`.
4. Image acceptée seulement si l'URL contient `kaktus` ; préférence pour `data-src` puis `src`.
5. **Extraction de la date depuis l'URL de l'image** via la regex `/(\d{4}-\d{2}-\d{2})_/`
   (ex : `2026-04-01_21-11-29_813241.jpg` → `2026-04-01`).
6. **Règles d'inclusion :**
   - Date détectée ≠ date demandée → article **ignoré**.
   - Aucune date détectée → article inclus **uniquement si la date demandée est aujourd'hui**.
7. Déduplication finale par URL.

**Gestion des erreurs :**

- `fetchHtml()` : capture `GuzzleException` → log `warning` + retourne `null`.
- `getNews()` : capture `Throwable` → log `error` + retourne un tableau vide (l'app ne plante jamais).

### 5.3 `App\Http\Controllers\NewsController`

Contrôleur à dépendance injectée (`NewsParserService` via constructeur, `readonly`).

| Méthode | Route | Entrée | Sortie |
|---|---|---|---|
| `index(Request)` | `GET /` | query `date?` (défaut : aujourd'hui) | `view('news.index')` |
| `search(Request)` | `GET /api/news` | query `date` (requis, `Y-m-d`), `search?` | `JsonResponse` |

Validation (via `search`) :

```php
'date'   => 'required|date_format:Y-m-d',
'search' => 'nullable|string|max:255',
```

Réponse JSON :

```json
{
  "success": true,
  "date": "2026-04-01",
  "search": "депутат",
  "total": 12,
  "news": [
    {
      "date": "2026-04-01",
      "title": "Депутат просит перенести сроки отключения горячей воды",
      "url": "https://kaktus.media/doc/543496_...",
      "image": "https://data.kaktus.media/image/medium/2026-04-01_10-50-12_813241.jpg"
    }
  ]
}
```

### 5.4 Vue `resources/views/news/index.blade.php`

Rendu **côté serveur** initial (`@forelse`) + **rendu dynamique JS** après un changement de filtres.

- Design « journal » : palette papier/encre, polices `Playfair Display`, `IBM Plex Sans`, `IBM Plex Mono`.
- Contrôles : sélecteur de date (`max = aujourd'hui`) + champ de recherche + bouton.
- `#loader` (spinner) affiché pendant les requêtes AJAX.
- JS (`IIFE`) :
  - `fetchNews(date, search)` → `fetch('/api/news?' + params)`.
  - `renderCards(news)` → génère les cartes en template literals.
  - `esc(str)` → **échappement HTML** des données injectées (protection XSS).
  - Écouteurs : `change` sur la date, `click` sur le bouton, `Enter` sur la recherche.
- Interface entièrement en **russe** (site source russophone).
- Responsive via `@media (max-width: 640px)`.

### 5.5 Routes

**`routes/web.php`**

| Méthode | URI | Cible | Nom |
|---|---|---|---|
| GET | `/` | `NewsController@index` | `news.index` |
| GET | `/debug-selectors` | Closure de debug | — |
| GET | `/debug-date` | Closure de debug | — |

Les routes `/debug-*` sont des **utilitaires de développement** : elles testent en direct le
nombre de liens `/doc/`, d'images et le statut HTTP de kaktus.media. À retirer en production.

**`routes/api.php`**

| Méthode | URI | Cible | Nom |
|---|---|---|---|
| GET | `/news` | `NewsController@search` | `api.news.search` |

Chargement des routes dans `bootstrap/app.php` via `withRouting(web: ..., api: ...)`.

---

## 6. Modèle de données / Base SQLite

Aucune entité métier n'est persistée : le scraping est recalculé à chaque requête.
SQLite sert uniquement aux besoins internes du framework.

| Table | Origine | Usage |
|---|---|---|
| `users`, `password_reset_tokens`, `sessions` | migration Laravel par défaut | Auth / sessions |
| `cache`, `cache_locks` | migration par défaut | Cache (`CACHE_STORE=database`) |
| `jobs`, `job_batches`, `failed_jobs` | migration par défaut | Files d'attente (`QUEUE_CONNECTION=database`) |
| `personal_access_tokens` | migration Sanctum | Tokens d'API |

Fichier de base : `database/database.sqlite` (SQLite, `DB_CONNECTION=sqlite`).

---

## 7. Configuration & environnement

`.env` (clé) — voir aussi `.env.example` :

| Variable | Valeur | Remarque |
|---|---|---|
| `APP_ENV` | `local` | |
| `APP_DEBUG` | `true` | Débogage actif |
| `APP_KEY` | *(vide dans ce dépôt)* | **À générer** via `php artisan key:generate` |
| `DB_CONNECTION` | `sqlite` | |
| `SESSION_DRIVER` | `database` | Sessions en base |
| `CACHE_STORE` | `database` | Cache en base |
| `QUEUE_CONNECTION` | `database` | Queue en base |
| `MAIL_MAILER` | `log` | Mails écrits dans les logs |
| `LOG_LEVEL` | `debug` | |

Scripts Composer notables (`composer.json`) :

- `composer setup` : install + copie `.env` + `key:generate` + `migrate` + `npm install` + `npm run build`.
- `composer dev` : lance en parallèle `artisan serve`, `queue:listen`, `pail` et `vite` (via `concurrently`).
- `composer test` : `config:clear` puis `artisan test`.

---

## 8. Installation & lancement

```bash
# 1. Installer les dépendances PHP
composer install

# 2. Copier l'environnement
cp .env.example .env

# 3. Générer la clé d'application  (OBLIGATOIRE)
php artisan key:generate

# 4. Créer la base SQLite
touch database/database.sqlite

# 5. Jouer les migrations
php artisan migrate

# 6. Lancer le serveur
php artisan serve
```

Application accessible sur `http://localhost:8000`.

**Prérequis système :** PHP >= 8.2, Composer, et les extensions PHP
`sqlite3`, `pdo_sqlite`, `curl`, `mbstring`, `xml`.

---

## 9. Endpoints

### Page principale

```
GET /
```
Rend la vue Blade avec les actualités du jour (ou de `?date=YYYY-MM-DD`).

### API de recherche (consommée par le JS)

```
GET /api/news?date=YYYY-MM-DD&search=mot-clé
```

| Paramètre | Type | Requis | Description |
|---|---|---|---|
| `date` | `YYYY-MM-DD` | oui | Date des actualités |
| `search` | `string` (max 255) | non | Mot-clé recherché dans le titre |

Retourne du JSON (voir §5.3).

---

## 10. Points techniques notables / choix de conception

- **Scraping plutôt qu'API** : kaktus.media n'expose ni API ni RSS.
- **Date déduite de l'URL de l'image** : pas de balise de date fiable dans le HTML des liens.
- **Règle « aujourd'hui »** : les articles sans date détectable ne sont visibles que pour la date du jour.
- **Robustesse** : toute erreur réseau/parsing est capturée, loguée et renvoie une liste vide
  (l'application ne renvoie jamais d'erreur 500 pour un échec de scraping).
- **Recherche 100 % backend** : le frontend ne filtre jamais lui-même.
- **Sécurité front** : échappement HTML systématique (`esc()`) avant injection dans le DOM.
- **SSL désactivé** (`verify => false`) : contournement pragmatique des problèmes de certificat.
- **DTO immuable** : découplage entre le parsing et la présentation.

### Limites / dette technique identifiées

- `verify => false` désactive la vérification du certificat SSL (**risque sécurité** — à ne pas garder en production).
- Routes de debug `/debug-selectors` et `/debug-date` laissées dans le code (**à supprimer en prod**).
- Classe legacy `App\Classes\News` **dupliquée et non utilisée** (code mort).
- Pas de **cache** du scraping : chaque requête refait un GET vers kaktus.media (latence + charge).
- Sélecteurs CSS dépendants du HTML de kaktus.media : **fragiles** si le site change de structure.
- Aucun test dédié au `NewsParserService` (seulement des tests d'exemple par défaut).
- Sanctum installé mais non utilisé dans le flux applicatif.

---

## 11. Tests

- `tests/Feature/ExampleTest.php` : vérifie que `GET /` renvoie un statut 200.
- `tests/Unit/ExampleTest.php` : test unitaire d'exemple Laravel.
- Configuration : `phpunit.xml`.

> ⚠️ Le test `GET /` effectue un appel réseau réel vers kaktus.media (pas de mock HTTP).

---

## 12. Résumé de l'architecture en une phrase

Une application Laravel 12 **stateless** où un **service de scraping** (`NewsParserService`)
transforme le HTML de kaktus.media en **DTO `News`**, exposés soit via une **vue Blade**
(rendu initial), soit via une **API JSON** consommée en JavaScript — sans persistance métier,
la base SQLite ne servant qu'aux mécanismes internes de Laravel.
