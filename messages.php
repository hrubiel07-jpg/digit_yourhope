<?php
require_once '../includes/config.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];

// Envoyer un message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $receiver_id = intval($_POST['receiver_id']);
    $subject = sanitize($_POST['subject']);
    $message = sanitize($_POST['message']);
    
    // Vérifier que l'utilisateur ne s'envoie pas un message à lui-même
    if ($receiver_id != $user_id) {
        $stmt = $pdo->prepare("
            INSERT INTO messages (sender_id, receiver_id, subject, message) 
            VALUES (?, ?, ?, ?)
        ");
        
        if ($stmt->execute([$user_id, $receiver_id, $subject, $message])) {
            $success = "Message envoyé avec succès!";
        } else {
            $error = "Erreur lors de l'envoi du message";
        }
    } else {
        $error = "Vous ne pouvez pas vous envoyer un message à vous-même";
    }
}

// Marquer un message comme lu
if (isset($_GET['read'])) {
    $message_id = intval($_GET['read']);
    
    $stmt = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE id = ? AND receiver_id = ?");
    $stmt->execute([$message_id, $user_id]);
}

// Supprimer un message
if (isset($_GET['delete'])) {
    $message_id = intval($_GET['delete']);
    
    $stmt = $pdo->prepare("DELETE FROM messages WHERE id = ? AND (sender_id = ? OR receiver_id = ?)");
    if ($stmt->execute([$message_id, $user_id, $user_id])) {
        $success = "Message supprimé avec succès!";
    }
}

// Récupérer les conversations
$stmt = $pdo->prepare("
    SELECT 
        CASE 
            WHEN sender_id = ? THEN receiver_id 
            ELSE sender_id 
        END as contact_id,
        MAX(messages.sent_at) as last_message,
        COUNT(CASE WHEN receiver_id = ? AND is_read = 0 THEN 1 END) as unread_count,
        u.full_name as contact_name,
        u.user_type as contact_type
    FROM messages
    JOIN users u ON (
        CASE 
            WHEN sender_id = ? THEN receiver_id 
            ELSE sender_id 
        END = u.id
    )
    WHERE sender_id = ? OR receiver_id = ?
    GROUP BY contact_id
    ORDER BY last_message DESC
");
$stmt->execute([$user_id, $user_id, $user_id, $user_id, $user_id]);
$conversations = $stmt->fetchAll();

// Récupérer les messages d'une conversation spécifique
$contact_id = isset($_GET['contact']) ? intval($_GET['contact']) : null;
$messages = [];
$contact_info = null;

if ($contact_id) {
    // Récupérer les infos du contact
    $stmt = $pdo->prepare("SELECT id, full_name, user_type FROM users WHERE id = ?");
    $stmt->execute([$contact_id]);
    $contact_info = $stmt->fetch();
    
    // Récupérer les messages
    $stmt = $pdo->prepare("
        SELECT m.*, u.full_name as sender_name, u.user_type as sender_type 
        FROM messages m 
        JOIN users u ON m.sender_id = u.id 
        WHERE (m.sender_id = ? AND m.receiver_id = ?) 
           OR (m.sender_id = ? AND m.receiver_id = ?)
        ORDER BY m.sent_at ASC
    ");
    $stmt->execute([$user_id, $contact_id, $contact_id, $user_id]);
    $messages = $stmt->fetchAll();
    
    // Marquer les messages comme lus
    $stmt = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE receiver_id = ? AND sender_id = ?");
    $stmt->execute([$user_id, $contact_id]);
}

// Récupérer les utilisateurs pour la liste déroulante
$stmt = $pdo->prepare("
    SELECT id, full_name, user_type 
    FROM users 
    WHERE id != ? AND is_active = 1 
    ORDER BY full_name
");
$stmt->execute([$user_id]);
$users = $stmt->fetchAll();

// Filtrer les utilisateurs selon le type
if ($user_type === 'school') {
    // Les écoles peuvent contacter enseignants et parents
    $users = array_filter($users, function($user) {
        return in_array($user['user_type'], ['teacher', 'parent']);
    });
} elseif ($user_type === 'teacher') {
    // Les enseignants peuvent contacter écoles et parents
    $users = array_filter($users, function($user) {
        return in_array($user['user_type'], ['school', 'parent']);
    });
} elseif ($user_type === 'parent') {
    // Les parents peuvent contacter écoles et enseignants
    $users = array_filter($users, function($user) {
        return in_array($user['user_type'], ['school', 'teacher']);
    });
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messagerie - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="dashboard">
    <?php
    // Inclure la sidebar appropriée selon le type d'utilisateur
    $sidebar_path = $user_type . '/sidebar.php';
    if (file_exists("$user_type/sidebar.php")) {
        include "$user_type/sidebar.php";
    } else {
        // Sidebar par défaut
    ?>
    <div class="sidebar">
        <div class="sidebar-header">
            <h3><i class="fas fa-envelope"></i> Messagerie</h3>
            <p>Communiquez avec les autres</p>
        </div>
        
        <nav class="sidebar-nav">
            <a href="<?php echo $user_type; ?>/index.php">
                <i class="fas fa-tachometer-alt"></i> Tableau de bord
            </a>
            <a href="messages.php" class="active">
                <i class="fas fa-envelope"></i> Messages
            </a>
            <a href="../auth/logout.php" class="logout">
                <i class="fas fa-sign-out-alt"></i> Déconnexion
            </a>
        </nav>
    </div>
    <?php } ?>
    
    <div class="main-content">
        <header class="top-bar">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Rechercher un message...">
            </div>
            <div class="user-info">
                <span><?php echo $_SESSION['email']; ?></span>
                <img src="../assets/images/default-avatar.png" alt="Avatar">
            </div>
        </header>
        
        <div class="content">
            <h1 class="page-title">Messagerie</h1>
            
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
            
            <div class="messaging-container">
                <!-- Liste des conversations -->
                <div class="conversations-list">
                    <div class="conversations-header">
                        <h3>Conversations</h3>
                        <button class="btn-small" onclick="showNewMessageForm()">
                            <i class="fas fa-plus"></i> Nouveau
                        </button>
                    </div>
                    
                    <!-- Formulaire de nouveau message (caché par défaut) -->
                    <div id="newMessageForm" style="display: none; padding: 15px; background: #f8f9fa; border-radius: 5px; margin: 10px 0;">
                        <form method="POST">
                            <div class="form-group">
                                <label for="receiver_id">Destinataire</label>
                                <select id="receiver_id" name="receiver_id" required>
                                    <option value="">Sélectionnez un destinataire</option>
                                    <?php foreach($users as $user): ?>
                                        <option value="<?php echo $user['id']; ?>">
                                            <?php echo htmlspecialchars($user['full_name']); ?> 
                                            (<?php echo $user['user_type']; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="subject">Sujet</label>
                                <input type="text" id="subject" name="subject" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="message">Message</label>
                                <textarea id="message" name="message" rows="3" required></textarea>
                            </div>
                            
                            <div class="form-buttons">
                                <button type="submit" name="send_message" class="btn-primary">
                                    <i class="fas fa-paper-plane"></i> Envoyer
                                </button>
                                <button type="button" class="btn-secondary" onclick="hideNewMessageForm()">
                                    <i class="fas fa-times"></i> Annuler
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Liste des conversations -->
                    <div class="conversations">
                        <?php if (count($conversations) > 0): ?>
                            <?php foreach($conversations as $conversation): ?>
                                <a href="?contact=<?php echo $conversation['contact_id']; ?>" 
                                   class="conversation-item <?php echo $contact_id == $conversation['contact_id'] ? 'active' : ''; ?>">
                                    <div class="conversation-avatar">
                                        <?php 
                                        $icons = [
                                            'school' => 'fa-school',
                                            'teacher' => 'fa-chalkboard-teacher',
                                            'parent' => 'fa-user-friends'
                                        ];
                                        $icon = $icons[$conversation['contact_type']] ?? 'fa-user';
                                        ?>
                                        <i class="fas <?php echo $icon; ?>"></i>
                                    </div>
                                    
                                    <div class="conversation-info">
                                        <h4><?php echo htmlspecialchars($conversation['contact_name']); ?></h4>
                                        <p class="conversation-type"><?php echo $conversation['contact_type']; ?></p>
                                        <p class="conversation-time">
                                            <?php echo date('d/m/Y H:i', strtotime($conversation['last_message'])); ?>
                                        </p>
                                    </div>
                                    
                                    <?php if ($conversation['unread_count'] > 0): ?>
                                        <span class="unread-badge"><?php echo $conversation['unread_count']; ?></span>
                                    <?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="no-conversations">
                                <i class="fas fa-comments fa-2x"></i>
                                <p>Aucune conversation</p>
                                <button class="btn-small" onclick="showNewMessageForm()">
                                    <i class="fas fa-plus"></i> Démarrer une conversation
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Zone de conversation -->
                <div class="conversation-area">
                    <?php if ($contact_info): ?>
                        <div class="conversation-header">
                            <div class="contact-info">
                                <div class="contact-avatar">
                                    <?php 
                                    $icons = [
                                        'school' => 'fa-school',
                                        'teacher' => 'fa-chalkboard-teacher',
                                        'parent' => 'fa-user-friends'
                                    ];
                                    $icon = $icons[$contact_info['user_type']] ?? 'fa-user';
                                    ?>
                                    <i class="fas <?php echo $icon; ?>"></i>
                                </div>
                                <div>
                                    <h3><?php echo htmlspecialchars($contact_info['full_name']); ?></h3>
                                    <p class="contact-type"><?php echo $contact_info['user_type']; ?></p>
                                </div>
                            </div>
                            
                            <div class="conversation-actions">
                                <a href="?delete_conversation=<?php echo $contact_id; ?>" 
                                   class="btn-small" 
                                   onclick="return confirm('Supprimer toute la conversation?');"
                                   style="background: #e74c3c;">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </div>
                        
                        <div class="messages-container" id="messagesContainer">
                            <?php if (count($messages) > 0): ?>
                                <?php foreach($messages as $message): ?>
                                    <div class="message <?php echo $message['sender_id'] == $user_id ? 'sent' : 'received'; ?>">
                                        <div class="message-header">
                                            <span class="message-sender">
                                                <?php echo htmlspecialchars($message['sender_name']); ?>
                                            </span>
                                            <span class="message-time">
                                                <?php echo date('d/m/Y H:i', strtotime($message['sent_at'])); ?>
                                            </span>
                                        </div>
                                        
                                        <?php if ($message['subject']): ?>
                                            <div class="message-subject">
                                                <strong><?php echo htmlspecialchars($message['subject']); ?></strong>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="message-content">
                                            <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                                        </div>
                                        
                                        <?php if ($message['sender_id'] == $user_id): ?>
                                            <div class="message-status">
                                                <?php if ($message['is_read']): ?>
                                                    <i class="fas fa-check-double" title="Message lu"></i>
                                                <?php else: ?>
                                                    <i class="fas fa-check" title="Message envoyé"></i>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="no-messages">
                                    <p>Aucun message dans cette conversation</p>
                                    <p>Envoyez votre premier message!</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Formulaire de réponse -->
                        <div class="reply-form">
                            <form method="POST" id="replyForm">
                                <input type="hidden" name="receiver_id" value="<?php echo $contact_id; ?>">
                                
                                <div class="form-group">
                                    <input type="text" name="subject" placeholder="Sujet (optionnel)">
                                </div>
                                
                                <div class="form-group">
                                    <textarea name="message" placeholder="Tapez votre message ici..." rows="3" required></textarea>
                                </div>
                                
                                <div class="form-buttons">
                                    <button type="submit" name="send_message" class="btn-primary">
                                        <i class="fas fa-paper-plane"></i> Envoyer
                                    </button>
                                </div>
                            </form>
                        </div>
                        
                    <?php else: ?>
                        <div class="no-conversation-selected">
                            <i class="fas fa-comments fa-3x"></i>
                            <h3>Sélectionnez une conversation</h3>
                            <p>Choisissez une conversation dans la liste ou démarrez-en une nouvelle</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/dashboard.js"></script>
    <script>
    function showNewMessageForm() {
        document.getElementById('newMessageForm').style.display = 'block';
    }
    
    function hideNewMessageForm() {
        document.getElementById('newMessageForm').style.display = 'none';
    }
    
    // Faire défiler vers le bas des messages
    window.onload = function() {
        const messagesContainer = document.getElementById('messagesContainer');
        if (messagesContainer) {
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }
    };
    
    // Auto-refresh des messages toutes les 30 secondes
    setInterval(function() {
        if (window.location.search.includes('contact=')) {
            window.location.reload();
        }
    }, 30000);
    </script>
    
    <style>
        .messaging-container {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 20px;
            height: calc(100vh - 200px);
        }
        
        .conversations-list {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        
        .conversations-header {
            padding: 15px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .conversations-header h3 {
            margin: 0;
        }
        
        .conversations {
            flex: 1;
            overflow-y: auto;
        }
        
        .conversation-item {
            display: flex;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
            text-decoration: none;
            color: inherit;
            transition: background 0.3s;
            position: relative;
        }
        
        .conversation-item:hover {
            background: #f8f9fa;
        }
        
        .conversation-item.active {
            background: #3498db;
            color: white;
        }
        
        .conversation-item.active .conversation-type {
            color: rgba(255,255,255,0.8);
        }
        
        .conversation-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
            color: #3498db;
        }
        
        .conversation-item.active .conversation-avatar {
            background: rgba(255,255,255,0.2);
            color: white;
        }
        
        .conversation-info {
            flex: 1;
            min-width: 0;
        }
        
        .conversation-info h4 {
            margin: 0 0 3px;
            font-size: 0.9rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .conversation-type {
            font-size: 0.8rem;
            color: #95a5a6;
            margin: 0 0 3px;
        }
        
        .conversation-time {
            font-size: 0.75rem;
            color: #95a5a6;
            margin: 0;
        }
        
        .unread-badge {
            background: #e74c3c;
            color: white;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: bold;
        }
        
        .no-conversations {
            text-align: center;
            padding: 40px 20px;
            color: #95a5a6;
        }
        
        .conversation-area {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        
        .conversation-header {
            padding: 15px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .contact-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .contact-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #3498db;
        }
        
        .contact-info h3 {
            margin: 0 0 3px;
            font-size: 1rem;
        }
        
        .contact-type {
            font-size: 0.8rem;
            color: #95a5a6;
            margin: 0;
        }
        
        .messages-container {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .message {
            max-width: 70%;
            padding: 10px 15px;
            border-radius: 15px;
            position: relative;
        }
        
        .message.sent {
            align-self: flex-end;
            background: #3498db;
            color: white;
            border-bottom-right-radius: 5px;
        }
        
        .message.received {
            align-self: flex-start;
            background: #f8f9fa;
            color: #333;
            border-bottom-left-radius: 5px;
        }
        
        .message-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }
        
        .message.sent .message-header {
            color: rgba(255,255,255,0.8);
        }
        
        .message.received .message-header {
            color: #95a5a6;
        }
        
        .message-sender {
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .message-time {
            font-size: 0.7rem;
        }
        
        .message-subject {
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .message-content {
            line-height: 1.5;
        }
        
        .message-status {
            position: absolute;
            right: -20px;
            bottom: 5px;
            color: #95a5a6;
            font-size: 0.8rem;
        }
        
        .message.sent .message-status {
            color: #2ecc71;
        }
        
        .no-messages {
            text-align: center;
            padding: 40px 20px;
            color: #95a5a6;
        }
        
        .reply-form {
            padding: 15px;
            border-top: 1px solid #e0e0e0;
        }
        
        .no-conversation-selected {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            text-align: center;
            color: #95a5a6;
            padding: 20px;
        }
        
        .no-conversation-selected i {
            margin-bottom: 20px;
        }
        
        .no-conversation-selected h3 {
            margin-bottom: 10px;
        }
        
        .form-buttons {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        
        @media (max-width: 992px) {
            .messaging-container {
                grid-template-columns: 1fr;
                height: auto;
            }
            
            .conversations-list {
                height: 400px;
            }
            
            .conversation-area {
                height: 500px;
            }
        }
    </style>
</body>
</html>