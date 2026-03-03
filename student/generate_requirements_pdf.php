<?php
require("db-config/security.php");

// Redirect if not logged in
if (!isLoggedIn()) {
    header('Location: index');
    exit;
}

// Include TCPDF library
require_once('../vendor/tecnickcom/tcpdf/tcpdf.php'); // Adjust path as needed

// Get student information
$student_id = $_SESSION['user_id'];

// Get student details
$student_query = "
    SELECT s.*, sec.section_name 
    FROM students s
    LEFT JOIN sections sec ON s.section_id = sec.id
    WHERE s.id = ?
";
$stmt = $pdo->prepare($student_query);
$stmt->execute([$student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

// Get all uploaded requirements with status
$files_query = "
    SELECT 
        rs.*, 
        r.req_name
    FROM requirements_status rs
    JOIN requirements r ON rs.req_id = r.id
    WHERE rs.student_id = ? 
    AND rs.file_path IS NOT NULL
    ORDER BY 
        CASE rs.is_checked
            WHEN 1 THEN 1
            WHEN 0 THEN 2
            WHEN 2 THEN 3
        END,
        r.req_name ASC
";

$stmt = $pdo->prepare($files_query);
$stmt->execute([$student_id]);
$uploaded_files = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Custom PDF class
class MYPDF extends TCPDF {
    protected $student_name;
    protected $student_section;
    
    public function setStudentInfo($name, $section) {
        $this->student_name = $name;
        $this->student_section = $section;
    }
    
    // Minimal header
    public function Header() {
        // Only show header on first page
        if ($this->getPage() == 1) {
            $this->SetFont('helvetica', 'B', 16);
            $this->SetTextColor(102, 126, 234);
            $this->Cell(0, 20, 'REQUIREMENTS SUBMISSION', 0, 1, 'C');
            $this->SetFont('helvetica', '', 12);
            $this->SetTextColor(0, 0, 0);
            $this->Cell(0, 8, $this->student_name, 0, 1, 'C');
            $this->Cell(0, 8, $this->student_section ?? 'No Section', 0, 1, 'C');
            $this->Ln(5);
        }
    }

    // Simple footer with page number only
    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->SetTextColor(128, 128, 128);
        $this->Cell(0, 10, 'Page '.$this->getAliasNumPage(), 0, false, 'C');
    }
    
    // Function to add document page
    public function addDocumentPage($file, $index) {
        // Add a new page
        $this->AddPage();
        
        // Check if file exists
        if (!file_exists($file['file_path'])) {
            $this->SetFont('helvetica', 'B', 14);
            $this->SetTextColor(220, 38, 38);
            $this->Cell(0, 10, 'File not found: ' . basename($file['file_path']), 0, 1, 'C');
            return;
        }
        
        // Handle different file types
        $file_name = basename($file['file_path']);
        $file_ext_lower = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        // Add document label
        $this->SetFont('helvetica', 'B', 10);
        $this->SetTextColor(100, 116, 139);
        // $this->Cell(0, 20, $file['req_name'] . ' - ' . date('M d, Y', strtotime($file['date_created'])), 0, 1, 'L');
        $this->Cell(0, 20, $file['req_name'] . ' : ', 0, 1, 'L');
        $this->Ln(5);
        
        switch($file_ext_lower) {
            case 'pdf':
                // Embed PDF - try to get number of pages
                try {
                    $pageCount = $this->setSourceFile($file['file_path']);
                    for ($i = 1; $i <= $pageCount; $i++) {
                        if ($i > 1) {
                            $this->AddPage();
                        }
                        $tplIdx = $this->importPage($i);
                        $this->useTemplate($tplIdx, 15, 40, 180);
                    }
                } catch (Exception $e) {
                    $this->SetFont('helvetica', '', 12);
                    $this->SetTextColor(220, 38, 38);
                    $this->Cell(0, 10, 'Error embedding PDF: ' . $e->getMessage(), 0, 1, 'C');
                }
                break;
                
            case 'jpg':
            case 'jpeg':
            case 'png':
                // Display image - get dimensions to optimize placement
                list($width, $height) = @getimagesize($file['file_path']);
                if ($width && $height) {
                    // Calculate ratio to fit within page margins
                    $max_width = 180;
                    $max_height = 230; // Leave space for header
                    
                    $ratio_w = $max_width / $width;
                    $ratio_h = $max_height / $height;
                    $ratio = min($ratio_w, $ratio_h);
                    
                    $new_width = $width * $ratio;
                    $new_height = $height * $ratio;
                    
                    // Center the image
                    $x = (210 - $new_width) / 2;
                    $y = $this->GetY();
                    
                    $this->Image($file['file_path'], $x, $y, $new_width, $new_height, '', '', 'T', true, 300);
                } else {
                    // If we can't get dimensions, use default sizing
                    $this->Image($file['file_path'], 15, $this->GetY(), 180, 0, '', '', 'T', true, 300);
                }
                break;
                
            default:
                // Unsupported file type
                $this->SetFont('helvetica', '', 12);
                $this->SetTextColor(100, 116, 139);
                $this->Cell(0, 20, 'Preview not available for: ' . strtoupper($file_ext_lower), 0, 1, 'C');
                $this->SetFont('helvetica', '', 10);
                $this->Cell(0, 10, $file_name, 0, 1, 'C');
                break;
        }
        
        // Add simple status indicator at bottom
        if ($file['is_checked'] != 1) {
            $this->SetY(-25);
            $this->SetFont('helvetica', 'I', 8);
            $status_text = $file['is_checked'] == 2 ? 'REJECTED' : 'PENDING REVIEW';
            $this->SetTextColor($file['is_checked'] == 2 ? 220 : 245, $file['is_checked'] == 2 ? 38 : 158, $file['is_checked'] == 2 ? 38 : 11);
            $this->Cell(0, 10, $status_text, 0, 1, 'R');
        }
    }
}

// Create new PDF document
$pdf = new MYPDF('P', 'mm', 'A4', true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('Student Portal');
$pdf->SetAuthor('Requirements System');
$pdf->SetTitle('Requirements - ' . $student['firstname'] . ' ' . $student['lastname']);

// Set margins - leave space for minimal header
$pdf->SetMargins(15, 40, 15);
$pdf->SetHeaderMargin(10);
$pdf->SetFooterMargin(15);

// Set auto page breaks
$pdf->SetAutoPageBreak(TRUE, 25);

// Set image scale factor
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

// Set student info
$pdf->setStudentInfo(
    $student['firstname'] . ' ' . $student['lastname'],
    $student['section_name'] ?? 'Not Assigned'
);

// Add documents
if (!empty($uploaded_files)) {
    $doc_count = 1;
    foreach ($uploaded_files as $file) {
        $pdf->addDocumentPage($file, $doc_count);
        $doc_count++;
    }
} else {
    // No documents page
    $pdf->AddPage();
    $pdf->SetFont('helvetica', 'B', 18);
    $pdf->SetTextColor(100, 116, 139);
    $pdf->SetY(120);
    $pdf->Cell(0, 20, 'NO REQUIREMENTS UPLOADED', 0, 1, 'C');
}

// Output PDF
$pdf->Output('Requirements_' . $student['lastname'] . '_' . date('Y-m-d') . '.pdf', 'I');
?>