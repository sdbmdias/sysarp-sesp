<?php
// 1. Incluir APENAS o inicializador PHP, sem HTML
require_once 'includes/init.php'; 
require_once 'libs/fpdf/fpdf.php';

// 2. Bloco de Segurança: Apenas admins e super admins podem gerar este relatório
if (!$isAdmin && !$isSuperAdmin) {
    die('Acesso negado. Você não tem permissão para gerar este relatório.');
}

// 3. Lógica para buscar os dados das missões com filtro por força de segurança
$missoes = [];
$params = [];
$types = '';

$sql_base = "
    SELECT 
        m.id, m.data, m.descricao_operacao, m.rgo_ocorrencia, m.total_tempo_voo, m.total_distancia_percorrida,
        a.prefixo AS aeronave_prefixo,
        GROUP_CONCAT(DISTINCT CONCAT(p.posto_graduacao, ' ', p.nome_completo) 
            ORDER BY
                CASE p.posto_graduacao
                    WHEN 'Cel. QOBM' THEN 1 WHEN 'Ten. Cel. QOBM' THEN 2 WHEN 'Maj. QOBM' THEN 3
                    WHEN 'Cap. QOBM' THEN 4 WHEN '1º Ten. QOBM' THEN 5 WHEN '2º Ten. QOBM' THEN 6
                    WHEN 'Asp. Oficial' THEN 7 WHEN 'Sub. Ten. QPBM' THEN 8 WHEN '1º Sgt. QPBM' THEN 9
                    WHEN '2º Sgt. QPBM' THEN 10 WHEN '3º Sgt. QPBM' THEN 11 WHEN 'Cb. QPBM' THEN 12
                    WHEN 'Sd. QPBM' THEN 13 ELSE 14
                END
            SEPARATOR '\n') AS pilotos_nomes
    FROM missoes m
    JOIN aeronaves a ON m.aeronave_id = a.id
    LEFT JOIN missoes_pilotos mp ON m.id = mp.missao_id
    LEFT JOIN pilotos p ON mp.piloto_id = p.id
";

$where_clause = '';
// Se for Admin (não Super Admin) ou Piloto, adiciona o filtro por força de segurança
if (($isAdmin || $isPiloto) && !$isSuperAdmin && !empty($user_forca_seguranca)) {
    $sql_base .= " JOIN unidades u ON a.crbm = u.nome_unidade ";
    $where_clause = " WHERE u.forca_sigla = ? ";
    $params[] = $user_forca_seguranca;
    $types .= 's';
}

$sql_missoes = $sql_base . $where_clause . " GROUP BY m.id ORDER BY m.data DESC, m.id DESC";

$stmt = $conn->prepare($sql_missoes);

if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result_missoes = $stmt->get_result();
    if ($result_missoes) {
        $missoes = $result_missoes->fetch_all(MYSQLI_ASSOC);
    }
    $stmt->close();
} else {
    die("Erro ao preparar a consulta de missões: " . $conn->error);
}
$conn->close();


// Função auxiliar para formatar tempo de voo
function formatarTempoVooPDF($segundos) {
    if ($segundos <= 0) return '0min';
    $horas = floor($segundos / 3600);
    $minutos = floor(($segundos % 3600) / 60);
    $resultado = '';
    if ($horas > 0) $resultado .= $horas . 'h ';
    if ($minutos > 0) $resultado .= $minutos . 'min';
    return trim($resultado) ?: '0min';
}

// Classe para criar o PDF
class PDF extends FPDF
{
    function Header()
    {
        $this->SetFont('Arial','B',15);
        $this->Cell(0,10,utf8_decode('Relatório de Missões'),0,1,'C');
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

$w = [25, 35, 60, 80, 20, 20]; 

$header = ['Data', 'Aeronave', 'Piloto(s)', 'Operação (RG/Descrição)', 'Tempo Voo', 'Distância'];
for($i=0; $i<count($header); $i++) {
    $pdf->Cell($w[$i], 7, utf8_decode($header[$i]), 1, 0, 'C');
}
$pdf->Ln();

$pdf->SetFont('Arial','',7); 
if (!empty($missoes)) {
    foreach($missoes as $missao) {
        $start_x_row = $pdf->GetX(); 
        $start_y_row = $pdf->GetY(); 
        $line_height_multicell = 4; 

        $pilotos_text = utf8_decode($missao['pilotos_nomes'] ?? 'Nenhum piloto associado');
        $operacao_text = utf8_decode((empty($missao['rgo_ocorrencia']) ? 'MISSÃO SEM RGO' : $missao['rgo_ocorrencia']) . "\n" . $missao['descricao_operacao']);
        
        $original_l_margin_calc = $pdf->GetLeftMargin();
        $original_r_margin_calc = $pdf->GetRightMargin();
        $temp_x_calc = $pdf->GetX(); 
        $temp_y_calc = $pdf->GetY(); 

        $pdf->SetLeftMargin($temp_x_calc + $w[0] + $w[1]); 
        $pdf->SetRightMargin($pdf->GetPageWidth() - ($temp_x_calc + $w[0] + $w[1] + $w[2])); 
        $pdf->MultiCell($w[2], $line_height_multicell, $pilotos_text, 0, 'C', false);
        $height_pilotos = $pdf->GetY() - $temp_y_calc;
        $pdf->SetXY($temp_x_calc, $temp_y_calc); 
        $pdf->SetLeftMargin($original_l_margin_calc); 
        $pdf->SetRightMargin($original_r_margin_calc); 

        $pdf->SetLeftMargin($temp_x_calc + $w[0] + $w[1] + $w[2]); 
        $pdf->SetRightMargin($pdf->GetPageWidth() - ($temp_x_calc + $w[0] + $w[1] + $w[2] + $w[3])); 
        $pdf->MultiCell($w[3], $line_height_multicell, $operacao_text, 0, 'C', false);
        $height_operacao = $pdf->GetY() - $temp_y_calc;
        $pdf->SetXY($temp_x_calc, $temp_y_calc); 
        $pdf->SetLeftMargin($original_l_margin_calc);
        $pdf->SetRightMargin($original_r_margin_calc);

        $row_height = max($height_pilotos, $height_operacao, 6); 

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
        
        $doc_original_l_margin = $pdf->GetLeftMargin();
        $doc_original_r_margin = $pdf->GetRightMargin();

        $pdf->Cell(array_sum($w), $row_height, '', 'B', 0, 'C'); 
        $pdf->SetXY($start_x_row, $start_y_row); 
        $current_cell_x_border = $start_x_row;
        foreach ($w as $col_width_border) {
            $pdf->Cell($col_width_border, $row_height, '', 'LR', 0, 'C'); 
            $current_cell_x_border += $col_width_border;
        }
        $pdf->SetXY($start_x_row, $start_y_row); 

        $pdf->Cell($w[0], $row_height, date("d/m/Y", strtotime($missao['data'])), 0, 0, 'C'); 
        $pdf->Cell($w[1], $row_height, utf8_decode($missao['aeronave_prefixo']), 0, 0, 'C');
        
        $x_pilotos_content = $start_x_row + $w[0] + $w[1];
        $y_pilotos_content = $start_y_row;
        $pdf->SetXY($x_pilotos_content, $y_pilotos_content); 
        $original_l_margin_temp = $pdf->GetLeftMargin();
        $original_r_margin_temp = $pdf->GetRightMargin();
        $pdf->SetLeftMargin($x_pilotos_content); 
        $pdf->SetRightMargin($pdf->GetPageWidth() - ($x_pilotos_content + $w[2])); 
        $pdf->MultiCell($w[2], $line_height_multicell, $pilotos_text, 0, 'C', false); 
        $pdf->SetLeftMargin($original_l_margin_temp); 
        $pdf->SetRightMargin($original_r_margin_temp); 
        $pdf->SetY($y_pilotos_content); 
        $pdf->SetX($x_pilotos_content + $w[2]); 

        $x_operacao_content = $start_x_row + $w[0] + $w[1] + $w[2];
        $y_operacao_content = $start_y_row;
        $pdf->SetXY($x_operacao_content, $y_operacao_content); 
        $original_l_margin_temp = $pdf->GetLeftMargin();
        $original_r_margin_temp = $pdf->GetRightMargin();
        $pdf->SetLeftMargin($x_operacao_content); 
        $pdf->SetRightMargin($pdf->GetPageWidth() - ($x_operacao_content + $w[3])); 
        $pdf->MultiCell($w[3], $line_height_multicell, $operacao_text, 0, 'C', false); 
        $pdf->SetLeftMargin($original_l_margin_temp); 
        $pdf->SetRightMargin($original_r_margin_temp); 
        $pdf->SetY($y_operacao_content); 
        $pdf->SetX($x_operacao_content + $w[3]); 

        $pdf->Cell($w[4], $row_height, formatarTempoVooPDF($missao['total_tempo_voo']), 0, 0, 'C');

        $distancia_km = $missao['total_distancia_percorrida'] / 1000; 
        $distancia_formatada = number_format($distancia_km, 2, ',', '') . ' km'; 
        $pdf->Cell($w[5], $row_height, $distancia_formatada, 0, 0, 'C');

        $pdf->SetY($start_y_row + $row_height);
        $pdf->SetX($pdf->GetLeftMargin()); 
    }
} else {
    $pdf->Cell(array_sum($w), 10, utf8_decode('Nenhuma missão encontrada para os critérios selecionados.'), 1, 1, 'C');
}

$pdf->Cell(array_sum($w),0,'','T');

// Saída do PDF
$pdf->Output('I', 'Relatorio_Missoes_SOARP.pdf');
?>