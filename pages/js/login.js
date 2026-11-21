document.querySelector('.login__form').addEventListener('submit', async e => {
    e.preventDefault();
    const login    = (e.target.login || e.target.email).value.trim();
    const password = e.target.password.value;

    try {
        await API.login(login, password);
        location.href = 'home.html';
    } catch (err) {
        alert(err.message);
    }
});
