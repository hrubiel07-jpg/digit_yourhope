<?php
require_once '../includes/config.php';
requireLogin();

$transaction_id = $_GET['txn_id'] ?? '';
$gateway = $_GET['gateway'] ?? '';
$phone = $_GET['phone'] ?? '';
$amount = $_GET['amount'] ?? '';

// Récupérer la transaction
$stmt = $pdo->prepare("SELECT * FROM online_payments WHERE transaction_id = ?");
$stmt->execute([$transaction_id]);
$transaction = $stmt->fetch();

if (!$transaction) {
    die("Transaction non trouvée.");
}

// SIMULATION DE PAIEMENT
// Dans un environnement réel, vous intégreriez ici l'API de Wave, Orange Money, etc.

// Simuler un délai de traitement
sleep(2);

// Simuler un paiement réussi (80% de chance)
$success = rand(1, 100) <= 80;

if ($success) {
    // Paiement réussi
    $status = 'completed';
    $message = "Paiement de {$amount} FCFA effectué avec succès via {$gateway}!";
    
    // Mettre à jour la transaction
    $stmt = $pdo->prepare("
        UPDATE online_payments 
        SET status = ?, completed_at = NOW(), 
            payer_phone = ?, gateway_response = 'Simulation: Paiement réussi'
        WHERE transaction_id = ?
    ");
    $stmt->execute([$status, $phone, $transaction_id]);
    
    // Mettre à jour le paiement principal
    $stmt = $pdo->prepare("
        UPDATE payments 
        SET payment_status = 'paid', paid_at = NOW(), payment_method = ?
        WHERE id = ?
    ");
    $stmt->execute([$gateway, $transaction['payment_id']]);
    
    // Envoyer un SMS de confirmation (simulé)
    $sms_content = "Paiement de {$amount} FCFA confirmé. Ref: {$transaction_id}. Merci!";
    
} else {
    // Paiement échoué
    $status = 'failed';
    $message = "Le paiement a échoué. Veuillez réessayer.";
    
    $stmt = $pdo->prepare("
        UPDATE online_payments 
        SET status = ?, error_message = 'Simulation: Paiement échoué'
        WHERE transaction_id = ?
    ");
    $stmt->execute([$status, $transaction_id]);
}

// Récupérer les infos pour le reçu
$stmt = $pdo->prepare("
    SELECT op.*, p.paid_at, f.fee_name, s.first_name, s.last_name, sch.school_name
    FROM online_payments op
    JOIN payments p ON op.payment_id = p.id
    JOIN school_fees f ON p.fee_id = f.id
    JOIN students s ON p.student_id = s.id
    JOIN schools sch ON s.school_id = sch.id
    WHERE op.transaction_id = ?
");
$stmt->execute([$transaction_id]);
$receipt_info = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Résultat du paiement - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .payment-result {
            max-width: 600px;
            margin: 50px auto;
            padding: 40px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .result-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            font-size: 2.5rem;
            color: white;
        }
        
        .result-success { background: #27ae60; }
        .result-failed { background: #e74c3c; }
        
        .receipt-details {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 25px;
            margin: 30px 0;
            text-align: left;
        }
        
        .receipt-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        
        .receipt-row:last-child {
            border-bottom: none;
        }
        
        .receipt-row.total {
            font-size: 1.2rem;
            font-weight: bold;
            color: #2c3e50;
            margin-top: 10px;
        }
        
        .actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
        }
        
        .countdown {
            color: #7f8c8d;
            margin-top: 20px;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="payment-result">
        <div class="result-icon <?php echo $success ? 'result-success' : 'result-failed'; ?>">
            <?php if ($success): ?>
                <i class="fas fa-check"></i>
            <?php else: ?>
                <i class="fas fa-times"></i>
            <?php endif; ?>
        </div>
        
        <h1><?php echo $success ? 'Paiement Réussi!' : 'Paiement Échoué'; ?></h1>
        <p><?php echo $message; ?></p>
        
        <?php if ($success && $receipt_info): ?>
        <div class="receipt-details">
            <h3>Reçu de paiement</h3>
            
            <div class="receipt-row">
                <span>Référence:</span>
                <span><strong><?php echo $transaction_id; ?></strong></span>
            </div>
            
            <div class="receipt-row">
                <span>Élève:</span>
                <span><?php echo $receipt_info['first_name'] . ' ' . $receipt_info['last_name']; ?></span>
            </div>
            
            <div class="receipt-row">
                <span>École:</span>
                <span><?php echo $receipt_info['school_name']; ?></span>
            </div>
            
            <div class="receipt-row">
                <span>Type de frais:</span>
                <span><?php echo $receipt_info['fee_name']; ?></span>
            </div>
            
            <div class="receipt-row">
                <span>Date:</span>
                <span><?php echo date('d/m/Y H:i', strtotime($receipt_info['paid_at'])); ?></span>
            </div>
            
            <div class="receipt-row">
                <span>Méthode:</span>
                <span>
                    <?php 
                    $gateway_names = [
                        'wave' => 'Wave',
                        'orange_money' => 'Orange Money',
                        'mtn_money' => 'MTN Money',
                        'bank_transfer' => 'Virement bancaire'
                    ];
                    echo $gateway_names[$gateway] ?? $gateway;
                    ?>
                </span>
            </div>
            
            <div class="receipt-row total">
                <span>Montant:</span>
                <span><?php echo number_format($amount, 0, ',', ' '); ?> FCFA</span>
            </div>
        </div>
        
        <p style="color: #27ae60; font-weight: 500;">
            <i class="fas fa-sms"></i> Un SMS de confirmation a été envoyé au <?php echo $phone; ?>
        </p>
        <?php endif; ?>
        
        <div class="actions">
            <?php if ($success): ?>
                <a href="receipt.php?txn_id=<?php echo $transaction_id; ?>" target="_blank" class="btn-primary">
                    <i class="fas fa-print"></i> Imprimer le reçu
                </a>
            <?php else: ?>
                <a href="index.php" class="btn-primary">
                    <i class="fas fa-redo"></i> Réessayer le paiement
                </a>
            <?php endif; ?>
            
            <a href="../dashboard/<?php echo $_SESSION['user_type']; ?>/index.php" class="btn-secondary">
                <i class="fas fa-home"></i> Retour au tableau de bord
            </a>
        </div>
        
        <div class="countdown" id="countdown">
            Redirection automatique dans <span id="seconds">10</span> secondes...
        </div>
    </div>
    
    <script>
        // Compte à rebours pour redirection
        let seconds = 10;
        const countdownElement = document.getElementById('seconds');
        
        const countdown = setInterval(() => {
            seconds--;
            countdownElement.textContent = seconds;
            
            if (seconds <= 0) {
                clearInterval(countdown);
                window.location.href = '../dashboard/<?php echo $_SESSION['user_type']; ?>/index.php';
            }
        }, 1000);
        
        // Générer le reçu PDF si succès
        <?php if ($success): ?>
        window.onload = function() {
            // Ouvrir le reçu PDF dans un nouvel onglet
            window.open('receipt.php?txn_id=<?php echo $transaction_id; ?>', '_blank');
        };
        <?php endif; ?>
    </script>
</body>
</html>