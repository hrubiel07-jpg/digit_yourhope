// Dashboard JavaScript for Digital YOURHOPE

document.addEventListener('DOMContentLoaded', function() {
    // ===== SIDEBAR TOGGLE FOR MOBILE =====
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.sidebar');
    const mainContent = document.querySelector('.main-content');
    
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
            mainContent.classList.toggle('sidebar-active');
        });
        
        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            if (window.innerWidth <= 768 && 
                !event.target.closest('.sidebar') && 
                !event.target.closest('.sidebar-toggle')) {
                sidebar.classList.remove('active');
                mainContent.classList.remove('sidebar-active');
            }
        });
    }
    
    // ===== ACTIVE LINK HIGHLIGHTING =====
    const currentPage = window.location.pathname.split('/').pop();
    const navLinks = document.querySelectorAll('.sidebar-nav a');
    
    navLinks.forEach(link => {
        const linkPage = link.getAttribute('href').split('/').pop();
        if (currentPage === linkPage || 
            (currentPage === '' && linkPage === 'index.php')) {
            link.classList.add('active');
        }
    });
    
    // ===== DASHBOARD STATS AUTO-UPDATE =====
    const statsCards = document.querySelectorAll('.stat-card');
    statsCards.forEach(card => {
        const valueElement = card.querySelector('h3');
        const targetValue = parseInt(valueElement.textContent);
        if (!isNaN(targetValue) && targetValue > 0) {
            animateCounter(valueElement, 0, targetValue, 1500);
        }
    });
    
    // ===== REAL-TIME NOTIFICATIONS =====
    setupDashboardNotifications();
    
    // ===== DATA TABLES ENHANCEMENTS =====
    enhanceDataTables();
    
    // ===== CHARTS INITIALIZATION =====
    initializeCharts();
    
    // ===== FORM VALIDATION FOR DASHBOARD =====
    setupDashboardForms();
    
    // ===== AUTO-SAVE FORMS =====
    setupAutoSave();
    
    // ===== FILE UPLOAD HANDLERS =====
    setupFileUploads();
    
    // ===== CALENDAR INTEGRATION =====
    setupCalendar();
});

// ===== DASHBOARD SPECIFIC FUNCTIONS =====

function animateCounter(element, start, end, duration) {
    let startTimestamp = null;
    const step = (timestamp) => {
        if (!startTimestamp) startTimestamp = timestamp;
        const progress = Math.min((timestamp - startTimestamp) / duration, 1);
        const value = Math.floor(progress * (end - start) + start);
        element.textContent = value.toLocaleString();
        if (progress < 1) {
            window.requestAnimationFrame(step);
        }
    };
    window.requestAnimationFrame(step);
}

function setupDashboardNotifications() {
    // Check for unread messages and notifications
    setInterval(() => {
        fetch('../api/dashboard/notifications')
            .then(response => response.json())
            .then(data => {
                updateNotificationBadges(data);
                if (data.new_messages > 0) {
                    showNewMessageNotification(data.new_messages);
                }
            })
            .catch(console.error);
    }, 60000); // Check every minute
}

function updateNotificationBadges(data) {
    // Update message badge
    const messageBadge = document.querySelector('a[href*="messages"] .badge');
    if (messageBadge && data.new_messages > 0) {
        messageBadge.textContent = data.new_messages;
        messageBadge.style.display = 'block';
    } else if (messageBadge) {
        messageBadge.style.display = 'none';
    }
    
    // Update appointment badge
    const appointmentBadge = document.querySelector('a[href*="appointments"] .badge');
    if (appointmentBadge && data.pending_appointments > 0) {
        appointmentBadge.textContent = data.pending_appointments;
        appointmentBadge.style.display = 'block';
    } else if (appointmentBadge) {
        appointmentBadge.style.display = 'none';
    }
}

function showNewMessageNotification(count) {
    const notification = document.createElement('div');
    notification.className = 'floating-notification';
    notification.innerHTML = `
        <i class="fas fa-envelope"></i>
        <span>Vous avez ${count} nouveau${count > 1 ? 'x' : ''} message${count > 1 ? 's' : ''}</span>
        <button class="close-notification">&times;</button>
    `;
    
    // Add styles
    notification.style.position = 'fixed';
    notification.style.bottom = '20px';
    notification.style.right = '20px';
    notification.style.background = 'white';
    notification.style.padding = '15px 20px';
    notification.style.borderRadius = '10px';
    notification.style.boxShadow = '0 5px 20px rgba(0,0,0,0.2)';
    notification.style.zIndex = '9999';
    notification.style.display = 'flex';
    notification.style.alignItems = 'center';
    notification.style.gap = '10px';
    notification.style.animation = 'slideUp 0.3s ease';
    
    document.body.appendChild(notification);
    
    // Close button
    notification.querySelector('.close-notification').addEventListener('click', () => {
        notification.style.animation = 'slideDown 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    });
    
    // Auto-remove after 10 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.style.animation = 'slideDown 0.3s ease';
            setTimeout(() => notification.remove(), 300);
        }
    }, 10000);
    
    // Add CSS animations if not present
    if (!document.querySelector('#notification-animations')) {
        const style = document.createElement('style');
        style.id = 'notification-animations';
        style.textContent = `
            @keyframes slideUp {
                from {
                    transform: translateY(100%);
                    opacity: 0;
                }
                to {
                    transform: translateY(0);
                    opacity: 1;
                }
            }
            @keyframes slideDown {
                from {
                    transform: translateY(0);
                    opacity: 1;
                }
                to {
                    transform: translateY(100%);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);
    }
}

function enhanceDataTables() {
    const tables = document.querySelectorAll('.data-table');
    tables.forEach(table => {
        // Add search functionality
        const searchInput = document.createElement('input');
        searchInput.type = 'text';
        searchInput.placeholder = 'Rechercher...';
        searchInput.style.marginBottom = '15px';
        searchInput.style.padding = '8px 15px';
        searchInput.style.width = '100%';
        searchInput.style.maxWidth = '300px';
        searchInput.style.border = '1px solid #ddd';
        searchInput.style.borderRadius = '5px';
        
        table.parentNode.insertBefore(searchInput, table);
        
        searchInput.addEventListener('input', function() {
            const filter = this.value.toLowerCase();
            const rows = table.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            });
        });
        
        // Add pagination for large tables
        if (table.querySelectorAll('tbody tr').length > 10) {
            addPagination(table);
        }
        
        // Add sorting
        const headers = table.querySelectorAll('th');
        headers.forEach((header, index) => {
            header.style.cursor = 'pointer';
            header.addEventListener('click', () => {
                sortTable(table, index);
            });
        });
    });
}

function addPagination(table) {
    const rows = table.querySelectorAll('tbody tr');
    const rowsPerPage = 10;
    const pageCount = Math.ceil(rows.length / rowsPerPage);
    
    if (pageCount <= 1) return;
    
    // Create pagination container
    const pagination = document.createElement('div');
    pagination.className = 'table-pagination';
    pagination.style.marginTop = '20px';
    pagination.style.display = 'flex';
    pagination.style.justifyContent = 'center';
    pagination.style.gap = '5px';
    
    // Create page buttons
    for (let i = 1; i <= pageCount; i++) {
        const button = document.createElement('button');
        button.textContent = i;
        button.className = 'page-btn';
        button.style.padding = '5px 10px';
        button.style.border = '1px solid #ddd';
        button.style.background = i === 1 ? '#3498db' : 'white';
        button.style.color = i === 1 ? 'white' : '#333';
        button.style.borderRadius = '3px';
        button.style.cursor = 'pointer';
        
        button.addEventListener('click', () => {
            showPage(table, i, rowsPerPage);
            updatePaginationButtons(pagination, i);
        });
        
        pagination.appendChild(button);
    }
    
    table.parentNode.appendChild(pagination);
    
    // Show first page initially
    showPage(table, 1, rowsPerPage);
}

function showPage(table, page, rowsPerPage) {
    const rows = table.querySelectorAll('tbody tr');
    const start = (page - 1) * rowsPerPage;
    const end = start + rowsPerPage;
    
    rows.forEach((row, index) => {
        row.style.display = (index >= start && index < end) ? '' : 'none';
    });
}

function updatePaginationButtons(pagination, currentPage) {
    const buttons = pagination.querySelectorAll('.page-btn');
    buttons.forEach((button, index) => {
        if (index + 1 === currentPage) {
            button.style.background = '#3498db';
            button.style.color = 'white';
        } else {
            button.style.background = 'white';
            button.style.color = '#333';
        }
    });
}

function sortTable(table, columnIndex) {
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    
    const isAscending = !table.dataset.sortAscending || table.dataset.sortColumn !== columnIndex;
    table.dataset.sortAscending = isAscending;
    table.dataset.sortColumn = columnIndex;
    
    rows.sort((a, b) => {
        const aText = a.children[columnIndex].textContent.trim();
        const bText = b.children[columnIndex].textContent.trim();
        
        // Try to parse as numbers
        const aNum = parseFloat(aText.replace(/[^0-9.-]+/g, ""));
        const bNum = parseFloat(bText.replace(/[^0-9.-]+/g, ""));
        
        if (!isNaN(aNum) && !isNaN(bNum)) {
            return isAscending ? aNum - bNum : bNum - aNum;
        }
        
        // Otherwise sort as strings
        return isAscending ? 
            aText.localeCompare(bText, 'fr', { sensitivity: 'base' }) :
            bText.localeCompare(aText, 'fr', { sensitivity: 'base' });
    });
    
    // Clear and re-add sorted rows
    rows.forEach(row => tbody.appendChild(row));
    
    // Update sort indicators
    updateSortIndicators(table, columnIndex, isAscending);
}

function updateSortIndicators(table, columnIndex, isAscending) {
    const headers = table.querySelectorAll('th');
    headers.forEach((header, index) => {
        header.classList.remove('sort-asc', 'sort-desc');
        if (index === columnIndex) {
            header.classList.add(isAscending ? 'sort-asc' : 'sort-desc');
        }
    });
}

function initializeCharts() {
    // Check if Chart.js is available
    if (typeof Chart === 'undefined') return;
    
    // Revenue chart
    const revenueCtx = document.getElementById('revenueChart');
    if (revenueCtx) {
        new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Jun'],
                datasets: [{
                    label: 'Revenus (FCFA)',
                    data: [120000, 150000, 180000, 160000, 200000, 220000],
                    borderColor: '#3498db',
                    backgroundColor: 'rgba(52, 152, 219, 0.1)',
                    borderWidth: 2,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                }
            }
        });
    }
    
    // Appointments chart
    const appointmentsCtx = document.getElementById('appointmentsChart');
    if (appointmentsCtx) {
        new Chart(appointmentsCtx, {
            type: 'bar',
            data: {
                labels: ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'],
                datasets: [{
                    label: 'Rendez-vous',
                    data: [5, 7, 3, 8, 6, 4],
                    backgroundColor: '#2ecc71'
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                }
            }
        });
    }
}

function setupDashboardForms() {
    const forms = document.querySelectorAll('.form-section form');
    forms.forEach(form => {
        // Add character counters for textareas
        const textareas = form.querySelectorAll('textarea');
        textareas.forEach(textarea => {
            const counter = document.createElement('div');
            counter.className = 'char-counter';
            counter.style.fontSize = '0.8rem';
            counter.style.color = '#95a5a6';
            counter.style.textAlign = 'right';
            counter.style.marginTop = '5px';
            
            textarea.parentNode.appendChild(counter);
            
            textarea.addEventListener('input', function() {
                const maxLength = this.getAttribute('maxlength') || 1000;
                const currentLength = this.value.length;
                counter.textContent = `${currentLength}/${maxLength} caractères`;
                
                if (currentLength > maxLength * 0.9) {
                    counter.style.color = '#e74c3c';
                } else if (currentLength > maxLength * 0.75) {
                    counter.style.color = '#f39c12';
                } else {
                    counter.style.color = '#95a5a6';
                }
            });
            
            // Trigger initial count
            textarea.dispatchEvent(new Event('input'));
        });
        
        // Add form submission loading state
        form.addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.classList.add('loading');
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enregistrement...';
            }
        });
    });
}

function setupAutoSave() {
    const autoSaveForms = document.querySelectorAll('form[data-autosave]');
    autoSaveForms.forEach(form => {
        const inputs = form.querySelectorAll('input, textarea, select');
        let timeout;
        
        inputs.forEach(input => {
            input.addEventListener('input', function() {
                clearTimeout(timeout);
                timeout = setTimeout(() => {
                    autoSaveForm(form);
                }, 1000);
            });
        });
        
        // Auto-save on page unload
        window.addEventListener('beforeunload', function(event) {
            if (formHasChanges(form)) {
                autoSaveForm(form, true);
            }
        });
    });
}

function autoSaveForm(form, isUnload = false) {
    const formData = new FormData(form);
    
    fetch(form.action, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (!isUnload) {
            showToast('Modifications enregistrées automatiquement', 'success');
        }
    })
    .catch(error => {
        if (!isUnload) {
            console.error('Auto-save error:', error);
        }
    });
}

function formHasChanges(form) {
    const initialData = form.dataset.initialData ? JSON.parse(form.dataset.initialData) : {};
    const currentData = {};
    
    new FormData(form).forEach((value, key) => {
        currentData[key] = value;
    });
    
    return JSON.stringify(currentData) !== JSON.stringify(initialData);
}

function setupFileUploads() {
    const fileInputs = document.querySelectorAll('input[type="file"]');
    fileInputs.forEach(input => {
        input.addEventListener('change', function() {
            const files = Array.from(this.files);
            const preview = document.getElementById(this.id + '-preview') || 
                           createFilePreview(this);
            
            preview.innerHTML = '';
            
            files.forEach(file => {
                const fileElement = document.createElement('div');
                fileElement.className = 'file-preview';
                fileElement.innerHTML = `
                    <i class="fas fa-file"></i>
                    <span>${file.name}</span>
                    <small>(${(file.size / 1024).toFixed(1)} KB)</small>
                `;
                preview.appendChild(fileElement);
            });
        });
    });
}

function createFilePreview(input) {
    const preview = document.createElement('div');
    preview.id = input.id + '-preview';
    preview.style.marginTop = '10px';
    input.parentNode.appendChild(preview);
    return preview;
}

function setupCalendar() {
    const calendarElement = document.getElementById('calendar');
    if (!calendarElement) return;
    
    // Initialize calendar (this would use a library like FullCalendar)
    // For now, just add a placeholder
    calendarElement.innerHTML = `
        <div style="text-align: center; padding: 40px;">
            <i class="fas fa-calendar-alt fa-3x" style="color: #ccc; margin-bottom: 20px;"></i>
            <h3>Calendrier</h3>
            <p>Vos rendez-vous s'afficheront ici.</p>
        </div>
    `;
}

// ===== DASHBOARD API FUNCTIONS =====
window.DashboardAPI = {
    getStats: function() {
        return fetch('../api/dashboard/stats')
            .then(response => response.json());
    },
    
    getRecentActivity: function() {
        return fetch('../api/dashboard/activity')
            .then(response => response.json());
    },
    
    updateProfile: function(data) {
        return fetch('../api/dashboard/profile', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });
    },
    
    uploadDocument: function(formData) {
        return fetch('../api/dashboard/upload', {
            method: 'POST',
            body: formData
        });
    }
};

// ===== REAL-TIME UPDATES =====
function connectToRealTimeUpdates() {
    // This would connect to WebSockets or use Server-Sent Events
    // For now, using polling
    setInterval(updateDashboard, 30000); // Update every 30 seconds
}

function updateDashboard() {
    DashboardAPI.getStats()
        .then(stats => {
            updateStatsDisplay(stats);
        })
        .catch(console.error);
}

function updateStatsDisplay(stats) {
    // Update stat cards with new data
    Object.keys(stats).forEach(statKey => {
        const element = document.getElementById(`stat-${statKey}`);
        if (element) {
            animateCounter(element, parseInt(element.textContent), stats[statKey], 500);
        }
    });
}

// ===== PRINT FUNCTIONALITY =====
window.printDashboard = function(elementId) {
    const element = document.getElementById(elementId);
    if (!element) return;
    
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
        <head>
            <title>Imprimer - ${document.title}</title>
            <style>
                body { font-family: Arial, sans-serif; }
                @media print {
                    .no-print { display: none; }
                }
            </style>
        </head>
        <body>
            ${element.innerHTML}
        </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.print();
};

// ===== EXPORT DATA =====
window.exportData = function(format = 'csv') {
    const table = document.querySelector('.data-table');
    if (!table) return;
    
    let data = [];
    const headers = [];
    
    // Get headers
    table.querySelectorAll('th').forEach(th => {
        headers.push(th.textContent.trim());
    });
    
    // Get rows
    table.querySelectorAll('tbody tr').forEach(row => {
        const rowData = {};
        row.querySelectorAll('td').forEach((td, index) => {
            rowData[headers[index]] = td.textContent.trim();
        });
        data.push(rowData);
    });
    
    if (format === 'csv') {
        exportToCSV(data, headers);
    } else if (format === 'excel') {
        exportToExcel(data, headers);
    }
};

function exportToCSV(data, headers) {
    let csv = headers.join(',') + '\n';
    
    data.forEach(row => {
        const rowValues = headers.map(header => {
            let value = row[header] || '';
            // Escape quotes and wrap in quotes if contains comma
            value = String(value).replace(/"/g, '""');
            if (value.includes(',')) {
                value = `"${value}"`;
            }
            return value;
        });
        csv += rowValues.join(',') + '\n';
    });
    
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'export_' + new Date().toISOString().split('T')[0] + '.csv';
    a.click();
    window.URL.revokeObjectURL(url);
}

// Initialize real-time updates when dashboard loads
if (window.location.pathname.includes('dashboard')) {
    connectToRealTimeUpdates();
}