<?php
// dashboard/school/settings/pricing.php
require_once '../../../includes/config.php';
requireLogin();

if ($_SESSION['user_type'] !== 'school') {
    header('Location: ../../');
    exit();
}

$school_id = $_SESSION['school_id'];
$theme = getSchoolTheme($school_id);

// Récupérer la configuration de prix existante
$pricing = $pdo->prepare("SELECT * FROM school_pricing WHERE school_id = ? ORDER BY level, class_name")
               ->execute([$school_id])->fetchAll();

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $level = sanitize($_POST['level']);
    $class_name = sanitize($_POST['class_name']);
    
    // Vérifier si ce niveau/classe existe déjà
    $existing = $pdo->prepare("SELECT id FROM school_pricing WHERE school_id = ? AND level = ? AND class_name = ?")
                   ->execute([$school_id, $level, $class_name])->fetch();
    
    if ($existing) {
        // Mise à jour
        $stmt = $pdo->prepare("
            UPDATE school_pricing SET
                registration_fee = ?,
                tuition_fee = ?,
                uniform_fee = ?,
                books_fee = ?,
                transportation_fee = ?,
                cafeteria_fee = ?,
                activities_fee = ?,
                discount_sibling = ?,
                discount_early_payment = ?,
                discount_scholarship = ?,
                payment_plan = ?,
                installments_count = ?,
                notes = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $_POST['registration_fee'], $_POST['tuition_fee'],
            $_POST['uniform_fee'], $_POST['books_fee'],
            $_POST['transportation_fee'], $_POST['cafeteria_fee'],
            $_POST['activities_fee'], $_POST['discount_sibling'],
            $_POST['discount_early_payment'], $_POST['discount_scholarship'],
            $_POST['payment_plan'], $_POST['installments_count'],
            $_POST['notes'], $existing['id']
        ]);
    } else {
        // Insertion
        $stmt = $pdo->prepare("
            INSERT INTO school_pricing (
                school_id, level, class_name, registration_fee, tuition_fee,
                uniform_fee, books_fee, transportation_fee, cafeteria_fee,
                activities_fee, discount_sibling, discount_early_payment,
                discount_scholarship, payment_plan, installments_count, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $school_id, $level, $class_name,
            $_POST['registration_fee'], $_POST['tuition_fee'],
            $_POST['uniform_fee'], $_POST['books_fee'],
            $_POST['transportation_fee'], $_POST['cafeteria_fee'],
            $_POST['activities_fee'], $_POST['discount_sibling'],
            $_POST['discount_early_payment'], $_POST['discount_scholarship'],
            $_POST['payment_plan'], $_POST['installments_count'],
            $_POST['notes']
        ]);
    }
    
    header('Location: pricing.php?success=1');
    exit();
}

// Inclure la sidebar
include '../school-sidebar.php';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Prix - <?php echo $school['school_name']; ?></title>
    <link rel="stylesheet" href="../../../assets/css/dashboard.css">
    <link rel="stylesheet" href="<?php echo generateSchoolCSS($school_id); ?>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .pricing-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            border-bottom: 2px solid #eaeaea;
        }
        
        .pricing-tab {
            padding: 15px 30px;
            background: none;
            border: none;
            border-bottom: 3px solid transparent;
            cursor: pointer;
            font-weight: 500;
            color: #666;
            transition: all 0.3s;
        }
        
        .pricing-tab:hover {
            color: var(--primary-color);
        }
        
        .pricing-tab.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
        }
        
        .pricing-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
        }
        
        .pricing-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            border: 1px solid #eaeaea;
            transition: transform 0.3s;
        }
        
        .pricing-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .pricing-header {
            text-align: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--primary-color);
        }
        
        .pricing-icon {
            width: 60px;
            height: 60px;
            background: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            color: white;
            font-size: 1.5rem;
        }
        
        .price-amount {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin: 10px 0;
        }
        
        .price-period {
            color: #666;
            font-size: 0.9rem;
        }
        
        .price-details {
            list-style: none;
            padding: 0;
            margin: 20px 0;
        }
        
        .price-details li {
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
        }
        
        .price-details li:last-child {
            border-bottom: none;
        }
        
        .fee-label {
            color: #666;
        }
        
        .fee-amount {
            font-weight: 600;
            color: #333;
        }
        
        .discount-badge {
            background: var(--accent-color);
            color: #333;
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
            margin-left: 10px;
        }
        
        .total-fees {
            background: var(--primary-color);
            color: white;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
            text-align: center;
        }
        
        .total-fees h4 {
            margin: 0 0 5px;
            font-size: 1.1rem;
        }
        
        .total-amount {
            font-size: 1.8rem;
            font-weight: 700;
        }
    </style>
</head>
<body class="dashboard">
    <?php include '../school-sidebar.php'; ?>
    
    <div class="main-content">
        <header class="top-bar">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Rechercher...">
            </div>
            <div class="user-info">
                <span><?php echo $_SESSION['email']; ?></span>
                <img src="../../../assets/images/default-avatar.png" alt="Avatar">
            </div>
        </header>
        
        <div class="content">
            <div class="page-header">
                <h1 class="page-title">
                    <i class="fas fa-money-bill-wave"></i> Gestion des Prix
                </h1>
                <p>Configurez les frais de scolarité pour chaque niveau</p>
            </div>
            
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> Prix mis à jour avec succès !
                </div>
            <?php endif; ?>
            
            <div class="pricing-tabs">
                <button class="pricing-tab active" onclick="showLevel('primaire')">
                    <i class="fas fa-child"></i> Primaire
                </button>
                <button class="pricing-tab" onclick="showLevel('college')">
                    <i class="fas fa-book"></i> Collège
                </button>
                <button class="pricing-tab" onclick="showLevel('lycee')">
                    <i class="fas fa-graduation-cap"></i> Lycée
                </button>
                <button class="pricing-tab" onclick="showLevel('universitaire')">
                    <i class="fas fa-university"></i> Universitaire
                </button>
            </div>
            
            <!-- Primaire -->
            <div id="primaire" class="pricing-level active">
                <div class="level-header">
                    <h3><i class="fas fa-child"></i> Frais de Scolarité - Primaire</h3>
                    <p>CP1 au CM2 - Système de l'enseignement de base</p>
                </div>
                
                <div class="pricing-grid">
                    <!-- CP1 -->
                    <div class="pricing-card">
                        <div class="pricing-header">
                            <div class="pricing-icon">
                                <i class="fas fa-user-graduate"></i>
                            </div>
                            <h4>CP1</h4>
                            <p>Cours Préparatoire 1ère année</p>
                        </div>
                        
                        <ul class="price-details">
                            <li>
                                <span class="fee-label">Frais d'inscription</span>
                                <span class="fee-amount"><?php echo formatCurrency(50000, $school_id); ?></span>
                            </li>
                            <li>
                                <span class="fee-label">Frais de scolarité</span>
                                <span class="fee-amount"><?php echo formatCurrency(150000, $school_id); ?></span>
                                <span class="discount-badge">Par trimestre</span>
                            </li>
                            <li>
                                <span class="fee-label">Uniforme</span>
                                <span class="fee-amount"><?php echo formatCurrency(25000, $school_id); ?></span>
                            </li>
                            <li>
                                <span class="fee-label">Fournitures scolaires</span>
                                <span class="fee-amount"><?php echo formatCurrency(35000, $school_id); ?></span>
                            </li>
                        </ul>
                        
                        <div class="total-fees">
                            <h4>TOTAL ANNUEL</h4>
                            <div class="total-amount"><?php echo formatCurrency(535000, $school_id); ?></div>
                            <small>Ou 3 versements de <?php echo formatCurrency(178333, $school_id); ?></small>
                        </div>
                        
                        <div class="text-center mt-3">
                            <button class="btn btn-outline" onclick="editPricing('primaire', 'CP1')">
                                <i class="fas fa-edit"></i> Modifier
                            </button>
                        </div>
                    </div>
                    
                    <!-- CM2 -->
                    <div class="pricing-card">
                        <div class="pricing-header">
                            <div class="pricing-icon">
                                <i class="fas fa-graduation-cap"></i>
                            </div>
                            <h4>CM2</h4>
                            <p>Cours Moyen 2ème année</p>
                        </div>
                        
                        <ul class="price-details">
                            <li>
                                <span class="fee-label">Frais d'inscription</span>
                                <span class="fee-amount"><?php echo formatCurrency(50000, $school_id); ?></span>
                            </li>
                            <li>
                                <span class="fee-label">Frais de scolarité</span>
                                <span class="fee-amount"><?php echo formatCurrency(180000, $school_id); ?></span>
                                <span class="discount-badge">Par trimestre</span>
                            </li>
                            <li>
                                <span class="fee-label">Uniforme</span>
                                <span class="fee-amount"><?php echo formatCurrency(25000, $school_id); ?></span>
                            </li>
                            <li>
                                <span class="fee-label">Fournitures scolaires</span>
                                <span class="fee-amount"><?php echo formatCurrency(45000, $school_id); ?></span>
                            </li>
                            <li>
                                <span class="fee-label">Préparation CEPE</span>
                                <span class="fee-amount"><?php echo formatCurrency(30000, $school_id); ?></span>
                            </li>
                        </ul>
                        
                        <div class="total-fees">
                            <h4>TOTAL ANNUEL</h4>
                            <div class="total-amount"><?php echo formatCurrency(660000, $school_id); ?></div>
                            <small>Ou 3 versements de <?php echo formatCurrency(220000, $school_id); ?></small>
                        </div>
                        
                        <div class="text-center mt-3">
                            <button class="btn btn-outline" onclick="editPricing('primaire', 'CM2')">
                                <i class="fas fa-edit"></i> Modifier
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Bouton ajouter prix -->
            <div class="text-center mt-4">
                <button class="btn btn-primary" onclick="addNewPricing()">
                    <i class="fas fa-plus"></i> Ajouter un niveau de prix
                </button>
            </div>
        </div>
    </div>
    
    <!-- Modal pour éditer/ajouter prix -->
    <div class="modal" id="pricingModal">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h3 id="modalTitle">Modifier les prix</h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="pricingForm" method="POST">
                    <input type="hidden" id="pricingId" name="pricing_id">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Niveau scolaire</label>
                            <select id="level" name="level" class="form-control" required>
                                <option value="primaire">Primaire</option>
                                <option value="college">Collège</option>
                                <option value="lycee">Lycée</option>
                                <option value="universitaire">Universitaire</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Classe/Niveau</label>
                            <input type="text" id="class_name" name="class_name" 
                                   class="form-control" placeholder="Ex: CP1, 6ème, Terminale" required>
                        </div>
                    </div>
                    
                    <h4>Frais principaux</h4>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Frais d'inscription</label>
                            <div class="input-group">
                                <input type="number" name="registration_fee" class="form-control" 
                                       placeholder="0" min="0" step="1000">
                                <span class="input-group-text"><?php echo $theme['currency_symbol']; ?></span>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Frais de scolarité (trimestre)</label>
                            <div class="input-group">
                                <input type="number" name="tuition_fee" class="form-control" 
                                       placeholder="0" min="0" step="1000" required>
                                <span class="input-group-text"><?php echo $theme['currency_symbol']; ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <h4>Frais supplémentaires</h4>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Uniforme</label>
                            <div class="input-group">
                                <input type="number" name="uniform_fee" class="form-control" 
                                       placeholder="0" min="0" step="1000">
                                <span class="input-group-text"><?php echo $theme['currency_symbol']; ?></span>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Livres et fournitures</label>
                            <div class="input-group">
                                <input type="number" name="books_fee" class="form-control" 
                                       placeholder="0" min="0" step="1000">
                                <span class="input-group-text"><?php echo $theme['currency_symbol']; ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Transport</label>
                            <div class="input-group">
                                <input type="number" name="transportation_fee" class="form-control" 
                                       placeholder="0" min="0" step="1000">
                                <span class="input-group-text"><?php echo $theme['currency_symbol']; ?></span>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Cantine</label>
                            <div class="input-group">
                                <input type="number" name="cafeteria_fee" class="form-control" 
                                       placeholder="0" min="0" step="1000">
                                <span class="input-group-text"><?php echo $theme['currency_symbol']; ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <h4>Réductions</h4>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Réduction fratrie (%)</label>
                            <input type="number" name="discount_sibling" class="form-control" 
                                   placeholder="0" min="0" max="100" step="5">
                        </div>
                        
                        <div class="form-group">
                            <label>Réduction paiement anticipé (%)</label>
                            <input type="number" name="discount_early_payment" class="form-control" 
                                   placeholder="0" min="0" max="100" step="5">
                        </div>
                        
                        <div class="form-group">
                            <label>Réduction bourse (%)</label>
                            <input type="number" name="discount_scholarship" class="form-control" 
                                   placeholder="0" min="0" max="100" step="5">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Plan de paiement</label>
                        <select name="payment_plan" class="form-control">
                            <option value="annuel">Paiement annuel</option>
                            <option value="trimestriel">Paiement trimestriel</option>
                            <option value="mensuel">Paiement mensuel</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Notes additionnelles</label>
                        <textarea name="notes" class="form-control" rows="3" 
                                  placeholder="Informations supplémentaires sur les frais..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Annuler</button>
                <button type="submit" form="pricingForm" class="btn btn-primary">
                    <i class="fas fa-save"></i> Enregistrer
                </button>
            </div>
        </div>
    </div>
    
    <script>
    function showLevel(level) {
        // Masquer tous les niveaux
        document.querySelectorAll('.pricing-level').forEach(el => {
            el.classList.remove('active');
        });
        
        // Montrer le niveau sélectionné
        document.getElementById(level).classList.add('active');
        
        // Mettre à jour les onglets
        document.querySelectorAll('.pricing-tab').forEach(tab => {
            tab.classList.remove('active');
        });
        event.currentTarget.classList.add('active');
    }
    
    function editPricing(level, className) {
        document.getElementById('modalTitle').textContent = `Modifier ${className} - ${level}`;
        document.getElementById('level').value = level;
        document.getElementById('class_name').value = className;
        document.getElementById('pricingModal').style.display = 'block';
        
        // Charger les données existantes (à implémenter avec AJAX)
        // fetchPricingData(level, className);
    }
    
    function addNewPricing() {
        document.getElementById('modalTitle').textContent = 'Ajouter un niveau de prix';
        document.getElementById('level').value = 'primaire';
        document.getElementById('class_name').value = '';
        document.getElementById('pricingForm').reset();
        document.getElementById('pricingModal').style.display = 'block';
    }
    
    function closeModal() {
        document.getElementById('pricingModal').style.display = 'none';
    }
    
    // Fermer la modal en cliquant en dehors
    window.onclick = function(event) {
        const modal = document.getElementById('pricingModal');
        if (event.target === modal) {
            closeModal();
        }
    }
    </script>
</body>
</html>