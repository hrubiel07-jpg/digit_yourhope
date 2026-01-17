<div class="sidebar">
    <div class="sidebar-header">
        <h3><i class="fas fa-user-friends"></i> 
            <?php 
            // Récupérer le nom du parent
            $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            echo htmlspecialchars($user['full_name'] ?? 'Parent');
            ?>
        </h3>
        <p>Espace Parent</p>
    </div>
    
    <nav class="sidebar-nav">
        <a href="index.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
            <i class="fas fa-tachometer-alt"></i> Tableau de bord
        </a>
        <a href="profile.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">
            <i class="fas fa-user"></i> Mon Profil
        </a>
        <a href="children.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'children.php' ? 'active' : ''; ?>">
            <i class="fas fa-child"></i> Mes Enfants
        </a>
        <a href="appointments.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'appointments.php' ? 'active' : ''; ?>">
            <i class="fas fa-calendar-alt"></i> Mes RDV
        </a>
        <a href="teachers.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'teachers.php' ? 'active' : ''; ?>">
            <i class="fas fa-chalkboard-teacher"></i> Enseignants
        </a>
        <a href="schools.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'schools.php' ? 'active' : ''; ?>">
            <i class="fas fa-school"></i> Écoles
        </a>
        <a href="../messages.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'messages.php' ? 'active' : ''; ?>">
            <i class="fas fa-envelope"></i> Messages
        </a>
        <a href="favorites.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'favorites.php' ? 'active' : ''; ?>">
            <i class="fas fa-heart"></i> Favoris
        </a>
        <a href="../../auth/logout.php" class="logout">
            <i class="fas fa-sign-out-alt"></i> Déconnexion
        </a>
    </nav>
</div>