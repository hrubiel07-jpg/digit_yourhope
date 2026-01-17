<?php
/**
 * Fonctions pour la gestion des bulletins scolaires
 */

require_once 'vendor/autoload.php'; // Pour TCPDF ou Dompdf

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Générer un bulletin scolaire au format PDF
 */
function generateReportCardPDF($student_id, $academic_year, $term, $output = 'I') {
    global $pdo;
    
    // Récupérer les informations de l'élève
    $stmt = $pdo->prepare("
        SELECT s.*, c.class_name, c.class_code, sc.school_name, sc.city, sc.country, sc.phone, sc.email
        FROM students s
        JOIN classes c ON s.current_class_id = c.id
        JOIN schools sc ON s.school_id = sc.id
        WHERE s.id = ?
    ");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();
    
    if (!$student) {
        return ['success' => false, 'error' => 'Élève non trouvé'];
    }
    
    // Récupérer les notes
    $stmt = $pdo->prepare("
        SELECT g.*, sj.subject_name, sj.coefficient, sj.category,
               t.qualification as teacher_qualification,
               u.full_name as teacher_name
        FROM grades g
        JOIN subjects sj ON g.subject_id = sj.id
        LEFT JOIN teachers t ON g.teacher_id = t.id
        LEFT JOIN users u ON t.user_id = u.id
        WHERE g.student_id = ? 
        AND g.academic_year = ? 
        AND g.term = ?
        AND g.is_deleted = 0
        ORDER BY sj.category, sj.subject_name
    ");
    $stmt->execute([$student_id, $academic_year, $term]);
    $grades = $stmt->fetchAll();
    
    if (empty($grades)) {
        return ['success' => false, 'error' => 'Aucune note trouvée pour cette période'];
    }
    
    // Calculer les moyennes
    $subject_averages = [];
    $total_coefficient = 0;
    $total_points = 0;
    
    foreach ($grades as $grade) {
        $subject_id = $grade['subject_id'];
        
        if (!isset($subject_averages[$subject_id])) {
            $subject_averages[$subject_id] = [
                'subject_name' => $grade['subject_name'],
                'coefficient' => $grade['coefficient'],
                'category' => $grade['category'],
                'grades' => [],
                'average' => 0
            ];
        }
        
        // Convertir la note en points sur 20
        $score = ($grade['score'] / $grade['max_score']) * 20;
        $subject_averages[$subject_id]['grades'][] = [
            'score' => $score,
            'type' => $grade['type'],
            'coefficient' => $grade['coefficient'] ?? 1
        ];
    }
    
    // Calculer la moyenne par matière
    foreach ($subject_averages as &$subject) {
        $total = 0;
        $coeff_total = 0;
        
        foreach ($subject['grades'] as $grade) {
            $total += $grade['score'] * $grade['coefficient'];
            $coeff_total += $grade['coefficient'];
        }
        
        $subject['average'] = $coeff_total > 0 ? round($total / $coeff_total, 2) : 0;
        $total_points += $subject['average'] * $subject['coefficient'];
        $total_coefficient += $subject['coefficient'];
    }
    
    // Moyenne générale
    $overall_average = $total_coefficient > 0 ? round($total_points / $total_coefficient, 2) : 0;
    
    // Appréciation selon le système congolais
    $appreciation = getCongoleseAppreciation($overall_average);
    
    // Classement
    $rank = getStudentRank($student_id, $student['current_class_id'], $academic_year, $term);
    
    // Générer le HTML du bulletin
    $html = generateReportCardHTML($student, $subject_averages, $overall_average, $appreciation, $rank, $academic_year, $term);
    
    // Générer le PDF
    return generatePDF($html, $student['matricule'] . '_' . $term . '.pdf', $output);
}

/**
 * Générer le HTML du bulletin
 */
function generateReportCardHTML($student, $subject_averages, $overall_average, $appreciation, $rank, $academic_year, $term) {
    $school_config = getSchoolConfig($student['school_id']);
    
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            @page {
                margin: 20mm;
                size: A4 portrait;
            }
            
            body {
                font-family: DejaVu Sans, Arial, sans-serif;
                font-size: 12px;
                line-height: 1.4;
                color: #000;
                margin: 0;
                padding: 0;
            }
            
            .header {
                text-align: center;
                margin-bottom: 20px;
                border-bottom: 3px double #000;
                padding-bottom: 15px;
            }
            
            .header h1 {
                font-size: 20px;
                margin: 0 0 5px;
                text-transform: uppercase;
            }
            
            .header h2 {
                font-size: 16px;
                margin: 0 0 10px;
                color: #333;
            }
            
            .header h3 {
                font-size: 14px;
                margin: 0;
                color: #666;
            }
            
            .student-info {
                margin-bottom: 20px;
                border: 1px solid #000;
                border-radius: 5px;
                padding: 15px;
                background: #f8f9fa;
            }
            
            .info-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 10px;
            }
            
            .info-row {
                display: flex;
                margin-bottom: 8px;
            }
            
            .info-label {
                font-weight: bold;
                min-width: 150px;
            }
            
            .info-value {
                flex: 1;
            }
            
            .grades-table {
                width: 100%;
                border-collapse: collapse;
                margin: 20px 0;
                page-break-inside: avoid;
            }
            
            .grades-table th {
                background: #e9ecef;
                border: 1px solid #000;
                padding: 8px;
                text-align: center;
                font-weight: bold;
            }
            
            .grades-table td {
                border: 1px solid #000;
                padding: 8px;
                text-align: center;
            }
            
            .subject-row {
                background: #fff;
            }
            
            .subject-name {
                text-align: left;
                font-weight: bold;
            }
            
            .category-header {
                background: #d1ecf1;
                font-weight: bold;
                text-align: left;
                padding-left: 20px !important;
            }
            
            .summary {
                margin-top: 30px;
                border: 1px solid #000;
                border-radius: 5px;
                padding: 15px;
                background: #f8f9fa;
                page-break-inside: avoid;
            }
            
            .summary-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 20px;
            }
            
            .summary-item {
                text-align: center;
            }
            
            .summary-value {
                font-size: 24px;
                font-weight: bold;
                margin-bottom: 5px;
            }
            
            .summary-label {
                font-size: 12px;
                color: #666;
            }
            
            .appreciation {
                text-align: center;
                font-size: 16px;
                font-weight: bold;
                margin: 20px 0;
                padding: 10px;
                background: ' . ($overall_average >= 10 ? '#d4edda' : '#f8d7da') . ';
                border: 1px solid ' . ($overall_average >= 10 ? '#c3e6cb' : '#f5c6cb') . ';
                border-radius: 5px;
            }
            
            .signatures {
                margin-top: 40px;
                display: flex;
                justify-content: space-between;
                page-break-inside: avoid;
            }
            
            .signature-box {
                text-align: center;
                width: 200px;
            }
            
            .signature-line {
                margin-top: 40px;
                border-top: 1px solid #000;
                width: 100%;
            }
            
            .signature-label {
                margin-top: 5px;
                font-size: 11px;
                color: #666;
            }
            
            .footer {
                margin-top: 30px;
                text-align: center;
                font-size: 10px;
                color: #666;
                border-top: 1px solid #ddd;
                padding-top: 10px;
            }
            
            .watermark {
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%) rotate(-45deg);
                font-size: 80px;
                color: rgba(0,0,0,0.1);
                z-index: -1;
                pointer-events: none;
            }
            
            @media print {
                .no-print {
                    display: none !important;
                }
                
                body {
                    font-size: 11px;
                }
                
                .header h1 {
                    font-size: 18px;
                }
                
                .grades-table {
                    font-size: 10px;
                }
            }
        </style>
    </head>
    <body>
        <div class="watermark">' . htmlspecialchars($student['school_name']) . '</div>
        
        <div class="header">
            <h1>RÉPUBLIQUE DU CONGO</h1>
            <h2>MINISTÈRE DE L\'ENSEIGNEMENT PRIMAIRE, SECONDAIRE ET DE L\'ALPHABÉTISATION</h2>
            <h3>' . htmlspecialchars($student['school_name']) . '</h3>
            <h3>' . htmlspecialchars($student['city']) . ' - ' . htmlspecialchars($student['country']) . '</h3>
            <h2>BULLETIN DE NOTES</h2>
        </div>
        
        <div class="student-info">
            <div class="info-grid">
                <div class="info-row">
                    <span class="info-label">Nom et Prénom:</span>
                    <span class="info-value">' . htmlspecialchars($student['last_name'] . ' ' . $student['first_name']) . '</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Matricule:</span>
                    <span class="info-value">' . htmlspecialchars($student['matricule']) . '</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Classe:</span>
                    <span class="info-value">' . htmlspecialchars($student['class_name']) . ' (' . htmlspecialchars($student['class_code']) . ')</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Année Scolaire:</span>
                    <span class="info-value">' . htmlspecialchars($academic_year) . '</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Trimestre:</span>
                    <span class="info-value">' . htmlspecialchars($term) . '</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Date de naissance:</span>
                    <span class="info-value">' . formatDate($student['birth_date'], 'd/m/Y') . '</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Lieu de naissance:</span>
                    <span class="info-value">' . htmlspecialchars($student['birth_place']) . '</span>
                </div>
            </div>
        </div>
        
        <table class="grades-table">
            <thead>
                <tr>
                    <th width="30%">MATIÈRES</th>
                    <th width="10%">COEF.</th>
                    <th width="15%">DEVOIRS</th>
                    <th width="15%">COMPOSITIONS</th>
                    <th width="15%">MOYENNE</th>
                    <th width="15%">APPRÉCIATION</th>
                </tr>
            </thead>
            <tbody>';
    
    $current_category = '';
    foreach ($subject_averages as $subject) {
        if ($current_category != $subject['category']) {
            $current_category = $subject['category'];
            $html .= '
                <tr>
                    <td colspan="6" class="category-header">' . htmlspecialchars($current_category) . '</td>
                </tr>';
        }
        
        $subject_appreciation = getCongoleseAppreciation($subject['average']);
        
        $html .= '
                <tr class="subject-row">
                    <td class="subject-name">' . htmlspecialchars($subject['subject_name']) . '</td>
                    <td>' . $subject['coefficient'] . '</td>
                    <td>';
        
        // Afficher les notes de devoirs
        $devoirs = array_filter($subject['grades'], function($g) { 
            return strpos(strtolower($g['type']), 'devoir') !== false; 
        });
        foreach ($devoirs as $devoir) {
            $html .= round($devoir['score'], 1) . ' ';
        }
        
        $html .= '</td>
                    <td>';
        
        // Afficher les notes de compositions
        $compositions = array_filter($subject['grades'], function($g) { 
            return strpos(strtolower($g['type']), 'composition') !== false || 
                   strpos(strtolower($g['type']), 'examen') !== false; 
        });
        foreach ($compositions as $comp) {
            $html .= round($comp['score'], 1) . ' ';
        }
        
        $html .= '</td>
                    <td><strong>' . number_format($subject['average'], 2, ',', ' ') . '/20</strong></td>
                    <td>' . htmlspecialchars($subject_appreciation) . '</td>
                </tr>';
    }
    
    $html .= '
            </tbody>
        </table>
        
        <div class="appreciation">
            APPRÉCIATION GÉNÉRALE: ' . htmlspecialchars($appreciation) . '
        </div>
        
        <div class="summary">
            <div class="summary-grid">
                <div class="summary-item">
                    <div class="summary-value">' . number_format($overall_average, 2, ',', ' ') . '/20</div>
                    <div class="summary-label">MOYENNE GÉNÉRALE</div>
                </div>
                <div class="summary-item">
                    <div class="summary-value">' . htmlspecialchars($rank['rank']) . '/' . htmlspecialchars($rank['total']) . '</div>
                    <div class="summary-label">RANG DANS LA CLASSE</div>
                </div>
                <div class="summary-item">
                    <div class="summary-value">' . htmlspecialchars($student['class_name']) . '</div>
                    <div class="summary-label">CLASSE</div>
                </div>
                <div class="summary-item">
                    <div class="summary-value">' . htmlspecialchars($term) . '</div>
                    <div class="summary-label">TRIMESTRE</div>
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
                <div class="signature-label">Le Directeur de l\'Établissement</div>
            </div>
            <div class="signature-box">
                <div class="signature-line"></div>
                <div class="signature-label">Les Parents</div>
            </div>
        </div>
        
        <div class="footer">
            Bulletin généré le ' . date('d/m/Y à H:i') . ' - ' . htmlspecialchars($student['school_name']) . '<br>
            Tél: ' . htmlspecialchars($student['phone']) . ' - Email: ' . htmlspecialchars($student['email']) . '<br>
            <strong>Document officiel - Toute falsification est punie par la loi</strong>
        </div>
    </body>
    </html>';
    
    return $html;
}

/**
 * Générer le PDF avec DomPDF
 */
function generatePDF($html, $filename = 'bulletin.pdf', $output = 'I') {
    try {
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'dejavu sans');
        
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        if ($output == 'I') {
            // Envoyer au navigateur
            $dompdf->stream($filename, ['Attachment' => false]);
            return ['success' => true];
        } elseif ($output == 'D') {
            // Télécharger
            $dompdf->stream($filename, ['Attachment' => true]);
            return ['success' => true];
        } elseif ($output == 'F') {
            // Sauvegarder sur le serveur
            $output = $dompdf->output();
            $filepath = dirname(__DIR__) . '/uploads/reports/' . $filename;
            
            if (!file_exists(dirname($filepath))) {
                mkdir(dirname($filepath), 0777, true);
            }
            
            file_put_contents($filepath, $output);
            return ['success' => true, 'filepath' => $filepath];
        }
        
        return ['success' => false, 'error' => 'Type de sortie invalide'];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Obtenir l'appréciation selon le système congolais
 */
function getCongoleseAppreciation($average) {
    if ($average >= 16) return 'EXCELLENT';
    if ($average >= 14) return 'TRÈS BIEN';
    if ($average >= 12) return 'BIEN';
    if ($average >= 10) return 'ASSEZ BIEN';
    if ($average >= 8) return 'PASSABLE';
    if ($average >= 6) return 'INSUFFISANT';
    return 'TRÈS INSUFFISANT';
}

/**
 * Obtenir le classement de l'élève
 */
function getStudentRank($student_id, $class_id, $academic_year, $term) {
    global $pdo;
    
    // Calculer les moyennes de tous les élèves de la classe
    $stmt = $pdo->prepare("
        SELECT s.id, 
               AVG((g.score / g.max_score) * 20) as average
        FROM students s
        LEFT JOIN grades g ON s.id = g.student_id 
            AND g.academic_year = ? 
            AND g.term = ?
            AND g.is_deleted = 0
        WHERE s.current_class_id = ? 
        GROUP BY s.id
        ORDER BY average DESC
    ");
    
    $stmt->execute([$academic_year, $term, $class_id]);
    $students = $stmt->fetchAll();
    
    $rank = 1;
    $found = false;
    
    foreach ($students as $student) {
        if ($student['id'] == $student_id) {
            $found = true;
            break;
        }
        $rank++;
    }
    
    return [
        'rank' => $found ? $rank : '-',
        'total' => count($students)
    ];
}

/**
 * Générer les bulletins pour toute une classe
 */
function generateClassReportCards($class_id, $academic_year, $term) {
    global $pdo;
    
    // Récupérer tous les élèves de la classe
    $stmt = $pdo->prepare("
        SELECT id FROM students 
        WHERE current_class_id = ? AND status = 'active'
    ");
    $stmt->execute([$class_id]);
    $students = $stmt->fetchAll();
    
    $results = [];
    $errors = [];
    
    foreach ($students as $student) {
        $result = generateReportCardPDF($student['id'], $academic_year, $term, 'F');
        
        if ($result['success']) {
            $results[] = [
                'student_id' => $student['id'],
                'filepath' => $result['filepath']
            ];
        } else {
            $errors[] = [
                'student_id' => $student['id'],
                'error' => $result['error']
            ];
        }
    }
    
    return [
        'success' => empty($errors),
        'generated' => count($results),
        'errors' => $errors,
        'results' => $results
    ];
}

/**
 * Envoyer un bulletin par email
 */
function sendReportCardByEmail($student_id, $academic_year, $term, $parent_email) {
    // Générer le PDF
    $result = generateReportCardPDF($student_id, $academic_year, $term, 'F');
    
    if (!$result['success']) {
        return $result;
    }
    
    // Récupérer les infos de l'élève
    $stmt = $pdo->prepare("
        SELECT s.*, c.class_name 
        FROM students s 
        JOIN classes c ON s.current_class_id = c.id 
        WHERE s.id = ?
    ");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();
    
    // Préparer l'email
    $subject = "Bulletin scolaire - " . $student['first_name'] . " " . $student['last_name'] . " - " . $term . " " . $academic_year;
    
    $message = "
    <html>
    <body>
        <h2>Bulletin Scolaire</h2>
        <p>Cher parent,</p>
        <p>Veuillez trouver ci-joint le bulletin scolaire de votre enfant <strong>" . htmlspecialchars($student['first_name'] . " " . $student['last_name']) . "</strong> pour le trimestre <strong>" . htmlspecialchars($term) . "</strong> de l'année scolaire <strong>" . htmlspecialchars($academic_year) . "</strong>.</p>
        <p><strong>Classe:</strong> " . htmlspecialchars($student['class_name']) . "</p>
        <p><strong>Matricule:</strong> " . htmlspecialchars($student['matricule']) . "</p>
        <br>
        <p>Cordialement,<br>
        La Direction de l'établissement</p>
    </body>
    </html>
    ";
    
    // Envoyer l'email avec pièce jointe
    return sendEmailWithAttachment($parent_email, $subject, $message, $result['filepath']);
}

/**
 * Envoyer un email avec pièce jointe
 */
function sendEmailWithAttachment($to, $subject, $message, $attachment_path) {
    $boundary = md5(time());
    
    $headers = "From: " . ADMIN_EMAIL . "\r\n";
    $headers .= "Reply-To: " . ADMIN_EMAIL . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n";
    
    // Message
    $body = "--$boundary\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
    $body .= $message . "\r\n\r\n";
    
    // Pièce jointe
    $filename = basename($attachment_path);
    $file_content = file_get_contents($attachment_path);
    $file_content = chunk_split(base64_encode($file_content));
    
    $body .= "--$boundary\r\n";
    $body .= "Content-Type: application/pdf; name=\"$filename\"\r\n";
    $body .= "Content-Transfer-Encoding: base64\r\n";
    $body .= "Content-Disposition: attachment; filename=\"$filename\"\r\n\r\n";
    $body .= $file_content . "\r\n\r\n";
    $body .= "--$boundary--";
    
    return mail($to, $subject, $body, $headers);
}
?>