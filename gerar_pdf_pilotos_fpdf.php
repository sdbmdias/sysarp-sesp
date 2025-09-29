<?php
// 1. Incluir APENAS o inicializador PHP, sem HTML
require_once 'includes/init.php';
require_once 'libs/fpdf/fpdf.php';

// 2. Bloco de Segurança: Apenas admins e super admins podem gerar este relatório
if (!$isAdmin && !$isSuperAdmin) {
    die('Acesso negado. Você não tem permissão para gerar este relatório.');
}

// 3. Lógica para buscar os dados dos pilotos com filtro por força
$resultados = [];
$params = [];
$types = '';

// Query base sem a coluna CPF
$sql = "SELECT p.posto_graduacao, p.nome_completo, p.crbm_piloto, p.obm_piloto, p.status_piloto 
        FROM pilotos p";

$where_clauses = [];

if (!empty($_GET['graduacao'])) { $where_clauses[] = "p.posto_graduacao = ?"; $params[] = $_GET['graduacao']; $types .= 's'; }
if (!empty($_GET['crbm_piloto'])) { $where_clauses[] = "p.crbm_piloto = ?"; $params[] = $_GET['crbm_piloto']; $types .= 's'; }
if (!empty($_GET['obm_piloto'])) { $where_clauses[] = "p.obm_piloto = ?"; $params[] = $_GET['obm_piloto']; $types .= 's'; }
if (!empty($_GET['status_piloto'])) { $where_clauses[] = "p.status_piloto = ?"; $params[] = $_GET['status_piloto']; $types .= 's'; }

if ($isAdmin && !$isSuperAdmin && !empty($user_forca_seguranca)) {
    $sql .= " JOIN unidades u ON p.crbm_piloto = u.nome_unidade";
    $where_clauses[] = "u.forca_sigla = ?";
    $params[] = $user_forca_seguranca;
    $types .= 's';
}

if (count($where_clauses) > 0) {
    $sql .= " WHERE " . implode(' AND ', $where_clauses);
}

$sql .= " ORDER BY CASE p.posto_graduacao WHEN 'Cel. QOBM' THEN 1 WHEN 'Ten. Cel. QOBM' THEN 2 WHEN 'Maj. QOBM' THEN 3 WHEN 'Cap. QOBM' THEN 4 WHEN '1º Ten. QOBM' THEN 5 WHEN '2º Ten. QOBM' THEN 6 WHEN 'Asp. Oficial' THEN 7 WHEN 'Sub. Ten. QPBM' THEN 8 WHEN '1º Sgt. QPBM' THEN 9 WHEN '2º Sgt. QPBM' THEN 10 WHEN '3º Sgt. QPBM' THEN 11 WHEN 'Cb. QPBM' THEN 12 WHEN 'Sd. QPBM' THEN 13 ELSE 14 END, p.nome_completo ASC";

$stmt = $conn->prepare($sql);
if ($stmt) {
    if (count($params) > 0) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $resultados = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    die("Erro na preparação da consulta SQL: " . $conn->error);
}
$conn->close();

// Classe para criar o PDF
class PDF extends FPDF
{
    function Header()
    {
        $this->SetFont('Arial','B',15);
        $this->Cell(0,10,utf8_decode('Relatório de Pilotos'),0,1,'C');
        $this->Ln(10); 
    }

    function Footer()
    {
        $this->SetY(-15); 
        $this->SetFont('Arial','I',8);
        $this->Cell(0,10,utf8_decode('Página ').$this->PageNo().'/{nb}',0,0,'C');
    }
    
    function GetAutoPageBreakMargin() { return $this->bMargin; }
    function GetLeftMargin() { return $this->lMargin; }
    function GetRightMargin() { return $this->rMargin; }
}

$pdf = new PDF('L', 'mm', 'A4'); 
$pdf->AliasNbPages(); 
$pdf->AddPage();
$pdf->SetFont('Arial','B',10);

// =================================================================
// ALTERAÇÃO APLICADA AQUI
// Redistribuição das larguras das colunas
// =================================================================
$w = [40, 80, 50, 70, 30];

// Cabeçalho da Tabela
$header = ['Posto/Grad.', 'Nome Completo', 'CRBM', 'OBM', 'Status'];
for($i=0; $i<count($header); $i++) {
    $pdf->Cell($w[$i], 7, utf8_decode($header[$i]), 1, 0, 'C');
}
$pdf->Ln();

$pdf->SetFont('Arial','',9);
$row_height = 6; 

if (!empty($resultados)) {
    foreach($resultados as $row) {
        if($pdf->GetY() + $row_height > ($pdf->GetPageHeight() - $pdf->GetAutoPageBreakMargin())) { 
            $pdf->AddPage();
            $pdf->SetFont('Arial','B',10); 
            for($i=0; $i<count($header); $i++) {
                $pdf->Cell($w[$i], 7, utf8_decode($header[$i]), 1, 0, 'C');
            }
            $pdf->Ln();
            $pdf->SetFont('Arial','',9); 
        }

        $pdf->Cell($w[0], $row_height, utf8_decode($row['posto_graduacao']), 1, 0, 'L'); 
        $pdf->Cell($w[1], $row_height, utf8_decode($row['nome_completo']), 1, 0, 'L');
        $pdf->Cell($w[2], $row_height, utf8_decode($row['crbm_piloto']), 1, 0, 'C'); 
        $pdf->Cell($w[3], $row_height, utf8_decode($row['obm_piloto']), 1, 0, 'C'); 
        $pdf->Cell($w[4], $row_height, utf8_decode(ucfirst($row['status_piloto'])), 1, 0, 'C'); 
        
        $pdf->Ln(); 
    }
} else {
    $pdf->Cell(array_sum($w), 10, utf8_decode('Nenhum piloto encontrado para os critérios selecionados.'), 1, 1, 'C');
}

$pdf->Output('I', 'Relatorio_Pilotos_SOARP.pdf');
?>