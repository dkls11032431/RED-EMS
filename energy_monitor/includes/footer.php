<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleTheme() {
    var dark = !document.documentElement.classList.contains('theme-dark');
    document.documentElement.classList.toggle('theme-dark', dark);
    document.body.classList.toggle('dark', dark);
    try {
        localStorage.setItem('ems-theme', dark ? 'dark' : 'light');
    } catch (e) {}
}
function toggleNav() {
    document.body.classList.toggle('nav-open');
}
document.querySelectorAll('.sidebar a[href]').forEach(function (link) {
    link.addEventListener('click', function () {
        if (window.matchMedia('(max-width: 768px)').matches) {
            document.body.classList.remove('nav-open');
        }
    });
});
if (document.documentElement.classList.contains('theme-dark')) {
    document.body.classList.add('dark');
}
</script>
</body>
</html>
