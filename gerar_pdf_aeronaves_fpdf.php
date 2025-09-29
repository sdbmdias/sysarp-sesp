<?php
// 1. Incluir APENAS o inicializador PHP, sem HTML
require_once 'includes/init.php';
require_once 'libs/fpdf/fpdf.php';

// 2. Bloco de Segurança: Apenas admins e super admins podem gerar este relatório
if (!$isAdmin && !$isSuperAdmin) {
    die('Acesso negado. Você não tem permissão para gerar este relatório.');
}

// 3. Lógica para buscar os dados das aeronaves com filtro por força de segurança
$aeronaves = [];
$params = [];
$types = '';

$sql_aeronaves = "SELECT id, prefixo, fabricante, modelo, numero_serie, cadastro_sisant, validade_sisant, crbm, obm, tipo_drone, pmd_kg, status, homologacao_anatel FROM aeronaves";

// Se o usuário for um Administrador (não Super Admin), modifica a query para filtrar pela sua força
if ($isAdmin && !$isSuperAdmin && !empty($user_forca_seguranca)) {
    $sql_aeronaves = "
        SELECT a.id, a.prefixo, a.fabricante, a.modelo, a.numero_serie, a.cadastro_sisant, a.validade_sisant, a.crbm, a.obm, a.tipo_drone, a.pmd_kg, a.status, a.homologacao_anatel 
        FROM aeronaves a
        JOIN unidades u ON a.crbm = u.nome_unidade
        WHERE u.forca_sigla = ?
    ";
    $params[] = $user_forca_seguranca;
    $types .= 's';
}

$sql_aeronaves .= " ORDER BY prefixo ASC";

$stmt = $conn->prepare($sql_aeronaves);

if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result_aeronaves = $stmt->get_result();
    if ($result_aeronaves) {
        $aeronaves = $result_aeronaves->fetch_all(MYSQLI_ASSOC);
    }
    $stmt->close();
} else {
    die("Erro ao preparar a consulta de aeronaves.");
}

$conn->close();


// Função auxiliar para formatar status
function formatarStatusAeronave($status) {
    $status_map = ['ativo' => 'Ativa', 'em_manutencao' => 'Em Manutenção', 'baixada' => 'Baixada', 'adida' => 'Adida', 'desativado' => 'Desativada'];
    return $status_map[$status] ?? ucfirst($status);
}

// Classe para criar o PDF
class PDF extends FPDF
{
    function Header()
    {
        $this->SetFont('Arial','B',15);
        $this->Cell(0,10,utf8_decode('Relatório de Aeronaves'),0,1,'C');
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

// Geração do PDF
$pdf = new PDF('L');
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial','B',8);

$w = [25, 45, 30, 35, 45, 25, 20, 25, 25]; 

$header = ['Prefixo', 'Fabricante/Modelo', 'Nº Série', 'SISANT (Val.)', 'Lotação', 'Tipo', 'PMD (kg)', 'Status', 'ANATEL'];
for($i=0; $i<count($header); $i++) {
    $pdf->Cell($w[$i], 7, utf8_decode($header[$i]), 1, 0, 'C');
}
$pdf->Ln();

$pdf->SetFont('Arial','',7);
$row_height = 6;

if (!empty($aeronaves)) {
    foreach($aeronaves as $aeronave) {
        $start_x_row = $pdf->GetX();
        $start_y_row = $pdf->GetY();
        
        if($pdf->GetY() + $row_height > ($pdf->GetPageHeight() - $pdf->GetAutoPageBreakMargin())) { 
            $pdf->AddPage();
            $pdf->SetFont('Arial','B',8);
            for($i=0; $i<count($header); $i++) {
                $pdf->Cell($w[$i], 7, utf8_decode($header[$i]), 1, 0, 'C');
            }
            $pdf->Ln();
            $pdf->SetFont('Arial','',7);
            $start_x_row = $pdf->GetX(); 
            $start_y_row = $pdf->GetY();
        }

        $pdf->Cell(array_sum($w), $row_height, '', 'B', 0, 'C'); 
        $pdf->SetXY($start_x_row, $start_y_row); 
        $current_cell_x_border = $start_x_row;
        foreach ($w as $col_width_border) {
            $pdf->Cell($col_width_border, $row_height, '', 'LR', 0, 'C'); 
            $current_cell_x_border += $col_width_border;
        }
        $pdf->SetXY($start_x_row, $start_y_row); 

        $pdf->Cell($w[0], $row_height, utf8_decode($aeronave['prefixo'] ?? 'N/A'), 0, 0, 'C'); 
        $pdf->Cell($w[1], $row_height, utf8_decode(($aeronave['fabricante'] ?? 'N/A') . ' / ' . ($aeronave['modelo'] ?? 'N/A')), 0, 0, 'C');
        $pdf->Cell($w[2], $row_height, utf8_decode($aeronave['numero_serie'] ?? 'N/A'), 0, 0, 'C');
        $sisant_val = ($aeronave['cadastro_sisant'] ?? 'N/A') . ' (' . (isset($aeronave['validade_sisant']) ? date("d/m/Y", strtotime($aeronave['validade_sisant'])) : 'N/A') . ')';
        $pdf->Cell($w[3], $row_height, utf8_decode($sisant_val), 0, 0, 'C');
        $crbm_formatado = preg_replace('/(\d)(CRBM)/', '$1º $2', $aeronave['crbm'] ?? 'N/A');
        $lotacao = $crbm_formatado . ' / ' . ($aeronave['obm'] ?? 'N/A');
        $pdf->Cell($w[4], $row_height, utf8_decode($lotacao), 0, 0, 'C');
        $pdf->Cell($w[5], $row_height, utf8_decode(ucfirst(str_replace('_', '-', $aeronave['tipo_drone'] ?? 'N/A'))), 0, 0, 'C');
        $pdf->Cell($w[6], $row_height, utf8_decode($aeronave['pmd_kg'] ?? 'N/A'), 0, 0, 'C');
        $pdf->Cell($w[7], $row_height, utf8_decode(formatarStatusAeronave($aeronave['status'] ?? 'desconhecido')), 0, 0, 'C');
        $pdf->Cell($w[8], $row_height, utf8_decode($aeronave['homologacao_anatel'] ?? 'Não'), 0, 0, 'C');
        
        $pdf->Ln($row_height); 
    }
} else {
    $pdf->Cell(array_sum($w), 10, utf8_decode('Nenhuma aeronave encontrada para a sua força de segurança.'), 1, 1, 'C');
}

$pdf->Cell(array_sum($w),0,'','T');

$pdf->Output('I', 'Relatorio_Aeronaves_SOARP.pdf');
?>