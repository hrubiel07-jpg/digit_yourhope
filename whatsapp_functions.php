<?php
/**
 * Fonctions d'intégration WhatsApp pour Digital YOURHOPE
 */

// Configuration WhatsApp (simulée - à remplacer par l'API réelle)
define('WHATSAPP_API_URL', 'https://api.whatsapp.com/send');
define('WHATSAPP_BUSINESS_API', 'https://graph.facebook.com/v17.0');

/**
 * Envoyer un message WhatsApp
 */
function sendWhatsAppMessage($phone, $message, $school_id = null, $recipient_type = null, $recipient_id = null) {
    // Nettoyer le numéro de téléphone
    $phone = cleanPhoneNumber($phone);
    
    if (!$phone) {
        return ['success' => false, 'error' => 'Numéro de téléphone invalide'];
    }
    
    // Dans un environnement réel, vous utiliseriez l'API WhatsApp Business
    // Pour l'instant, nous simulons l'envoi
    
    // Enregistrer dans la base de données
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO whatsapp_messages (school_id, recipient_type, recipient_id, recipient_phone, message_content, status)
            VALUES (?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([$school_id, $recipient_type, $recipient_id, $phone, $message]);
        
        $message_id = $pdo->lastInsertId();
        
        // SIMULATION: 90% de chance de succès
        $success = rand(1, 100) <= 90;
        
        if ($success) {
            $wa_message_id = 'WA_' . uniqid();
            $stmt = $pdo->prepare("
                UPDATE whatsapp_messages 
                SET status = 'sent', sent_at = NOW(), message_id = ?
                WHERE id = ?
            ");
            $stmt->execute([$wa_message_id, $message_id]);
            
            return ['success' => true, 'message_id' => $wa_message_id];
        } else {
            $stmt = $pdo->prepare("
                UPDATE whatsapp_messages 
                SET status = 'failed', error_message = 'Simulation: Échec d\'envoi'
                WHERE id = ?
            ");
            $stmt->execute([$message_id]);
            
            return ['success' => false, 'error' => 'Échec d\'envoi du message'];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Envoyer un bulletin par WhatsApp
 */
function sendBulletinByWhatsApp($bulletin_id, $phone, $school_id = null) {
    global $pdo;
    
    // Récupérer les infos du bulletin
    $stmt = $pdo->prepare("
        SELECT b.*, s.first_name, s.last_name, s.parent_phone, s.parent_name,
               sch.school_name, s.id as student_id
        FROM bulletins b
        JOIN students s ON b.student_id = s.id
        JOIN schools sch ON s.school_id = sch.id
        WHERE b.id = ?
    ");
    $stmt->execute([$bulletin_id]);
    $bulletin = $stmt->fetch();
    
    if (!$bulletin) {
        return ['success' => false, 'error' => 'Bulletin non trouvé'];
    }
    
    // Construire le message
    $term_labels = [
        'trimestre1' => '1er Trimestre',
        'trimestre2' => '2ème Trimestre',
        'trimestre3' => '3ème Trimestre',
        'semestre1' => '1er Semestre',
        'semestre2' => '2ème Semestre',
        'annuel' => 'Annuel'
    ];
    
    $message = "📚 *Bulletin Scolaire* 📚\n\n";
    $message .= "Élève: *{$bulletin['first_name']} {$bulletin['last_name']}*\n";
    $message .= "École: {$bulletin['school_name']}\n";
    $message .= "Période: {$term_labels[$bulletin['term']]} {$bulletin['school_year']}\n";
    
    if ($bulletin['average']) {
        $message .= "Moyenne: *{$bulletin['average']}/20*\n\n";
    }
    
    if ($bulletin['rank'] && $bulletin['total_students']) {
        $message .= "Rang: {$bulletin['rank']}/{$bulletin['total_students']}\n";
    }
    
    $message .= "\nLe bulletin complet est disponible sur votre espace parent.\n";
    $message .= "Merci de votre confiance!";
    
    // Envoyer le message
    return sendWhatsAppMessage($phone, $message, $school_id, 'student', $bulletin['student_id']);
}

/**
 * Envoyer une notification de paiement
 */
function sendPaymentNotification($payment_id, $phone, $school_id = null) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT p.*, f.fee_name, f.amount, s.first_name, s.last_name,
               sch.school_name, s.id as student_id
        FROM payments p
        JOIN school_fees f ON p.fee_id = f.id
        JOIN students s ON p.student_id = s.id
        JOIN schools sch ON s.school_id = sch.id
        WHERE p.id = ?
    ");
    $stmt->execute([$payment_id]);
    $payment = $stmt->fetch();
    
    if (!$payment) {
        return ['success' => false, 'error' => 'Paiement non trouvé'];
    }
    
    $message = "💰 *Notification de Paiement* 💰\n\n";
    $message .= "Cher parent,\n\n";
    $message .= "Le paiement pour *{$payment['fee_name']}* a été enregistré.\n";
    $message .= "Montant: *" . number_format($payment['amount'], 0, ',', ' ') . " FCFA*\n";
    $message .= "Élève: {$payment['first_name']} {$payment['last_name']}\n";
    $message .= "École: {$payment['school_name']}\n";
    $message .= "Date: " . date('d/m/Y') . "\n\n";
    $message .= "Merci pour votre règlement!";
    
    return sendWhatsAppMessage($phone, $message, $school_id, 'student', $payment['student_id']);
}

/**
 * Nettoyer le numéro de téléphone
 */
function cleanPhoneNumber($phone) {
    // Supprimer tous les caractères non numériques
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Si le numéro commence par 221 (indicatif Congo)
    if (strlen($phone) == 12 && substr($phone, 0, 3) == '221') {
        return $phone;
    }
    
    // Si le numéro commence par +221
    if (strlen($phone) == 13 && substr($phone, 0, 4) == '221') {
        return $phone;
    }
    
    // Si le numéro a 9 chiffres (sans indicatif)
    if (strlen($phone) == 9) {
        return '221' . $phone;
    }
    
    // Format invalide
    return false;
}

/**
 * Générer un lien WhatsApp
 */
function generateWhatsAppLink($phone, $message = '') {
    $phone = cleanPhoneNumber($phone);
    if (!$phone) return false;
    
    $encoded_message = urlencode($message);
    return "https://wa.me/$phone?text=$encoded_message";
}

/**
 * Envoyer un message à plusieurs destinataires
 */
function broadcastWhatsAppMessage($recipients, $message, $school_id = null) {
    global $pdo;
    
    $results = [];
    $success_count = 0;
    $failed_count = 0;
    
    foreach ($recipients as $recipient) {
        if (is_array($recipient)) {
            $phone = $recipient['phone'] ?? '';
            $name = $recipient['name'] ?? '';
            $recipient_id = $recipient['id'] ?? null;
            $recipient_type = $recipient['type'] ?? null;
        } else {
            $phone = $recipient;
            $name = '';
            $recipient_id = null;
            $recipient_type = null;
        }
        
        if (!$phone) continue;
        
        $result = sendWhatsAppMessage($phone, $message, $school_id, $recipient_type, $recipient_id);
        
        if ($result['success']) {
            $success_count++;
            $results[] = [
                'phone' => $phone,
                'name' => $name,
                'status' => 'success',
                'message_id' => $result['message_id'] ?? null
            ];
        } else {
            $failed_count++;
            $results[] = [
                'phone' => $phone,
                'name' => $name,
                'status' => 'failed',
                'error' => $result['error'] ?? 'Erreur inconnue'
            ];
        }
        
        // Petite pause pour éviter le rate limiting
        usleep(100000); // 0.1 seconde
    }
    
    return [
        'total' => count($recipients),
        'success' => $success_count,
        'failed' => $failed_count,
        'results' => $results
    ];
}

/**
 * Récupérer l'historique des messages WhatsApp
 */
function getWhatsAppHistory($school_id = null, $limit = 50) {
    global $pdo;
    
    $query = "SELECT wm.*, s.school_name 
              FROM whatsapp_messages wm
              LEFT JOIN schools s ON wm.school_id = s.id
              WHERE 1=1";
    
    $params = [];
    
    if ($school_id) {
        $query .= " AND wm.school_id = ?";
        $params[] = $school_id;
    }
    
    $query .= " ORDER BY wm.created_at DESC LIMIT ?";
    $params[] = $limit;
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    
    return $stmt->fetchAll();
}

/**
 * Vérifier le statut d'un message WhatsApp
 */
function checkWhatsAppMessageStatus($message_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT status, sent_at, error_message 
        FROM whatsapp_messages 
        WHERE message_id = ?
    ");
    $stmt->execute([$message_id]);
    
    return $stmt->fetch();
}

/**
 * Envoyer des rappels de paiement par WhatsApp
 */
function sendPaymentReminders($school_id = null) {
    global $pdo;
    
    // Récupérer les paiements en retard
    $query = "
        SELECT p.*, s.first_name, s.last_name, s.parent_phone, s.parent_name,
               f.fee_name, f.amount, sch.school_name, s.id as student_id
        FROM payments p
        JOIN students s ON p.student_id = s.id
        JOIN school_fees f ON p.fee_id = f.id
        JOIN schools sch ON s.school_id = sch.id
        WHERE p.payment_status = 'pending' 
        AND p.due_date < CURDATE()
    ";
    
    if ($school_id) {
        $query .= " AND s.school_id = ?";
    }
    
    $query .= " ORDER BY p.due_date";
    
    $stmt = $pdo->prepare($query);
    
    if ($school_id) {
        $stmt->execute([$school_id]);
    } else {
        $stmt->execute();
    }
    
    $payments = $stmt->fetchAll();
    
    $results = [];
    
    foreach ($payments as $payment) {
        if (!$payment['parent_phone']) continue;
        
        $days_late = floor((time() - strtotime($payment['due_date'])) / (60 * 60 * 24));
        
        $message = "📅 *Rappel de Paiement* 📅\n\n";
        $message .= "Cher parent,\n\n";
        $message .= "Nous vous rappelons que le paiement des frais de *{$payment['fee_name']}*\n";
        $message .= "pour *{$payment['first_name']} {$payment['last_name']}* est en retard de *{$days_late} jour(s)*.\n\n";
        $message .= "Montant: *" . number_format($payment['amount'], 0, ',', ' ') . " FCFA*\n";
        $message .= "Date limite: " . date('d/m/Y', strtotime($payment['due_date'])) . "\n\n";
        $message .= "Veuillez effectuer le paiement dès que possible.\n";
        $message .= "Merci de votre compréhension.";
        
        $result = sendWhatsAppMessage($payment['parent_phone'], $message, $school_id, 'student', $payment['student_id']);
        
        $results[] = [
            'payment_id' => $payment['id'],
            'student' => $payment['first_name'] . ' ' . $payment['last_name'],
            'phone' => $payment['parent_phone'],
            'amount' => $payment['amount'],
            'days_late' => $days_late,
            'success' => $result['success']
        ];
    }
    
    return $results;
}

/**
 * Envoyer une notification d'examen
 */
function sendExamNotification($candidate_id, $phone, $school_id = null) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT ec.*, e.exam_type, e.exam_date, e.center_name,
               s.first_name, s.last_name, sch.school_name,
               s.id as student_id
        FROM exam_candidates ec
        JOIN exam_registrations e ON ec.exam_registration_id = e.id
        JOIN students s ON ec.student_id = s.id
        JOIN schools sch ON s.school_id = sch.id
        WHERE ec.id = ?
    ");
    $stmt->execute([$candidate_id]);
    $candidate = $stmt->fetch();
    
    if (!$candidate) {
        return ['success' => false, 'error' => 'Candidat non trouvé'];
    }
    
    $exam_names = [
        'CEPE' => 'CEPE',
        'BEPC' => 'BEPC',
        'BAC' => 'BACALAURÉAT',
        'BTS' => 'BTS',
        'CAP' => 'CAP'
    ];
    
    $exam_name = $exam_names[$candidate['exam_type']] ?? $candidate['exam_type'];
    
    $message = "📝 *Notification Examen* 📝\n\n";
    $message .= "Cher parent,\n\n";
    $message .= "Votre enfant *{$candidate['first_name']} {$candidate['last_name']}*\n";
    $message .= "a été inscrit(e) à l'examen:\n\n";
    $message .= "*{$exam_name}*\n";
    
    if ($candidate['exam_date']) {
        $message .= "Date: " . date('d/m/Y', strtotime($candidate['exam_date'])) . "\n";
    }
    
    if ($candidate['center_name']) {
        $message .= "Centre: {$candidate['center_name']}\n";
    }
    
    if ($candidate['registration_number']) {
        $message .= "N° d'inscription: {$candidate['registration_number']}\n";
    }
    
    $message .= "\nBonne chance à votre enfant!";
    
    return sendWhatsAppMessage($phone, $message, $school_id, 'student', $candidate['student_id']);
}

/**
 * Envoyer une notification de réunion
 */
function sendMeetingNotification($event_id, $phone, $school_id = null) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT e.*, s.school_name
        FROM school_calendar e
        JOIN schools s ON e.school_id = s.id
        WHERE e.id = ?
    ");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch();
    
    if (!$event) {
        return ['success' => false, 'error' => 'Événement non trouvé'];
    }
    
    $message = "📅 *Notification Réunion* 📅\n\n";
    $message .= "Cher parent,\n\n";
    $message .= "Nous vous informons de la réunion:\n\n";
    $message .= "*{$event['title']}*\n";
    
    if ($event['description']) {
        $message .= "Description: {$event['description']}\n";
    }
    
    $message .= "Date: " . date('d/m/Y', strtotime($event['start_date']));
    
    if ($event['start_time']) {
        $message .= " à " . date('H:i', strtotime($event['start_time']));
    }
    
    if ($event['location']) {
        $message .= "\nLieu: {$event['location']}";
    }
    
    $message .= "\n\nVotre présence est importante!";
    
    return sendWhatsAppMessage($phone, $message, $school_id, 'parent', null);
}

/**
 * Récupérer les templates WhatsApp
 */
function getWhatsAppTemplates($school_id = null) {
    global $pdo;
    
    $query = "SELECT * FROM whatsapp_templates WHERE 1=1";
    $params = [];
    
    if ($school_id) {
        $query .= " AND (school_id = ? OR school_id IS NULL)";
        $params[] = $school_id;
    } else {
        $query .= " AND school_id IS NULL";
    }
    
    $query .= " ORDER BY template_name";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    
    return $stmt->fetchAll();
}

/**
 * Ajouter un template WhatsApp
 */
function addWhatsAppTemplate($school_id, $template_name, $template_type, $message_template, $variables = null) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        INSERT INTO whatsapp_templates 
        (school_id, template_name, template_type, message_template, variables)
        VALUES (?, ?, ?, ?, ?)
    ");
    
    return $stmt->execute([$school_id, $template_name, $template_type, $message_template, $variables]);
}

/**
 * Utiliser un template pour envoyer un message
 */
function sendTemplateMessage($template_id, $phone, $variables = [], $school_id = null) {
    global $pdo;
    
    // Récupérer le template
    $stmt = $pdo->prepare("SELECT * FROM whatsapp_templates WHERE id = ?");
    $stmt->execute([$template_id]);
    $template = $stmt->fetch();
    
    if (!$template) {
        return ['success' => false, 'error' => 'Template non trouvé'];
    }
    
    // Remplacer les variables dans le message
    $message = $template['message_template'];
    
    if (!empty($variables)) {
        foreach ($variables as $key => $value) {
            $message = str_replace("{{$key}}", $value, $message);
        }
    }
    
    // Envoyer le message
    return sendWhatsAppMessage($phone, $message, $school_id);
}

/**
 * Obtenir les statistiques WhatsApp
 */
function getWhatsAppStats($school_id = null, $period = 'month') {
    global $pdo;
    
    $query = "
        SELECT 
            COUNT(*) as total_messages,
            COUNT(CASE WHEN status = 'sent' THEN 1 END) as sent_messages,
            COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_messages,
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_messages,
            COUNT(DISTINCT recipient_phone) as unique_recipients
        FROM whatsapp_messages
        WHERE 1=1
    ";
    
    $params = [];
    
    if ($school_id) {
        $query .= " AND school_id = ?";
        $params[] = $school_id;
    }
    
    // Filtrer par période
    switch ($period) {
        case 'today':
            $query .= " AND DATE(created_at) = CURDATE()";
            break;
        case 'week':
            $query .= " AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            break;
        case 'month':
            $query .= " AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            break;
        case 'year':
            $query .= " AND created_at >= DATE_SUB(NOW(), INTERVAL 365 DAY)";
            break;
    }
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    
    return $stmt->fetch();
}

/**
 * Générer un rapport WhatsApp
 */
function generateWhatsAppReport($school_id = null, $start_date = null, $end_date = null) {
    global $pdo;
    
    $query = "
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as total_messages,
            COUNT(CASE WHEN status = 'sent' THEN 1 END) as sent,
            COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed,
            GROUP_CONCAT(DISTINCT recipient_type) as recipient_types
        FROM whatsapp_messages
        WHERE 1=1
    ";
    
    $params = [];
    
    if ($school_id) {
        $query .= " AND school_id = ?";
        $params[] = $school_id;
    }
    
    if ($start_date) {
        $query .= " AND DATE(created_at) >= ?";
        $params[] = $start_date;
    }
    
    if ($end_date) {
        $query .= " AND DATE(created_at) <= ?";
        $params[] = $end_date;
    }
    
    $query .= " GROUP BY DATE(created_at) ORDER BY date DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    
    return $stmt->fetchAll();
}

/**
 * Vérifier si un numéro est valide pour WhatsApp
 */
function isValidWhatsAppNumber($phone) {
    $phone = cleanPhoneNumber($phone);
    
    if (!$phone) {
        return false;
    }
    
    // Vérifier la longueur (indicatif + 9 chiffres)
    if (strlen($phone) != 12) {
        return false;
    }
    
    // Vérifier que ça commence par 221 (Congo)
    if (substr($phone, 0, 3) != '221') {
        return false;
    }
    
    // Vérifier que les 9 chiffres suivants sont valides
    $number_part = substr($phone, 3);
    if (!preg_match('/^[0-9]{9}$/', $number_part)) {
        return false;
    }
    
    return true;
}

/**
 * Formater un numéro de téléphone pour l'affichage
 */
function formatPhoneNumber($phone) {
    $phone = cleanPhoneNumber($phone);
    
    if (!$phone || strlen($phone) != 12) {
        return $phone;
    }
    
    // Format: +221 XX XXX XX XX
    $indicatif = '+221';
    $part1 = substr($phone, 3, 2);
    $part2 = substr($phone, 5, 3);
    $part3 = substr($phone, 8, 2);
    $part4 = substr($phone, 10, 2);
    
    return "$indicatif $part1 $part2 $part3 $part4";
}

/**
 * Tester la connexion WhatsApp (simulé)
 */
function testWhatsAppConnection() {
    // Dans un environnement réel, vous testeriez la connexion à l'API WhatsApp Business
    // Pour l'instant, nous simulons un test
    
    $test_number = '221000000000'; // Numéro de test
    
    $message = "🔧 *Test de Connexion WhatsApp* 🔧\n\n";
    $message .= "Ceci est un message de test pour vérifier la connexion WhatsApp.\n";
    $message .= "Date: " . date('d/m/Y H:i:s') . "\n";
    $message .= "Statut: ✅ Connexion établie";
    
    $result = sendWhatsAppMessage($test_number, $message);
    
    return [
        'success' => $result['success'],
        'message' => $result['success'] ? 'Connexion WhatsApp établie avec succès' : 'Échec de la connexion WhatsApp',
        'details' => $result
    ];
}