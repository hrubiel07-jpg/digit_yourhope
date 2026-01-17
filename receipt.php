<?php
require_once '../includes/config.php';
requireLogin();

$transaction_id = $_GET['txn_id'] ?? '';

// Récupérer les infos du reçu
$stmt = $pdo->prepare("
    SELECT op.*, p.paid_at, f.fee_name, s.first_name, s.last_name, s.matricule,
           sch.school_name, sch.address, sch.city, sch.phone as school_phone,
           sch.email as school_email, sch.logo, sch.principal_name,
           u.full_name as parent_name, u.phone as parent_phone
    FROM online_payments op
    JOIN payments p ON op.payment_id = p.id
    JOIN school_fees f ON p.fee_id = f.id
    JOIN students s ON p.student_id = s.id
    JOIN schools sch ON s.school_id = sch.id
    JOIN parents par ON s.parent_id = par.id
    JOIN users u ON par.user_id = u.id
    WHERE op.transaction_id = ?
");
$stmt->execute([$transaction_id]);
$receipt = $stmt->fetch();

if (!$receipt) {
    die("Reçu non trouvé.");
}

// Générer le PDF
require_once '../includes/tcpdf/tcpdf.php';

class RECEIPT_PDF extends TCPDF {
    public function Header() {
        global $receipt;
        
        // Logo
        $logo = '../uploads/' . $receipt['logo'];
        if (file_exists($logo)) {
            $this->Image($logo, 15, 10, 25, 25, '', '', 'T', false, 300, '', false, false, 0, false, false, false);
        }
        
        // Titre
        $this->SetFont('helvetica', 'B', 14);
        $this->SetXY(45, 10);
        $this->Cell(0, 7, strtoupper($receipt['school_name']), 0, 1, 'L');
        
        $this->SetFont('helvetica', '', 10);
        $this->SetX(45);
        $this->Cell(0, 5, $receipt['address'] . ' - ' . $receipt['city'], 0, 1, 'L');
        
        $this->SetX(45);
        $this->Cell(0, 5, 'Tél: ' . $receipt['school_phone'] . ' - Email: ' . $receipt['school_email'], 0, 1, 'L');
        
        // Ligne
        $this->SetLineWidth(0.5);
        $this->Line(15, 40, 195, 40);
        
        // Titre principal
        $this->SetY(45);
        $this->SetFont('helvetica', 'B', 16);
        $this->Cell(0, 10, 'REÇU DE PAIEMENT', 0, 1, 'C');
        
        $this->SetFont('helvetica', 'B', 12);
        $this->Cell(0, 8, 'N° ' . $receipt['transaction_id'], 0, 1, 'C');
    }
    
    public function Footer() {
        $this->SetY(-20);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Ce reçu est généré automatiquement et ne nécessite pas de signature manuscrite.', 0, 1, 'C');
        $this->Cell(0, 5, 'Conservez ce document comme preuve de paiement.', 0, 1, 'C');
    }
}

// Créer le PDF
$pdf = new RECEIPT_PDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor($receipt['school_name']);
$pdf->SetTitle('Reçu de paiement - ' . $receipt['transaction_id']);
$pdf->SetSubject('Reçu de paiement');

$pdf->SetHeaderMargin(10);
$pdf->SetFooterMargin(15);
$pdf->SetMargins(15, 50, 15);

$pdf->AddPage();

// Informations du paiement
$pdf->SetY(65);
$pdf->SetFont('helvetica', '', 11);

$info_table = '<table border="0" cellpadding="5">
    <tr>
        <td width="120"><strong>Date du paiement:</strong></td>
        <td width="250">' . date('d/m/Y à H:i', strtotime($receipt['paid_at'])) . '</td>
    </tr>
    <tr>
        <td><strong>Élève:</strong></td>
        <td>' . $receipt['first_name'] . ' ' . $receipt['last_name'] . ' (' . $receipt['matricule'] . ')</td>
    </tr>
    <tr>
        <td><strong>Parent:</strong></td>
        <td>' . $receipt['parent_name'] . ' (' . $receipt['parent_phone'] . ')</td>
    </tr>
    <tr>
        <td><strong>Moyen de paiement:</strong></td>
        <td>' . strtoupper(str_replace('_', ' ', $receipt['gateway'])) . '</td>
    </tr>
</table>';

$pdf->writeHTML($info_table, true, false, false, false, '');

// Détails du paiement
$pdf->SetY($pdf->GetY() + 10);
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 8, 'DÉTAILS DU PAIEMENT', 0, 1);

$pdf->SetFont('helvetica', '', 11);

$details_table = '<table border="1" cellpadding="8" style="border-collapse: collapse; width: 100%;">
    <thead>
        <tr style="background-color: #f2f2f2;">
            <th width="70%" align="left"><strong>DESCRIPTION</strong></th>
            <th width="30%" align="right"><strong>MONTANT</strong></th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>' . $receipt['fee_name'] . '</td>
            <td align="right">' . number_format($receipt['amount'], 0, ',', ' ') . ' FCFA</td>
        </tr>
    </tbody>
</table>';

$pdf->writeHTML($details_table, true, false, false, false, '');

// Total
$pdf->SetY($pdf->GetY() + 5);
$pdf->SetFont('helvetica', 'B', 13);

$total_table = '<table border="0" cellpadding="8" style="width: 100%;">
    <tr>
        <td width="70%" align="right"><strong>TOTAL PAYÉ:</strong></td>
        <td width="30%" align="right" style="color: #27ae60;">
            <strong>' . number_format($receipt['amount'], 0, ',', ' ') . ' FCFA</strong>
        </td>
    </tr>
</table>';

$pdf->writeHTML($total_table, true, false, false, false, '');

// Observations
$pdf->SetY($pdf->GetY() + 15);
$pdf->SetFont('helvetica', '', 10);
$pdf->MultiCell(0, 6, 'Observations:', 0, 'L');
$pdf->Rect(15, $pdf->GetY(), 180, 40);
$pdf->SetY($pdf->GetY() + 45);

// Codes QR pour vérification
$pdf->SetY($pdf->GetY() + 10);
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(0, 5, 'Pour vérifier ce reçu, scannez le code QR ci-dessous:', 0, 1);

$verification_url = SITE_URL . 'payment/verify_receipt.php?txn_id=' . $transaction_id;
$style = array(
    'border' => false,
    'padding' => 0,
    'fgcolor' => array(0,0,0),
    'bgcolor' => false
);
$pdf->write2DBarcode($verification_url, 'QRCODE,L', 80, $pdf->GetY(), 50, 50, $style);

// Informations de contact
$pdf->SetY($pdf->GetY() + 60);
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(0, 5, 'Pour toute question concernant ce paiement, contactez:', 0, 1, 'C');
$pdf->Cell(0, 5, $receipt['school_phone'] . ' | ' . $receipt['school_email'], 0, 1, 'C');

// Sortie du PDF
$filename = 'receipt_' . $transaction_id . '.pdf';
$pdf->Output($filename, 'I');