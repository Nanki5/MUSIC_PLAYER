document.querySelector('.login__form').addEventListener('submit', e => {
    e.preventDefault();
    const email    = e.target.email.value.trim();
    const password = e.target.password.value;

    try {
        Auth.login({ email, password });
        location.href = 'home.html';
    } catch (err) {
        alert(err.message);
    }
});