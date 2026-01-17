<?php
require_once __DIR__ . '/../../includes/config.php';
requireUserType('school');

$bulletin_id = $_GET['id'] ?? 0;
$user_id = $_SESSION['user_id'];

// Récupérer le bulletin
$stmt = $pdo->prepare("
    SELECT b.*, s.first_name, s.last_name, s.matricule, s.birthdate, s.gender,
           s.parent_name, s.parent_phone, s.parent_email,
           c.class_name, sch.school_name, sch.address, sch.city, sch.phone as school_phone,
           sch.email as school_email, sch.logo, sch.principal_name
    FROM bulletins b
    JOIN students s ON b.student_id = s.id
    JOIN schools sch ON s.school_id = sch.id
    LEFT JOIN classes c ON b.class_id = c.id
    WHERE b.id = ? AND sch.user_id = ?
");
$stmt->execute([$bulletin_id, $user_id]);
$bulletin = $stmt->fetch();

if (!$bulletin) {
    die("Bulletin non trouvé ou accès non autorisé.");
}

// Récupérer les notes
$stmt = $pdo->prepare("SELECT * FROM bulletin_grades WHERE bulletin_id = ? ORDER BY subject");
$stmt->execute([$bulletin_id]);
$grades = $stmt->fetchAll();

// Calculer les moyennes si non déjà fait
if (!$bulletin['average'] && count($grades) > 0) {
    $total_weighted_sum = 0;
    $total_coefficient = 0;
    
    foreach ($grades as $grade) {
        if ($grade['average']) {
            $total_weighted_sum += $grade['average'] * $grade['coefficient'];
            $total_coefficient += $grade['coefficient'];
        }
    }
    
    if ($total_coefficient > 0) {
        $average = $total_weighted_sum / $total_coefficient;
        
        // Mettre à jour le bulletin
        $stmt = $pdo->prepare("UPDATE bulletins SET average = ? WHERE id = ?");
        $stmt->execute([$average, $bulletin_id]);
        $bulletin['average'] = $average;
    }
}

// Déterminer l'appréciation
function getAppreciation($moyenne) {
    if ($moyenne >= 16) return 'Excellent';
    if ($moyenne >= 14) return 'Très Bien';
    if ($moyenne >= 12) return 'Bien';
    if ($moyenne >= 10) return 'Assez Bien';
    if ($moyenne >= 8) return 'Passable';
    if ($moyenne >= 6) return 'Insuffisant';
    return 'Très Insuffisant';
}

// Générer le PDF
require_once '../../../includes/tcpdf/tcpdf.php';

class BULLETIN_PDF extends TCPDF {
    // Header
    public function Header() {
        global $bulletin;
        
        // Logo de l'école
        $logo = '../../../uploads/' . $bulletin['logo'];
        if (file_exists($logo)) {
            $this->Image($logo, 15, 10, 25, 25, '', '', 'T', false, 300, '', false, false, 0, false, false, false);
        }
        
        // Titre de l'école
        $this->SetFont('helvetica', 'B', 14);
        $this->SetXY(45, 10);
        $this->Cell(0, 7, strtoupper($bulletin['school_name']), 0, 1, 'L');
        
        $this->SetFont('helvetica', '', 10);
        $this->SetX(45);
        $this->Cell(0, 5, $bulletin['address'] . ' - ' . $bulletin['city'], 0, 1, 'L');
        
        $this->SetX(45);
        $this->Cell(0, 5, 'Tél: ' . $bulletin['school_phone'] . ' - Email: ' . $bulletin['school_email'], 0, 1, 'L');
        
        // Ligne de séparation
        $this->SetLineWidth(0.5);
        $this->Line(15, 40, 195, 40);
        
        // Titre principal
        $this->SetY(45);
        $this->SetFont('helvetica', 'B', 16);
        $this->Cell(0, 10, 'BULLETIN SCOLAIRE', 0, 1, 'C');
        
        // Année scolaire et trimestre
        $this->SetFont('helvetica', '', 12);
        $term_labels = [
            'trimestre1' => '1er Trimestre',
            'trimestre2' => '2ème Trimestre',
            'trimestre3' => '3ème Trimestre'
        ];
        $term = $term_labels[$bulletin['term']] ?? $bulletin['term'];
        $this->Cell(0, 7, $term . ' - Année scolaire ' . $bulletin['school_year'], 0, 1, 'C');
    }
    
    // Footer
    public function Footer() {
        $this->SetY(-25);
        $this->SetFont('helvetica', '', 9);
        
        // Signature
        $this->Cell(0, 5, 'Fait à ' . $bulletin['city'] . ', le ' . date('d/m/Y'), 0, 1, 'L');
        $this->Cell(0, 15, '', 0, 1, 'L');
        
        $this->Cell(90, 5, 'Le Chef d\'Établissement', 0, 0, 'C');
        $this->Cell(90, 5, 'Le Responsable Pédagogique', 0, 1, 'C');
        
        $this->SetFont('helvetica', 'B', 10);
        $this->Cell(90, 10, strtoupper($bulletin['principal_name']), 0, 0, 'C');
        $this->Cell(90, 10, '_________________________', 0, 1, 'C');
        
        // Numéro de page
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, 0, 'C');
    }
}

// Créer le PDF
$pdf = new BULLETIN_PDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor($bulletin['school_name']);
$pdf->SetTitle('Bulletin Scolaire - ' . $bulletin['first_name'] . ' ' . $bulletin['last_name']);
$pdf->SetSubject('Bulletin scolaire');
$pdf->SetKeywords('Bulletin, Scolaire, École');

$pdf->SetHeaderMargin(10);
$pdf->SetFooterMargin(15);
$pdf->SetMargins(15, 50, 15);

$pdf->AddPage();

// Information de l'élève
$pdf->SetY(60);
$pdf->SetFont('helvetica', '', 11);

$info_table = '<table border="0" cellpadding="4">
    <tr>
        <td width="100"><strong>Nom & Prénom:</strong></td>
        <td width="250">' . $bulletin['last_name'] . ' ' . $bulletin['first_name'] . '</td>
        <td width="100"><strong>Classe:</strong></td>
        <td width="150">' . $bulletin['class_name'] . '</td>
    </tr>
    <tr>
        <td><strong>Matricule:</strong></td>
        <td>' . $bulletin['matricule'] . '</td>
        <td><strong>Date de naissance:</strong></td>
        <td>' . date('d/m/Y', strtotime($bulletin['birthdate'])) . '</td>
    </tr>
    <tr>
        <td><strong>Sexe:</strong></td>
        <td>' . ($bulletin['gender'] == 'M' ? 'Masculin' : 'Féminin') . '</td>
        <td><strong>Parent:</strong></td>
        <td>' . $bulletin['parent_name'] . '</td>
    </tr>
</table>';

$pdf->writeHTML($info_table, true, false, false, false, '');

// Tableau des notes
$pdf->SetY(85);
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 8, 'RÉSULTATS SCOLAIRES', 0, 1, 'C');

$pdf->SetFont('helvetica', '', 10);

// En-tête du tableau
$html = '<table border="1" cellpadding="5" style="border-collapse: collapse;">
    <thead>
        <tr style="background-color: #f2f2f2;">
            <th width="150" align="center"><strong>MATIÈRES</strong></th>
            <th width="60" align="center"><strong>COEF</strong></th>
            <th width="50" align="center"><strong>N1</strong></th>
            <th width="50" align="center"><strong>N2</strong></th>
            <th width="50" align="center"><strong>N3</strong></th>
            <th width="50" align="center"><strong>N4</strong></th>
            <th width="60" align="center"><strong>MOY</strong></th>
            <th width="80" align="center"><strong>APPRÉCIATION</strong></th>
        </tr>
    </thead>
    <tbody>';

foreach ($grades as $grade) {
    $html .= '<tr>';
    $html .= '<td>' . $grade['subject'] . '</td>';
    $html .= '<td align="center">' . $grade['coefficient'] . '</td>';
    $html .= '<td align="center">' . ($grade['grade1'] ? number_format($grade['grade1'], 1) : '-') . '</td>';
    $html .= '<td align="center">' . ($grade['grade2'] ? number_format($grade['grade2'], 1) : '-') . '</td>';
    $html .= '<td align="center">' . ($grade['grade3'] ? number_format($grade['grade3'], 1) : '-') . '</td>';
    $html .= '<td align="center">' . ($grade['grade4'] ? number_format($grade['grade4'], 1) : '-') . '</td>';
    $html .= '<td align="center"><strong>' . ($grade['average'] ? number_format($grade['average'], 2) : '-') . '</strong></td>';
    $html .= '<td align="center">' . ($grade['appreciation'] ?: '-') . '</td>';
    $html .= '</tr>';
}

$html .= '</tbody></table>';
$pdf->writeHTML($html, true, false, false, false, '');

// Récapitulatif
$pdf->SetY($pdf->GetY() + 10);
$pdf->SetFont('helvetica', 'B', 11);

$recap_table = '<table border="0" cellpadding="5">
    <tr>
        <td width="200"><strong>Moyenne Générale:</strong></td>
        <td width="100" align="center"><strong>' . number_format($bulletin['average'], 2) . ' / 20</strong></td>
        <td width="150"><strong>Appréciation:</strong></td>
        <td width="150" align="center"><strong>' . getAppreciation($bulletin['average']) . '</strong></td>
    </tr>';

if ($bulletin['rank'] && $bulletin['total_students']) {
    $recap_table .= '
    <tr>
        <td><strong>Rang:</strong></td>
        <td align="center"><strong>' . $bulletin['rank'] . ' / ' . $bulletin['total_students'] . '</strong></td>
        <td><strong>Résultat:</strong></td>
        <td align="center"><strong>' . ($bulletin['average'] >= 10 ? 'ADMIS(E)' : 'NON ADMIS(E)') . '</strong></td>
    </tr>';
}

$recap_table .= '</table>';

$pdf->writeHTML($recap_table, true, false, false, false, '');

// Observations
if ($bulletin['teacher_comment'] || $bulletin['principal_comment']) {
    $pdf->SetY($pdf->GetY() + 10);
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 8, 'OBSERVATIONS', 0, 1);
    
    $pdf->SetFont('helvetica', '', 10);
    
    if ($bulletin['teacher_comment']) {
        $pdf->MultiCell(0, 6, 'Professeur Principal: ' . $bulletin['teacher_comment'], 0, 'L');
    }
    
    if ($bulletin['principal_comment']) {
        $pdf->MultiCell(0, 6, 'Chef d\'Établissement: ' . $bulletin['principal_comment'], 0, 'L');
    }
}

// Conseils de passage
$pdf->AddPage();
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 10, 'CONSEILS DE PASSAGE ET ORIENTATION', 0, 1, 'C');

$pdf->SetFont('helvetica', '', 10);
$conseils = 'Après délibération du Conseil de classe, l\'élève est:';
$pdf->MultiCell(0, 7, $conseils, 0, 'L');

$pdf->SetY($pdf->GetY() + 10);

// Cases à cocher
$options = [
    'ADMIS(E) EN CLASSE SUPÉRIEURE',
    'REDOUBLANT(E)',
    'ADMIS(E) À L\'EXAMEN',
    'ADMIS(E) SOUS RÉSERVE',
    'ORIENTÉ(E) VERS'
];

foreach ($options as $option) {
    $pdf->Cell(30, 10, '☐ ' . $option, 0, 1);
    $pdf->SetX(25);
}

$pdf->SetY($pdf->GetY() + 10);
$pdf->MultiCell(0, 7, 'Observations complémentaires du Conseil de classe:', 0, 'L');
$pdf->Rect(15, $pdf->GetY(), 180, 40);
$pdf->SetY($pdf->GetY() + 45);

$pdf->Cell(0, 7, 'Date de la prochaine rentrée scolaire: _________________________', 0, 1);
$pdf->Cell(0, 7, 'Frais de scolarité pour l\'année prochaine: ___________________ FCFA', 0, 1);

// Sortie du PDF
$filename = 'bulletin_' . $bulletin['matricule'] . '_' . $bulletin['term'] . '_' . $bulletin['school_year'] . '.pdf';
$pdf->Output($filename, 'I');
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulletin - <?php echo htmlspecialchars($school['school_name']); ?></title>
    <style>
        @page {
            size: A4;
            margin: 20mm;
        }
        
        body {
            font-family: 'Times New Roman', Times, serif;
            color: #333;
            line-height: 1.4;
            margin: 0;
            padding: 0;
        }
        
        .bulletin-container {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            padding: 15mm;
            box-sizing: border-box;
        }
        
        .header {
            text-align: center;
            border-bottom: 3px double #333;
            padding-bottom: 10mm;
            margin-bottom: 10mm;
        }
        
        .school-name {
            font-size: 24pt;
            font-weight: bold;
            color: #333;
            margin-bottom: 5mm;
        }
        
        .bulletin-title {
            font-size: 18pt;
            color: #666;
            margin-bottom: 5mm;
        }
        
        .period {
            font-size: 14pt;
            color: #666;
        }
        
        .student-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10mm;
            padding: 5mm;
            background: #f8f8f8;
            border-radius: 2mm;
        }
        
        .info-left {
            flex: 2;
        }
        
        .info-right {
            flex: 1;
            text-align: center;
        }
        
        .student-name {
            font-size: 16pt;
            font-weight: bold;
            margin-bottom: 2mm;
        }
        
        .info-row {
            margin-bottom: 1mm;
        }
        
        .photo-placeholder {
            width: 40mm;
            height: 50mm;
            border: 1px solid #ccc;
            background: #fff;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10pt;
            color: #999;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 5mm 0;
        }
        
        th {
            background: #333;
            color: white;
            padding: 2mm;
            text-align: left;
            font-weight: bold;
            border: 1px solid #333;
        }
        
        td {
            padding: 2mm;
            border: 1px solid #ccc;
        }
        
        .category-row {
            background: #f0f0f0;
            font-weight: bold;
        }
        
        .total-row {
            background: #333;
            color: white;
            font-weight: bold;
        }
        
        .summary {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 5mm;
            margin: 10mm 0;
        }
        
        .summary-item {
            text-align: center;
            padding: 3mm;
            border: 1px solid #ccc;
            border-radius: 2mm;
        }
        
        .summary-label {
            font-size: 10pt;
            color: #666;
            margin-bottom: 1mm;
        }
        
        .summary-value {
            font-size: 16pt;
            font-weight: bold;
        }
        
        .comments {
            margin: 10mm 0;
        }
        
        .comment-box {
            margin-bottom: 5mm;
        }
        
        .comment-label {
            font-weight: bold;
            margin-bottom: 2mm;
            display: block;
        }
        
        .comment-content {
            min-height: 20mm;
            padding: 2mm;
            border: 1px solid #ccc;
            border-radius: 1mm;
        }
        
        .signatures {
            display: flex;
            justify-content: space-between;
            margin-top: 15mm;
            padding-top: 5mm;
            border-top: 1px solid #333;
        }
        
        .signature-box {
            text-align: center;
            width: 60mm;
        }
        
        .signature-line {
            border-top: 1px solid #333;
            margin: 10mm 0 2mm;
        }
        
        .signature-label {
            font-size: 10pt;
            color: #666;
        }
        
        .footer {
            text-align: center;
            margin-top: 10mm;
            padding-top: 5mm;
            border-top: 1px dashed #ccc;
            font-size: 10pt;
            color: #666;
        }
        
        .no-print {
            display: none;
        }
        
        @media print {
            body {
                margin: 0;
                padding: 0;
            }
            
            .bulletin-container {
                width: 100%;
                min-height: 100vh;
                padding: 15mm;
                box-shadow: none;
                border: none;
            }
            
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="bulletin-container">
        <div class="header">
            <div class="school-name"><?php echo htmlspecialchars($school['school_name']); ?></div>
            <div class="bulletin-title">BULLETIN SCOLAIRE</div>
            <div class="period">
                <?php echo $report['term']; ?> - Année académique <?php echo $report['academic_year']; ?>
            </div>
        </div>
        
        <div class="student-info">
            <div class="info-left">
                <div class="student-name">
                    <?php echo htmlspecialchars($report['first_name'] . ' ' . $report['last_name']); ?>
                </div>
                <div class="info-row">
                    <strong>Matricule:</strong> <?php echo htmlspecialchars($report['matricule']); ?>
                </div>
                <div class="info-row">
                    <strong>Classe:</strong> <?php echo htmlspecialchars($report['class_name'] . ' - ' . $report['level_name']); ?>
                </div>
                <div class="info-row">
                    <strong>Professeur principal:</strong> 
                    <?php echo htmlspecialchars($report['teacher_name'] ?? 'Non affecté'); ?>
                </div>
            </div>
            
            <div class="info-right">
                <div class="photo-placeholder">
                    Photo de l'élève
                </div>
            </div>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>Matières</th>
                    <th>Coeff.</th>
                    <th>Devoir 1</th>
                    <th>Devoir 2</th>
                    <th>Composition</th>
                    <th>Moyenne</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $current_category = '';
                $total_coefficient = 0;
                $weighted_sum = 0;
                
                // Grouper par matière
                $subject_grades = [];
                foreach ($grades as $grade) {
                    $subject_id = $grade['subject_id'];
                    if (!isset($subject_grades[$subject_id])) {
                        $subject_grades[$subject_id] = [
                            'subject_name' => $grade['subject_name'],
                            'coefficient' => $grade['coefficient'],
                            'scores' => []
                        ];
                    }
                    $subject_grades[$subject_id]['scores'][] = $grade['score'];
                }
                
                foreach ($subject_grades as $subject_id => $subject): 
                    $average = !empty($subject['scores']) ? array_sum($subject['scores']) / count($subject['scores']) : 0;
                    $total_coefficient += $subject['coefficient'];
                    $weighted_sum += $average * $subject['coefficient'];
                ?>
                    <tr>
                        <td><?php echo htmlspecialchars($subject['subject_name']); ?></td>
                        <td style="text-align: center;"><?php echo $subject['coefficient']; ?></td>
                        <td style="text-align: center;"><?php echo isset($subject['scores'][0]) ? number_format($subject['scores'][0], 1) : '-'; ?></td>
                        <td style="text-align: center;"><?php echo isset($subject['scores'][1]) ? number_format($subject['scores'][1], 1) : '-'; ?></td>
                        <td style="text-align: center;"><?php echo isset($subject['scores'][2]) ? number_format($subject['scores'][2], 1) : '-'; ?></td>
                        <td style="text-align: center; font-weight: bold;"><?php echo number_format($average, 2); ?></td>
                    </tr>
                <?php endforeach; ?>
                
                <tr class="total-row">
                    <td colspan="5" style="text-align: right;">MOYENNE GÉNÉRALE</td>
                    <td style="text-align: center; font-size: 14pt;">
                        <?php 
                        $overall_average = $total_coefficient > 0 ? $weighted_sum / $total_coefficient : 0;
                        echo number_format($overall_average, 2);
                        ?>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <div class="summary">
            <div class="summary-item">
                <div class="summary-label">Moyenne Générale</div>
                <div class="summary-value"><?php echo number_format($report['average_score'], 2); ?>/20</div>
            </div>
            
            <div class="summary-item">
                <div class="summary-label">Classement</div>
                <div class="summary-value">
                    <?php echo $report['class_rank']; ?><sup>ème</sup>/<?php echo $report['total_students']; ?>
                </div>
            </div>
            
            <div class="summary-item">
                <div class="summary-label">Mention</div>
                <div class="summary-value"><?php echo getMention($report['average_score']); ?></div>
            </div>
            
            <div class="summary-item">
                <div class="summary-label">Absences</div>
                <div class="summary-value"><?php echo $report['absence_days']; ?> jours</div>
            </div>
        </div>
        
        <div class="comments">
            <div class="comment-box">
                <span class="comment-label">Appréciation du Professeur Principal:</span>
                <div class="comment-content">
                    <?php echo nl2br(htmlspecialchars($report['teacher_comment'] ?? 'Aucun commentaire')); ?>
                </div>
            </div>
            
            <div class="comment-box">
                <span class="comment-label">Observation du Chef d'Établissement:</span>
                <div class="comment-content">
                    <?php echo nl2br(htmlspecialchars($report['principal_comment'] ?? 'Aucun commentaire')); ?>
                </div>
            </div>
        </div>
        
        <div class="signatures">
            <div class="signature-box">
                <div class="signature-line"></div>
                <div class="signature-label">Le Professeur Principal</div>
            </div>
            
            <div class="signature-box">
                <div class="signature-line"></div>
                <div class="signature-label">Le Chef d'Établissement</div>
            </div>
            
            <div class="signature-box">
                <div class="signature-line"></div>
                <div class="signature-label">Les Parents</div>
            </div>
        </div>
        
        <div class="footer">
            Bulletin généré le <?php echo date('d/m/Y H:i', strtotime($report['generated_at'])); ?> 
            • <?php echo htmlspecialchars($school['school_name']); ?> • 
            <?php echo $report['is_published'] ? 'Publié' : 'Brouillon'; ?>
        </div>
    </div>
    
    <div class="no-print" style="position: fixed; bottom: 20px; right: 20px;">
        <button onclick="window.print()" style="padding: 10px 20px; background: #333; color: white; border: none; border-radius: 5px; cursor: pointer;">
            <i class="fas fa-print"></i> Imprimer
        </button>
        <button onclick="window.close()" style="padding: 10px 20px; background: #ccc; color: #333; border: none; border-radius: 5px; cursor: pointer; margin-left: 10px;">
            <i class="fas fa-times"></i> Fermer
        </button>
    </div>
    
    <script>
        window.onload = function() {
            window.print();
        };
        
        window.onafterprint = function() {
            // Fermer la fenêtre après impression
            setTimeout(function() {
                window.close();
            }, 1000);
        };
    </script>
</body>
</html>