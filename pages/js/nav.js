document.addEventListener('DOMContentLoaded', () => {// выбранная страница
    const path = window.location.pathname;
    document.querySelectorAll('.nav__link').forEach(link => {
        const href = link.getAttribute('href');
        if (path.includes(href)) {
            link.classList.add('nav__link--active');
        } else {
            link.classList.remove('nav__link--active');
        }
    });
    const authGroup = document.getElementById('authGroup');
    if (!authGroup) return; //если нет хедера

    const user = DB.getCurrentUser();
    if (user) {
        authGroup.innerHTML = `
            <a href="profile.html" class="button__login button__login--icon" title="${user.name}">
                <i class="ri-user-fill"></i>
            </a>
            <button class="button__logout" id="logoutBtn" title="Выйти">
                <i class="ri-logout-box-r-line"></i>
            </button>
        `;
        document.getElementById('logoutBtn').onclick = () => {
    if (confirm('Вы уверены, что хотите выйти?')) {
        Auth.logout();
    }
};
    } else {
        authGroup.innerHTML = `
            <a href="login.html" class="button__login button__login--text">Sign in</a>
        `;
    }
});
const searchButton = document.getElementById('navSearchButton');
if (searchButton) {
    searchButton.addEventListener('click', () => {
        const form = document.querySelector('.search__form');
        if (!form) return;
        form.classList.toggle('search__form--open');
        if (form.classList.contains('search__form--open')) {
            form.querySelector('.search__form-input').focus();
        }
    });
}