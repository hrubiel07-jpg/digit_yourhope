// Main JavaScript file for Digital YOURHOPE

document.addEventListener('DOMContentLoaded', function() {
    // ===== MOBILE MENU TOGGLE =====
    const menuToggle = document.querySelector('.menu-toggle');
    const navLinks = document.querySelector('.nav-links');
    
    if (menuToggle && navLinks) {
        menuToggle.addEventListener('click', function() {
            navLinks.classList.toggle('active');
        });
        
        // Close menu when clicking outside
        document.addEventListener('click', function(event) {
            if (!event.target.closest('.navbar')) {
                navLinks.classList.remove('active');
            }
        });
    }
    
    // ===== FORM VALIDATION =====
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(event) {
            const requiredFields = form.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    highlightError(field);
                } else {
                    removeError(field);
                }
            });
            
            if (!isValid) {
                event.preventDefault();
                showToast('Veuillez remplir tous les champs obligatoires', 'error');
            }
        });
    });
    
    // ===== PASSWORD STRENGTH =====
    const passwordInput = document.getElementById('password');
    if (passwordInput) {
        passwordInput.addEventListener('input', function() {
            const strength = calculatePasswordStrength(this.value);
            updatePasswordStrengthIndicator(strength);
        });
    }
    
    // ===== IMAGE UPLOAD PREVIEW =====
    const imageInputs = document.querySelectorAll('input[type="file"][accept*="image"]');
    imageInputs.forEach(input => {
        input.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const preview = document.getElementById(this.id + '-preview') || 
                               createImagePreview(this);
                previewPreview(file, preview);
            }
        });
    });
    
    // ===== AUTO-GROW TEXTAREA =====
    const textareas = document.querySelectorAll('textarea');
    textareas.forEach(textarea => {
        textarea.addEventListener('input', autoGrow);
        autoGrow.call(textarea); // Initial sizing
    });
    
    // ===== SMOOTH SCROLL =====
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            if (href !== '#') {
                e.preventDefault();
                const target = document.querySelector(href);
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            }
        });
    });
    
    // ===== LAZY LOAD IMAGES =====
    if ('IntersectionObserver' in window) {
        const lazyImages = document.querySelectorAll('img[data-src]');
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.classList.add('loaded');
                    observer.unobserve(img);
                }
            });
        });
        
        lazyImages.forEach(img => imageObserver.observe(img));
    }
    
    // ===== REAL-TIME SEARCH =====
    const searchInputs = document.querySelectorAll('.search-box input');
    searchInputs.forEach(input => {
        input.addEventListener('input', debounce(function() {
            performSearch(this.value);
        }, 300));
    });
    
    // ===== NOTIFICATION SYSTEM =====
    setupNotifications();
});

// ===== HELPER FUNCTIONS =====

function calculatePasswordStrength(password) {
    let strength = 0;
    
    if (password.length >= 8) strength++;
    if (/[A-Z]/.test(password)) strength++;
    if (/[a-z]/.test(password)) strength++;
    if (/[0-9]/.test(password)) strength++;
    if (/[^A-Za-z0-9]/.test(password)) strength++;
    
    return Math.min(strength, 5);
}

function updatePasswordStrengthIndicator(strength) {
    const indicator = document.getElementById('password-strength');
    if (!indicator) return;
    
    const messages = [
        'Très faible',
        'Faible',
        'Moyen',
        'Fort',
        'Très fort'
    ];
    
    const colors = [
        '#e74c3c',
        '#e67e22',
        '#f1c40f',
        '#2ecc71',
        '#27ae60'
    ];
    
    indicator.textContent = messages[strength - 1] || '';
    indicator.style.color = colors[strength - 1] || '#95a5a6';
    
    // Update strength bars
    const bars = indicator.parentElement.querySelectorAll('.strength-bar');
    bars.forEach((bar, index) => {
        bar.style.backgroundColor = index < strength ? colors[strength - 1] : '#eee';
    });
}

function highlightError(element) {
    element.style.borderColor = '#e74c3c';
    element.style.boxShadow = '0 0 0 2px rgba(231, 76, 60, 0.2)';
    
    // Add error message if not present
    if (!element.nextElementSibling || !element.nextElementSibling.classList.contains('error-message')) {
        const errorMsg = document.createElement('div');
        errorMsg.className = 'error-message';
        errorMsg.textContent = 'Ce champ est obligatoire';
        errorMsg.style.color = '#e74c3c';
        errorMsg.style.fontSize = '0.875rem';
        errorMsg.style.marginTop = '5px';
        element.parentNode.insertBefore(errorMsg, element.nextSibling);
    }
}

function removeError(element) {
    element.style.borderColor = '';
    element.style.boxShadow = '';
    
    // Remove error message
    const errorMsg = element.nextElementSibling;
    if (errorMsg && errorMsg.classList.contains('error-message')) {
        errorMsg.remove();
    }
}

function createImagePreview(input) {
    const preview = document.createElement('div');
    preview.id = input.id + '-preview';
    preview.style.width = '100px';
    preview.style.height = '100px';
    preview.style.borderRadius = '50%';
    preview.style.overflow = 'hidden';
    preview.style.margin = '10px 0';
    preview.style.background = '#f8f9fa';
    preview.style.display = 'flex';
    preview.style.alignItems = 'center';
    preview.style.justifyContent = 'center';
    preview.style.color = '#ccc';
    
    input.parentNode.insertBefore(preview, input.nextSibling);
    return preview;
}

function previewPreview(file, preview) {
    const reader = new FileReader();
    reader.onload = function(e) {
        preview.innerHTML = '';
        const img = document.createElement('img');
        img.src = e.target.result;
        img.style.width = '100%';
        img.style.height = '100%';
        img.style.objectFit = 'cover';
        preview.appendChild(img);
    };
    reader.readAsDataURL(file);
}

function autoGrow() {
    this.style.height = 'auto';
    this.style.height = (this.scrollHeight) + 'px';
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function performSearch(query) {
    // This would be implemented based on the page
    // For example, filtering tables or lists
    const tables = document.querySelectorAll('.data-table');
    tables.forEach(table => {
        filterTable(table, query);
    });
    
    const lists = document.querySelectorAll('.list-container');
    lists.forEach(list => {
        filterList(list, query);
    });
}

function filterTable(table, query) {
    const rows = table.querySelectorAll('tbody tr');
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(query.toLowerCase()) ? '' : 'none';
    });
}

function filterList(list, query) {
    const items = list.querySelectorAll('.list-item');
    items.forEach(item => {
        const text = item.textContent.toLowerCase();
        item.style.display = text.includes(query.toLowerCase()) ? '' : 'none';
    });
}

function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = message;
    
    // Add styles
    toast.style.position = 'fixed';
    toast.style.top = '20px';
    toast.style.right = '20px';
    toast.style.padding = '15px 20px';
    toast.style.background = type === 'error' ? '#e74c3c' : 
                            type === 'success' ? '#2ecc71' : '#3498db';
    toast.style.color = 'white';
    toast.style.borderRadius = '5px';
    toast.style.boxShadow = '0 5px 15px rgba(0,0,0,0.2)';
    toast.style.zIndex = '9999';
    toast.style.animation = 'slideIn 0.3s ease';
    
    document.body.appendChild(toast);
    
    // Remove after 3 seconds
    setTimeout(() => {
        toast.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
    
    // Add CSS animations
    if (!document.querySelector('#toast-animations')) {
        const style = document.createElement('style');
        style.id = 'toast-animations';
        style.textContent = `
            @keyframes slideIn {
                from {
                    transform: translateX(100%);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
            @keyframes slideOut {
                from {
                    transform: translateX(0);
                    opacity: 1;
                }
                to {
                    transform: translateX(100%);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);
    }
}

function setupNotifications() {
    // Check for new notifications every 30 seconds
    if (window.Notification && Notification.permission === 'granted') {
        setInterval(checkNotifications, 30000);
    }
    
    // Request notification permission
    const notificationBtn = document.getElementById('enable-notifications');
    if (notificationBtn) {
        notificationBtn.addEventListener('click', requestNotificationPermission);
    }
}

function requestNotificationPermission() {
    if (window.Notification && Notification.permission === 'default') {
        Notification.requestPermission().then(permission => {
            if (permission === 'granted') {
                showToast('Notifications activées avec succès', 'success');
            }
        });
    }
}

function checkNotifications() {
    // This would make an AJAX call to check for new notifications
    // For now, just a placeholder
    fetch('/api/notifications/unread')
        .then(response => response.json())
        .then(data => {
            if (data.count > 0) {
                showNotification('Nouvelle notification', `Vous avez ${data.count} nouvelles notifications`);
            }
        })
        .catch(console.error);
}

function showNotification(title, body) {
    if (window.Notification && Notification.permission === 'granted') {
        new Notification(title, {
            body: body,
            icon: '/assets/images/logo.png'
        });
    }
}

// ===== API HELPER FUNCTIONS =====
window.API = {
    get: function(url) {
        return fetch(url, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json'
            }
        });
    },
    
    post: function(url, data) {
        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });
    },
    
    upload: function(url, formData) {
        return fetch(url, {
            method: 'POST',
            body: formData
        });
    }
};

// ===== FORM DATA HELPER =====
window.FormHelper = {
    serialize: function(form) {
        const data = {};
        new FormData(form).forEach((value, key) => {
            data[key] = value;
        });
        return data;
    },
    
    clear: function(form) {
        form.reset();
        form.querySelectorAll('input, textarea, select').forEach(field => {
            field.style.borderColor = '';
            field.style.boxShadow = '';
        });
        
        // Clear error messages
        form.querySelectorAll('.error-message').forEach(msg => msg.remove());
    }
};

// ===== LOADING INDICATOR =====
window.showLoading = function(element) {
    element.classList.add('loading');
};

window.hideLoading = function(element) {
    element.classList.remove('loading');
};

// ===== DATE FORMATTING =====
window.formatDate = function(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    });
};

window.formatDateTime = function(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};