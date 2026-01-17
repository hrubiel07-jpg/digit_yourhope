<?php
/**
 * Fonctions de messagerie pour Digital YOURHOPE
 */

/**
 * Créer une nouvelle conversation
 */
function createConversation($title = '', $participants = [], $created_by, $is_group = false) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Créer la conversation
        $stmt = $pdo->prepare("
            INSERT INTO conversations (title, created_by, is_group) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$title, $created_by, $is_group ? 1 : 0]);
        $conversation_id = $pdo->lastInsertId();
        
        // Ajouter le créateur comme participant
        $stmt = $pdo->prepare("
            INSERT INTO conversation_participants (conversation_id, user_id, role) 
            VALUES (?, ?, 'admin')
        ");
        $stmt->execute([$conversation_id, $created_by]);
        
        // Ajouter les autres participants
        foreach ($participants as $participant_id) {
            if ($participant_id != $created_by) {
                $stmt = $pdo->prepare("
                    INSERT INTO conversation_participants (conversation_id, user_id) 
                    VALUES (?, ?)
                ");
                $stmt->execute([$conversation_id, $participant_id]);
            }
        }
        
        $pdo->commit();
        
        // Envoyer une notification aux participants
        foreach ($participants as $participant_id) {
            if ($participant_id != $created_by) {
                sendNotification($participant_id, 
                    'Nouvelle conversation',
                    'Vous avez été ajouté à une nouvelle conversation: ' . $title,
                    'message'
                );
            }
        }
        
        return ['success' => true, 'conversation_id' => $conversation_id];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Envoyer un message
 */
function sendMessage($conversation_id, $sender_id, $message, $type = 'text', $attachment = null) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Vérifier si l'utilisateur est participant
        $stmt = $pdo->prepare("
            SELECT 1 FROM conversation_participants 
            WHERE conversation_id = ? AND user_id = ?
        ");
        $stmt->execute([$conversation_id, $sender_id]);
        
        if (!$stmt->fetch()) {
            throw new Exception("Vous n'êtes pas participant à cette conversation");
        }
        
        // Gérer l'attachement
        $attachment_url = null;
        $attachment_name = null;
        $attachment_size = null;
        
        if ($attachment && $type != 'text') {
            $upload_result = uploadMessageAttachment($attachment);
            if (!$upload_result['success']) {
                throw new Exception($upload_result['error']);
            }
            
            $attachment_url = $upload_result['url'];
            $attachment_name = $attachment['name'];
            $attachment_size = $attachment['size'];
        }
        
        // Enregistrer le message
        $stmt = $pdo->prepare("
            INSERT INTO messages (conversation_id, sender_id, message, message_type, attachment_url, attachment_name, attachment_size) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $conversation_id, 
            $sender_id, 
            $message, 
            $type, 
            $attachment_url, 
            $attachment_name, 
            $attachment_size
        ]);
        
        $message_id = $pdo->lastInsertId();
        
        // Mettre à jour la dernière activité de la conversation
        $stmt = $pdo->prepare("
            UPDATE conversations SET last_message_at = NOW() WHERE id = ?
        ");
        $stmt->execute([$conversation_id]);
        
        // Marquer le message comme vu par l'expéditeur
        $stmt = $pdo->prepare("
            INSERT INTO message_views (message_id, user_id) VALUES (?, ?)
        ");
        $stmt->execute([$message_id, $sender_id]);
        
        // Récupérer les participants pour notification
        $stmt = $pdo->prepare("
            SELECT cp.user_id, u.full_name 
            FROM conversation_participants cp 
            JOIN users u ON cp.user_id = u.id 
            WHERE cp.conversation_id = ? AND cp.user_id != ?
        ");
        $stmt->execute([$conversation_id, $sender_id]);
        $participants = $stmt->fetchAll();
        
        // Envoyer des notifications
        $sender_name = getUserInfo($sender_id)['full_name'];
        $conversation_title = getConversationTitle($conversation_id);
        
        foreach ($participants as $participant) {
            // Notification dans l'application
            sendNotification(
                $participant['user_id'],
                'Nouveau message de ' . $sender_name,
                substr($message, 0, 100) . (strlen($message) > 100 ? '...' : ''),
                'message',
                '/messages.php?conversation=' . $conversation_id
            );
            
            // Email de notification si configuré
            if (EMAIL_NOTIFICATIONS && $participant['user_id'] != $sender_id) {
                $user_email = getUserInfo($participant['user_id'])['email'];
                sendEmailNotification(
                    $user_email,
                    'Nouveau message - ' . $conversation_title,
                    $sender_name . ' a envoyé un message: ' . $message
                );
            }
        }
        
        $pdo->commit();
        
        return ['success' => true, 'message_id' => $message_id];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Uploader un fichier joint
 */
function uploadMessageAttachment($file) {
    $upload_dir = dirname(__DIR__) . '/uploads/messages/';
    
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Vérifier le type de fichier
    $allowed_types = [
        'image/jpeg', 'image/jpg', 'image/png', 'image/gif',
        'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/zip', 'application/x-rar-compressed'
    ];
    
    if (!in_array($file['type'], $allowed_types)) {
        return ['success' => false, 'error' => 'Type de fichier non autorisé'];
    }
    
    // Limite de taille (10MB)
    $max_size = 10 * 1024 * 1024;
    if ($file['size'] > $max_size) {
        return ['success' => false, 'error' => 'Fichier trop volumineux (max 10MB)'];
    }
    
    // Générer un nom de fichier sécurisé
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($file['name']));
    $filepath = $upload_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return [
            'success' => true,
            'url' => 'messages/' . $filename,
            'filename' => $filename,
            'original_name' => $file['name']
        ];
    }
    
    return ['success' => false, 'error' => 'Erreur lors du téléchargement'];
}

/**
 * Obtenir les conversations d'un utilisateur
 */
function getUserConversations($user_id, $limit = 50, $offset = 0) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            c.*,
            m.message as last_message,
            m.created_at as last_message_at,
            m.sender_id as last_sender_id,
            u_sender.full_name as last_sender_name,
            u_sender.profile_image as last_sender_avatar,
            COUNT(DISTINCT m2.id) as unread_count
        FROM conversations c
        JOIN conversation_participants cp ON c.id = cp.conversation_id
        LEFT JOIN messages m ON c.id = m.conversation_id 
            AND m.created_at = (
                SELECT MAX(created_at) 
                FROM messages 
                WHERE conversation_id = c.id 
                AND is_deleted = 0
            )
        LEFT JOIN users u_sender ON m.sender_id = u_sender.id
        LEFT JOIN messages m2 ON c.id = m2.conversation_id 
            AND m2.created_at > COALESCE(cp.last_read_at, '1970-01-01')
            AND m2.sender_id != ?
            AND m2.is_deleted = 0
        WHERE cp.user_id = ?
        GROUP BY c.id, c.title, c.created_by, c.created_at, c.last_message_at, 
                 c.is_group, c.group_photo, m.message, m.created_at, 
                 m.sender_id, u_sender.full_name, u_sender.profile_image
        ORDER BY COALESCE(c.last_message_at, c.created_at) DESC
        LIMIT ? OFFSET ?
    ");
    
    $stmt->execute([$user_id, $user_id, $limit, $offset]);
    $conversations = $stmt->fetchAll();
    
    // Ajouter les informations des participants
    foreach ($conversations as &$conversation) {
        $conversation['participants'] = getConversationParticipants($conversation['id']);
        
        // Générer un titre pour les conversations individuelles
        if (empty($conversation['title']) && !$conversation['is_group']) {
            $other_participants = array_filter($conversation['participants'], 
                function($p) use ($user_id) { return $p['user_id'] != $user_id; });
            
            if (count($other_participants) == 1) {
                $conversation['title'] = reset($other_participants)['full_name'];
            } else {
                $conversation['title'] = 'Conversation avec ' . count($other_participants) . ' personnes';
            }
        }
    }
    
    return $conversations;
}

/**
 * Obtenir les messages d'une conversation
 */
function getConversationMessages($conversation_id, $user_id, $limit = 50, $offset = 0) {
    global $pdo;
    
    // Vérifier l'accès
    $stmt = $pdo->prepare("
        SELECT 1 FROM conversation_participants 
        WHERE conversation_id = ? AND user_id = ?
    ");
    $stmt->execute([$conversation_id, $user_id]);
    
    if (!$stmt->fetch()) {
        return ['success' => false, 'error' => 'Accès non autorisé'];
    }
    
    // Récupérer les messages
    $stmt = $pdo->prepare("
        SELECT 
            m.*,
            u.full_name as sender_name,
            u.profile_image as sender_avatar,
            u.user_type as sender_type,
            mv.viewed_at as read_at
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        LEFT JOIN message_views mv ON m.id = mv.message_id AND mv.user_id = ?
        WHERE m.conversation_id = ? AND m.is_deleted = 0
        ORDER BY m.created_at ASC
        LIMIT ? OFFSET ?
    ");
    
    $stmt->execute([$user_id, $conversation_id, $limit, $offset]);
    $messages = $stmt->fetchAll();
    
    // Marquer comme lus
    markMessagesAsRead($conversation_id, $user_id);
    
    return ['success' => true, 'messages' => $messages];
}

/**
 * Marquer les messages comme lus
 */
function markMessagesAsRead($conversation_id, $user_id) {
    global $pdo;
    
    try {
        // Récupérer les messages non lus
        $stmt = $pdo->prepare("
            SELECT m.id FROM messages m
            WHERE m.conversation_id = ? 
            AND m.sender_id != ?
            AND m.is_deleted = 0
            AND NOT EXISTS (
                SELECT 1 FROM message_views mv 
                WHERE mv.message_id = m.id AND mv.user_id = ?
            )
        ");
        $stmt->execute([$conversation_id, $user_id, $user_id]);
        $unread_messages = $stmt->fetchAll();
        
        // Marquer comme lus
        foreach ($unread_messages as $message) {
            $stmt = $pdo->prepare("
                INSERT INTO message_views (message_id, user_id) 
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE viewed_at = NOW()
            ");
            $stmt->execute([$message['id'], $user_id]);
        }
        
        // Mettre à jour le dernier accès du participant
        $stmt = $pdo->prepare("
            UPDATE conversation_participants 
            SET last_read_at = NOW() 
            WHERE conversation_id = ? AND user_id = ?
        ");
        $stmt->execute([$conversation_id, $user_id]);
        
        return true;
    } catch (Exception $e) {
        error_log("Erreur markMessagesAsRead: " . $e->getMessage());
        return false;
    }
}

/**
 * Obtenir les participants d'une conversation
 */
function getConversationParticipants($conversation_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            cp.*,
            u.full_name,
            u.email,
            u.user_type,
            u.profile_image
        FROM conversation_participants cp
        JOIN users u ON cp.user_id = u.id
        WHERE cp.conversation_id = ?
        ORDER BY cp.joined_at ASC
    ");
    
    $stmt->execute([$conversation_id]);
    return $stmt->fetchAll();
}

/**
 * Obtenir le titre d'une conversation
 */
function getConversationTitle($conversation_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT title, is_group FROM conversations WHERE id = ?");
    $stmt->execute([$conversation_id]);
    $conversation = $stmt->fetch();
    
    if (!empty($conversation['title'])) {
        return $conversation['title'];
    }
    
    // Pour les conversations individuelles, utiliser le nom des participants
    $participants = getConversationParticipants($conversation_id);
    $names = array_map(function($p) {
        return $p['full_name'];
    }, $participants);
    
    return implode(', ', $names);
}

/**
 * Supprimer un message
 */
function deleteMessage($message_id, $user_id) {
    global $pdo;
    
    try {
        // Vérifier que l'utilisateur est l'expéditeur
        $stmt = $pdo->prepare("
            SELECT id FROM messages 
            WHERE id = ? AND sender_id = ?
        ");
        $stmt->execute([$message_id, $user_id]);
        
        if (!$stmt->fetch()) {
            return ['success' => false, 'error' => 'Message non trouvé ou accès non autorisé'];
        }
        
        // Marquer comme supprimé
        $stmt = $pdo->prepare("
            UPDATE messages 
            SET is_deleted = 1, deleted_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$message_id]);
        
        return ['success' => true];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Obtenir le nombre de messages non lus
 */
function getUnreadMessageCount($user_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT m.id) as count
        FROM messages m
        JOIN conversation_participants cp ON m.conversation_id = cp.conversation_id
        WHERE cp.user_id = ? 
        AND m.sender_id != ?
        AND m.is_deleted = 0
        AND NOT EXISTS (
            SELECT 1 FROM message_views mv 
            WHERE mv.message_id = m.id AND mv.user_id = ?
        )
    ");
    
    $stmt->execute([$user_id, $user_id, $user_id]);
    $result = $stmt->fetch();
    
    return $result['count'] ?? 0;
}

/**
 * Rechercher des utilisateurs pour démarrer une conversation
 */
function searchUsersForConversation($current_user_id, $search_term = '', $user_type = null) {
    global $pdo;
    
    $sql = "
        SELECT u.id, u.full_name, u.email, u.user_type, u.profile_image
        FROM users u
        WHERE u.id != ? AND u.is_active = 1
    ";
    
    $params = [$current_user_id];
    
    if ($search_term) {
        $sql .= " AND (u.full_name LIKE ? OR u.email LIKE ?)";
        $search_param = "%$search_term%";
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    if ($user_type) {
        $sql .= " AND u.user_type = ?";
        $params[] = $user_type;
    }
    
    $sql .= " ORDER BY u.full_name LIMIT 20";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll();
}

/**
 * Envoyer une notification par email
 */
function sendEmailNotification($to_email, $subject, $message) {
    $headers = "From: " . ADMIN_EMAIL . "\r\n";
    $headers .= "Reply-To: " . ADMIN_EMAIL . "\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    $html_message = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #3498db; color: white; padding: 20px; text-align: center; }
            .content { background: #f8f9fa; padding: 20px; border-radius: 5px; }
            .footer { margin-top: 20px; text-align: center; color: #666; font-size: 0.9em; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>" . SITE_NAME . "</h2>
            </div>
            <div class='content'>
                " . nl2br(htmlspecialchars($message)) . "
            </div>
            <div class='footer'>
                <p>Cet email vous a été envoyé par " . SITE_NAME . "</p>
                <p><a href='" . SITE_URL . "'>Accéder à la plateforme</a></p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return mail($to_email, $subject, $html_message, $headers);
}

/**
 * Envoyer une notification dans l'application
 */
function sendNotification($user_id, $title, $message, $type = 'info', $link = null) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, title, message, type, link, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        return $stmt->execute([$user_id, $title, $message, $type, $link]);
    } catch (Exception $e) {
        error_log("Erreur sendNotification: " . $e->getMessage());
        return false;
    }
}
?>