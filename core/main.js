import { auth, songs, likes } from '../core/api.js';

document.querySelectorAll('.home__nav-link').forEach(link => {
    const href = link.getAttribute('href');
    if (location.pathname.endsWith(href)) {
        link.classList.add('home__nav-link--active');
    }
});