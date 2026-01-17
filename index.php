<?php
require_once '../includes/config.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];

// Récupérer les paiements selon le type d'utilisateur
if ($user_type == 'school') {
    requireUserType('school');
    
    $stmt = $pdo->prepare("SELECT s.* FROM schools s JOIN users u ON s.user_id = u.id WHERE u.id = ?");
    $stmt->execute([$user_id]);
    $school = $stmt->fetch();
    $school_id = $school['id'];
    
    // Récupérer les paiements de l'école
    $query = "
        SELECT p.*, s.first_name, s.last_name, s.matricule, f.fee_name, f.amount,
               op.status as online_status, op.transaction_id, op.gateway
        FROM payments p
        JOIN students s ON p.student_id = s.id
        JOIN school_fees f ON p.fee_id = f.id
        LEFT JOIN online_payments op ON p.id = op.payment_id
        WHERE s.school_id = ?
        ORDER BY p.due_date DESC
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$school_id]);
    
} elseif ($user_type == 'parent') {
    requireUserType('parent');
    
    $stmt = $pdo->prepare("SELECT p.* FROM parents p JOIN users u ON p.user_id = u.id WHERE u.id = ?");
    $stmt->execute([$user_id]);
    $parent = $stmt->fetch();
    $parent_id = $parent['id'];
    
    // Récupérer les paiements du parent (via ses enfants)
    $query = "
        SELECT p.*, s.first_name, s.last_name, s.matricule, f.fee_name, f.amount,
               sch.school_name, op.status as online_status, op.transaction_id, op.gateway
        FROM payments p
        JOIN students s ON p.student_id = s.id
        JOIN school_fees f ON p.fee_id = f.id
        JOIN schools sch ON s.school_id = sch.id
        LEFT JOIN online_payments op ON p.id = op.payment_id
        WHERE s.parent_id = ?
        ORDER BY p.due_date DESC
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$parent_id]);
    
} else {
    // Pour les enseignants, pas de paiements
    header('Location: ../dashboard/' . $user_type . '/index.php');
    exit();
}

$payments = $stmt->fetchAll();

// Traitement du paiement
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['initiate_payment'])) {
    $payment_id = $_POST['payment_id'];
    $gateway = $_POST['gateway'];
    
    // Générer un ID de transaction unique
    $transaction_id = 'TXN_' . strtoupper(uniqid());
    $amount = $_POST['amount'];
    $phone = $_POST['phone'];
    
    // Insérer la transaction
    $stmt = $pdo->prepare("
        INSERT INTO online_payments (payment_id, transaction_id, gateway, amount, currency, payer_phone, status)
        VALUES (?, ?, ?, ?, 'XOF', ?, 'pending')
    ");
    $stmt->execute([$payment_id, $transaction_id, $gateway, $amount, $phone]);
    
    // Simuler le paiement (dans la réalité, on appellerait l'API du processeur)
    $payment_url = "process_payment.php?txn_id=$transaction_id&gateway=$gateway&phone=$phone&amount=$amount";
    
    header("Location: $payment_url");
    exit();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiements en ligne - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .payment-gateways {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .gateway-card {
            background: white;
            border-radius: 10px;
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: transform 0.3s, box-shadow 0.3s;
            border: 2px solid transparent;
        }
        
        .gateway-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .gateway-card.selected {
            border-color: #3498db;
            background: #f8f9fa;
        }
        
        .gateway-icon {
            width: 70px;
            height: 70px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 2rem;
            color: white;
        }
        
        .gateway-wave { background: linear-gradient(135deg, #0652DD, #1B1464); }
        .gateway-orange { background: linear-gradient(135deg, #FF7900, #FF5500); }
        .gateway-mtn { background: linear-gradient(135deg, #FFCC00, #FF9900); }
        .gateway-bank { background: linear-gradient(135deg, #27ae60, #2ecc71); }
        
        .payment-form {
            background: white;
            padding: 30px;
            border-radius: 10px;
            max-width: 500px;
            margin: 0 auto;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .payment-summary {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #3498db;
        }
        
        .payment-details {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        
        .payment-details:last-child {
            border-bottom: none;
        }
        
        .payment-details.total {
            font-size: 1.2rem;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .payment-status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .status-pending { background: #f39c12; color: white; }
        .status-completed { background: #2ecc71; color: white; }
        .status-failed { background: #e74c3c; color: white; }
        .status-processing { background: #3498db; color: white; }
        
        .payment-history {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-top: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .payment-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr 1fr 1fr;
            align-items: center;
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
        }
        
        .payment-row.header {
            font-weight: bold;
            background: #f8f9fa;
            border-radius: 5px 5px 0 0;
        }
        
        .payment-row:last-child {
            border-bottom: none;
        }
    </style>
</head>
<body class="dashboard">
    <?php 
    if ($user_type == 'school') {
        include '../dashboard/school/sidebar.php';
    } else {
        include '../dashboard/parent/sidebar.php';
    }
    ?>
    
    <div class="main-content">
        <header class="top-bar">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Rechercher un paiement...">
            </div>
            <div class="user-info">
                <span><?php echo $_SESSION['email']; ?></span>
                <img src="../assets/images/default-avatar.png" alt="Avatar">
            </div>
        </header>
        
        <div class="content">
            <h1 class="page-title">
                <i class="fas fa-credit-card"></i> Paiements en ligne
            </h1>
            
            <?php if ($user_type == 'parent'): ?>
                <!-- Pour les parents : paiement des frais -->
                <div class="form-section">
                    <h2>Mes paiements en attente</h2>
                    
                    <?php
                    $pending_payments = array_filter($payments, function($p) {
                        return $p['payment_status'] == 'pending' && (!$p['online_status'] || $p['online_status'] == 'failed');
                    });
                    
                    if (empty($pending_payments)): ?>
                        <div class="payment-summary">
                            <p style="text-align: center; color: #27ae60;">
                                <i class="fas fa-check-circle fa-2x"></i><br>
                                Aucun paiement en attente.
                            </p>
                        </div>
                    <?php else: ?>
                        <div class="payment-form">
                            <div class="payment-summary">
                                <h3>Récapitulatif du paiement</h3>
                                <?php 
                                $total = 0;
                                $selected_payment = current($pending_payments);
                                ?>
                                <div class="payment-details">
                                    <span>Élève:</span>
                                    <span><?php echo $selected_payment['first_name'] . ' ' . $selected_payment['last_name']; ?></span>
                                </div>
                                <div class="payment-details">
                                    <span>École:</span>
                                    <span><?php echo $selected_payment['school_name']; ?></span>
                                </div>
                                <div class="payment-details">
                                    <span>Type de frais:</span>
                                    <span><?php echo $selected_payment['fee_name']; ?></span>
                                </div>
                                <div class="payment-details">
                                    <span>Date limite:</span>
                                    <span><?php echo date('d/m/Y', strtotime($selected_payment['due_date'])); ?></span>
                                </div>
                                <div class="payment-details total">
                                    <span>Montant à payer:</span>
                                    <span><?php echo number_format($selected_payment['amount'], 0, ',', ' '); ?> FCFA</span>
                                </div>
                            </div>
                            
                            <form method="POST" id="paymentForm">
                                <input type="hidden" name="payment_id" value="<?php echo $selected_payment['id']; ?>">
                                <input type="hidden" name="amount" value="<?php echo $selected_payment['amount']; ?>">
                                
                                <div class="form-group">
                                    <label>Choisissez votre méthode de paiement</label>
                                    <div class="payment-gateways">
                                        <div class="gateway-card" onclick="selectGateway('wave')">
                                            <div class="gateway-icon gateway-wave">
                                                <i class="fas fa-wave-square"></i>
                                            </div>
                                            <h4>Wave</h4>
                                            <p>Paiement mobile</p>
                                        </div>
                                        
                                        <div class="gateway-card" onclick="selectGateway('orange_money')">
                                            <div class="gateway-icon gateway-orange">
                                                <i class="fas fa-money-bill-wave"></i>
                                            </div>
                                            <h4>Orange Money</h4>
                                            <p>Paiement mobile</p>
                                        </div>
                                        
                                        <div class="gateway-card" onclick="selectGateway('mtn_money')">
                                            <div class="gateway-icon gateway-mtn">
                                                <i class="fas fa-mobile-alt"></i>
                                            </div>
                                            <h4>MTN Money</h4>
                                            <p>Paiement mobile</p>
                                        </div>
                                        
                                        <div class="gateway-card" onclick="selectGateway('bank_transfer')">
                                            <div class="gateway-icon gateway-bank">
                                                <i class="fas fa-university"></i>
                                            </div>
                                            <h4>Virement bancaire</h4>
                                            <p>Transfert bancaire</p>
                                        </div>
                                    </div>
                                    <input type="hidden" name="gateway" id="selectedGateway" required>
                                </div>
                                
                                <div class="form-group">
                                    <label>Numéro de téléphone *</label>
                                    <input type="tel" name="phone" placeholder="Ex: +221 77 123 4567" required>
                                </div>
                                
                                <div class="form-group">
                                    <label>Email pour reçu</label>
                                    <input type="email" name="email" placeholder="email@exemple.com">
                                </div>
                                
                                <button type="submit" name="initiate_payment" class="btn-primary" style="width: 100%; padding: 15px;">
                                    <i class="fas fa-lock"></i> Payer maintenant
                                </button>
                                
                                <p style="text-align: center; margin-top: 15px; color: #7f8c8d; font-size: 0.9rem;">
                                    <i class="fas fa-shield-alt"></i> Paiement sécurisé
                                </p>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <!-- Historique des paiements -->
            <div class="payment-history">
                <h3>Historique des paiements</h3>
                
                <div class="payment-row header">
                    <div>Description</div>
                    <div>Montant</div>
                    <div>Date</div>
                    <div>Méthode</div>
                    <div>Statut</div>
                    <div>Actions</div>
                </div>
                
                <?php if (empty($payments)): ?>
                    <div class="payment-row">
                        <div colspan="6" style="text-align: center; padding: 30px; color: #7f8c8d;">
                            Aucun paiement enregistré.
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach($payments as $payment): ?>
                        <div class="payment-row">
                            <div>
                                <strong>
                                    <?php if ($user_type == 'parent'): ?>
                                        <?php echo $payment['fee_name']; ?>
                                    <?php else: ?>
                                        <?php echo $payment['first_name'] . ' ' . $payment['last_name']; ?>
                                    <?php endif; ?>
                                </strong>
                                <?php if ($user_type == 'parent'): ?>
                                    <br>
                                    <small><?php echo $payment['school_name']; ?></small>
                                <?php else: ?>
                                    <br>
                                    <small><?php echo $payment['fee_name']; ?></small>
                                <?php endif; ?>
                            </div>
                            <div>
                                <?php echo number_format($payment['amount'], 0, ',', ' '); ?> FCFA
                            </div>
                            <div>
                                <?php echo date('d/m/Y', strtotime($payment['due_date'])); ?>
                            </div>
                            <div>
                                <?php if ($payment['gateway']): ?>
                                    <?php 
                                    $gateway_names = [
                                        'wave' => 'Wave',
                                        'orange_money' => 'Orange Money',
                                        'mtn_money' => 'MTN Money',
                                        'bank_transfer' => 'Virement'
                                    ];
                                    echo $gateway_names[$payment['gateway']] ?? $payment['gateway'];
                                    ?>
                                <?php else: ?>
                                    <span style="color: #7f8c8d;">-</span>
                                <?php endif; ?>
                            </div>
                            <div>
                                <?php if ($payment['online_status']): ?>
                                    <span class="payment-status status-<?php echo $payment['online_status']; ?>">
                                        <?php echo $payment['online_status']; ?>
                                    </span>
                                <?php else: ?>
                                    <span class="payment-status status-<?php echo $payment['payment_status']; ?>">
                                        <?php echo $payment['payment_status']; ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div>
                                <?php if ($payment['transaction_id']): ?>
                                    <a href="receipt.php?txn_id=<?php echo $payment['transaction_id']; ?>" 
                                       class="btn-small" target="_blank">
                                        <i class="fas fa-receipt"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        function selectGateway(gateway) {
            // Mettre à jour l'interface
            document.querySelectorAll('.gateway-card').forEach(card => {
                card.classList.remove('selected');
            });
            event.currentTarget.classList.add('selected');
            
            // Mettre à jour le champ caché
            document.getElementById('selectedGateway').value = gateway;
            
            // Ajuster le placeholder du téléphone selon le gateway
            const phoneInput = document.querySelector('input[name="phone"]');
            switch(gateway) {
                case 'wave':
                    phoneInput.placeholder = 'Ex: +221 77 123 4567';
                    break;
                case 'orange_money':
                    phoneInput.placeholder = 'Ex: +221 76 123 4567';
                    break;
                case 'mtn_money':
                    phoneInput.placeholder = 'Ex: +221 78 123 4567';
                    break;
            }
        }
        
        // Validation du formulaire
        document.getElementById('paymentForm').onsubmit = function(e) {
            const gateway = document.getElementById('selectedGateway').value;
            const phone = document.querySelector('input[name="phone"]').value;
            
            if (!gateway) {
                e.preventDefault();
                alert('Veuillez sélectionner une méthode de paiement.');
                return false;
            }
            
            if (!phone) {
                e.preventDefault();
                alert('Veuillez entrer votre numéro de téléphone.');
                return false;
            }
            
            // Validation du numéro congolais
            const phoneRegex = /^(\+221|221)?[0-9]{9}$/;
            if (!phoneRegex.test(phone.replace(/\s/g, ''))) {
                e.preventDefault();
                alert('Numéro de téléphone invalide. Format attendu: +221 77 123 4567');
                return false;
            }
            
            return true;
        };
    </script>
</body>
</html>