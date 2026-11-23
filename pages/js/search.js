document.addEventListener('DOMContentLoaded', () => {
    const input   = document.getElementById('searchInput');
    const results = document.getElementById('searchResults');
    if (!input || !results) return;

    const MAX_RESULTS = 4;
    let items = [];
    let foundTracks = [];
    let lastRequestId = 0;

    async function renderResults(query) {
        const q = query.trim();
        if (!q) {
            hideResults();
            return;
        }

        const reqId = ++lastRequestId;

        try {
            const tracks = await API.getTracks({ query: q, limit: MAX_RESULTS });

            let playlists = [];
            const user = await API.getCurrentUser();
            if (user) {
                const myPlaylists = await API.getMyPlaylists();
                const lowerQ = q.toLowerCase();
                playlists = myPlaylists.filter(p => p.name.toLowerCase().includes(lowerQ));
            }

            if (reqId !== lastRequestId) return;

            foundTracks = tracks;
            items = [
                ...tracks.map(t => ({ type: 'track', data: t })),
                ...playlists.map(p => ({ type: 'playlist', data: p })),
            ].slice(0, MAX_RESULTS);

            if (!items.length) {
                results.innerHTML = `<div class="search-result">Ничего не найдено</div>`;
                results.hidden = false;
                return;
            }

            results.innerHTML = items.map((item, i) => {
                const d = item.data;
                const cover = d.cover || '../../assets/images/images.png';
                const title = item.type === 'track' ? d.title : d.name;
                const sub   = item.type === 'track' ? d.artist : 'Мой плейлист';
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
        } catch (err) {
            if (reqId === lastRequestId) {
                console.error('Ошибка поиска:', err);
                hideResults();
            }
        }
    }

    function hideResults() {
        results.hidden = true;
        results.innerHTML = '';
        items = [];
        foundTracks = [];
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, c => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        }[c]));
    }

    let timer;
    input.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(() => renderResults(input.value), 250);
    });

    input.addEventListener('focus', () => {
        if (input.value.trim()) renderResults(input.value);
    });

    results.addEventListener('mousedown', async (e) => {
        const row = e.target.closest('.search-result[data-index]');
        if (!row) return;
        e.preventDefault();

        const item = items[parseInt(row.dataset.index, 10)];
        if (!item) return;

        if (item.type === 'track') {
            const idx = foundTracks.findIndex(t => t.id === item.data.id);
            Player.play(foundTracks, idx >= 0 ? idx : 0);
        } else {
            if (typeof openPlaylist === 'function') {
                openPlaylist(item.data.id);
            } else {
                const fullPlaylist = await API.getPlaylist(item.data.id);
                if (fullPlaylist.tracks.length) {
                    Player.play(fullPlaylist.tracks, 0);
                } else {
                    alert('Плейлист пуст');
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