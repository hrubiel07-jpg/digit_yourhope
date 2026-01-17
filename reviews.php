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

// Ajouter un avis
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_review'])) {
    $teacher_id = intval($_POST['teacher_id'] ?? 0);
    $appointment_id = intval($_POST['appointment_id'] ?? 0);
    $rating = intval($_POST['rating'] ?? 5);
    $comment = sanitize($_POST['comment'] ?? '');
    
    if ($rating < 1 || $rating > 5) {
        $error = "La note doit être entre 1 et 5 étoiles";
    } elseif (empty($comment)) {
        $error = "Veuillez écrire un commentaire";
    } else {
        // Vérifier si l'utilisateur a déjà laissé un avis pour ce rendez-vous
        $stmt = $pdo->prepare("SELECT id FROM reviews WHERE reviewer_id = ? AND appointment_id = ?");
        $stmt->execute([$user_id, $appointment_id]);
        
        if ($stmt->rowCount() > 0) {
            $error = "Vous avez déjà laissé un avis pour ce rendez-vous";
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO reviews (reviewer_id, reviewer_type, teacher_id, appointment_id, rating, comment) 
                VALUES (?, 'parent', ?, ?, ?, ?)
            ");
            
            if ($stmt->execute([$user_id, $teacher_id, $appointment_id, $rating, $comment])) {
                $success = "Avis publié avec succès! Merci pour votre feedback.";
                
                // Marquer le rendez-vous comme revu
                $stmt = $pdo->prepare("UPDATE appointments SET reviewed = 1 WHERE id = ?");
                $stmt->execute([$appointment_id]);
            } else {
                $error = "Erreur lors de la publication de l'avis";
            }
        }
    }
}

// Modifier un avis
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_review'])) {
    $review_id = intval($_POST['review_id'] ?? 0);
    $rating = intval($_POST['rating'] ?? 5);
    $comment = sanitize($_POST['comment'] ?? '');
    
    $stmt = $pdo->prepare("UPDATE reviews SET rating = ?, comment = ? WHERE id = ? AND reviewer_id = ?");
    
    if ($stmt->execute([$rating, $comment, $review_id, $user_id])) {
        $success = "Avis mis à jour avec succès!";
    } else {
        $error = "Erreur lors de la mise à jour de l'avis";
    }
}

// Supprimer un avis
if (isset($_GET['delete'])) {
    $review_id = intval($_GET['delete']);
    $stmt = $pdo->prepare("DELETE FROM reviews WHERE id = ? AND reviewer_id = ?");
    
    if ($stmt->execute([$review_id, $user_id])) {
        $success = "Avis supprimé avec succès!";
    }
}

// Récupérer les rendez-vous pouvant être notés
$stmt = $pdo->prepare("
    SELECT a.*, t.id as teacher_id, u.full_name as teacher_name, ts.title as service_name 
    FROM appointments a 
    JOIN teachers t ON a.teacher_id = t.id 
    JOIN users u ON t.user_id = u.id 
    LEFT JOIN teacher_services ts ON a.service_id = ts.id 
    WHERE a.parent_id = ? AND a.status = 'completed' AND a.reviewed = 0 
    ORDER BY a.appointment_date DESC 
    LIMIT 10
");
$stmt->execute([$parent['id']]);
$appointments_to_review = $stmt->fetchAll();

// Récupérer les avis laissés par le parent
$stmt = $pdo->prepare("
    SELECT r.*, u.full_name as teacher_name, t.specialization, a.appointment_date 
    FROM reviews r 
    JOIN users u ON r.teacher_id = u.id 
    JOIN teachers t ON r.teacher_id = t.id 
    LEFT JOIN appointments a ON r.appointment_id = a.id 
    WHERE r.reviewer_id = ? AND r.reviewer_type = 'parent' 
    ORDER BY r.created_at DESC
");
$stmt->execute([$user_id]);
$reviews = $stmt->fetchAll();

// Récupérer un avis pour modification
$review_to_edit = null;
if (isset($_GET['edit'])) {
    $review_id = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM reviews WHERE id = ? AND reviewer_id = ?");
    $stmt->execute([$review_id, $user_id]);
    $review_to_edit = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Avis - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .rating-stars {
            display: flex;
            gap: 5px;
            margin: 10px 0;
        }
        
        .rating-star {
            font-size: 2rem;
            color: #ddd;
            cursor: pointer;
            transition: color 0.2s;
        }
        
        .rating-star:hover,
        .rating-star.selected {
            color: #f39c12;
        }
        
        .review-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .review-teacher {
            font-weight: 500;
            color: #2c3e50;
        }
        
        .review-date {
            color: #95a5a6;
            font-size: 0.9rem;
        }
        
        .appointment-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            border-left: 4px solid #3498db;
        }
    </style>
</head>
<body class="dashboard">
    <div class="sidebar">
        <div class="sidebar-header">
            <h3><i class="fas fa-star"></i> Mes Avis</h3>
            <p>Vos évaluations</p>
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
            <a href="appointments.php">
                <i class="fas fa-calendar-alt"></i> Mes Rendez-vous
            </a>
            <a href="favorites.php">
                <i class="fas fa-heart"></i> Favoris
            </a>
            <a href="reviews.php" class="active">
                <i class="fas fa-star"></i> Mes Avis
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
                <input type="text" placeholder="Rechercher un avis...">
            </div>
            <div class="user-info">
                <span><?php echo $_SESSION['email']; ?></span>
                <img src="../../assets/images/default-avatar.png" alt="Avatar">
            </div>
        </header>
        
        <div class="content">
            <h1 class="page-title">Mes Avis</h1>
            
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
                <button class="tab-button active" onclick="openTab('leaveReview')">
                    <i class="fas fa-pen"></i> Laisser un avis (<?php echo count($appointments_to_review); ?>)
                </button>
                <button class="tab-button" onclick="openTab('myReviews')">
                    <i class="fas fa-list"></i> Mes avis (<?php echo count($reviews); ?>)
                </button>
                <?php if ($review_to_edit): ?>
                    <button class="tab-button" onclick="openTab('editReview')">
                        <i class="fas fa-edit"></i> Modifier un avis
                    </button>
                <?php endif; ?>
            </div>
            
            <!-- Laisser un avis -->
            <div id="leaveReview" class="tab-content active">
                <?php if (count($appointments_to_review) > 0): ?>
                    <div class="form-section">
                        <h3>Séances à évaluer</h3>
                        <p style="color: #666; margin-bottom: 20px;">Vous pouvez laisser un avis sur les séances terminées.</p>
                        
                        <?php foreach($appointments_to_review as $appointment): ?>
                            <div class="appointment-card">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
                                    <div>
                                        <h4 style="margin: 0 0 5px;"><?php echo htmlspecialchars($appointment['service_name']); ?></h4>
                                        <p style="margin: 0; color: #666;">
                                            <i class="fas fa-user-graduate"></i> <?php echo htmlspecialchars($appointment['teacher_name']); ?>
                                            • <?php echo date('d/m/Y', strtotime($appointment['appointment_date'])); ?>
                                        </p>
                                    </div>
                                    <button type="button" class="btn-small btn-primary" onclick="showReviewForm(<?php echo $appointment['id']; ?>, <?php echo $appointment['teacher_id']; ?>, '<?php echo htmlspecialchars($appointment['teacher_name']); ?>')">
                                        <i class="fas fa-star"></i> Évaluer
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Formulaire d'avis (caché par défaut) -->
                    <div id="reviewForm" class="form-section" style="display: none; margin-top: 30px;">
                        <h3>Laisser un avis</h3>
                        <form method="POST" id="reviewFormContent">
                            <input type="hidden" name="appointment_id" id="appointment_id">
                            <input type="hidden" name="teacher_id" id="teacher_id">
                            
                            <div class="form-group">
                                <label>Enseignant</label>
                                <p id="teacher_name" style="font-weight: 500; margin: 10px 0;"></p>
                            </div>
                            
                            <div class="form-group">
                                <label>Note *</label>
                                <div class="rating-stars" id="ratingStars">
                                    <?php for($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star rating-star" data-rating="<?php echo $i; ?>"></i>
                                    <?php endfor; ?>
                                </div>
                                <input type="hidden" name="rating" id="rating" value="5" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="comment">Commentaire *</label>
                                <textarea id="comment" name="comment" rows="5" required 
                                          placeholder="Partagez votre expérience avec cet enseignant..."></textarea>
                                <small style="color: #666;">Votre avis aidera d'autres parents à choisir.</small>
                            </div>
                            
                            <div class="form-buttons">
                                <button type="submit" name="add_review" class="btn-primary">
                                    <i class="fas fa-paper-plane"></i> Publier l'avis
                                </button>
                                <button type="button" class="btn-secondary" onclick="hideReviewForm()">
                                    <i class="fas fa-times"></i> Annuler
                                </button>
                            </div>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="no-data" style="text-align: center; padding: 40px; background: #f8f9fa; border-radius: 10px;">
                        <i class="fas fa-check-circle fa-3x" style="color: #2ecc71; margin-bottom: 20px;"></i>
                        <h3>Toutes vos séances sont évaluées</h3>
                        <p>Vous avez évalué toutes vos séances terminées.</p>
                        <p>Retrouvez ci-dessous tous vos avis publiés.</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Mes avis -->
            <div id="myReviews" class="tab-content">
                <?php if (count($reviews) > 0): ?>
                    <div class="reviews-list">
                        <?php foreach($reviews as $review): ?>
                            <div class="review-card">
                                <div class="review-header">
                                    <div>
                                        <h4 class="review-teacher"><?php echo htmlspecialchars($review['teacher_name']); ?></h4>
                                        <p style="color: #666; margin: 5px 0;"><?php echo htmlspecialchars($review['specialization']); ?></p>
                                        <?php if ($review['appointment_date']): ?>
                                            <p style="color: #95a5a6; margin: 0; font-size: 0.9rem;">
                                                Séance du <?php echo date('d/m/Y', strtotime($review['appointment_date'])); ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <div style="color: #f39c12; margin-bottom: 5px;">
                                            <?php for($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star <?php echo $i <= $review['rating'] ? 'filled' : ''; ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                        <p class="review-date"><?php echo date('d/m/Y H:i', strtotime($review['created_at'])); ?></p>
                                    </div>
                                </div>
                                
                                <div style="margin-bottom: 15px;">
                                    <p><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
                                </div>
                                
                                <div style="display: flex; gap: 10px;">
                                    <a href="reviews.php?edit=<?php echo $review['id']; ?>" class="btn-small">
                                        <i class="fas fa-edit"></i> Modifier
                                    </a>
                                    <a href="reviews.php?delete=<?php echo $review['id']; ?>" 
                                       class="btn-small btn-danger"
                                       onclick="return confirm('Supprimer cet avis ?');">
                                        <i class="fas fa-trash"></i> Supprimer
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="no-data" style="text-align: center; padding: 40px; background: #f8f9fa; border-radius: 10px;">
                        <i class="fas fa-star fa-3x" style="color: #ccc; margin-bottom: 20px;"></i>
                        <h3>Aucun avis publié</h3>
                        <p>Vous n'avez pas encore laissé d'avis sur vos enseignants.</p>
                        <p>Retournez à l'onglet "Laisser un avis" pour évaluer vos séances.</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Modifier un avis -->
            <?php if ($review_to_edit): ?>
            <div id="editReview" class="tab-content">
                <div class="form-section">
                    <h3><i class="fas fa-edit"></i> Modifier mon avis</h3>
                    
                    <form method="POST">
                        <input type="hidden" name="review_id" value="<?php echo $review_to_edit['id']; ?>">
                        
                        <div class="form-group">
                            <label>Note *</label>
                            <div class="rating-stars" id="editRatingStars">
                                <?php for($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star rating-star <?php echo $i <= $review_to_edit['rating'] ? 'selected' : ''; ?>" 
                                       data-rating="<?php echo $i; ?>"
                                       onclick="selectEditRating(<?php echo $i; ?>)"></i>
                                <?php endfor; ?>
                            </div>
                            <input type="hidden" name="rating" id="editRating" value="<?php echo $review_to_edit['rating']; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="editComment">Commentaire *</label>
                            <textarea id="editComment" name="comment" rows="5" required><?php echo htmlspecialchars($review_to_edit['comment']); ?></textarea>
                        </div>
                        
                        <div class="form-buttons">
                            <button type="submit" name="update_review" class="btn-primary">
                                <i class="fas fa-save"></i> Mettre à jour
                            </button>
                            <a href="reviews.php" class="btn-secondary">
                                <i class="fas fa-times"></i> Annuler
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>
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
    
    function showReviewForm(appointmentId, teacherId, teacherName) {
        document.getElementById('appointment_id').value = appointmentId;
        document.getElementById('teacher_id').value = teacherId;
        document.getElementById('teacher_name').textContent = teacherName;
        document.getElementById('reviewForm').style.display = 'block';
        
        // Faire défiler jusqu'au formulaire
        document.getElementById('reviewForm').scrollIntoView({ behavior: 'smooth' });
    }
    
    function hideReviewForm() {
        document.getElementById('reviewForm').style.display = 'none';
    }
    
    // Gestion des étoiles de notation
    const stars = document.querySelectorAll('.rating-star');
    stars.forEach(star => {
        star.addEventListener('click', function() {
            const rating = this.getAttribute('data-rating');
            document.getElementById('rating').value = rating;
            
            // Mettre à jour l'affichage
            stars.forEach(s => {
                s.classList.remove('selected');
                if (s.getAttribute('data-rating') <= rating) {
                    s.classList.add('selected');
                }
            });
        });
    });
    
    function selectEditRating(rating) {
        document.getElementById('editRating').value = rating;
        
        const editStars = document.querySelectorAll('#editRatingStars .rating-star');
        editStars.forEach(star => {
            star.classList.remove('selected');
            if (star.getAttribute('data-rating') <= rating) {
                star.classList.add('selected');
            }
        });
    }
    
    // Initialiser les étoiles pour l'édition
    <?php if ($review_to_edit): ?>
    selectEditRating(<?php echo $review_to_edit['rating']; ?>);
    <?php endif; ?>
    </script>
</body>
</html>