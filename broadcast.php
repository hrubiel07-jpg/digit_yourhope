<?php
require_once __DIR__ . '/../../includes/config.php';
requireUserType('school');

$user_id = $_SESSION['user_id'];

// Récupérer l'école
$stmt = $pdo->prepare("SELECT s.* FROM schools s JOIN users u ON s.user_id = u.id WHERE u.id = ?");
$stmt->execute([$user_id]);
$school = $stmt->fetch();
$school_id = $school['id'];

// Types de destinataires
$recipient_types = [
    'all_parents' => 'Tous les parents',
    'all_teachers' => 'Tous les enseignants',
    'all_students' => 'Tous les élèves',
    'specific_class' => 'Classe spécifique',
    'specific_parents' => 'Parents spécifiques'
];

// Gestion de l'envoi
$sent_messages = [];
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_message'])) {
    $message_type = $_POST['message_type'];
    $recipient_type = $_POST['recipient_type'];
    $subject = $_POST['subject'] ?? '';
    $message = $_POST['message'];
    $attachment = null;
    
    // Gestion de l'upload de fichier
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {
        $upload_result = uploadFile($_FILES['attachment'], '../../../uploads/broadcast/');
        if ($upload_result['success']) {
            $attachment = $upload_result['filename'];
        }
    }
    
    // Récupérer les destinataires selon le type
    $recipients = [];
    
    switch ($recipient_type) {
        case 'all_parents':
            $stmt = $pdo->prepare("
                SELECT DISTINCT s.parent_phone as phone, 
                       CONCAT(s.parent_name, ' (', s.first_name, ' ', s.last_name, ')') as name
                FROM students s
                WHERE s.school_id = ? AND s.parent_phone IS NOT NULL AND s.parent_phone != ''
            ");
            $stmt->execute([$school_id]);
            $recipients = $stmt->fetchAll();
            break;
            
        case 'all_teachers':
            $stmt = $pdo->prepare("
                SELECT u.phone, u.full_name as name
                FROM teachers t
                JOIN users u ON t.user_id = u.id
                WHERE t.school_id = ? AND u.phone IS NOT NULL AND u.phone != ''
            ");
            $stmt->execute([$school_id]);
            $recipients = $stmt->fetchAll();
            break;
            
        case 'specific_class':
            $class_id = $_POST['class_id'] ?? 0;
            if ($class_id) {
                $stmt = $pdo->prepare("
                    SELECT s.parent_phone as phone, 
                           CONCAT(s.parent_name, ' (', s.first_name, ' ', s.last_name, ')') as name
                    FROM students s
                    WHERE s.school_id = ? AND s.current_class_id = ? 
                    AND s.parent_phone IS NOT NULL AND s.parent_phone != ''
                ");
                $stmt->execute([$school_id, $class_id]);
                $recipients = $stmt->fetchAll();
            }
            break;
            
        case 'specific_parents':
            $parent_ids = $_POST['parent_ids'] ?? [];
            if (!empty($parent_ids)) {
                $placeholders = str_repeat('?,', count($parent_ids) - 1) . '?';
                $stmt = $pdo->prepare("
                    SELECT s.parent_phone as phone, 
                           CONCAT(s.parent_name, ' (', s.first_name, ' ', s.last_name, ')') as name
                    FROM students s
                    WHERE s.id IN ($placeholders)
                    AND s.parent_phone IS NOT NULL AND s.parent_phone != ''
                ");
                $stmt->execute($parent_ids);
                $recipients = $stmt->fetchAll();
            }
            break;
    }
    
    // Envoyer les messages
    if (!empty($recipients)) {
        if ($message_type == 'whatsapp') {
            // Envoyer par WhatsApp
            $sent_messages = broadcastWhatsAppMessage($recipients, $message, $school_id);
        } else {
            // Envoyer par email
            $sent_messages = sendBulkEmails($recipients, $subject, $message, $attachment, $school_id);
        }
        
        // Enregistrer dans la base de données
        foreach ($recipients as $recipient) {
            $stmt = $pdo->prepare("
                INSERT INTO broadcast_messages 
                (school_id, message_type, recipient_type, recipient_phone, recipient_name, 
                 subject, message, attachment, status, sent_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'sent', NOW())
            ");
            $stmt->execute([
                $school_id, $message_type, $recipient_type, 
                $recipient['phone'], $recipient['name'],
                $subject, $message, $attachment
            ]);
        }
        
        $_SESSION['success_message'] = count($recipients) . " message(s) envoyé(s) avec succès!";
    } else {
        $_SESSION['error_message'] = "Aucun destinataire trouvé.";
    }
}

// Récupérer les classes pour le filtre
$classes = $pdo->prepare("
    SELECT id, class_name 
    FROM classes 
    WHERE school_id = ? 
    ORDER BY class_name
")->execute([$school_id])->fetchAll();

// Récupérer l'historique des messages
$history = $pdo->prepare("
    SELECT * FROM broadcast_messages 
    WHERE school_id = ? 
    ORDER BY sent_at DESC 
    LIMIT 50
")->execute([$school_id])->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Envoi de messages - <?php echo $school['school_name']; ?></title>
    <link rel="stylesheet" href="../../../assets/css/admin.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .broadcast-container {
            display: flex;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .message-form {
            flex: 2;
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .recipient-preview {
            flex: 1;
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .message-type-selector {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .message-type-btn {
            flex: 1;
            padding: 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            background: white;
            cursor: pointer;
            text-align: center;
            transition: all 0.3s;
        }
        
        .message-type-btn:hover {
            border-color: #3498db;
            background: #f8f9fa;
        }
        
        .message-type-btn.active {
            border-color: #3498db;
            background: #3498db;
            color: white;
        }
        
        .message-type-btn i {
            display: block;
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        .recipient-selector {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .recipient-btn {
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            background: white;
            cursor: pointer;
            text-align: center;
            transition: all 0.3s;
        }
        
        .recipient-btn:hover {
            border-color: #3498db;
            background: #f8f9fa;
        }
        
        .recipient-btn.active {
            border-color: #3498db;
            background: #3498db;
            color: white;
        }
        
        .recipient-count {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .recipient-count h3 {
            margin: 0;
            color: #3498db;
            font-size: 2rem;
        }
        
        .recipient-list {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 10px;
        }
        
        .recipient-item {
            padding: 8px;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .recipient-item:last-child {
            border-bottom: none;
        }
        
        .recipient-avatar {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #f1f1f1;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .history-table {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .history-row {
            display: grid;
            grid-template-columns: 1fr 1fr 2fr 1fr 1fr;
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
            align-items: center;
        }
        
        .history-row.header {
            font-weight: bold;
            background: #f8f9fa;
            border-radius: 5px 5px 0 0;
        }
        
        .message-status {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 0.8rem;
        }
        
        .status-sent { background: #2ecc71; color: white; }
        .status-pending { background: #f39c12; color: white; }
        .status-failed { background: #e74c3c; color: white; }
        
        .template-selector {
            margin-bottom: 20px;
        }
        
        .template-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 10px;
            cursor: pointer;
            border: 2px solid transparent;
            transition: all 0.3s;
        }
        
        .template-card:hover {
            border-color: #3498db;
            background: white;
        }
        
        .template-card h4 {
            margin: 0 0 5px;
            color: #2c3e50;
        }
        
        .template-card p {
            margin: 0;
            color: #7f8c8d;
            font-size: 0.9rem;
        }
    </style>
</head>
<body class="dashboard">
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <header class="top-bar">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Rechercher un message...">
            </div>
            <div class="user-info">
                <span><?php echo $school['school_name']; ?></span>
                <img src="../../../assets/images/default-school.png" alt="Logo">
            </div>
        </header>
        
        <div class="content">
            <h1 class="page-title">
                <i class="fas fa-bullhorn"></i> Envoi de messages
            </h1>
            
            <div class="broadcast-container">
                <!-- Formulaire d'envoi -->
                <div class="message-form">
                    <form method="POST" enctype="multipart/form-data" id="broadcastForm">
                        <div class="form-group">
                            <label>Type de message</label>
                            <div class="message-type-selector">
                                <button type="button" class="message-type-btn active" onclick="selectMessageType('whatsapp')">
                                    <i class="fab fa-whatsapp"></i>
                                    <span>WhatsApp</span>
                                </button>
                                <button type="button" class="message-type-btn" onclick="selectMessageType('email')">
                                    <i class="fas fa-envelope"></i>
                                    <span>Email</span>
                                </button>
                            </div>
                            <input type="hidden" name="message_type" id="messageType" value="whatsapp">
                        </div>
                        
                        <div class="form-group">
                            <label>Destinataires</label>
                            <div class="recipient-selector">
                                <button type="button" class="recipient-btn active" onclick="selectRecipientType('all_parents')">
                                    <i class="fas fa-users"></i>
                                    <span>Tous les parents</span>
                                </button>
                                <button type="button" class="recipient-btn" onclick="selectRecipientType('all_teachers')">
                                    <i class="fas fa-chalkboard-teacher"></i>
                                    <span>Tous les enseignants</span>
                                </button>
                                <button type="button" class="recipient-btn" onclick="selectRecipientType('specific_class')">
                                    <i class="fas fa-chalkboard"></i>
                                    <span>Par classe</span>
                                </button>
                                <button type="button" class="recipient-btn" onclick="selectRecipientType('specific_parents')">
                                    <i class="fas fa-user-check"></i>
                                    <span>Parents spécifiques</span>
                                </button>
                            </div>
                            <input type="hidden" name="recipient_type" id="recipientType" value="all_parents">
                        </div>
                        
                        <!-- Sélecteur de classe (visible seulement pour "Par classe") -->
                        <div class="form-group" id="classSelector" style="display: none;">
                            <label>Sélectionnez une classe</label>
                            <select name="class_id" id="classSelect" onchange="updateRecipients()">
                                <option value="">Choisir une classe...</option>
                                <?php foreach($classes as $class): ?>
                                    <option value="<?php echo $class['id']; ?>">
                                        <?php echo $class['class_name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Sélecteur de parents spécifiques -->
                        <div class="form-group" id="parentSelector" style="display: none;">
                            <label>Sélectionnez des parents</label>
                            <div style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; border-radius: 5px; padding: 10px;">
                                <?php
                                $parents = $pdo->prepare("
                                    SELECT s.id, s.first_name, s.last_name, s.parent_name, s.parent_phone
                                    FROM students s
                                    WHERE s.school_id = ? AND s.parent_phone IS NOT NULL
                                    ORDER BY s.parent_name
                                ")->execute([$school_id])->fetchAll();
                                
                                foreach($parents as $parent):
                                ?>
                                    <label style="display: block; padding: 5px; border-bottom: 1px solid #eee;">
                                        <input type="checkbox" name="parent_ids[]" value="<?php echo $parent['id']; ?>" 
                                               onchange="updateRecipients()">
                                        <?php echo $parent['parent_name']; ?>
                                        <small style="color: #7f8c8d;">
                                            (<?php echo $parent['first_name'] . ' ' . $parent['last_name']; ?>)
                                        </small>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- Champ sujet (visible seulement pour email) -->
                        <div class="form-group" id="subjectField" style="display: none;">
                            <label>Sujet *</label>
                            <input type="text" name="subject" placeholder="Sujet de l'email">
                        </div>
                        
                        <div class="form-group">
                            <label>Message *</label>
                            <textarea name="message" id="messageContent" rows="8" placeholder="Votre message..." required></textarea>
                            <small style="color: #7f8c8d; display: block; margin-top: 5px;">
                                <span id="charCount">0</span> caractères
                                <?php if ($message_type == 'whatsapp'): ?>
                                    (WhatsApp limite à 1000 caractères)
                                <?php endif; ?>
                            </small>
                        </div>
                        
                        <div class="form-group">
                            <label>Pièce jointe (optionnel)</label>
                            <input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                        </div>
                        
                        <div style="display: flex; gap: 10px; justify-content: flex-end;">
                            <button type="button" class="btn-secondary" onclick="loadTemplate()">
                                <i class="fas fa-file-alt"></i> Charger un modèle
                            </button>
                            <button type="submit" name="send_message" class="btn-primary">
                                <i class="fas fa-paper-plane"></i> Envoyer les messages
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Aperçu des destinataires -->
                <div class="recipient-preview">
                    <h3>Destinataires</h3>
                    <div class="recipient-count">
                        <h3 id="recipientCount">0</h3>
                        <p>personne(s) recevra(ont) ce message</p>
                    </div>
                    
                    <div id="recipientList" class="recipient-list">
                        <p style="text-align: center; color: #7f8c8d; padding: 20px;">
                            Sélectionnez des destinataires pour voir la liste
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Modèles prédéfinis -->
            <div class="form-section">
                <h3>Modèles prédéfinis</h3>
                <div class="template-selector">
                    <div class="template-card" onclick="loadTemplate('bulletin')">
                        <h4>📚 Bulletin scolaire disponible</h4>
                        <p>Notifier les parents que le bulletin est disponible</p>
                    </div>
                    
                    <div class="template-card" onclick="loadTemplate('payment_reminder')">
                        <h4>💰 Rappel de paiement</h4>
                        <p>Rappeler les paiements en retard</p>
                    </div>
                    
                    <div class="template-card" onclick="loadTemplate('meeting')">
                        <h4>📅 Réunion parents-professeurs</h4>
                        <p>Annoncer une réunion</p>
                    </div>
                    
                    <div class="template-card" onclick="loadTemplate('exam')">
                        <h4>📝 Information examen</h4>
                        <p>Donner des informations sur un examen</p>
                    </div>
                </div>
            </div>
            
            <!-- Historique des messages -->
            <div class="history-table">
                <h3>Historique des messages</h3>
                
                <div class="history-row header">
                    <div>Date</div>
                    <div>Type</div>
                    <div>Destinataire</div>
                    <div>Statut</div>
                    <div>Actions</div>
                </div>
                
                <?php if (empty($history)): ?>
                    <div class="history-row">
                        <div colspan="5" style="text-align: center; padding: 30px; color: #7f8c8d;">
                            Aucun message envoyé.
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach($history as $msg): ?>
                        <div class="history-row">
                            <div>
                                <?php echo date('d/m/Y H:i', strtotime($msg['sent_at'])); ?>
                            </div>
                            <div>
                                <?php if ($msg['message_type'] == 'whatsapp'): ?>
                                    <i class="fab fa-whatsapp" style="color: #25D366;"></i> WhatsApp
                                <?php else: ?>
                                    <i class="fas fa-envelope" style="color: #3498db;"></i> Email
                                <?php endif; ?>
                            </div>
                            <div>
                                <?php echo $msg['recipient_name'] ?: $msg['recipient_phone']; ?>
                                <br>
                                <small style="color: #7f8c8d;">
                                    <?php 
                                    $recipient_types = [
                                        'all_parents' => 'Tous les parents',
                                        'all_teachers' => 'Tous les enseignants',
                                        'specific_class' => 'Par classe',
                                        'specific_parents' => 'Parents spécifiques'
                                    ];
                                    echo $recipient_types[$msg['recipient_type']] ?? $msg['recipient_type'];
                                    ?>
                                </small>
                            </div>
                            <div>
                                <span class="message-status status-<?php echo $msg['status']; ?>">
                                    <?php echo $msg['status']; ?>
                                </span>
                            </div>
                            <div>
                                <button class="btn-small" onclick="viewMessage(<?php echo $msg['id']; ?>)">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Modal d'affichage de message -->
    <div class="modal" id="messageModal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Détails du message</h3>
                <button class="modal-close" onclick="closeMessageModal()">&times;</button>
            </div>
            <div class="modal-body" id="messageDetails">
                <!-- Les détails seront chargés ici -->
            </div>
        </div>
    </div>
    
    <script>
        // Variables globales
        let currentMessageType = 'whatsapp';
        let currentRecipientType = 'all_parents';
        let currentRecipients = [];
        
        // Sélection du type de message
        function selectMessageType(type) {
            currentMessageType = type;
            document.getElementById('messageType').value = type;
            
            // Mettre à jour l'interface
            document.querySelectorAll('.message-type-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.currentTarget.classList.add('active');
            
            // Afficher/masquer le champ sujet
            const subjectField = document.getElementById('subjectField');
            if (type === 'email') {
                subjectField.style.display = 'block';
            } else {
                subjectField.style.display = 'none';
            }
            
            // Mettre à jour le compteur de caractères
            updateCharCount();
        }
        
        // Sélection du type de destinataire
        function selectRecipientType(type) {
            currentRecipientType = type;
            document.getElementById('recipientType').value = type;
            
            // Mettre à jour l'interface
            document.querySelectorAll('.recipient-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.currentTarget.classList.add('active');
            
            // Afficher/masquer les sélecteurs spécifiques
            const classSelector = document.getElementById('classSelector');
            const parentSelector = document.getElementById('parentSelector');
            
            if (type === 'specific_class') {
                classSelector.style.display = 'block';
                parentSelector.style.display = 'none';
            } else if (type === 'specific_parents') {
                classSelector.style.display = 'none';
                parentSelector.style.display = 'block';
            } else {
                classSelector.style.display = 'none';
                parentSelector.style.display = 'none';
            }
            
            // Mettre à jour les destinataires
            updateRecipients();
        }
        
        // Mettre à jour la liste des destinataires
        function updateRecipients() {
            const formData = new FormData();
            formData.append('school_id', <?php echo $school_id; ?>);
            formData.append('recipient_type', currentRecipientType);
            
            if (currentRecipientType === 'specific_class') {
                const classId = document.getElementById('classSelect').value;
                if (classId) {
                    formData.append('class_id', classId);
                }
            } else if (currentRecipientType === 'specific_parents') {
                const selectedParents = Array.from(document.querySelectorAll('input[name="parent_ids[]"]:checked'))
                    .map(cb => cb.value);
                formData.append('parent_ids', selectedParents.join(','));
            }
            
            fetch('get_recipients.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                currentRecipients = data.recipients || [];
                
                // Mettre à jour le compteur
                document.getElementById('recipientCount').textContent = currentRecipients.length;
                
                // Mettre à jour la liste
                const recipientList = document.getElementById('recipientList');
                if (currentRecipients.length === 0) {
                    recipientList.innerHTML = `
                        <p style="text-align: center; color: #7f8c8d; padding: 20px;">
                            Aucun destinataire trouvé.
                        </p>
                    `;
                } else {
                    let html = '';
                    currentRecipients.slice(0, 10).forEach(recipient => {
                        html += `
                            <div class="recipient-item">
                                <div class="recipient-avatar">
                                    <i class="fas fa-user"></i>
                                </div>
                                <div>
                                    <div>${recipient.name || recipient.phone}</div>
                                    <small style="color: #7f8c8d;">${recipient.phone}</small>
                                </div>
                            </div>
                        `;
                    });
                    
                    if (currentRecipients.length > 10) {
                        html += `
                            <div class="recipient-item" style="text-align: center; color: #7f8c8d;">
                                ... et ${currentRecipients.length - 10} autre(s) destinataire(s)
                            </div>
                        `;
                    }
                    
                    recipientList.innerHTML = html;
                }
            });
        }
        
        // Charger un modèle prédéfini
        function loadTemplate(templateName = null) {
            const templates = {
                'bulletin': {
                    subject: 'Bulletin scolaire disponible',
                    message: 'Cher parent,\n\nLe bulletin scolaire de votre enfant est maintenant disponible sur votre espace parent.\n\nVous pouvez le consulter en vous connectant à votre compte.\n\nCordialement,\nL\'administration'
                },
                'payment_reminder': {
                    subject: 'Rappel de paiement',
                    message: 'Cher parent,\n\nNous vous rappelons que le paiement des frais de scolarité est attendu.\n\nVeuillez effectuer le paiement dès que possible.\n\nMerci de votre compréhension.'
                },
                'meeting': {
                    subject: 'Réunion parents-professeurs',
                    message: 'Cher parent,\n\nUne réunion parents-professeurs est prévue le [DATE] à [HEURE] dans les locaux de l\'école.\n\nVotre présence est importante.\n\nCordialement'
                },
                'exam': {
                    subject: 'Information examen',
                    message: 'Cher parent,\n\nLes examens du [NOM DE L\'EXAMEN] auront lieu du [DATE DÉBUT] au [DATE FIN].\n\nVeuillez vous assurer que votre enfant est bien préparé.\n\nBonne chance!'
                }
            };
            
            if (templateName && templates[templateName]) {
                const template = templates[templateName];
                
                if (currentMessageType === 'email' && template.subject) {
                    document.querySelector('input[name="subject"]').value = template.subject;
                }
                
                document.getElementById('messageContent').value = template.message;
                updateCharCount();
            }
        }
        
        // Compteur de caractères
        function updateCharCount() {
            const textarea = document.getElementById('messageContent');
            const charCount = document.getElementById('charCount');
            const count = textarea.value.length;
            
            charCount.textContent = count;
            
            // Mettre en évidence si on dépasse la limite WhatsApp
            if (currentMessageType === 'whatsapp' && count > 1000) {
                charCount.style.color = '#e74c3c';
                charCount.style.fontWeight = 'bold';
            } else {
                charCount.style.color = '#7f8c8d';
                charCount.style.fontWeight = 'normal';
            }
        }
        
        // Validation du formulaire
        document.getElementById('broadcastForm').onsubmit = function(e) {
            const message = document.getElementById('messageContent').value;
            
            if (!message.trim()) {
                e.preventDefault();
                alert('Veuillez saisir un message.');
                return false;
            }
            
            if (currentMessageType === 'whatsapp' && message.length > 1000) {
                e.preventDefault();
                alert('Le message WhatsApp ne peut pas dépasser 1000 caractères.');
                return false;
            }
            
            if (currentRecipients.length === 0) {
                e.preventDefault();
                alert('Aucun destinataire sélectionné.');
                return false;
            }
            
            // Confirmation pour l'envoi massif
            if (currentRecipients.length > 10) {
                const confirmed = confirm(
                    `Vous êtes sur le point d'envoyer ce message à ${currentRecipients.length} personne(s).\n\nÊtes-vous sûr de vouloir continuer ?`
                );
                
                if (!confirmed) {
                    e.preventDefault();
                    return false;
                }
            }
            
            return true;
        };
        
        // Voir les détails d'un message
        function viewMessage(messageId) {
            fetch(`get_message.php?id=${messageId}`)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('messageDetails').innerHTML = html;
                    document.getElementById('messageModal').style.display = 'flex';
                });
        }
        
        function closeMessageModal() {
            document.getElementById('messageModal').style.display = 'none';
        }
        
        // Initialisation
        document.addEventListener('DOMContentLoaded', function() {
            // Mettre à jour le compteur de caractères en temps réel
            document.getElementById('messageContent').addEventListener('input', updateCharCount);
            
            // Charger les destinataires initiaux
            updateRecipients();
            
            // Charger les modèles de templates
            fetch('get_templates.php')
                .then(response => response.json())
                .then(templates => {
                    // Stocker les templates pour une utilisation ultérieure
                    window.templates = templates;
                });
        });
    </script>
</body>
</html>