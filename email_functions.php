<?php
/**
 * Fonctions d'envoi d'emails pour Digital YOURHOPE
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Charger PHPMailer
require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Envoyer un email
 */
function sendEmail($to, $subject, $message, $attachment = null) {
    $mail = new PHPMailer(true);
    
    try {
        // Configuration SMTP
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com'; // À configurer selon votre hébergement
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        
        // Expéditeur
        $mail->setFrom('noreply@digitalyourhope.com', SITE_NAME);
        $mail->addReplyTo('contact@digitalyourhope.com', SITE_NAME);
        
        // Destinataire
        if (is_array($to)) {
            foreach ($to as $recipient) {
                if (is_array($recipient)) {
                    $mail->addAddress($recipient['email'], $recipient['name'] ?? '');
                } else {
                    $mail->addAddress($recipient);
                }
            }
        } else {
            $mail->addAddress($to);
        }
        
        // Contenu
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $message;
        $mail->AltBody = strip_tags($message);
        
        // Pièce jointe
        if ($attachment && file_exists($attachment)) {
            $mail->addAttachment($attachment);
        }
        
        // Envoyer
        return $mail->send();
        
    } catch (Exception $e) {
        error_log("Erreur d'envoi d'email: " . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Envoyer des emails en masse
 */
function sendBulkEmails($recipients, $subject, $message, $attachment = null, $school_id = null) {
    $results = [];
    $success_count = 0;
    $failed_count = 0;
    
    foreach ($recipients as $recipient) {
        if (is_array($recipient)) {
            $email = $recipient['email'] ?? '';
            $name = $recipient['name'] ?? '';
        } else {
            $email = $recipient;
            $name = '';
        }
        
        if (!$email) continue;
        
        // Vérifier la validité de l'email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $failed_count++;
            $results[] = [
                'email' => $email,
                'name' => $name,
                'status' => 'failed',
                'error' => 'Email invalide'
            ];
            continue;
        }
        
        // Envoyer l'email
        $success = sendEmail($email, $subject, $message, $attachment);
        
        if ($success) {
            $success_count++;
            $results[] = [
                'email' => $email,
                'name' => $name,
                'status' => 'success'
            ];
        } else {
            $failed_count++;
            $results[] = [
                'email' => $email,
                'name' => $name,
                'status' => 'failed',
                'error' => 'Échec d\'envoi'
            ];
        }
        
        // Petite pause pour éviter de surcharger le serveur SMTP
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
 * Envoyer un bulletin par email
 */
function sendBulletinByEmail($bulletin_id, $email, $attachment_path = null) {
    global $pdo;
    
    // Récupérer les infos du bulletin
    $stmt = $pdo->prepare("
        SELECT b.*, s.first_name, s.last_name, s.parent_name,
               sch.school_name, sch.email as school_email
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
    
    // Construire le sujet
    $term_labels = [
        'trimestre1' => '1er Trimestre',
        'trimestre2' => '2ème Trimestre',
        'trimestre3' => '3ème Trimestre'
    ];
    
    $subject = "Bulletin scolaire - {$bulletin['first_name']} {$bulletin['last_name']} - {$term_labels[$bulletin['term']]} {$bulletin['school_year']}";
    
    // Construire le message
    $message = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { text-align: center; margin-bottom: 30px; }
            .school-name { color: #3498db; font-size: 24px; font-weight: bold; }
            .student-info { background: #f8f9fa; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
            .info-item { margin-bottom: 10px; }
            .info-label { font-weight: bold; color: #7f8c8d; }
            .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; text-align: center; color: #7f8c8d; font-size: 0.9em; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <div class="school-name">' . $bulletin['school_name'] . '</div>
                <h2>Bulletin Scolaire</h2>
            </div>
            
            <div class="student-info">
                <div class="info-item">
                    <span class="info-label">Élève:</span> ' . $bulletin['first_name'] . ' ' . $bulletin['last_name'] . '
                </div>
                <div class="info-item">
                    <span class="info-label">Période:</span> ' . $term_labels[$bulletin['term']] . ' - Année ' . $bulletin['school_year'] . '
                </div>
                <div class="info-item">
                    <span class="info-label">Moyenne générale:</span> <strong>' . $bulletin['average'] . '/20</strong>
                </div>';
    
    if ($bulletin['rank'] && $bulletin['total_students']) {
        $message .= '
                <div class="info-item">
                    <span class="info-label">Rang:</span> ' . $bulletin['rank'] . ' sur ' . $bulletin['total_students'] . ' élèves
                </div>';
    }
    
    $message .= '
            </div>
            
            <p>Cher parent,</p>
            <p>Le bulletin scolaire de votre enfant est disponible en pièce jointe de cet email.</p>
            <p>Vous pouvez également le consulter en vous connectant à votre espace parent sur notre plateforme.</p>
            
            <div class="footer">
                <p>Cet email a été envoyé automatiquement par le système de gestion scolaire.</p>
                <p>Pour toute question, contactez-nous à: ' . $bulletin['school_email'] . '</p>
                <p>&copy; ' . date('Y') . ' ' . SITE_NAME . '. Tous droits réservés.</p>
            </div>
        </div>
    </body>
    </html>';
    
    // Envoyer l'email
    $success = sendEmail($email, $subject, $message, $attachment_path);
    
    if ($success) {
        return ['success' => true, 'message' => 'Email envoyé avec succès'];
    } else {
        return ['success' => false, 'error' => 'Échec d\'envoi de l\'email'];
    }
}

/**
 * Envoyer une notification de paiement par email
 */
function sendPaymentEmail($payment_id, $email, $attachment_path = null) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT p.*, f.fee_name, f.amount, s.first_name, s.last_name, s.parent_name,
               sch.school_name, sch.email as school_email
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
    
    $subject = "Reçu de paiement - " . $payment['fee_name'];
    
    $message = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .receipt-header { text-align: center; margin-bottom: 30px; }
            .receipt-details { background: #f8f9fa; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
            .detail-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #eee; }
            .detail-row:last-child { border-bottom: none; }
            .total-row { font-weight: bold; font-size: 1.2em; color: #27ae60; }
            .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; text-align: center; color: #7f8c8d; font-size: 0.9em; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="receipt-header">
                <h2 style="color: #27ae60;">✓ Paiement Confirmé</h2>
                <div style="color: #7f8c8d;">Référence: #' . str_pad($payment['id'], 6, '0', STR_PAD_LEFT) . '</div>
            </div>
            
            <div class="receipt-details">
                <div class="detail-row">
                    <span>Date:</span>
                    <span>' . date('d/m/Y H:i', strtotime($payment['paid_at'])) . '</span>
                </div>
                <div class="detail-row">
                    <span>Élève:</span>
                    <span>' . $payment['first_name'] . ' ' . $payment['last_name'] . '</span>
                </div>
                <div class="detail-row">
                    <span>Type de frais:</span>
                    <span>' . $payment['fee_name'] . '</span>
                </div>
                <div class="detail-row">
                    <span>Méthode de paiement:</span>
                    <span>' . ucfirst(str_replace('_', ' ', $payment['payment_method'])) . '</span>
                </div>
                <div class="detail-row total-row">
                    <span>Montant payé:</span>
                    <span>' . number_format($payment['amount'], 0, ',', ' ') . ' FCFA</span>
                </div>
            </div>
            
            <p>Cher ' . $payment['parent_name'] . ',</p>
            <p>Votre paiement a été enregistré avec succès. Merci pour votre confiance.</p>
            
            <div class="footer">
                <p>' . $payment['school_name'] . '</p>
                <p>Pour toute question: ' . $payment['school_email'] . '</p>
            </div>
        </div>
    </body>
    </html>';
    
    $success = sendEmail($email, $subject, $message, $attachment_path);
    
    return ['success' => $success];
}

/**
 * Envoyer un rappel de paiement
 */
function sendPaymentReminderEmail($payment_id, $email) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT p.*, f.fee_name, f.amount, s.first_name, s.last_name, s.parent_name,
               sch.school_name, sch.email as school_email
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
    
    $days_late = floor((time() - strtotime($payment['due_date'])) / (60 * 60 * 24));
    
    $subject = "Rappel de paiement - " . $payment['fee_name'];
    
    $message = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .reminder-header { background: #f39c12; color: white; padding: 20px; border-radius: 10px; text-align: center; margin-bottom: 20px; }
            .payment-details { background: #f8f9fa; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
            .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; text-align: center; color: #7f8c8d; font-size: 0.9em; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="reminder-header">
                <h2 style="margin: 0;">Rappel de Paiement</h2>
                <p style="margin: 5px 0 0;">' . $days_late . ' jour(s) de retard</p>
            </div>
            
            <div class="payment-details">
                <p><strong>Cher parent,</strong></p>
                <p>Nous vous rappelons que le paiement suivant est en attente:</p>
                
                <ul>
                    <li><strong>Élève:</strong> ' . $payment['first_name'] . ' ' . $payment['last_name'] . '</li>
                    <li><strong>Frais:</strong> ' . $payment['fee_name'] . '</li>
                    <li><strong>Montant:</strong> ' . number_format($payment['amount'], 0, ',', ' ') . ' FCFA</li>
                    <li><strong>Date limite:</strong> ' . date('d/m/Y', strtotime($payment['due_date'])) . '</li>
                    <li><strong>Statut:</strong> En retard de ' . $days_late . ' jour(s)</li>
                </ul>
                
                <p>Veuillez effectuer le paiement dès que possible pour éviter toute interruption de service.</p>
            </div>
            
            <p>Vous pouvez effectuer le paiement en ligne sur votre espace parent ou vous rendre à l\'école pour régler en espèces.</p>
            
            <div class="footer">
                <p>' . $payment['school_name'] . '</p>
                <p>Contact: ' . $payment['school_email'] . '</p>
            </div>
        </div>
    </body>
    </html>';
    
    $success = sendEmail($email, $subject, $message);
    
    return ['success' => $success];
}