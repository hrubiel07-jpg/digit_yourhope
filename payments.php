<?php
require_once __DIR__ . '/../../includes/config.php';
require_once '../../../includes/school_config.php';
requireLogin();

if ($_SESSION['user_type'] !== 'school') {
    header('Location: ../');
    exit();
}

$user_id = $_SESSION['user_id'];

// Récupérer l'ID de l'école
$stmt = $pdo->prepare("SELECT id FROM schools WHERE user_id = ?");
$stmt->execute([$user_id]);
$school = $stmt->fetch();
$school_id = $school['id'];

$action = $_GET['action'] ?? 'list';
$payment_id = $_GET['id'] ?? 0;
$student_id = $_GET['student_id'] ?? 0;
$message = '';
$error = '';

// Récupérer la configuration de l'école pour la devise
$school_config = getSchoolConfig($school_id);
$currency_symbol = $school_config['currency_symbol'] ?? 'FCFA';

// Récupérer les étudiants de l'école
$students = [];
$stmt = $pdo->prepare("
    SELECT s.id, s.first_name, s.last_name, s.matricule, c.class_name 
    FROM students s 
    LEFT JOIN classes c ON s.current_class_id = c.id 
    WHERE s.school_id = ? AND s.status = 'active' 
    ORDER BY s.last_name, s.first_name
");
$stmt->execute([$school_id]);
$students = $stmt->fetchAll();

// Récupérer les types de frais
$fees = [];
$stmt = $pdo->prepare("
    SELECT * FROM school_fees 
    WHERE school_id = ? OR school_id IS NULL 
    ORDER BY fee_type, fee_name
");
$stmt->execute([$school_id]);
$fees = $stmt->fetchAll();

// Traitement du formulaire de paiement
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'student_id' => $_POST['student_id'],
        'fee_id' => $_POST['fee_id'],
        'amount' => floatval($_POST['amount']),
        'currency' => $school_config['currency'] ?? 'FCFA',
        'payment_date' => $_POST['payment_date'],
        'payment_method' => $_POST['payment_method'],
        'receipt_number' => sanitize($_POST['receipt_number']),
        'notes' => sanitize($_POST['notes'])
    ];
    
    // Validation
    if (empty($data['student_id']) || empty($data['fee_id']) || $data['amount'] <= 0) {
        $error = "L'étudiant, le type de frais et le montant sont obligatoires";
    } else {
        try {
            $pdo->beginTransaction();
            
            // Vérifier si le numéro de reçu est unique
            if (!empty($data['receipt_number'])) {
                $stmt = $pdo->prepare("SELECT id FROM payments WHERE receipt_number = ? AND student_id IN (SELECT id FROM students WHERE school_id = ?)");
                $stmt->execute([$data['receipt_number'], $school_id]);
                if ($stmt->rowCount() > 0) {
                    throw new Exception("Ce numéro de reçu existe déjà");
                }
            } else {
                // Générer un numéro de reçu automatique
                $year = date('Y');
                $stmt = $pdo->prepare("SELECT COUNT(*) + 1 as seq FROM payments WHERE YEAR(payment_date) = ? AND student_id IN (SELECT id FROM students WHERE school_id = ?)");
                $stmt->execute([$year, $school_id]);
                $result = $stmt->fetch();
                $data['receipt_number'] = 'REC-' . $year . '-' . str_pad($result['seq'], 6, '0', STR_PAD_LEFT);
            }
            
            // Ajouter l'utilisateur connecté comme receveur
            $data['received_by'] = $user_id;
            
            // Insérer le paiement
            $columns = implode(', ', array_keys($data));
            $placeholders = implode(', ', array_fill(0, count($data), '?'));
            
            $sql = "INSERT INTO payments ($columns) VALUES ($placeholders)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array_values($data));
            
            $payment_id = $pdo->lastInsertId();
            
            // Mettre à jour le solde dans les inscriptions
            $stmt = $pdo->prepare("
                UPDATE enrollments e 
                SET paid_amount = paid_amount + ?, 
                    balance = tuition_fee - (paid_amount + ?),
                    payment_status = CASE 
                        WHEN tuition_fee <= (paid_amount + ?) THEN 'paid'
                        WHEN (paid_amount + ?) > 0 THEN 'partial'
                        ELSE 'pending'
                    END
                WHERE e.student_id = ? 
                AND e.academic_year = (SELECT academic_year FROM school_fees WHERE id = ?)
            ");
            $stmt->execute([
                $data['amount'], 
                $data['amount'], 
                $data['amount'], 
                $data['amount'],
                $data['student_id'],
                $data['fee_id']
            ]);
            
            $pdo->commit();
            $message = "Paiement enregistré avec succès. Reçu: {$data['receipt_number']}";
            
            // Redirection après succès
            if ($action === 'add') {
                header("Location: payments.php?action=view&id=$payment_id&success=1");
                exit();
            }
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Erreur: " . $e->getMessage();
        }
    }
}

// Récupérer les données du paiement pour l'édition/visualisation
$payment = null;
if (($action === 'edit' || $action === 'view') && $payment_id > 0) {
    $stmt = $pdo->prepare("
        SELECT p.*, s.first_name, s.last_name, s.matricule, 
               c.class_name, f.fee_name, f.fee_type, f.amount as fee_amount,
               u.full_name as receiver_name
        FROM payments p 
        JOIN students s ON p.student_id = s.id 
        LEFT JOIN classes c ON s.current_class_id = c.id 
        JOIN school_fees f ON p.fee_id = f.id 
        LEFT JOIN users u ON p.received_by = u.id 
        WHERE p.id = ? AND s.school_id = ?
    ");
    $stmt->execute([$payment_id, $school_id]);
    $payment = $stmt->fetch();
    
    if (!$payment) {
        header('Location: payments.php');
        exit();
    }
}

// Récupérer la liste des paiements
if ($action === 'list') {
    $search = $_GET['search'] ?? '';
    $student_filter = $_GET['student'] ?? '';
    $fee_filter = $_GET['fee'] ?? '';
    $status_filter = $_GET['status'] ?? '';
    $date_from = $_GET['date_from'] ?? '';
    $date_to = $_GET['date_to'] ?? '';
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = 20;
    $offset = ($page - 1) * $limit;
    
    $sql = "SELECT p.*, s.first_name, s.last_name, s.matricule, 
                   c.class_name, f.fee_name, f.fee_type,
                   u.full_name as receiver_name 
            FROM payments p 
            JOIN students s ON p.student_id = s.id 
            LEFT JOIN classes c ON s.current_class_id = c.id 
            JOIN school_fees f ON p.fee_id = f.id 
            LEFT JOIN users u ON p.received_by = u.id 
            WHERE s.school_id = ?";
    $params = [$school_id];
    
    if (!empty($search)) {
        $sql .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR p.receipt_number LIKE ?)";
        $search_term = "%$search%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    if (!empty($student_filter)) {
        $sql .= " AND p.student_id = ?";
        $params[] = $student_filter;
    }
    
    if (!empty($fee_filter)) {
        $sql .= " AND p.fee_id = ?";
        $params[] = $fee_filter;
    }
    
    if (!empty($date_from)) {
        $sql .= " AND p.payment_date >= ?";
        $params[] = $date_from;
    }
    
    if (!empty($date_to)) {
        $sql .= " AND p.payment_date <= ?";
        $params[] = $date_to;
    }
    
    // Total pour la pagination
    $count_sql = str_replace("SELECT p.*, s.first_name, s.last_name, s.matricule, c.class_name, f.fee_name, f.fee_type, u.full_name as receiver_name", 
                            "SELECT COUNT(*) as total", $sql);
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total_payments = $stmt->fetchColumn();
    $total_pages = ceil($total_payments / $limit);
    
    // Données paginées
    $sql .= " ORDER BY p.payment_date DESC, p.created_at DESC LIMIT $limit OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $payments = $stmt->fetchAll();
    
    // Statistiques financières
    $stmt = $pdo->prepare("
        SELECT 
            SUM(p.amount) as total_paid,
            COUNT(p.id) as total_transactions,
            AVG(p.amount) as avg_payment,
            MIN(p.payment_date) as first_payment,
            MAX(p.payment_date) as last_payment
        FROM payments p 
        JOIN students s ON p.student_id = s.id 
        WHERE s.school_id = ? 
        AND p.payment_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ");
    $stmt->execute([$school_id]);
    $financial_stats = $stmt->fetch();
    
    // Soldes en attente
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT e.student_id) as students_with_balance,
            SUM(e.balance) as total_balance,
            SUM(CASE WHEN e.payment_status = 'pending' THEN 1 ELSE 0 END) as pending_count,
            SUM(CASE WHEN e.payment_status = 'partial' THEN 1 ELSE 0 END) as partial_count
        FROM enrollments e 
        JOIN students s ON e.student_id = s.id 
        WHERE s.school_id = ? 
        AND e.academic_year = YEAR(CURDATE()) || '-' || (YEAR(CURDATE()) + 1)
    ");
    $stmt->execute([$school_id]);
    $balance_stats = $stmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Paiements - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../../../assets/css/admin.css">
    <?php echo applySchoolTheme($school_id); ?>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .financial-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .summary-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            border: 1px solid #eee;
            text-align: center;
        }
        
        .summary-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            color: white;
            font-size: 1.2rem;
        }
        
        .summary-amount {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 5px;
        }
        
        .summary-label {
            color: #666;
            font-size: 0.9rem;
        }
        
        .summary-trend {
            font-size: 0.8rem;
            margin-top: 10px;
            padding: 3px 10px;
            border-radius: 15px;
            display: inline-block;
        }
        
        .trend-up { background: #d4edda; color: #155724; }
        .trend-down { background: #f8d7da; color: #721c24; }
        .trend-neutral { background: #e2e3e5; color: #383d41; }
        
        .payments-table {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
        
        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid #eee;
            background: #f8f9fa;
        }
        
        .table-body {
            padding: 0;
        }
        
        .payment-row {
            display: grid;
            grid-template-columns: auto 150px 120px 120px 120px 100px auto;
            gap: 15px;
            padding: 15px 20px;
            border-bottom: 1px solid #f5f5f5;
            align-items: center;
        }
        
        .payment-row:hover {
            background: #f8f9fa;
        }
        
        .payment-row.header {
            background: #f1f3f4;
            font-weight: 600;
            color: #666;
            border-bottom: 2px solid #ddd;
        }
        
        .student-info {
            display: flex;
            flex-direction: column;
        }
        
        .student-name {
            font-weight: 500;
            color: #333;
        }
        
        .student-details {
            font-size: 0.85rem;
            color: #666;
        }
        
        .receipt-number {
            font-family: monospace;
            background: #f8f9fa;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.9rem;
        }
        
        .amount {
            font-weight: 600;
            color: #27ae60;
        }
        
        .payment-method {
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            text-align: center;
        }
        
        .method-cash { background: #d4edda; color: #155724; }
        .method-check { background: #cce5ff; color: #004085; }
        .method-transfer { background: #d1ecf1; color: #0c5460; }
        .method-mobile { background: #fff3cd; color: #856404; }
        
        .payment-status {
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            text-align: center;
            font-weight: 500;
        }
        
        .status-completed { background: #d4edda; color: #155724; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        
        .payment-actions {
            display: flex;
            gap: 5px;
        }
        
        .btn-icon {
            width: 30px;
            height: 30px;
            border-radius: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            color: white;
            font-size: 0.9rem;
        }
        
        .btn-view { background: #3498db; }
        .btn-edit { background: #f39c12; }
        .btn-print { background: #2ecc71; }
        .btn-delete { background: #e74c3c; }
        
        .btn-icon:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }
        
        .no-payments {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        .date-range-filter {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }
        
        .date-input-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .date-input-group label {
            font-size: 0.9rem;
            color: #666;
        }
        
        .fee-amount-info {
            margin-top: 5px;
            color: #666;
            font-size: 0.9rem;
        }
        
        .receipt-preview {
            background: white;
            border: 2px solid #ddd;
            border-radius: 10px;
            padding: 30px;
            margin-top: 20px;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .receipt-header {
            text-align: center;
            border-bottom: 3px double #333;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        
        .receipt-school {
            font-size: 1.5rem;
            font-weight: bold;
            color: #333;
        }
        
        .receipt-title {
            font-size: 1.2rem;
            color: #666;
            margin: 10px 0;
        }
        
        .receipt-details {
            margin: 30px 0;
        }
        
        .receipt-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px dashed #ddd;
        }
        
        .receipt-label {
            font-weight: 600;
            color: #333;
        }
        
        .receipt-value {
            color: #666;
        }
        
        .receipt-total {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-top: 20px;
            text-align: right;
        }
        
        .total-amount {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary-color);
        }
        
        .receipt-footer {
            text-align: center;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            color: #666;
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
                <input type="text" placeholder="Rechercher paiement..." id="globalSearch">
            </div>
            <div class="user-info">
                <span>Gestion des Paiements</span>
                <img src="../../../assets/images/default-avatar.png" alt="Avatar">
            </div>
        </header>
        
        <div class="content">
            <div class="page-actions">
                <h1 class="page-title">
                    <i class="fas fa-money-bill-wave"></i>
                    <?php echo $action === 'list' ? 'Gestion des Paiements' : 
                           ($action === 'add' ? 'Nouveau Paiement' : 
                           ($action === 'view' ? 'Détails du Paiement' : 'Modifier Paiement')); ?>
                </h1>
                
                <div class="search-filters">
                    <?php if ($action === 'list'): ?>
                        <form method="GET" class="filter-form" style="display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap;">
                            <div class="date-range-filter">
                                <div class="date-input-group">
                                    <label>Du</label>
                                    <input type="date" name="date_from" 
                                           value="<?php echo htmlspecialchars($_GET['date_from'] ?? ''); ?>"
                                           style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 5px;">
                                </div>
                                <div class="date-input-group">
                                    <label>Au</label>
                                    <input type="date" name="date_to" 
                                           value="<?php echo htmlspecialchars($_GET['date_to'] ?? ''); ?>"
                                           style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 5px;">
                                </div>
                            </div>
                            
                            <div class="filter-group">
                                <select name="student">
                                    <option value="">Tous les étudiants</option>
                                    <?php foreach($students as $student): ?>
                                        <option value="<?php echo $student['id']; ?>" 
                                            <?php echo ($_GET['student'] ?? '') == $student['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name'] . ' (' . $student['matricule'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <select name="fee">
                                    <option value="">Tous les frais</option>
                                    <?php foreach($fees as $fee): ?>
                                        <option value="<?php echo $fee['id']; ?>" 
                                            <?php echo ($_GET['fee'] ?? '') == $fee['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($fee['fee_name'] . ' (' . number_format($fee['amount'], 0, ',', ' ') . ' ' . $currency_symbol . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <button type="submit" class="btn-primary">
                                <i class="fas fa-filter"></i> Filtrer
                            </button>
                            <a href="payments.php" class="btn-secondary">
                                <i class="fas fa-redo"></i> Réinitialiser
                            </a>
                        </form>
                    <?php endif; ?>
                    
                    <?php if ($action === 'list'): ?>
                        <a href="payments.php?action=add" class="btn-primary">
                            <i class="fas fa-plus-circle"></i> Nouveau Paiement
                        </a>
                    <?php elseif ($action === 'view'): ?>
                        <a href="payments.php?action=edit&id=<?php echo $payment_id; ?>" class="btn-primary">
                            <i class="fas fa-edit"></i> Modifier
                        </a>
                        <a href="print-receipt.php?id=<?php echo $payment_id; ?>" target="_blank" class="btn-secondary">
                            <i class="fas fa-print"></i> Imprimer
                        </a>
                        <a href="payments.php" class="btn-secondary">
                            <i class="fas fa-arrow-left"></i> Retour
                        </a>
                    <?php else: ?>
                        <a href="payments.php" class="btn-secondary">
                            <i class="fas fa-arrow-left"></i> Retour à la liste
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($message || $error): ?>
                <div class="alert <?php echo $error ? 'alert-error' : 'alert-success'; ?>">
                    <?php echo $error ? $error : $message; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($action === 'list'): ?>
                
                <!-- Résumé financier -->
                <div class="financial-summary">
                    <div class="summary-card">
                        <div class="summary-icon" style="background: #2ecc71;">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <div class="summary-amount">
                            <?php echo number_format($financial_stats['total_paid'] ?? 0, 0, ',', ' '); ?> <?php echo $currency_symbol; ?>
                        </div>
                        <div class="summary-label">Recettes 30 derniers jours</div>
                        <div class="summary-trend trend-up">
                            <i class="fas fa-arrow-up"></i> 12%
                        </div>
                    </div>
                    
                    <div class="summary-card">
                        <div class="summary-icon" style="background: #3498db;">
                            <i class="fas fa-exchange-alt"></i>
                        </div>
                        <div class="summary-amount">
                            <?php echo $financial_stats['total_transactions'] ?? 0; ?>
                        </div>
                        <div class="summary-label">Transactions</div>
                        <div class="summary-trend trend-neutral">
                            <i class="fas fa-minus"></i> 0%
                        </div>
                    </div>
                    
                    <div class="summary-card">
                        <div class="summary-icon" style="background: #e74c3c;">
                            <i class="fas fa-balance-scale"></i>
                        </div>
                        <div class="summary-amount">
                            <?php echo number_format($balance_stats['total_balance'] ?? 0, 0, ',', ' '); ?> <?php echo $currency_symbol; ?>
                        </div>
                        <div class="summary-label">Soldes impayés</div>
                        <div class="summary-trend trend-down">
                            <i class="fas fa-arrow-down"></i> 8%
                        </div>
                    </div>
                    
                    <div class="summary-card">
                        <div class="summary-icon" style="background: #f39c12;">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="summary-amount">
                            <?php echo $balance_stats['students_with_balance'] ?? 0; ?>
                        </div>
                        <div class="summary-label">Étudiants avec solde</div>
                        <div class="summary-trend trend-up">
                            <i class="fas fa-arrow-up"></i> 5%
                        </div>
                    </div>
                </div>
                
                <!-- Liste des paiements -->
                <div class="payments-table">
                    <div class="table-header">
                        <h3 style="margin: 0;">Derniers paiements</h3>
                        <span style="color: #666; font-size: 0.9rem;">
                            <?php echo $total_payments; ?> paiement(s) trouvé(s)
                        </span>
                    </div>
                    
                    <?php if ($payments && count($payments) > 0): ?>
                        <div class="table-body">
                            <!-- En-tête du tableau -->
                            <div class="payment-row header">
                                <div>Étudiant</div>
                                <div>Reçu</div>
                                <div>Type de frais</div>
                                <div>Montant</div>
                                <div>Méthode</div>
                                <div>Date</div>
                                <div>Actions</div>
                            </div>
                            
                            <!-- Lignes de données -->
                            <?php foreach($payments as $payment_item): ?>
                                <div class="payment-row">
                                    <div class="student-info">
                                        <span class="student-name">
                                            <?php echo htmlspecialchars($payment_item['first_name'] . ' ' . $payment_item['last_name']); ?>
                                        </span>
                                        <span class="student-details">
                                            <?php echo htmlspecialchars($payment_item['matricule']); ?> • 
                                            <?php echo htmlspecialchars($payment_item['class_name'] ?? 'Non affecté'); ?>
                                        </span>
                                    </div>
                                    
                                    <div>
                                        <span class="receipt-number"><?php echo htmlspecialchars($payment_item['receipt_number']); ?></span>
                                    </div>
                                    
                                    <div><?php echo htmlspecialchars($payment_item['fee_name']); ?></div>
                                    
                                    <div class="amount">
                                        <?php echo number_format($payment_item['amount'], 0, ',', ' '); ?> <?php echo $currency_symbol; ?>
                                    </div>
                                    
                                    <div>
                                        <?php 
                                        $method_class = 'method-' . str_replace(' ', '', strtolower($payment_item['payment_method']));
                                        ?>
                                        <span class="payment-method <?php echo $method_class; ?>">
                                            <?php echo $payment_item['payment_method']; ?>
                                        </span>
                                    </div>
                                    
                                    <div>
                                        <?php echo date('d/m/Y', strtotime($payment_item['payment_date'])); ?>
                                    </div>
                                    
                                    <div class="payment-actions">
                                        <a href="payments.php?action=view&id=<?php echo $payment_item['id']; ?>" 
                                           class="btn-icon btn-view" title="Voir">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="payments.php?action=edit&id=<?php echo $payment_item['id']; ?>" 
                                           class="btn-icon btn-edit" title="Modifier">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="print-receipt.php?id=<?php echo $payment_item['id']; ?>" 
                                           target="_blank" class="btn-icon btn-print" title="Imprimer">
                                            <i class="fas fa-print"></i>
                                        </a>
                                        <a href="delete.php?type=payment&id=<?php echo $payment_item['id']; ?>" 
                                           class="btn-icon btn-delete" title="Supprimer"
                                           onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce paiement ?');">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php if ($total_pages > 1): ?>
                            <div class="pagination" style="padding: 20px; border-top: 1px solid #eee;">
                                <?php if ($page > 1): ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" 
                                       class="page-link">
                                        <i class="fas fa-chevron-left"></i> Précédent
                                    </a>
                                <?php endif; ?>
                                
                                <?php
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);
                                
                                for ($i = $start_page; $i <= $end_page; $i++):
                                ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                                       class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" 
                                       class="page-link">
                                        Suivant <i class="fas fa-chevron-right"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                    <?php else: ?>
                        <div class="no-payments">
                            <i class="fas fa-money-bill-wave fa-3x" style="color: #ccc; margin-bottom: 20px;"></i>
                            <h3>Aucun paiement trouvé</h3>
                            <p>Commencez par enregistrer les paiements des étudiants</p>
                            <a href="payments.php?action=add" class="btn-primary" style="margin-top: 15px;">
                                <i class="fas fa-plus-circle"></i> Enregistrer un paiement
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
                
            <?php elseif ($action === 'view'): ?>
                <!-- Affichage du reçu -->
                <div class="form-section">
                    <div class="receipt-preview" id="receiptPreview">
                        <div class="receipt-header">
                            <div class="receipt-school"><?php echo htmlspecialchars($school['school_name']); ?></div>
                            <div class="receipt-title">REÇU DE PAIEMENT</div>
                            <div style="color: #666; font-size: 0.9rem;">
                                <?php echo $payment['receipt_number']; ?>
                            </div>
                        </div>
                        
                        <div class="receipt-details">
                            <div class="receipt-row">
                                <span class="receipt-label">Date:</span>
                                <span class="receipt-value">
                                    <?php echo date('d/m/Y', strtotime($payment['payment_date'])); ?>
                                </span>
                            </div>
                            
                            <div class="receipt-row">
                                <span class="receipt-label">Étudiant:</span>
                                <span class="receipt-value">
                                    <?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?>
                                    (<?php echo htmlspecialchars($payment['matricule']); ?>)
                                </span>
                            </div>
                            
                            <div class="receipt-row">
                                <span class="receipt-label">Classe:</span>
                                <span class="receipt-value">
                                    <?php echo htmlspecialchars($payment['class_name'] ?? 'Non affecté'); ?>
                                </span>
                            </div>
                            
                            <div class="receipt-row">
                                <span class="receipt-label">Type de frais:</span>
                                <span class="receipt-value">
                                    <?php echo htmlspecialchars($payment['fee_name']); ?> 
                                    (<?php echo $payment['fee_type']; ?>)
                                </span>
                            </div>
                            
                            <div class="receipt-row">
                                <span class="receipt-label">Montant dû:</span>
                                <span class="receipt-value">
                                    <?php echo number_format($payment['fee_amount'], 0, ',', ' '); ?> <?php echo $currency_symbol; ?>
                                </span>
                            </div>
                            
                            <div class="receipt-row">
                                <span class="receipt-label">Méthode de paiement:</span>
                                <span class="receipt-value">
                                    <?php echo htmlspecialchars($payment['payment_method']); ?>
                                </span>
                            </div>
                            
                            <?php if ($payment['notes']): ?>
                                <div class="receipt-row">
                                    <span class="receipt-label">Notes:</span>
                                    <span class="receipt-value"><?php echo htmlspecialchars($payment['notes']); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="receipt-total">
                            <div style="margin-bottom: 10px; color: #666;">Montant payé:</div>
                            <div class="total-amount">
                                <?php echo number_format($payment['amount'], 0, ',', ' '); ?> <?php echo $currency_symbol; ?>
                            </div>
                        </div>
                        
                        <div class="receipt-footer">
                            <div style="margin-bottom: 10px;">
                                Reçu émis par: <?php echo htmlspecialchars($payment['receiver_name']); ?>
                            </div>
                            <div>
                                Date d'émission: <?php echo date('d/m/Y H:i', strtotime($payment['created_at'])); ?>
                            </div>
                            <div style="margin-top: 10px; font-style: italic;">
                                Ce reçu est valide comme justificatif de paiement
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions" style="margin-top: 30px;">
                        <button onclick="window.print()" class="btn-primary">
                            <i class="fas fa-print"></i> Imprimer le reçu
                        </button>
                        <a href="payments.php" class="btn-secondary">
                            <i class="fas fa-arrow-left"></i> Retour à la liste
                        </a>
                    </div>
                </div>
                
            <?php else: ?>
                <!-- Formulaire d'ajout/modification -->
                <div class="form-section">
                    <form method="POST" id="paymentForm">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="student_id">Étudiant *</label>
                                <select id="student_id" name="student_id" required class="form-control">
                                    <option value="">Sélectionner un étudiant</option>
                                    <?php foreach($students as $student): ?>
                                        <option value="<?php echo $student['id']; ?>" 
                                            <?php echo ($payment['student_id'] ?? ($student_id ?: '')) == $student['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name'] . ' - ' . $student['matricule'] . ' (' . ($student['class_name'] ?? 'Non affecté') . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="fee_id">Type de frais *</label>
                                <select id="fee_id" name="fee_id" required class="form-control">
                                    <option value="">Sélectionner le type de frais</option>
                                    <?php foreach($fees as $fee): ?>
                                        <option value="<?php echo $fee['id']; ?>" 
                                            data-amount="<?php echo $fee['amount']; ?>"
                                            <?php echo ($payment['fee_id'] ?? '') == $fee['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($fee['fee_name'] . ' - ' . number_format($fee['amount'], 0, ',', ' ') . ' ' . $currency_symbol . ' (' . $fee['fee_type'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div id="feeAmountInfo" class="fee-amount-info"></div>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="amount">Montant * (<?php echo $currency_symbol; ?>)</label>
                                <input type="number" id="amount" name="amount" 
                                       value="<?php echo htmlspecialchars($payment['amount'] ?? ''); ?>" 
                                       required class="form-control" step="0.01" min="0">
                            </div>
                            
                            <div class="form-group">
                                <label for="payment_date">Date de paiement *</label>
                                <input type="date" id="payment_date" name="payment_date" 
                                       value="<?php echo htmlspecialchars($payment['payment_date'] ?? date('Y-m-d')); ?>" 
                                       required class="form-control">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="payment_method">Méthode de paiement *</label>
                                <select id="payment_method" name="payment_method" required class="form-control">
                                    <option value="">Sélectionner une méthode</option>
                                    <option value="Espèces" <?php echo ($payment['payment_method'] ?? '') == 'Espèces' ? 'selected' : ''; ?>>Espèces</option>
                                    <option value="Chèque" <?php echo ($payment['payment_method'] ?? '') == 'Chèque' ? 'selected' : ''; ?>>Chèque</option>
                                    <option value="Virement" <?php echo ($payment['payment_method'] ?? '') == 'Virement' ? 'selected' : ''; ?>>Virement bancaire</option>
                                    <option value="Mobile Money" <?php echo ($payment['payment_method'] ?? '') == 'Mobile Money' ? 'selected' : ''; ?>>Mobile Money</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="receipt_number">Numéro de reçu</label>
                                <input type="text" id="receipt_number" name="receipt_number" 
                                       value="<?php echo htmlspecialchars($payment['receipt_number'] ?? ''); ?>" 
                                       class="form-control" 
                                       placeholder="Laisser vide pour générer automatiquement">
                                <small style="color: #666; display: block; margin-top: 5px;">
                                    Format recommandé: REC-AAAA-NNNNNN
                                </small>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="notes">Notes</label>
                            <textarea id="notes" name="notes" rows="3" class="form-control"><?php echo htmlspecialchars($payment['notes'] ?? ''); ?></textarea>
                            <small style="color: #666; display: block; margin-top: 5px;">
                                Informations complémentaires sur ce paiement
                            </small>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn-primary">
                                <i class="fas fa-save"></i> 
                                <?php echo $action === 'add' ? 'Enregistrer le paiement' : 'Mettre à jour'; ?>
                            </button>
                            <a href="payments.php" class="btn-secondary">
                                <i class="fas fa-times"></i> Annuler
                            </a>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="../../../assets/js/dashboard.js"></script>
    <script>
        // Afficher le montant du frais sélectionné
        const feeSelect = document.getElementById('fee_id');
        const amountInput = document.getElementById('amount');
        const feeAmountInfo = document.getElementById('feeAmountInfo');
        
        function updateFeeAmount() {
            const selectedOption = feeSelect.options[feeSelect.selectedIndex];
            const feeAmount = selectedOption.getAttribute('data-amount');
            
            if (feeAmount && !amountInput.value) {
                amountInput.value = feeAmount;
            }
            
            if (feeAmount) {
                feeAmountInfo.textContent = `Montant standard: ${parseFloat(feeAmount).toLocaleString('fr-FR')} <?php echo $currency_symbol; ?>`;
                feeAmountInfo.style.color = '#666';
            } else {
                feeAmountInfo.textContent = '';
            }
        }
        
        feeSelect.addEventListener('change', updateFeeAmount);
        
        // Initialiser si un frais est déjà sélectionné
        if (feeSelect.value) {
            updateFeeAmount();
        }
        
        // Validation du formulaire
        document.getElementById('paymentForm').addEventListener('submit', function(e) {
            const studentId = document.getElementById('student_id').value;
            const feeId = document.getElementById('fee_id').value;
            const amount = document.getElementById('amount').value;
            const paymentMethod = document.getElementById('payment_method').value;
            const paymentDate = document.getElementById('payment_date').value;
            
            if (!studentId) {
                e.preventDefault();
                alert('Veuillez sélectionner un étudiant');
                return false;
            }
            
            if (!feeId) {
                e.preventDefault();
                alert('Veuillez sélectionner le type de frais');
                return false;
            }
            
            if (!amount || parseFloat(amount) <= 0) {
                e.preventDefault();
                alert('Veuillez saisir un montant valide');
                return false;
            }
            
            if (!paymentMethod) {
                e.preventDefault();
                alert('Veuillez sélectionner la méthode de paiement');
                return false;
            }
            
            if (!paymentDate) {
                e.preventDefault();
                alert('Veuillez sélectionner la date de paiement');
                return false;
            }
            
            return true;
        });
        
        // Générer le numéro de reçu automatiquement
        function generateReceiptNumber() {
            const receiptInput = document.getElementById('receipt_number');
            if (!receiptInput.value) {
                const year = new Date().getFullYear();
                const random = Math.floor(Math.random() * 900000) + 100000;
                receiptInput.value = `REC-${year}-${random}`;
            }
        }
        
        // Générer au focus si vide
        document.getElementById('receipt_number').addEventListener('focus', function() {
            if (!this.value) {
                generateReceiptNumber();
            }
        });
        
        // Impression du reçu
        if (window.location.search.includes('action=view')) {
            window.addEventListener('load', function() {
                // Ajouter un bouton d'impression
                const printBtn = document.createElement('button');
                printBtn.innerHTML = '<i class="fas fa-print"></i> Imprimer le reçu';
                printBtn.className = 'btn-primary';
                printBtn.style.marginRight = '10px';
                printBtn.onclick = function() {
                    window.print();
                };
                
                const actions = document.querySelector('.form-actions');
                if (actions) {
                    actions.prepend(printBtn);
                }
            });
        }
    </script>
</body>
</html>