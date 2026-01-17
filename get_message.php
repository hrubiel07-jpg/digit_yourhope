<?php
require_once __DIR__ . '/../../includes/config.php';
requireUserType('school');

$message_id = $_GET['id'] ?? 0;
$user_id = $_SESSION['user_id'];

// Récupérer le message
$stmt = $pdo->prepare("
    SELECT m.*, s.school_name
    FROM broadcast_messages m
    JOIN schools s ON m.school_id = s.id
    WHERE m.id = ? AND s.user_id = ?
");
$stmt->execute([$message_id, $user_id]);
$message = $stmt->fetch();

if (!$message) {
    echo '<p>Message non trouvé.</p>';
    exit();
}

// Formater la date
$sent_date = date('d/m/Y à H:i', strtotime($message['sent_at']));

// Déterminer l'icône selon le type
$icon = $message['message_type'] == 'whatsapp' ? 'fab fa-whatsapp' : 'fas fa-envelope';
$color = $message['message_type'] == 'whatsapp' ? '#25D366' : '#3498db';

// Nom du type de destinataire
$recipient_types = [
    'all_parents' => 'Tous les parents',
    'all_teachers' => 'Tous les enseignants',
    'specific_class' => 'Par classe',
    'specific_parents' => 'Parents spécifiques'
];
?>
<div style="padding: 20px;">
    <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 20px;">
        <div style="width: 50px; height: 50px; border-radius: 50%; background: <?php echo $color; ?>; display: flex; align-items: center; justify-content: center;">
            <i class="<?php echo $icon; ?>" style="font-size: 24px; color: white;"></i>
        </div>
        <div>
            <h3 style="margin: 0;"><?php echo $message['school_name']; ?></h3>
            <p style="margin: 5px 0 0; color: #7f8c8d;">Envoyé le <?php echo $sent_date; ?></p>
        </div>
    </div>
    
    <div style="background: #f8f9fa; padding: 20px; border-radius: 10px; margin-bottom: 20px;">
        <h4 style="margin-top: 0; color: #2c3e50;">Informations du message</h4>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
            <div>
                <p style="margin: 5px 0;"><strong>Type:</strong> 
                    <?php echo $message['message_type'] == 'whatsapp' ? 'WhatsApp' : 'Email'; ?>
                </p>
            </div>
            <div>
                <p style="margin: 5px 0;"><strong>Destinataire:</strong> 
                    <?php echo $recipient_types[$message['recipient_type']] ?? $message['recipient_type']; ?>
                </p>
            </div>
            <div>
                <p style="margin: 5px 0;"><strong>Statut:</strong> 
                    <span style="display: inline-block; padding: 3px 8px; border-radius: 10px; background: #2ecc71; color: white; font-size: 0.9rem;">
                        <?php echo $message['status']; ?>
                    </span>
                </p>
            </div>
            <div>
                <p style="margin: 5px 0;"><strong>Contact:</strong> 
                    <?php echo $message['recipient_phone'] ?: $message['recipient_email'] ?: '-'; ?>
                </p>
            </div>
        </div>
    </div>
    
    <?php if ($message['subject']): ?>
        <div style="margin-bottom: 15px;">
            <h4 style="margin-bottom: 10px; color: #2c3e50;">Sujet</h4>
            <div style="background: white; padding: 15px; border-radius: 8px; border: 1px solid #eee;">
                <?php echo htmlspecialchars($message['subject']); ?>
            </div>
        </div>
    <?php endif; ?>
    
    <div style="margin-bottom: 15px;">
        <h4 style="margin-bottom: 10px; color: #2c3e50;">Message</h4>
        <div style="background: white; padding: 20px; border-radius: 8px; border: 1px solid #eee; white-space: pre-wrap; line-height: 1.6;">
            <?php echo htmlspecialchars($message['message']); ?>
        </div>
    </div>
    
    <?php if ($message['attachment']): ?>
        <div style="margin-bottom: 15px;">
            <h4 style="margin-bottom: 10px; color: #2c3e50;">Pièce jointe</h4>
            <div style="display: flex; align-items: center; gap: 10px; background: white; padding: 15px; border-radius: 8px; border: 1px solid #eee;">
                <i class="fas fa-paperclip" style="color: #7f8c8d;"></i>
                <span><?php echo basename($message['attachment']); ?></span>
                <a href="../../../uploads/broadcast/<?php echo $message['attachment']; ?>" 
                   target="_blank" 
                   style="margin-left: auto; color: #3498db; text-decoration: none;">
                    <i class="fas fa-download"></i> Télécharger
                </a>
            </div>
        </div>
    <?php endif; ?>
    
    <div style="display: flex; justify-content: flex-end; margin-top: 30px;">
        <button class="btn-secondary" onclick="closeMessageModal()">Fermer</button>
    </div>
</div>