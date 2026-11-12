document.addEventListener('DOMContentLoaded', () => {
    const input   = document.getElementById('searchInput');
    const results = document.getElementById('searchResults');
    if (!input || !results) return;

    const MAX_RESULTS = 4;
    let items = [];

    function renderResults(query) {
        const q = query.trim().toLowerCase();

        if (!q) {
            hideResults();
            return;
        }

        const tracks = DB.getPublicTracks()
            .filter(t =>
                t.title.toLowerCase().includes(q) ||
                t.artist.toLowerCase().includes(q)
            )
            .map(t => ({ type: 'track', data: t }));

        const playlists = DB.getPublicPlaylists()
            .filter(p => p.name.toLowerCase().includes(q))
            .map(p => ({ type: 'playlist', data: p }));

        items = [...tracks, ...playlists].slice(0, MAX_RESULTS);

        if (!items.length) {
            results.innerHTML = '<div class="search-result search-result--empty">Ничего не найдено</div>';
            results.hidden = false;
            return;
        }

        results.innerHTML = items.map((item, i) => {
            const d = item.data;
            const cover = d.cover || '../../assets/images/images.png';
            const title = item.type === 'track' ? d.title : d.name;
            const sub   = item.type === 'track'
                ? d.artist
                : `${d.trackIds.length} треков`;
            const type  = item.type === 'track' ? 'Трек' : 'Плейлист';

            return `
                <div class="search-result" data-index="${i}">
                    <img src="${cover}" alt="" class="search-result__cover">
                    <div class="search-result__info">
                        <div class="search-result__title">${escapeHtml(title)}</div>
                        <div class="search-result__artist">${escapeHtml(sub)}</div>
                    </div>
                    <span class="search-result__type">${type}</span>
                </div>
            `;
        }).join('');

        results.hidden = false;
    }

    function hideResults() {
        results.hidden = true;
        results.innerHTML = '';
        items = [];
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, c => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[c]));
    }

    let timer;
    input.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(() => renderResults(input.value), 120);
    });

    input.addEventListener('focus', () => {
        if (input.value.trim()) renderResults(input.value);
    });

    results.addEventListener('mousedown', (e) => {
        const row = e.target.closest('.search-result[data-index]');
        if (!row) return;
        e.preventDefault();

        const item = items[parseInt(row.dataset.index, 10)];
        if (!item) return;

        if (item.type === 'track') {
            const all = DB.getPublicTracks();
            const idx = all.findIndex(t => t.id === item.data.id);
            Player.play(all, idx >= 0 ? idx : 0);
        } else {
            const tracks = item.data.trackIds
                .map(id => DB.getTracks().find(t => t.id === id))
                .filter(Boolean);
            if (tracks.length) {
                if (typeof openPlaylist === 'function') {
                    openPlaylist(item.data.id);
                } else {
                    Player.play(tracks, 0);
                }
            }
        }

        hideResults();
        input.value = '';
        input.blur();
    });

    input.addEventListener('blur', () => {
        setTimeout(hideResults, 150);
    });

    input.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            hideResults();
            input.blur();
        }
    });
});