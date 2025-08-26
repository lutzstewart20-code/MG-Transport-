<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Get booking ID from URL
$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;

if ($booking_id <= 0) {
    die('Invalid booking ID');
}

// Get booking details with user and vehicle information
$booking_query = "
SELECT b.*, u.username, u.email, u.first_name, u.last_name, u.phone,
       v.name as vehicle_name, v.image_url, v.rate_per_day,
       v.vehicle_type, v.description as vehicle_description
FROM bookings b
JOIN users u ON b.user_id = u.id
JOIN vehicles v ON b.vehicle_id = v.id
WHERE b.id = ? AND (b.user_id = ? OR ? = 'admin')
";

$stmt = mysqli_prepare($conn, $booking_query);
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'user';
mysqli_stmt_bind_param($stmt, "iis", $booking_id, $user_id, $user_role);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$booking = mysqli_fetch_assoc($result);

if (!$booking) {
    die('Booking not found or access denied');
}

// Include TCPDF library
if (!file_exists('tcpdf/tcpdf.php')) {
    // Redirect to HTML version if TCPDF is not available
    header('Location: generate_invoice_html.php?booking_id=' . $booking_id);
    exit();
}
require_once('tcpdf/tcpdf.php');

// Create new PDF document
class MYPDF extends TCPDF {
    public function Header() {
        // Logo
        $image_file = 'assets/images/MG Logo.jpg';
        if (file_exists($image_file)) {
            $this->Image($image_file, 10, 10, 30, 0, 'JPG', '', 'T', false, 300, '', false, false, 0, false, false, false);
        }
        
        // Set font
        $this->SetFont('helvetica', 'B', 20);
        
        // Title
        $this->Cell(0, 15, 'MG Transport Services', 0, false, 'C', 0, '', 0, false, 'M', 'M');
        $this->Ln();
        $this->SetFont('helvetica', '', 12);
        $this->Cell(0, 10, 'Invoice', 0, false, 'C', 0, '', 0, false, 'M', 'M');
        $this->Ln(20);
    }
    
    public function Footer() {
        // Position at 15 mm from bottom
        $this->SetY(-15);
        // Set font
        $this->SetFont('helvetica', 'I', 8);
        // Page number
        $this->Cell(0, 10, 'Page '.$this->getAliasNumPage().'/'.$this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}

// Create new PDF document
$pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('MG Transport Services');
$pdf->SetAuthor('MG Transport Services');
$pdf->SetTitle('Invoice #' . $booking['id']);
$pdf->SetSubject('Booking Invoice');

// Set default header data
$pdf->SetHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, PDF_HEADER_TITLE, PDF_HEADER_STRING);

// Set header and footer fonts
$pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
$pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

// Set default monospaced font
$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

// Set margins
$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

// Set auto page breaks
$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

// Set image scale factor
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

// Set font
$pdf->SetFont('helvetica', '', 10);

// Add a page
$pdf->AddPage();

// Calculate days
$start_date = new DateTime($booking['start_date']);
$end_date = new DateTime($booking['end_date']);
$days = $start_date->diff($end_date)->days + 1;

// Calculate GST
$gst_rate = getSystemSetting('gst_rate', $conn);
$gst_amount = ($booking['total_amount'] * $gst_rate) / 100;
$subtotal = $booking['total_amount'] - $gst_amount;

// Invoice content
$html = '
<table cellpadding="5" cellspacing="0" style="width: 100%; border: 1px solid #ddd;">
    <tr style="background-color: #f8f9fa;">
        <td colspan="2" style="border: 1px solid #ddd;">
            <strong>Invoice Details</strong>
        </td>
    </tr>
    <tr>
        <td style="border: 1px solid #ddd; width: 30%;"><strong>Invoice Number:</strong></td>
        <td style="border: 1px solid #ddd;">INV-' . str_pad($booking['id'], 6, '0', STR_PAD_LEFT) . '</td>
    </tr>
    <tr>
        <td style="border: 1px solid #ddd;"><strong>Invoice Date:</strong></td>
        <td style="border: 1px solid #ddd;">' . date('F d, Y', strtotime($booking['created_at'])) . '</td>
    </tr>
    <tr>
        <td style="border: 1px solid #ddd;"><strong>Due Date:</strong></td>
        <td style="border: 1px solid #ddd;">' . date('F d, Y', strtotime($booking['created_at'])) . '</td>
    </tr>
</table>

<br><br>

<table cellpadding="5" cellspacing="0" style="width: 100%; border: 1px solid #ddd;">
    <tr style="background-color: #f8f9fa;">
        <td colspan="2" style="border: 1px solid #ddd;">
            <strong>Customer Information</strong>
        </td>
    </tr>
    <tr>
        <td style="border: 1px solid #ddd; width: 30%;"><strong>Name:</strong></td>
        <td style="border: 1px solid #ddd;">' . htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']) . '</td>
    </tr>
    <tr>
        <td style="border: 1px solid #ddd;"><strong>Email:</strong></td>
        <td style="border: 1px solid #ddd;">' . htmlspecialchars($booking['email']) . '</td>
    </tr>
    <tr>
        <td style="border: 1px solid #ddd;"><strong>Phone:</strong></td>
        <td style="border: 1px solid #ddd;">' . htmlspecialchars($booking['phone']) . '</td>
    </tr>
</table>

<br><br>

<table cellpadding="5" cellspacing="0" style="width: 100%; border: 1px solid #ddd;">
    <tr style="background-color: #f8f9fa;">
        <td colspan="2" style="border: 1px solid #ddd;">
            <strong>Booking Details</strong>
        </td>
    </tr>
    <tr>
        <td style="border: 1px solid #ddd; width: 30%;"><strong>Vehicle:</strong></td>
        <td style="border: 1px solid #ddd;">' . htmlspecialchars($booking['vehicle_name']) . ' (' . htmlspecialchars($booking['vehicle_type']) . ')</td>
    </tr>
    <tr>
        <td style="border: 1px solid #ddd;"><strong>Pickup Date:</strong></td>
        <td style="border: 1px solid #ddd;">' . date('F d, Y', strtotime($booking['start_date'])) . '</td>
    </tr>
    <tr>
        <td style="border: 1px solid #ddd;"><strong>Return Date:</strong></td>
        <td style="border: 1px solid #ddd;">' . date('F d, Y', strtotime($booking['end_date'])) . '</td>
    </tr>
    <tr>
        <td style="border: 1px solid #ddd;"><strong>Duration:</strong></td>
        <td style="border: 1px solid #ddd;">' . $days . ' day(s)</td>
    </tr>
    <tr>
        <td style="border: 1px solid #ddd;"><strong>Status:</strong></td>
        <td style="border: 1px solid #ddd;">' . ucfirst($booking['status']) . '</td>
    </tr>
    <tr>
        <td style="border: 1px solid #ddd;"><strong>Payment Method:</strong></td>
        <td style="border: 1px solid #ddd;">' . ucfirst($booking['payment_method']) . '</td>
    </tr>
    <tr>
        <td style="border: 1px solid #ddd;"><strong>Payment Status:</strong></td>
        <td style="border: 1px solid #ddd;">' . ucfirst($booking['payment_status']) . '</td>
    </tr>
</table>

<br><br>

<table cellpadding="5" cellspacing="0" style="width: 100%; border: 1px solid #ddd;">
    <tr style="background-color: #f8f9fa;">
        <td colspan="4" style="border: 1px solid #ddd;">
            <strong>Item Details</strong>
        </td>
    </tr>
    <tr style="background-color: #f8f9fa;">
        <td style="border: 1px solid #ddd; width: 40%;"><strong>Description</strong></td>
        <td style="border: 1px solid #ddd; width: 20%;"><strong>Rate</strong></td>
        <td style="border: 1px solid #ddd; width: 20%;"><strong>Days</strong></td>
        <td style="border: 1px solid #ddd; width: 20%;"><strong>Amount</strong></td>
    </tr>
    <tr>
        <td style="border: 1px solid #ddd;">' . htmlspecialchars($booking['vehicle_name']) . ' (' . htmlspecialchars($booking['vehicle_type']) . ')</td>
        <td style="border: 1px solid #ddd;">' . formatCurrency($booking['rate_per_day']) . '</td>
        <td style="border: 1px solid #ddd;">' . $days . '</td>
        <td style="border: 1px solid #ddd;">' . formatCurrency($booking['rate_per_day'] * $days) . '</td>
    </tr>
</table>

<br><br>

<table cellpadding="5" cellspacing="0" style="width: 100%; border: 1px solid #ddd;">
    <tr style="background-color: #f8f9fa;">
        <td colspan="2" style="border: 1px solid #ddd;">
            <strong>Payment Summary</strong>
        </td>
    </tr>
    <tr>
        <td style="border: 1px solid #ddd; width: 70%; text-align: right;"><strong>Subtotal:</strong></td>
        <td style="border: 1px solid #ddd; width: 30%;">' . formatCurrency($subtotal) . '</td>
    </tr>
    <tr>
        <td style="border: 1px solid #ddd; text-align: right;"><strong>GST (' . $gst_rate . '%):</strong></td>
        <td style="border: 1px solid #ddd;">' . formatCurrency($gst_amount) . '</td>
    </tr>
    <tr style="background-color: #f8f9fa;">
        <td style="border: 1px solid #ddd; text-align: right;"><strong>Total Amount:</strong></td>
        <td style="border: 1px solid #ddd;"><strong>' . formatCurrency($booking['total_amount']) . '</strong></td>
    </tr>
</table>

<br><br>

<table cellpadding="5" cellspacing="0" style="width: 100%; border: 1px solid #ddd;">
    <tr style="background-color: #f8f9fa;">
        <td colspan="2" style="border: 1px solid #ddd;">
            <strong>Terms & Conditions</strong>
        </td>
    </tr>
    <tr>
        <td colspan="2" style="border: 1px solid #ddd;">
            <ul>
                <li>Payment is due upon receipt of this invoice</li>
                <li>Late payments may incur additional charges</li>
                <li>All prices include GST where applicable</li>
                <li>Please retain this invoice for your records</li>
                <li>For any queries, please contact MG Transport Services</li>
            </ul>
        </td>
    </tr>
</table>

<br><br>

<div style="text-align: center; color: #666; font-size: 10px;">
    <p>Thank you for choosing MG Transport Services!</p>
    <p>This is a computer-generated invoice. No signature required.</p>
</div>
';

// Print text using writeHTMLCell()
$pdf->writeHTML($html, true, false, true, false, '');

// Close and output PDF document
$pdf->Output('Invoice-' . str_pad($booking['id'], 6, '0', STR_PAD_LEFT) . '.pdf', 'I');
?> 