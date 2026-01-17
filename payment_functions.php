<?php
/**
 * Fonctions de paiement pour le Congo
 */

/**
 * Générer une référence unique
 */
function generatePaymentReference($prefix = 'PAY') {
    return $prefix . date('Ymd') . strtoupper(uniqid());
}

/**
 * Initier un paiement
 */
function initiatePayment($student_id, $fee_id, $amount, $payment_method, $description = '') {
    global $pdo;
    
    try {
        // Vérifier si le paiement existe déjà
        $stmt = $pdo->prepare("
            SELECT id FROM transactions 
            WHERE student_id = ? AND fee_id = ? AND status IN ('pending', 'processing')
        ");
        $stmt->execute([$student_id, $fee_id]);
        
        if ($stmt->fetch()) {
            return ['success' => false, 'error' => 'Un paiement est déjà en cours pour cette facture'];
        }
        
        // Générer une référence
        $reference = generatePaymentReference();
        
        // Créer la transaction
        $stmt = $pdo->prepare("
            INSERT INTO transactions (reference, student_id, fee_id, amount, payment_method, description, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $reference,
            $student_id,
            $fee_id,
            $amount,
            $payment_method,
            $description,
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT']
        ]);
        
        $transaction_id = $pdo->lastInsertId();
        
        // Générer le lien de paiement selon la méthode
        $payment_url = generatePaymentLink($payment_method, $reference, $amount);
        
        // Envoyer une notification
        sendPaymentNotification($student_id, $transaction_id);
        
        return [
            'success' => true,
            'transaction_id' => $transaction_id,
            'reference' => $reference,
            'payment_url' => $payment_url
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Générer un lien de paiement selon la méthode
 */
function generatePaymentLink($payment_method, $reference, $amount) {
    switch ($payment_method) {
        case 'airtel_money':
            return SITE_URL . "payment/process/airtel?ref=" . $reference;
            
        case 'mpesa':
            return SITE_URL . "payment/process/mpesa?ref=" . $reference;
            
        case 'orange_money':
            return SITE_URL . "payment/process/orange?ref=" . $reference;
            
        case 'bank_transfer':
            return SITE_URL . "payment/process/bank?ref=" . $reference;
            
        case 'card':
            return SITE_URL . "payment/process/card?ref=" . $reference;
            
        default:
            return SITE_URL . "payment/process/cash?ref=" . $reference;
    }
}

/**
 * Traiter un paiement Mobile Money
 */
function processMobileMoneyPayment($provider, $reference, $phone_number, $amount) {
    // Ici, vous intégrerez l'API du fournisseur
    // Pour l'instant, simulation
    
    $api_url = '';
    $api_key = '';
    
    switch ($provider) {
        case 'airtel_money':
            $api_url = 'https://payments.airtel.africa/';
            break;
        case 'mpesa':
            $api_url = 'https://api.safaricom.co.ke/';
            break;
        case 'orange_money':
            $api_url = 'https://api.orange.com/';
            break;
    }
    
    // Simulation de paiement réussi
    sleep(2); // Simuler le traitement
    
    $success = rand(0, 10) > 2; // 80% de succès
    
    if ($success) {
        return [
            'success' => true,
            'transaction_id' => 'TXN_' . strtoupper(uniqid()),
            'message' => 'Paiement effectué avec succès'
        ];
    } else {
        return [
            'success' => false,
            'error' => 'Échec du paiement. Veuillez réessayer.'
        ];
    }
}

/**
 * Vérifier le statut d'une transaction
 */
function checkTransactionStatus($reference) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT t.*, s.first_name, s.last_name, s.matricule, f.fee_name
        FROM transactions t
        JOIN students s ON t.student_id = s.id
        JOIN school_fees f ON t.fee_id = f.id
        WHERE t.reference = ?
    ");
    $stmt->execute([$reference]);
    $transaction = $stmt->fetch();
    
    if (!$transaction) {
        return ['success' => false, 'error' => 'Transaction non trouvée'];
    }
    
    return ['success' => true, 'transaction' => $transaction];
}

/**
 * Confirmer un paiement
 */
function confirmPayment($reference, $provider_transaction_id = null) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Récupérer la transaction
        $stmt = $pdo->prepare("SELECT * FROM transactions WHERE reference = ?");
        $stmt->execute([$reference]);
        $transaction = $stmt->fetch();
        
        if (!$transaction) {
            throw new Exception('Transaction non trouvée');
        }
        
        if ($transaction['status'] == 'completed') {
            throw new Exception('Paiement déjà confirmé');
        }
        
        // Mettre à jour la transaction
        $stmt = $pdo->prepare("
            UPDATE transactions 
            SET status = 'completed', 
                completed_at = NOW(),
                provider_transaction_id = COALESCE(?, provider_transaction_id)
            WHERE reference = ?
        ");
        $stmt->execute([$provider_transaction_id, $reference]);
        
        // Mettre à jour le statut du paiement dans la table payments
        $stmt = $pdo->prepare("
            UPDATE payments 
            SET payment_status = 'paid',
                payment_date = NOW(),
                receipt_number = ?
            WHERE student_id = ? AND fee_id = ?
            ORDER BY created_at DESC LIMIT 1
        ");
        
        $receipt_number = 'RC' . date('Ymd') . str_pad($transaction['id'], 6, '0', STR_PAD_LEFT);
        $stmt->execute([$receipt_number, $transaction['student_id'], $transaction['fee_id']]);
        
        // Générer un reçu
        generateReceipt($transaction['id'], $receipt_number);
        
        // Envoyer une confirmation par email
        sendPaymentConfirmation($transaction['student_id'], $transaction['id']);
        
        $pdo->commit();
        
        return ['success' => true, 'receipt_number' => $receipt_number];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Générer un reçu
 */
function generateReceipt($transaction_id, $receipt_number) {
    global $pdo;
    
    // Récupérer les infos de la transaction
    $stmt = $pdo->prepare("
        SELECT t.*, s.first_name, s.last_name, s.matricule, f.fee_name,
               sc.school_name, sc.address, sc.phone, sc.email
        FROM transactions t
        JOIN students s ON t.student_id = s.id
        JOIN school_fees f ON t.fee_id = f.id
        JOIN schools sc ON s.school_id = sc.id
        WHERE t.id = ?
    ");
    $stmt->execute([$transaction_id]);
    $transaction = $stmt->fetch();
    
    if (!$transaction) return false;
    
    // Générer le HTML du reçu
    $html = generateReceiptHTML($transaction, $receipt_number);
    
    // Générer le PDF
    require_once 'vendor/autoload.php';
    
    $options = new \Dompdf\Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    
    $dompdf = new \Dompdf\Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    
    // Sauvegarder le PDF
    $upload_dir = dirname(__DIR__) . '/uploads/receipts/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $filename = $receipt_number . '.pdf';
    $filepath = $upload_dir . $filename;
    
    file_put_contents($filepath, $dompdf->output());
    
    // Enregistrer dans la base de données
    $stmt = $pdo->prepare("
        INSERT INTO receipts (transaction_id, receipt_number, issued_at, issued_by, pdf_path)
        VALUES (?, ?, NOW(), ?, ?)
    ");
    
    $user_id = $_SESSION['user_id'] ?? 0;
    $stmt->execute([$transaction_id, $receipt_number, $user_id, 'receipts/' . $filename]);
    
    return true;
}

/**
 * Générer le HTML du reçu
 */
function generateReceiptHTML($transaction, $receipt_number) {
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            @page { margin: 20mm; }
            body { font-family: Arial, sans-serif; font-size: 12px; line-height: 1.4; }
            .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 15px; }
            .header h1 { margin: 0; font-size: 24px; color: #2c3e50; }
            .header h2 { margin: 5px 0; font-size: 18px; color: #7f8c8d; }
            .receipt-info { margin-bottom: 20px; }
            .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
            .info-item { display: flex; justify-content: space-between; border-bottom: 1px solid #eee; padding: 5px 0; }
            .info-label { font-weight: bold; }
            .details { margin: 30px 0; }
            .details-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            .details-table th { background: #f8f9fa; text-align: left; padding: 10px; border: 1px solid #ddd; }
            .details-table td { padding: 10px; border: 1px solid #ddd; }
            .total { text-align: right; font-size: 16px; font-weight: bold; margin: 20px 0; }
            .footer { margin-top: 40px; text-align: center; font-size: 10px; color: #666; }
            .watermark { position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-45deg); font-size: 60px; color: rgba(0,0,0,0.1); z-index: -1; }
        </style>
    </head>
    <body>
        <div class="watermark">PAYÉ</div>
        
        <div class="header">
            <h1>' . htmlspecialchars($transaction['school_name']) . '</h1>
            <h2>REÇU DE PAIEMENT</h2>
            <div style="font-size: 14px;">N° ' . htmlspecialchars($receipt_number) . '</div>
        </div>
        
        <div class="receipt-info">
            <div class="info-grid">
                <div>
                    <div class="info-item">
                        <span class="info-label">Date:</span>
                        <span>' . date('d/m/Y H:i') . '</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Référence:</span>
                        <span>' . htmlspecialchars($transaction['reference']) . '</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Méthode:</span>
                        <span>' . htmlspecialchars($transaction['payment_method']) . '</span>
                    </div>
                </div>
                <div>
                    <div class="info-item">
                        <span class="info-label">Matricule:</span>
                        <span>' . htmlspecialchars($transaction['matricule']) . '</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Élève:</span>
                        <span>' . htmlspecialchars($transaction['first_name'] . ' ' . $transaction['last_name']) . '</span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="details">
            <table class="details-table">
                <thead>
                    <tr>
                        <th>Description</th>
                        <th>Montant</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>' . htmlspecialchars($transaction['fee_name']) . '</td>
                        <td>' . number_format($transaction['amount'], 0, ',', ' ') . ' FCFA</td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <div class="total">
            TOTAL: ' . number_format($transaction['amount'], 0, ',', ' ') . ' FCFA
        </div>
        
        <div style="margin-top: 40px; display: flex; justify-content: space-between;">
            <div style="text-align: center; width: 200px;">
                <div style="border-top: 1px solid #000; margin-top: 40px; padding-top: 5px;">
                    Signature du caissier
                </div>
            </div>
            <div style="text-align: center; width: 200px;">
                <div style="border-top: 1px solid #000; margin-top: 40px; padding-top: 5px;">
                    Cachet de l\'établissement
                </div>
            </div>
        </div>
        
        <div class="footer">
            <p>' . htmlspecialchars($transaction['school_name']) . ' - ' . htmlspecialchars($transaction['address']) . '</p>
            <p>Tél: ' . htmlspecialchars($transaction['phone']) . ' - Email: ' . htmlspecialchars($transaction['email']) . '</p>
            <p><strong>Ce reçu est un document officiel. Conservez-le précieusement.</strong></p>
        </div>
    </body>
    </html>';
    
    return $html;
}

/**
 * Envoyer une notification de paiement
 */
function sendPaymentNotification($student_id, $transaction_id) {
    // Récupérer les infos du parent
    $stmt = $pdo->prepare("
        SELECT u.email, u.phone, u.full_name as parent_name
        FROM students s
        JOIN parents p ON s.parent_id = p.id
        JOIN users u ON p.user_id = u.id
        WHERE s.id = ?
    ");
    $stmt->execute([$student_id]);
    $parent = $stmt->fetch();
    
    if (!$parent) return false;
    
    // Récupérer les infos de la transaction
    $stmt = $pdo->prepare("
        SELECT t.*, s.first_name, s.last_name, f.fee_name
        FROM transactions t
        JOIN students s ON t.student_id = s.id
        JOIN school_fees f ON t.fee_id = f.id
        WHERE t.id = ?
    ");
    $stmt->execute([$transaction_id]);
    $transaction = $stmt->fetch();
    
    // Envoyer email
    $subject = "Notification de paiement - " . $transaction['fee_name'];
    $message = "
    Cher parent " . $parent['parent_name'] . ",
    
    Un paiement a été initié pour votre enfant " . $transaction['first_name'] . " " . $transaction['last_name'] . ".
    
    Détails:
    - Frais: " . $transaction['fee_name'] . "
    - Montant: " . number_format($transaction['amount'], 0, ',', ' ') . " FCFA
    - Référence: " . $transaction['reference'] . "
    - Statut: En attente
    
    Veuillez compléter le paiement via le lien suivant:
    " . SITE_URL . "payment/complete?ref=" . $transaction['reference'] . "
    
    Cordialement,
    Service financier
    ";
    
    // Envoyer SMS si configuré
    if (SMS_NOTIFICATIONS && $parent['phone']) {
        sendSMS($parent['phone'], 
            "Paiement initié: " . $transaction['fee_name'] . " - " . number_format($transaction['amount'], 0, ',', ' ') . " FCFA. Réf: " . $transaction['reference']);
    }
    
    return mail($parent['email'], $subject, $message);
}

/**
 * Envoyer une confirmation de paiement
 */
function sendPaymentConfirmation($student_id, $transaction_id) {
    // Récupérer les infos
    $stmt = $pdo->prepare("
        SELECT t.*, s.first_name, s.last_name, f.fee_name,
               u.email as parent_email, u.phone as parent_phone, u.full_name as parent_name
        FROM transactions t
        JOIN students s ON t.student_id = s.id
        JOIN school_fees f ON t.fee_id = f.id
        LEFT JOIN parents p ON s.parent_id = p.id
        LEFT JOIN users u ON p.user_id = u.id
        WHERE t.id = ?
    ");
    $stmt->execute([$transaction_id]);
    $data = $stmt->fetch();
    
    if (!$data || !$data['parent_email']) return false;
    
    // Récupérer le numéro de reçu
    $stmt = $pdo->prepare("SELECT receipt_number FROM receipts WHERE transaction_id = ?");
    $stmt->execute([$transaction_id]);
    $receipt = $stmt->fetch();
    
    $subject = "Confirmation de paiement - " . $data['fee_name'];
    $message = "
    Cher parent " . $data['parent_name'] . ",
    
    Nous vous confirmons la réception du paiement pour votre enfant " . $data['first_name'] . " " . $data['last_name'] . ".
    
    Détails:
    - Frais: " . $data['fee_name'] . "
    - Montant: " . number_format($data['amount'], 0, ',', ' ') . " FCFA
    - Référence: " . $data['reference'] . "
    - N° Reçu: " . ($receipt['receipt_number'] ?? 'En cours') . "
    - Date: " . date('d/m/Y H:i') . "
    
    Le reçu est disponible en pièce jointe et dans votre espace parent.
    
    Cordialement,
    Service financier
    ";
    
    // Envoyer le reçu par email
    if ($receipt) {
        $receipt_path = dirname(__DIR__) . '/uploads/receipts/' . $receipt['receipt_number'] . '.pdf';
        if (file_exists($receipt_path)) {
            sendEmailWithAttachment($data['parent_email'], $subject, $message, $receipt_path);
        }
    }
    
    // Envoyer SMS
    if (SMS_NOTIFICATIONS && $data['parent_phone']) {
        sendSMS($data['parent_phone'], 
            "Paiement confirmé: " . $data['fee_name'] . " - " . number_format($data['amount'], 0, ',', ' ') . " FCFA. Réf: " . $data['reference']);
    }
    
    return true;
}

/**
 * Obtenir les méthodes de paiement disponibles
 */
function getAvailablePaymentMethods($school_id = null) {
    global $pdo;
    
    $sql = "SELECT * FROM payment_methods WHERE is_active = 1";
    $params = [];
    
    if ($school_id) {
        // Vérifier la configuration de l'école
        $sql .= " AND code IN (SELECT payment_method FROM school_payment_config WHERE school_id = ?)";
        $params[] = $school_id;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll();
}

/**
 * Générer un QR Code pour paiement
 */
function generatePaymentQRCode($reference, $amount, $phone = null) {
    // Générer le texte pour le QR Code
    $text = "";
    
    if ($phone) {
        // Format pour Mobile Money
        $text = "tel:" . $phone . "?amount=" . $amount . "&reference=" . $reference;
    } else {
        // Lien de paiement
        $text = SITE_URL . "payment/qr/" . $reference;
    }
    
    // Générer le QR Code (utiliser une librairie comme phpqrcode)
    // Pour l'instant, retourner le texte
    return $text;
}

/**
 * Obtenir l'historique des paiements d'un élève
 */
function getStudentPaymentHistory($student_id, $limit = 50, $offset = 0) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT t.*, f.fee_name, f.fee_type, r.receipt_number, r.pdf_path
        FROM transactions t
        JOIN school_fees f ON t.fee_id = f.id
        LEFT JOIN receipts r ON t.id = r.transaction_id
        WHERE t.student_id = ?
        ORDER BY t.initiated_at DESC
        LIMIT ? OFFSET ?
    ");
    
    $stmt->execute([$student_id, $limit, $offset]);
    return $stmt->fetchAll();
}

/**
 * Obtenir les statistiques de paiement d'une école
 */
function getSchoolPaymentStats($school_id, $period = 'month') {
    global $pdo;
    
    $date_format = $period == 'month' ? '%Y-%m' : '%Y-%m-%d';
    
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(t.completed_at, ?) as period,
            COUNT(*) as count,
            SUM(t.amount) as total_amount,
            AVG(t.amount) as avg_amount
        FROM transactions t
        JOIN students s ON t.student_id = s.id
        WHERE s.school_id = ? 
        AND t.status = 'completed'
        AND t.completed_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY period
        ORDER BY period DESC
    ");
    
    $stmt->execute([$date_format, $school_id]);
    return $stmt->fetchAll();
}
?>