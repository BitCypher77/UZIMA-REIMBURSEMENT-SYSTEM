</main>
<footer class="theme-blue dark:bg-gray-800 text-white py-6 mt-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <p class="text-sm">&copy; <?= date('Y') ?> Uzima Reimbursement System. All rights reserved.</p>
    </div>
</footer>

<script>
    // Theme Toggle
    document.getElementById('themeToggle').addEventListener('click', () => {
        const html = document.documentElement;
        const isDark = html.classList.contains('dark');
        
        html.classList.toggle('dark');
        localStorage.setItem('theme', isDark ? 'light' : 'dark');
        document.cookie = `theme=${isDark ? 'light' : 'dark'}; path=/; max-age=31536000`;
    });

    // Initialize Lucide Icons
    lucide.createIcons();
</script>
</body>
</html>