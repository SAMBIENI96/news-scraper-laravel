<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Новости — Kaktus.media</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=IBM+Plex+Sans:wght@300;400;500&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --ink:     #0d0d0d;
            --paper:   #f5f0e8;
            --accent:  #c8392b;
            --muted:   #7a7060;
            --border:  #d4cbb8;
            --card-bg: #faf7f2;
        }
        body { font-family: 'IBM Plex Sans', sans-serif; background: var(--paper); color: var(--ink); min-height: 100vh; }

        header { border-bottom: 3px solid var(--ink); padding: 0 2rem; background: var(--paper); position: sticky; top: 0; z-index: 100; }
        .masthead-inner { max-width: 1200px; margin: 0 auto; display: flex; align-items: center; justify-content: space-between; padding: 1rem 0; }
        .site-logo { font-family: 'Playfair Display', serif; font-weight: 900; font-size: 2rem; letter-spacing: -1px; color: var(--ink); text-decoration: none; }
        .site-logo span { color: var(--accent); }
        .site-tagline { font-size: .7rem; letter-spacing: .15em; text-transform: uppercase; color: var(--muted); font-family: 'IBM Plex Mono', monospace; }

        .controls { background: var(--ink); color: var(--paper); padding: .75rem 2rem; }
        .controls-inner { max-width: 1200px; margin: 0 auto; display: flex; gap: 1rem; align-items: center; flex-wrap: wrap; }
        .ctrl-label { font-family: 'IBM Plex Mono', monospace; font-size: .7rem; letter-spacing: .1em; text-transform: uppercase; color: #aaa; white-space: nowrap; }
        .ctrl-input { background: transparent; border: 1px solid #444; border-radius: 4px; color: var(--paper); font-family: 'IBM Plex Mono', monospace; font-size: .85rem; padding: .45rem .75rem; transition: border-color .2s; }
        .ctrl-input:focus { outline: none; border-color: var(--accent); }
        .ctrl-input[type="date"]::-webkit-calendar-picker-indicator { filter: invert(1); cursor: pointer; }
        .search-wrap { flex: 1; min-width: 200px; display: flex; gap: .5rem; }
        .search-wrap input { flex: 1; }
        .btn-search { background: var(--accent); border: none; border-radius: 4px; color: #fff; font-family: 'IBM Plex Mono', monospace; font-size: .8rem; letter-spacing: .05em; padding: .45rem 1.1rem; cursor: pointer; transition: opacity .2s; white-space: nowrap; }
        .btn-search:hover { opacity: .85; }
        .btn-search:disabled { opacity: .4; cursor: not-allowed; }

        main { max-width: 1200px; margin: 0 auto; padding: 2rem; }
        .status-bar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; padding-bottom: .75rem; border-bottom: 1px solid var(--border); }
        .status-count { font-family: 'IBM Plex Mono', monospace; font-size: .8rem; color: var(--muted); }
        .status-count strong { color: var(--ink); }
        .status-date { font-family: 'IBM Plex Mono', monospace; font-size: .75rem; color: var(--muted); }

        #news-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.5rem; }

        .news-card { background: var(--card-bg); border: 1px solid var(--border); border-radius: 6px; overflow: hidden; box-shadow: 0 2px 12px rgba(0,0,0,.08); display: flex; flex-direction: column; transition: transform .2s, box-shadow .2s; animation: fadeUp .35s ease both; }
        .news-card:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(0,0,0,.12); }
        @keyframes fadeUp { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }

        .card-img { width: 100%; height: 180px; object-fit: cover; display: block; }
        .card-img-placeholder { width: 100%; height: 180px; background: linear-gradient(135deg, #e8e0d0 0%, #d4cbb8 100%); display: flex; align-items: center; justify-content: center; }
        .card-body { padding: 1rem 1.1rem 1.2rem; flex: 1; display: flex; flex-direction: column; }
        .card-date { font-family: 'IBM Plex Mono', monospace; font-size: .68rem; color: var(--accent); letter-spacing: .05em; margin-bottom: .5rem; }
        .card-title { font-family: 'Playfair Display', serif; font-weight: 700; font-size: 1.05rem; line-height: 1.4; color: var(--ink); flex: 1; margin-bottom: 1rem; }
        .card-link { align-self: flex-start; font-family: 'IBM Plex Mono', monospace; font-size: .72rem; letter-spacing: .06em; text-transform: uppercase; color: var(--accent); text-decoration: none; border-bottom: 1px solid currentColor; padding-bottom: 1px; transition: opacity .15s; }
        .card-link:hover { opacity: .7; }

        .empty-state { grid-column: 1 / -1; text-align: center; padding: 4rem 2rem; }
        .empty-state h2 { font-family: 'Playfair Display', serif; font-size: 1.8rem; color: var(--muted); margin-bottom: .5rem; }
        .empty-state p { color: var(--muted); font-size: .9rem; }

        #loader { display: none; grid-column: 1 / -1; text-align: center; padding: 3rem; }
        .spinner { width: 36px; height: 36px; border: 3px solid var(--border); border-top-color: var(--accent); border-radius: 50%; animation: spin .7s linear infinite; margin: 0 auto 1rem; }
        @keyframes spin { to { transform: rotate(360deg); } }
        #loader p { font-family: 'IBM Plex Mono', monospace; font-size: .8rem; color: var(--muted); }

        footer { border-top: 1px solid var(--border); padding: 1.5rem 2rem; text-align: center; font-family: 'IBM Plex Mono', monospace; font-size: .72rem; color: var(--muted); margin-top: 3rem; }

        @media (max-width: 640px) {
            .masthead-inner { flex-direction: column; gap: .5rem; text-align: center; }
            .controls-inner { flex-direction: column; align-items: stretch; }
            .search-wrap { flex-direction: column; }
            #news-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<header>
    <div class="masthead-inner">
        <a href="/" class="site-logo"><span>.</span>News</a>
    </div>
</header>

<div class="controls">
    <div class="controls-inner">
        <span class="ctrl-label">Дата</span>
        <input id="date-picker" class="ctrl-input" type="date" value="{{ $selectedDate }}" max="{{ now()->format('Y-m-d') }}">
        <span class="ctrl-label">Поиск</span>
        <div class="search-wrap">
            <input id="search-input" class="ctrl-input" type="text" placeholder="Введите ключевое слово…">
            <button id="btn-search" class="btn-search">Найти</button>
        </div>
    </div>
</div>

<main>
    <div class="status-bar">
        <span class="status-count">Найдено: <strong id="count-val">{{ $total }}</strong> новостей</span>
        <span class="status-date" id="status-date">{{ $selectedDate }}</span>
    </div>

    <div id="news-grid">
        <div id="loader">
            <div class="spinner"></div>
            <p>Загружаем новости…</p>
        </div>

        @forelse ($news as $item)
            <article class="news-card">
                @if($item['image'])
                    <img class="card-img" src="{{ $item['image'] }}" alt="{{ $item['title'] }}" loading="lazy">
                @else
                    <div class="card-img-placeholder">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#7a7060" stroke-width="1.5">
                            <rect x="3" y="3" width="18" height="18" rx="2"/>
                            <circle cx="8.5" cy="8.5" r="1.5"/>
                            <polyline points="21 15 16 10 5 21"/>
                        </svg>
                    </div>
                @endif
                <div class="card-body">
                    <div class="card-date">{{ $item['date'] }}</div>
                    <h2 class="card-title">{{ $item['title'] }}</h2>
                    <a class="card-link" href="{{ $item['url'] }}" target="_blank" rel="noopener">Читать →</a>
                </div>
            </article>
        @empty
            <div class="empty-state">
                <h2>Новостей не найдено</h2>
                <p>Попробуйте выбрать другую дату.</p>
            </div>
        @endforelse
    </div>
</main>

<footer>
    Источник данных: <a href="https://kaktus.media" target="_blank" style="color:var(--accent)">kaktus.media</a>
    &nbsp;·&nbsp; Kaktus News Aggregator &copy; {{ date('Y') }}
</footer>

<script>
(function () {
    const datePicker  = document.getElementById('date-picker');
    const searchInput = document.getElementById('search-input');
    const btnSearch   = document.getElementById('btn-search');
    const grid        = document.getElementById('news-grid');
    const loader      = document.getElementById('loader');
    const countVal    = document.getElementById('count-val');
    const statusDate  = document.getElementById('status-date');

    async function fetchNews(date, search) {
        showLoader();
        try {
            const params = new URLSearchParams({ date });
            if (search && search.trim()) params.set('search', search.trim());

            const res  = await fetch('/api/news?' + params.toString());
            const data = await res.json();

            renderCards(data.news);
            countVal.textContent   = data.total;
            statusDate.textContent = data.date;
        } catch (err) {
            grid.innerHTML = emptyState('Ошибка загрузки', 'Не удалось получить данные. Проверьте соединение.');
        } finally {
            hideLoader();
        }
    }

    function renderCards(news) {
        if (!news || !news.length) {
            grid.innerHTML = emptyState('Новостей не найдено', 'Попробуйте другую дату или измените запрос.');
            return;
        }
        grid.innerHTML = news.map((item, i) => `
            <article class="news-card" style="animation-delay:${i * 0.04}s">
                ${item.image
                    ? `<img class="card-img" src="${esc(item.image)}" alt="${esc(item.title)}" loading="lazy">`
                    : `<div class="card-img-placeholder"><svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#7a7060" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg></div>`
                }
                <div class="card-body">
                    <div class="card-date">${esc(item.date)}</div>
                    <h2 class="card-title">${esc(item.title)}</h2>
                    <a class="card-link" href="${esc(item.url)}" target="_blank" rel="noopener">Читать →</a>
                </div>
            </article>
        `).join('');
    }

    function emptyState(title, msg) {
        return `<div class="empty-state"><h2>${title}</h2><p>${msg}</p></div>`;
    }

    function esc(str) {
        if (!str) return '';
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

function showLoader() {
    grid.innerHTML = '';      // vide tout
    grid.appendChild(loader); // remet le loader
    loader.style.display = 'block';
    btnSearch.disabled = true;
}

    function hideLoader() {
        loader.style.display = 'none';
        btnSearch.disabled   = false;
    }

    datePicker.addEventListener('change', () => fetchNews(datePicker.value, searchInput.value));
    btnSearch.addEventListener('click',   () => fetchNews(datePicker.value, searchInput.value));
    searchInput.addEventListener('keydown', e => { if (e.key === 'Enter') fetchNews(datePicker.value, searchInput.value); });
})();
</script>
</body>
</html>
