// Dark mode toggle
document.getElementById('themeToggle').addEventListener('click', () => {
    const html = document.documentElement;
    html.classList.toggle('dark');
    localStorage.setItem('theme', html.classList.contains('dark') ? 'dark' : 'light');
});

// Form validation
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const formData = new FormData(form);
        formData.append('ajax', true);

        try {
            const response = await fetch(form.action, {
                method: form.method,
                body: formData
            });

            const result = await response.json();
            
            if (result.success) {
                showToast(result.message);
                if (result.redirect) {
                    setTimeout(() => window.location = result.redirect, 1500);
                }
            } else {
                showToast(result.error, 'error');
            }
        } catch (error) {
            showToast('Network error - please try again', 'error');
        }
    });
});

function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg 
        ${type === 'error' ? 'bg-red-500' : 'bg-green-500'} text-white`;
    toast.classList.remove('hidden');
    
    setTimeout(() => toast.classList.add('hidden'), 3000);
}
// Theme Toggle
document.getElementById('themeToggle').addEventListener('click', () => {
    const html = document.documentElement;
    const isDark = html.classList.contains('dark');
    
    html.classList.toggle('dark');
    localStorage.setItem('theme', isDark ? 'light' : 'dark');
    document.cookie = `theme=${isDark ? 'light' : 'dark'}; path=/; max-age=31536000`;
});

// User Dropdown
document.querySelectorAll('.relative button').forEach(button => {
    button.addEventListener('click', () => {
        const dropdown = button.nextElementSibling;
        dropdown.classList.toggle('hidden');
    });
});

// Close dropdown when clicking outside
document.addEventListener('click', (e) => {
    if (!e.target.closest('.relative')) {
        document.querySelectorAll('.relative .absolute').forEach(dropdown => {
            dropdown.classList.add('hidden');
        });
    }
});