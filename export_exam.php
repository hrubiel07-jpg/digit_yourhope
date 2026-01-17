<?php
require_once __DIR__ . '/../../includes/config.php';
requireUserType('school');

$exam_id = $_GET['id'] ?? 0;
$format = $_GET['format'] ?? 'excel';
$user_id = $_SESSION['user_id'];

// Récupérer les infos de l'examen
$stmt = $pdo->prepare("
    SELECT e.*, s.school_name, s.address, s.city, s.phone, s.email, s.logo
    FROM exam_registrations e
    JOIN schools s ON e.school_id = s.id
    WHERE e.id = ? AND s.user_id = ?
");
$stmt->execute([$exam_id, $user_id]);
$exam = $stmt->fetch();

if (!$exam) {
    die("Examen non trouvé ou accès non autorisé.");
}

// Récupérer les candidats
$stmt = $pdo->prepare("
    SELECT ec.*, s.first_name, s.last_name, s.matricule, s.birthdate, s.gender,
           s.parent_name, s.parent_phone, s.parent_email,
           c.class_name
    FROM exam_candidates ec
    JOIN students s ON ec.student_id = s.id
    LEFT JOIN classes c ON s.current_class_id = c.id
    WHERE ec.exam_registration_id = ?
    ORDER BY s.last_name, s.first_name
");
$stmt->execute([$exam_id]);
$candidates = $stmt->fetchAll();

// Noms des examens
$exam_names = [
    'CEPE' => 'Certificat d\'Études Primaires Élémentaires',
    'BEPC' => 'Brevet d\'Études du Premier Cycle',
    'BAC' => 'Baccalauréat',
    'BTS' => 'Brevet de Technicien Supérieur',
    'CAP' => 'Certificat d\'Aptitude Professionnelle'
];

if ($format == 'excel') {
    // Générer un fichier Excel
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="liste_candidats_' . $exam['exam_type'] . '_' . $exam['academic_year'] . '.xls"');
    
    echo '<table border="1">';
    echo '<tr><td colspan="9" style="text-align: center; font-size: 16px; font-weight: bold; background: #f2f2f2; padding: 10px;">';
    echo strtoupper($exam['school_name']) . '<br>';
    echo 'LISTE DES CANDIDATS - ' . $exam_names[$exam['exam_type']] . '<br>';
    echo 'Année scolaire ' . $exam['academic_year'];
    echo '</td></tr>';
    
    echo '<tr><td colspan="9" style="padding: 5px;">';
    echo 'Centre d\'examen: ' . ($exam['center_name'] ?: 'Non spécifié') . '<br>';
    echo 'Date d\'examen: ' . ($exam['exam_date'] ? date('d/m/Y', strtotime($exam['exam_date'])) : 'À définir');
    echo '</td></tr>';
    
    // En-tête du tableau
    echo '<tr style="background: #3498db; color: white; font-weight: bold;">';
    echo '<th width="30">N°</th>';
    echo '<th width="100">Matricule</th>';
    echo '<th width="150">Nom</th>';
    echo '<th width="150">Prénom</th>';
    echo '<th width="80">Sexe</th>';
    echo '<th width="100">Date naissance</th>';
    echo '<th width="100">Classe</th>';
    echo '<th width="120">N° Inscription</th>';
    echo '<th width="80">Statut</th>';
    echo '</tr>';
    
    // Données
    $i = 1;
    foreach ($candidates as $candidate) {
        echo '<tr>';
        echo '<td align="center">' . $i++ . '</td>';
        echo '<td>' . $candidate['matricule'] . '</td>';
        echo '<td>' . $candidate['last_name'] . '</td>';
        echo '<td>' . $candidate['first_name'] . '</td>';
        echo '<td align="center">' . $candidate['gender'] . '</td>';
        echo '<td align="center">' . date('d/m/Y', strtotime($candidate['birthdate'])) . '</td>';
        echo '<td>' . $candidate['class_name'] . '</td>';
        echo '<td align="center">' . ($candidate['registration_number'] ?: '-') . '</td>';
        echo '<td align="center">' . strtoupper($candidate['status']) . '</td>';
        echo '</tr>';
    }
    
    echo '<tr style="font-weight: bold; background: #f2f2f2;">';
    echo '<td colspan="8" align="right">Total candidats:</td>';
    echo '<td align="center">' . count($candidates) . '</td>';
    echo '</tr>';
    
    echo '</table>';
    
} elseif ($format == 'pdf') {
    // Générer un PDF
    require_once '../../../includes/tcpdf/tcpdf.php';
    
    class EXAM_LIST_PDF extends TCPDF {
        public function Header() {
            global $exam;
            
            // Logo
            $logo = '../../../uploads/' . $exam['logo'];
            if (file_exists($logo)) {
                $this->Image($logo, 15, 10, 25, 25, '', '', 'T', false, 300, '', false, false, 0, false, false, false);
            }
            
            // Titre
            $this->SetFont('helvetica', 'B', 14);
            $this->SetXY(45, 10);
            $this->Cell(0, 7, strtoupper($exam['school_name']), 0, 1, 'L');
            
            $this->SetFont('helvetica', '', 10);
            $this->SetX(45);
            $this->Cell(0, 5, $exam['address'] . ' - ' . $exam['city'], 0, 1, 'L');
            
            // Ligne
            $this->SetLineWidth(0.5);
            $this->Line(15, 40, 195, 40);
            
            // Titre principal
            $this->SetY(45);
            $this->SetFont('helvetica', 'B', 16);
            $this->Cell(0, 10, 'LISTE DES CANDIDATS', 0, 1, 'C');
            
            $exam_names = [
                'CEPE' => 'Certificat d\'Études Primaires Élémentaires',
                'BEPC' => 'Brevet d\'Études du Premier Cycle',
                'BAC' => 'Baccalauréat'
            ];
            
            $this->SetFont('helvetica', 'B', 12);
            $this->Cell(0, 8, $exam_names[$exam['exam_type']] ?? $exam['exam_type'], 0, 1, 'C');
            $this->Cell(0, 8, 'Année scolaire ' . $exam['academic_year'], 0, 1, 'C');
        }
        
        public function Footer() {
            $this->SetY(-15);
            $this->SetFont('helvetica', 'I', 8);
            $this->Cell(0, 10, 'Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, 0, 'C');
        }
    }
    
    $pdf = new EXAM_LIST_PDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor($exam['school_name']);
    $pdf->SetTitle('Liste des candidats - ' . $exam['exam_type']);
    
    $pdf->SetHeaderMargin(10);
    $pdf->SetFooterMargin(15);
    $pdf->SetMargins(15, 50, 15);
    
    $pdf->AddPage();
    
    // Informations de l'examen
    $pdf->SetY(65);
    $pdf->SetFont('helvetica', '', 11);
    
    $info = '<table border="0" cellpadding="5">
        <tr>
            <td width="100"><strong>Centre d\'examen:</strong></td>
            <td width="250">' . ($exam['center_name'] ?: 'Non spécifié') . '</td>
            <td width="100"><strong>Code centre:</strong></td>
            <td width="150">' . ($exam['center_code'] ?: '-') . '</td>
        </tr>
        <tr>
            <td><strong>Date d\'examen:</strong></td>
            <td>' . ($exam['exam_date'] ? date('d/m/Y', strtotime($exam['exam_date'])) : 'À définir') . '</td>
            <td><strong>Total candidats:</strong></td>
            <td><strong>' . count($candidates) . '</strong></td>
        </tr>
    </table>';
    
    $pdf->writeHTML($info, true, false, false, false, '');
    
    // Tableau des candidats
    $pdf->SetY($pdf->GetY() + 10);
    $pdf->SetFont('helvetica', 'B', 11);
    
    $table = '<table border="1" cellpadding="5" style="border-collapse: collapse; width: 100%;">
        <thead>
            <tr style="background-color: #f2f2f2;">
                <th width="30" align="center"><strong>N°</strong></th>
                <th width="100" align="center"><strong>MATRICULE</strong></th>
                <th width="150" align="center"><strong>NOM</strong></th>
                <th width="150" align="center"><strong>PRÉNOM</strong></th>
                <th width="50" align="center"><strong>SEXE</strong></th>
                <th width="80" align="center"><strong>DATE NAISS.</strong></th>
                <th width="100" align="center"><strong>CLASSE</strong></th>
                <th width="120" align="center"><strong>N° INSCRIPTION</strong></th>
                <th width="80" align="center"><strong>STATUT</strong></th>
            </tr>
        </thead>
        <tbody>';
    
    $i = 1;
    foreach ($candidates as $candidate) {
        $table .= '<tr>';
        $table .= '<td align="center">' . $i++ . '</td>';
        $table .= '<td align="center">' . $candidate['matricule'] . '</td>';
        $table .= '<td>' . $candidate['last_name'] . '</td>';
        $table .= '<td>' . $candidate['first_name'] . '</td>';
        $table .= '<td align="center">' . $candidate['gender'] . '</td>';
        $table .= '<td align="center">' . date('d/m/Y', strtotime($candidate['birthdate'])) . '</td>';
        $table .= '<td align="center">' . $candidate['class_name'] . '</td>';
        $table .= '<td align="center">' . ($candidate['registration_number'] ?: '-') . '</td>';
        $table .= '<td align="center">' . strtoupper($candidate['status']) . '</td>';
        $table .= '</tr>';
    }
    
    $table .= '</tbody></table>';
    $pdf->writeHTML($table, true, false, false, false, '');
    
    // Signature
    $pdf->SetY($pdf->GetY() + 20);
    $pdf->Cell(0, 5, 'Fait à ' . $exam['city'] . ', le ' . date('d/m/Y'), 0, 1, 'L');
    $pdf->Cell(0, 15, '', 0, 1, 'L');
    $pdf->Cell(90, 5, 'Le Chef d\'Établissement', 0, 0, 'C');
    $pdf->Cell(90, 5, 'Le Responsable des Examens', 0, 1, 'C');
    
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(90, 10, '_________________________', 0, 0, 'C');
    $pdf->Cell(90, 10, '_________________________', 0, 1, 'C');
    
    // Sortie
    $filename = 'liste_candidats_' . $exam['exam_type'] . '_' . $exam['academic_year'] . '.pdf';
    $pdf->Output($filename, 'D');
}