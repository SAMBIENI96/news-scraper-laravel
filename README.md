# Kaktus News — Agrégateur d'actualités

> Projet réalisé dans le cadre du test technique **Polaris** pour un poste de développeur PHP Laravel junior.

Agrégateur d'actualités basé sur [kaktus.media](https://kaktus.media), développé avec **PHP 8.2** et **Laravel 12**.

---

## Fonctionnalités

- Affichage des actualités du jour au chargement de la page
- Filtre par date — consulter les actualités d'un jour choisi
- Recherche par titre **côté backend** — le frontend reçoit une liste déjà filtrée
- Extraction automatique (scraping) des données depuis kaktus.media
- Affichage des images, titres et liens de chaque article

---

## Schéma de fonctionnement

```
1. GET /              → Laravel scrape kaktus.media et affiche les news du jour
2. User change date   → JS envoie GET /api/news?date=YYYY-MM-DD
3. User tape un mot   → JS envoie GET /api/news?date=YYYY-MM-DD&search=mot
4. Backend filtre     → NewsParserService::getNews($date, $search)
5. Frontend reçoit    → JSON avec la liste filtrée → affichage dynamique
```

---

## Stack technique

| Technologie | Rôle |
|---|---|
| PHP 8.2 | Langage principal |
| Laravel 12 | Framework backend |
| GuzzleHTTP | Requêtes HTTP vers kaktus.media |
| Symfony DomCrawler | Parsing HTML |
| Blade + JavaScript | Frontend |
| SQLite | Base de données (sessions) |

---

## Prérequis

- PHP >= 8.2
- Composer
- Extensions PHP : `sqlite3`, `curl`, `mbstring`, `xml`

---

## Installation

### 1. Cloner le projet

```bash
git clone https://laravel-news-parser-admin@bitbucket.org/laravel-news-parser/laravel-news.git
cd laravel-news
```

### 2. Installer les dépendances PHP

```bash
composer install
```

### 3. Copier le fichier d'environnement

```bash
cp .env.example .env
```

### 4. Générer la clé d'application

```bash
php artisan key:generate
```

### 5. Créer la base de données SQLite

```bash
touch database/database.sqlite
```

### 6. Lancer les migrations

```bash
php artisan migrate
```

---

## Lancer le projet

```bash
php artisan serve
```

L'application sera accessible sur :

```
http://localhost:8000
```

---

## Architecture du projet

```
app/
├── DTO/
│   └── News.php                  # Classe News (date, title, url, image)
├── Services/
│   └── NewsParserService.php     # Scraping et parsing de kaktus.media
├── Http/
│   └── Controllers/
│       └── NewsController.php    # Contrôleur (index + search)
routes/
├── web.php                       # GET /
├── api.php                       # GET /api/news
bootstrap/
└── app.php                       # Chargement des routes web et API
resources/views/news/
└── index.blade.php               # Vue Blade + JS (fetch API)
```

---

## Classe News

```php
// app/DTO/News.php
class News
{
    public function __construct(
        public readonly string  $date,
        public readonly string  $title,
        public readonly string  $url,
        public readonly ?string $image = null,
    ) {}

    public function toArray(): array
    {
        return [
            'date'  => $this->date,
            'title' => $this->title,
            'url'   => $this->url,
            'image' => $this->image,
        ];
    }
}
```

---

## Endpoints

### Page principale

```
GET /
```

Affiche les actualités du jour dans la vue Blade (rendu serveur).

---

### API de recherche

```
GET /api/news?date=YYYY-MM-DD&search=mot-clé
```

Retourne les actualités filtrées en JSON.

**Paramètres :**

| Paramètre | Type | Requis | Description |
|---|---|---|---|
| `date` | `YYYY-MM-DD` | ✅ | Date des actualités |
| `search` | `string` | ❌ | Mot-clé de recherche dans le titre |

**Exemple de réponse :**

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

---

## Dépendances externes

```bash
# Requêtes HTTP vers kaktus.media
composer require guzzlehttp/guzzle

# Parsing HTML
composer require symfony/dom-crawler symfony/css-selector
```

---

## Notes techniques

- **kaktus.media** ne dispose pas d'API publique ni de flux RSS — les données sont extraites par scraping HTML via Symfony DomCrawler.
- La date des articles est détectée via l'URL de l'image associée (ex: `2026-04-01_21-11-29_...jpg`).
- Les articles sans image et sans date détectable sont inclus uniquement pour la date du jour.
- Le certificat SSL est ignoré (`verify => false`) pour éviter les erreurs de connexion.
- La recherche est **entièrement côté backend** — le frontend ne filtre jamais lui-même.

---

## Auteur

SAMBIENI Ebenezer



# Kaktus News — Агрегатор новостей

> Проект выполнен в рамках технического тестового задания **Polaris** на должность junior PHP Laravel разработчика.

Агрегатор новостей на основе [kaktus.media](https://kaktus.media), разработан на **PHP 8.2** и **Laravel 12**.

---

## Функциональность

- Отображение новостей текущего дня при загрузке страницы
- Фильтр по дате — просмотр новостей за выбранный день
- Поиск по заголовку **на стороне бэкенда** — фронтенд получает уже отфильтрованный список
- Автоматическое извлечение (парсинг) данных с сайта kaktus.media
- Отображение изображений, заголовков и ссылок каждой статьи

---

## Схема работы

```
1. GET /               → Laravel парсит kaktus.media и отображает новости за сегодня
2. Пользователь меняет дату   → JS отправляет GET /api/news?date=YYYY-MM-DD
3. Пользователь вводит слово  → JS отправляет GET /api/news?date=YYYY-MM-DD&search=слово
4. Бэкенд фильтрует   → NewsParserService::getNews($date, $search)
5. Фронтенд получает  → JSON с отфильтрованным списком → динамическое отображение
```

---

## Технологический стек

| Технология | Роль |
|---|---|
| PHP 8.2 | Основной язык |
| Laravel 12 | Backend фреймворк |
| GuzzleHTTP | HTTP-запросы к kaktus.media |
| Symfony DomCrawler | Парсинг HTML |
| Blade + JavaScript | Фронтенд |
| SQLite | База данных (сессии) |

---

## Требования

- PHP >= 8.2
- Composer
- PHP расширения: `sqlite3`, `curl`, `mbstring`, `xml`

---

## Установка

### 1. Клонировать проект

```bash
git clone https://laravel-news-parser-admin@bitbucket.org/laravel-news-parser/laravel-news.git
cd laravel-news
```

### 2. Установить PHP зависимости

```bash
composer install
```

### 3. Скопировать файл окружения

```bash
cp .env.example .env
```

### 4. Сгенерировать ключ приложения

```bash
php artisan key:generate
```

### 5. Создать базу данных SQLite

```bash
touch database/database.sqlite
```

### 6. Запустить миграции

```bash
php artisan migrate
```

---

## Запуск проекта

```bash
php artisan serve
```

Приложение будет доступно по адресу:

```
http://localhost:8000
```

---

## Архитектура проекта

```
app/
├── DTO/
│   └── News.php                  # Класс News (date, title, url, image)
├── Services/
│   └── NewsParserService.php     # Парсинг kaktus.media
├── Http/
│   └── Controllers/
│       └── NewsController.php    # Контроллер (index + search)
routes/
├── web.php                       # GET /
├── api.php                       # GET /api/news
bootstrap/
└── app.php                       # Подключение web и API маршрутов
resources/views/news/
└── index.blade.php               # Blade шаблон + JS (fetch API)
```

---

## Класс News

```php
// app/DTO/News.php
class News
{
    public function __construct(
        public readonly string  $date,
        public readonly string  $title,
        public readonly string  $url,
        public readonly ?string $image = null,
    ) {}

    public function toArray(): array
    {
        return [
            'date'  => $this->date,
            'title' => $this->title,
            'url'   => $this->url,
            'image' => $this->image,
        ];
    }
}
```

---

## Эндпоинты

### Главная страница

```
GET /
```

Отображает новости текущего дня в Blade шаблоне (серверный рендеринг).

---

### API поиска

```
GET /api/news?date=YYYY-MM-DD&search=ключевое-слово
```

Возвращает отфильтрованные новости в формате JSON.

**Параметры:**

| Параметр | Тип | Обязательный | Описание |
|---|---|---|---|
| `date` | `YYYY-MM-DD` | ✅ | Дата новостей |
| `search` | `string` | ❌ | Ключевое слово для поиска по заголовку |

**Пример ответа:**

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

---

## Внешние зависимости

```bash
# HTTP-запросы к kaktus.media
composer require guzzlehttp/guzzle

# Парсинг HTML
composer require symfony/dom-crawler symfony/css-selector
```

---

## Технические примечания

- **kaktus.media** не предоставляет публичный API и RSS-ленту — данные извлекаются путём парсинга HTML с помощью Symfony DomCrawler.
- Дата статей определяется по URL изображения (например: `2026-04-01_21-11-29_...jpg`).
- Статьи без изображения и без определённой даты включаются только для текущего дня.
- SSL-сертификат игнорируется (`verify => false`) во избежание ошибок подключения.
- Поиск реализован **полностью на стороне бэкенда** — фронтенд никогда не фильтрует самостоятельно.

---

