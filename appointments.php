<?php
require_once '../../includes/config.php';
requireLogin();

if ($_SESSION['user_type'] !== 'parent') {
    header('Location: ../');
    exit();
}

$user_id = $_SESSION['user_id'];

// Récupérer le parent
$stmt = $pdo->prepare("SELECT id FROM parents WHERE user_id = ?");
$stmt->execute([$user_id]);
$parent = $stmt->fetch();

// Récupérer les enfants pour le formulaire
$stmt = $pdo->prepare("SELECT id, child_name FROM parent_children WHERE parent_id = ? ORDER BY child_name");
$stmt->execute([$parent['id']]);
$children = $stmt->fetchAll();

// Récupérer les filtres
$status = $_GET['status'] ?? '';
$child_id = $_GET['child_id'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Prendre un nouveau rendez-vous
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_appointment'])) {
    $teacher_id = intval($_POST['teacher_id'] ?? 0);
    $service_id = intval($_POST['service_id'] ?? 0);
    $child_id = intval($_POST['child_id'] ?? 0);
    $appointment_date = sanitize($_POST['appointment_date'] ?? '');
    $start_time = sanitize($_POST['start_time'] ?? '');
    $duration = intval($_POST['duration'] ?? 60);
    $notes = sanitize($_POST['notes'] ?? '');
    
    // Calculer l'heure de fin
    $end_time = date('H:i', strtotime($start_time) + ($duration * 60));
    
    // Vérifier la disponibilité
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM appointments 
        WHERE teacher_id = ? AND appointment_date = ? 
        AND ((start_time < ? AND end_time > ?) OR (start_time < ? AND end_time > ?))
    ");
    $stmt->execute([$teacher_id, $appointment_date, $end_time, $start_time, $start_time, $end_time]);
    
    if ($stmt->fetchColumn() > 0) {
        $error = "L'enseignant n'est pas disponible à ce créneau horaire";
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO appointments (parent_id, teacher_id, service_id, child_id, 
                                     appointment_date, start_time, end_time, notes) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        if ($stmt->execute([$parent['id'], $teacher_id, $service_id, $child_id, 
                           $appointment_date, $start_time, $end_time, $notes])) {
            $success = "Rendez-vous pris avec succès! L'enseignant doit confirmer.";
        } else {
            $error = "Erreur lors de la prise de rendez-vous";
        }
    }
}

// Annuler un rendez-vous
if (isset($_GET['cancel'])) {
    $appointment_id = intval($_GET['cancel']);
    $stmt = $pdo->prepare("UPDATE appointments SET status = 'cancelled' WHERE id = ? AND parent_id = ?");
    if ($stmt->execute([$appointment_id, $parent['id']])) {
        $success = "Rendez-vous annulé avec succès!";
    }
}

// Confirmer un rendez-vous terminé
if (isset($_GET['complete'])) {
    $appointment_id = intval($_GET['complete']);
    $stmt = $pdo->prepare("UPDATE appointments SET status = 'completed' WHERE id = ? AND parent_id = ?");
    if ($stmt->execute([$appointment_id, $parent['id']])) {
        $success = "Rendez-vous marqué comme terminé!";
    }
}

// Récupérer les rendez-vous avec filtres
$query = "
    SELECT a.*, t.qualification, u.full_name as teacher_name, 
           u.profile_image, ts.title as service_name, pc.child_name 
    FROM appointments a 
    JOIN teachers t ON a.teacher_id = t.id 
    JOIN users u ON t.user_id = u.id 
    LEFT JOIN teacher_services ts ON a.service_id = ts.id 
    LEFT JOIN parent_children pc ON a.child_id = pc.id 
    WHERE a.parent_id = ?
";

$params = [$parent['id']];

if ($status && $status !== 'all') {
    $query .= " AND a.status = ?";
    $params[] = $status;
}

if ($child_id && $child_id !== 'all') {
    $query .= " AND a.child_id = ?";
    $params[] = $child_id;
}

if ($date_from) {
    $query .= " AND a.appointment_date >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $query .= " AND a.appointment_date <= ?";
    $params[] = $date_to;
}

$query .= " ORDER BY a.appointment_date DESC, a.start_time DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$appointments = $stmt->fetchAll();

// Récupérer les enseignants pour le formulaire
$teachers = $pdo->query("
    SELECT t.id, u.full_name, t.specialization 
    FROM teachers t 
    JOIN users u ON t.user_id = u.id 
    WHERE u.is_active = 1 
    ORDER BY u.full_name
")->fetchAll();

// Récupérer les services d'un enseignant spécifique
$teacher_services = [];
if (isset($_GET['teacher_id'])) {
    $teacher_id = intval($_GET['teacher_id']);
    $stmt = $pdo->prepare("SELECT * FROM teacher_services WHERE teacher_id = ? AND is_available = 1");
    $stmt->execute([$teacher_id]);
    $teacher_services = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Rendez-vous - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="dashboard">
    <div class="sidebar">
        <div class="sidebar-header">
            <h3><i class="fas fa-calendar-alt"></i> Mes Rendez-vous</h3>
            <p>Gestion des séances</p>
        </div>
        
        <nav class="sidebar-nav">
            <a href="index.php">
                <i class="fas fa-tachometer-alt"></i> Tableau de bord
            </a>
            <a href="profile.php">
                <i class="fas fa-user"></i> Mon Profil
            </a>
            <a href="children.php">
                <i class="fas fa-child"></i> Mes Enfants
            </a>
            <a href="appointments.php" class="active">
                <i class="fas fa-calendar-alt"></i> Mes Rendez-vous
            </a>
            <a href="favorites.php">
                <i class="fas fa-heart"></i> Favoris
            </a>
            <a href="../messages.php">
                <i class="fas fa-envelope"></i> Messages
            </a>
            <a href="../../auth/logout.php" class="logout">
                <i class="fas fa-sign-out-alt"></i> Déconnexion
            </a>
        </nav>
    </div>
    
    <div class="main-content">
        <header class="top-bar">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Rechercher un rendez-vous...">
            </div>
            <div class="user-info">
                <span><?php echo $_SESSION['email']; ?></span>
                <img src="../../assets/images/default-avatar.png" alt="Avatar">
            </div>
        </header>
        
        <div class="content">
            <h1 class="page-title">Mes Rendez-vous</h1>
            
            <?php if (isset($success)): ?>
                <div class="alert alert-success" style="background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-error" style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <div class="tabs">
                <button class="tab-button active" onclick="openTab('myAppointments')">
                    <i class="fas fa-list"></i> Mes RDV (<?php echo count($appointments); ?>)
                </button>
                <button class="tab-button" onclick="openTab('bookAppointment')">
                    <i class="fas fa-plus-circle"></i> Prendre un RDV
                </button>
            </div>
            
            <!-- Mes rendez-vous -->
            <div id="myAppointments" class="tab-content active">
                <!-- Filtres -->
                <div class="filter-section" style="background: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; box-shadow: 0 3px 10px rgba(0,0,0,0.05);">
                    <h4 style="margin-bottom: 15px;">Filtrer les rendez-vous</h4>
                    <form method="GET" class="filter-form" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                        <div class="form-group">
                            <label for="status">Statut</label>
                            <select id="status" name="status">
                                <option value="all">Tous les statuts</option>
                                <option value="pending" <?php echo $status == 'pending' ? 'selected' : ''; ?>>En attente</option>
                                <option value="confirmed" <?php echo $status == 'confirmed' ? 'selected' : ''; ?>>Confirmé</option>
                                <option value="completed" <?php echo $status == 'completed' ? 'selected' : ''; ?>>Terminé</option>
                                <option value="cancelled" <?php echo $status == 'cancelled' ? 'selected' : ''; ?>>Annulé</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="child_id">Enfant</label>
                            <select id="child_id" name="child_id">
                                <option value="all">Tous les enfants</option>
                                <?php foreach($children as $child): ?>
                                    <option value="<?php echo $child['id']; ?>" <?php echo $child_id == $child['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($child['child_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="date_from">Date début</label>
                            <input type="date" id="date_from" name="date_from" value="<?php echo $date_from; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="date_to">Date fin</label>
                            <input type="date" id="date_to" name="date_to" value="<?php echo $date_to; ?>">
                        </div>
                        
                        <div class="form-group" style="align-self: end;">
                            <button type="submit" class="btn-primary">
                                <i class="fas fa-filter"></i> Filtrer
                            </button>
                            <a href="appointments.php" class="btn-secondary">
                                <i class="fas fa-times"></i> Réinitialiser
                            </a>
                        </div>
                    </form>
                </div>
                
                <!-- Liste des rendez-vous -->
                <?php if (count($appointments) > 0): ?>
                    <div class="appointments-list" style="display: flex; flex-direction: column; gap: 15px;">
                        <?php foreach($appointments as $app): 
                            $is_past = strtotime($app['appointment_date'] . ' ' . $app['end_time']) < time();
                            ?>
                            <div class="appointment-card" style="background: white; border-radius: 10px; padding: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.05);">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;">
                                    <div>
                                        <h4 style="margin: 0 0 5px;"><?php echo htmlspecialchars($app['service_name']); ?></h4>
                                        <p style="color: #666; margin: 0;">
                                            <i class="fas fa-user-graduate"></i> 
                                            <?php echo htmlspecialchars($app['teacher_name']); ?>
                                            <?php if ($app['child_name']): ?>
                                                • <i class="fas fa-child"></i> <?php echo htmlspecialchars($app['child_name']); ?>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    
                                    <div style="text-align: right;">
                                        <span class="status-badge status-<?php echo $app['status']; ?>" style="margin-bottom: 5px; display: inline-block;">
                                            <?php 
                                            $status_labels = [
                                                'pending' => 'En attente',
                                                'confirmed' => 'Confirmé',
                                                'completed' => 'Terminé',
                                                'cancelled' => 'Annulé'
                                            ];
                                            echo $status_labels[$app['status']] ?? $app['status'];
                                            ?>
                                        </span>
                                        <p style="color: #666; margin: 0; font-size: 0.9rem;">
                                            <?php echo date('d/m/Y', strtotime($app['appointment_date'])); ?>
                                            • <?php echo date('H:i', strtotime($app['start_time'])); ?>-<?php echo date('H:i', strtotime($app['end_time'])); ?>
                                        </p>
                                    </div>
                                </div>
                                
                                <?php if ($app['notes']): ?>
                                    <div style="background: #f8f9fa; padding: 10px; border-radius: 5px; margin-bottom: 15px;">
                                        <p style="margin: 0; color: #666;"><strong>Notes:</strong> <?php echo htmlspecialchars($app['notes']); ?></p>
                                    </div>
                                <?php endif; ?>
                                
                                <div style="display: flex; gap: 10px;">
                                    <a href="appointment-details.php?id=<?php echo $app['id']; ?>" class="btn-small">
                                        <i class="fas fa-eye"></i> Détails
                                    </a>
                                    
                                    <?php if ($app['status'] == 'pending' && !$is_past): ?>
                                        <a href="appointments.php?cancel=<?php echo $app['id']; ?>" 
                                           class="btn-small btn-danger"
                                           onclick="return confirm('Annuler ce rendez-vous ?');">
                                            <i class="fas fa-times"></i> Annuler
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if ($app['status'] == 'confirmed' && $is_past): ?>
                                        <a href="appointments.php?complete=<?php echo $app['id']; ?>" 
                                           class="btn-small btn-secondary"
                                           onclick="return confirm('Marquer ce rendez-vous comme terminé ?');">
                                            <i class="fas fa-check"></i> Terminer
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if ($app['status'] == 'completed' && !$app['reviewed']): ?>
                                        <a href="review.php?appointment_id=<?php echo $app['id']; ?>" 
                                           class="btn-small btn-primary">
                                            <i class="fas fa-star"></i> Noter
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="no-data" style="text-align: center; padding: 40px; background: #f8f9fa; border-radius: 10px;">
                        <i class="fas fa-calendar-times fa-3x" style="color: #ccc; margin-bottom: 20px;"></i>
                        <h3>Aucun rendez-vous trouvé</h3>
                        <p>Vous n'avez pas encore de rendez-vous.</p>
                        <a href="#bookAppointment" class="btn-primary" onclick="openTab('bookAppointment')">
                            <i class="fas fa-plus-circle"></i> Prendre un premier rendez-vous
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Prendre un rendez-vous -->
            <div id="bookAppointment" class="tab-content">
                <div class="form-section">
                    <h3><i class="fas fa-calendar-plus"></i> Prendre un nouveau rendez-vous</h3>
                    
                    <form method="POST" id="appointmentForm">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="teacher_id">Enseignant *</label>
                                <select id="teacher_id" name="teacher_id" required onchange="loadTeacherServices(this.value)">
                                    <option value="">Sélectionner un enseignant</option>
                                    <?php foreach($teachers as $teacher): ?>
                                        <option value="<?php echo $teacher['id']; ?>">
                                            <?php echo htmlspecialchars($teacher['full_name']); ?> - <?php echo $teacher['specialization']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="service_id">Service *</label>
                                <select id="service_id" name="service_id" required disabled>
                                    <option value="">Sélectionnez d'abord un enseignant</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="child_id">Enfant *</label>
                                <select id="child_id" name="child_id" required>
                                    <option value="">Sélectionner un enfant</option>
                                    <?php foreach($children as $child): ?>
                                        <option value="<?php echo $child['id']; ?>">
                                            <?php echo htmlspecialchars($child['child_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="appointment_date">Date *</label>
                                <input type="date" id="appointment_date" name="appointment_date" required 
                                       min="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="start_time">Heure de début *</label>
                                <input type="time" id="start_time" name="start_time" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="duration">Durée (minutes) *</label>
                                <select id="duration" name="duration" required>
                                    <option value="60">1 heure</option>
                                    <option value="90">1h30</option>
                                    <option value="120">2 heures</option>
                                    <option value="150">2h30</option>
                                    <option value="180">3 heures</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="notes">Notes supplémentaires</label>
                            <textarea id="notes" name="notes" rows="3" placeholder="Objectifs spécifiques, difficultés à travailler..."></textarea>
                        </div>
                        
                        <div class="form-buttons">
                            <button type="submit" name="book_appointment" class="btn-primary">
                                <i class="fas fa-calendar-check"></i> Prendre rendez-vous
                            </button>
                            <button type="button" class="btn-secondary" onclick="checkAvailability()">
                                <i class="fas fa-search"></i> Vérifier disponibilité
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script src="../../assets/js/dashboard.js"></script>
    <script>
    function openTab(tabName) {
        const tabContents = document.getElementsByClassName('tab-content');
        for (let i = 0; i < tabContents.length; i++) {
            tabContents[i].classList.remove('active');
        }
        
        const tabButtons = document.getElementsByClassName('tab-button');
        for (let i = 0; i < tabButtons.length; i++) {
            tabButtons[i].classList.remove('active');
        }
        
        document.getElementById(tabName).classList.add('active');
        event.currentTarget.classList.add('active');
    }
    
    function loadTeacherServices(teacherId) {
        if (!teacherId) return;
        
        fetch(`../api/get-teacher-services.php?teacher_id=${teacherId}`)
            .then(response => response.json())
            .then(services => {
                const serviceSelect = document.getElementById('service_id');
                serviceSelect.innerHTML = '<option value="">Sélectionner un service</option>';
                serviceSelect.disabled = false;
                
                services.forEach(service => {
                    const option = document.createElement('option');
                    option.value = service.id;
                    option.textContent = `${service.title} - ${service.price} FCFA (${service.duration})`;
                    serviceSelect.appendChild(option);
                });
            })
            .catch(error => {
                console.error('Error loading services:', error);
            });
    }
    
    function checkAvailability() {
        const teacherId = document.getElementById('teacher_id').value;
        const appointmentDate = document.getElementById('appointment_date').value;
        const startTime = document.getElementById('start_time').value;
        const duration = document.getElementById('duration').value;
        
        if (!teacherId || !appointmentDate || !startTime) {
            alert('Veuillez remplir tous les champs pour vérifier la disponibilité');
            return;
        }
        
        fetch(`../api/check-availability.php?teacher_id=${teacherId}&date=${appointmentDate}&time=${startTime}&duration=${duration}`)
            .then(response => response.json())
            .then(data => {
                if (data.available) {
                    alert('✔ Créneau disponible!');
                } else {
                    alert('✘ Créneau non disponible. Veuillez choisir un autre horaire.');
                }
            })
            .catch(error => {
                console.error('Error checking availability:', error);
                alert('Erreur lors de la vérification de disponibilité');
            });
    }
    
    // Initial setup
    document.addEventListener('DOMContentLoaded', function() {
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('appointment_date').min = today;
        
        // Set default time to next hour
        const nextHour = new Date();
        nextHour.setHours(nextHour.getHours() + 1, 0, 0, 0);
        document.getElementById('start_time').value = nextHour.toTimeString().substring(0, 5);
    });
    </script>
    
    <style>
        .tabs {
            display: flex;
            border-bottom: 2px solid #dee2e6;
            margin-bottom: 30px;
        }
        
        .tab-button {
            padding: 12px 20px;
            background: none;
            border: none;
            border-bottom: 3px solid transparent;
            font-size: 1rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            color: #6c757d;
            transition: all 0.3s;
        }
        
        .tab-button:hover {
            color: #3498db;
        }
        
        .tab-button.active {
            color: #3498db;
            border-bottom-color: #3498db;
            font-weight: 500;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .filter-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .filter-btn {
            padding: 8px 16px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 20px;
            text-decoration: none;
            color: #495057;
            transition: all 0.3s;
        }
        
        .filter-btn:hover,
        .filter-btn.active {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }
        
        .appointments-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }
        
        .appointment-card {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 20px;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .appointment-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .appointment-header {
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .appointment-header h4 {
            margin: 0 0 5px;
            color: #2c3e50;
        }
        
        .appointment-teacher {
            color: #3498db;
            font-size: 0.9rem;
        }
        
        .appointment-details {
            margin-bottom: 15px;
        }
        
        .appointment-details p {
            margin: 8px 0;
            color: #555;
            display: flex;
            align-items: flex-start;
            gap: 8px;
        }
        
        .appointment-details i {
            width: 20px;
            color: #7f8c8d;
            margin-top: 2px;
        }
        
        .past-badge {
            background: #95a5a6;
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.8rem;
            margin-left: 10px;
        }
        
        .appointment-status {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        
        .appointment-actions {
            display: flex;
            gap: 5px;
        }
        
        .price-summary {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #3498db;
        }
        
        .price-summary h4 {
            margin: 0 0 10px;
            color: #2c3e50;
        }
        
        .price-summary p {
            margin: 5px 0;
            color: #555;
        }
        
        .form-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .availability-calendar {
            background: white;
            padding: 20px;
            border-radius: 10px;
            border: 1px solid #e0e0e0;
            margin-top: 30px;
        }
        
        .availability-calendar h4 {
            margin-bottom: 15px;
            color: #2c3e50;
        }
    </style>
</body>
</html>