<?php
require_once '../includes/config.php';
requireLogin();

$query = $_GET['q'] ?? '';
$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];

if (strlen($query) < 2) {
    exit();
}

if ($user_type == 'school') {
    $stmt = $pdo->prepare("
        SELECT u.id, u.full_name, 'teacher' as type, u.profile_image, t.specialization
        FROM users u
        JOIN teachers t ON u.id = t.user_id
        WHERE u.full_name LIKE ? AND u.user_type = 'teacher'
        UNION
        SELECT u.id, u.full_name, 'parent' as type, u.profile_image, NULL as specialization
        FROM users u
        WHERE u.full_name LIKE ? AND u.user_type = 'parent'
        LIMIT 10
    ");
    $stmt->execute(["%$query%", "%$query%"]);
} elseif ($user_type == 'teacher') {
    $stmt = $pdo->prepare("
        SELECT u.id, u.full_name, 'school' as type, s.logo as profile_image, s.school_name as specialization
        FROM users u
        JOIN schools s ON u.id = s.user_id
        WHERE (u.full_name LIKE ? OR s.school_name LIKE ?) AND u.user_type = 'school'
        UNION
        SELECT u.id, u.full_name, 'parent' as type, u.profile_image, NULL as specialization
        FROM users u
        WHERE u.full_name LIKE ? AND u.user_type = 'parent'
        LIMIT 10
    ");
    $stmt->execute(["%$query%", "%$query%", "%$query%"]);
} else { // parent
    $stmt = $pdo->prepare("
        SELECT u.id, u.full_name, 'school' as type, s.logo as profile_image, s.school_name as specialization
        FROM users u
        JOIN schools s ON u.id = s.user_id
        WHERE (u.full_name LIKE ? OR s.school_name LIKE ?) AND u.user_type = 'school'
        UNION
        SELECT u.id, u.full_name, 'teacher' as type, u.profile_image, t.specialization
        FROM users u
        JOIN teachers t ON u.id = t.user_id
        WHERE u.full_name LIKE ? AND u.user_type = 'teacher'
        LIMIT 10
    ");
    $stmt->execute(["%$query%", "%$query%", "%$query%"]);
}

$results = $stmt->fetchAll();

foreach ($results as $result): ?>
    <div class="user-result" onclick="selectUser(<?php echo $result['id']; ?>, '<?php echo addslashes($result['full_name']); ?>', '<?php echo $result['type']; ?>')">
        <div class="conversation-avatar">
            <?php if ($result['profile_image']): ?>
                <img src="../uploads/<?php echo $result['profile_image']; ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
            <?php else: ?>
                <i class="fas fa-user"></i>
            <?php endif; ?>
        </div>
        <div>
            <div class="conversation-name"><?php echo htmlspecialchars($result['full_name']); ?></div>
            <div class="conversation-preview">
                <?php echo $result['type']; ?>
                <?php if ($result['specialization']): ?>
                    • <?php echo htmlspecialchars($result['specialization']); ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endforeach; ?>