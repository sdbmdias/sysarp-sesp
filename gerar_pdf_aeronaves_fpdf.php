<?php
// 1. Incluir os arquivos necessários
require_once 'includes/database.php';
require_once 'libs/fpdf/fpdf.php';

// 2. Lógica para buscar os dados das aeronaves
$aeronaves = [];
$sql_aeronaves = "SELECT id, prefixo, fabricante, modelo, numero_serie, cadastro_sisant, validade_sisant, crbm, obm, tipo_drone, pmd_kg, status, homologacao_anatel FROM aeronaves ORDER BY prefixo ASC";
$result_aeronaves = $conn->query($sql_aeronaves);

if ($result_aeronaves && $result_aeronaves->num_rows > 0) {
    while ($row = $result_aeronaves->fetch_assoc()) {
        $aeronaves[] = $row;
    }
}
$conn->close();

// Função auxiliar para formatar status
function formatarStatusAeronave($status) {
    $status_map = ['ativo' => 'Ativa', 'em_manutencao' => 'Em Manutenção', 'baixada' => 'Baixada', 'adida' => 'Adida', 'desativado' => 'Desativada'];
    return $status_map[$status] ?? ucfirst($status);
}

// 3. Classe para criar o PDF
class PDF extends FPDF
{
    // Cabeçalho
    function Header()
    {
        $this->SetFont('Arial','B',15);
        $this->Cell(0,10,utf8_decode('Relatório de Aeronaves'),0,1,'C');
        $this->Ln(10); // Pular linha
    }

    // Rodapé
    function Footer()
    {
        $this->SetY(-15); // Posição a 1.5 cm do final
        $this->SetFont('Arial','I',8);
        $this->Cell(0,10,utf8_decode('Página ').$this->PageNo().'/{nb}',0,0,'C');
    }

    // Método público para acessar a margem de quebra de página automática
    function GetAutoPageBreakMargin()
    {
        return $this->bMargin;
    }

    // Método público para acessar a margem esquerda
    function GetLeftMargin()
    {
        return $this->lMargin;
    }

    // Método público para acessar a margem direita
    function GetRightMargin()
    {
        return $this->rMargin;
    }
}

// 4. Geração do PDF
$pdf = new PDF('L'); // Layout Paisagem (Landscape) para mais colunas
$pdf->AliasNbPages(); // Habilita a contagem total de páginas no rodapé
$pdf->AddPage();
$pdf->SetFont('Arial','B',8);

// Definição das larguras das colunas
$w = [30, 45, 30, 35, 30, 25, 20, 25, 30]; 

// Cabeçalho da Tabela
$header = ['Prefixo', 'Fabricante/Modelo', 'Nº Série', 'SISANT (Val.)', 'Lotação', 'Tipo', 'PMD (kg)', 'Status', 'ANATEL'];
for($i=0; $i<count($header); $i++) {
    $pdf->Cell($w[$i], 7, utf8_decode($header[$i]), 1, 0, 'C'); // Cabeçalhos já centralizados
}
$pdf->Ln();

// Dados da Tabela
$pdf->SetFont('Arial','',7); // Fonte menor para os dados
$row_height = 6; // Altura padrão da linha de dados

if (!empty($aeronaves)) {
    foreach($aeronaves as $aeronave) {
        $start_x_row = $pdf->GetX();
        $start_y_row = $pdf->GetY();
        
        // Adicionar uma nova página se a linha atual exceder o limite
        if($pdf->GetY() + $row_height > ($pdf->GetPageHeight() - $pdf->GetAutoPageBreakMargin())) { 
            $pdf->AddPage();
            $pdf->SetFont('Arial','B',8); // Restaura fonte para cabeçalho
            for($i=0; $i<count($header); $i++) {
                $pdf->Cell($w[$i], 7, utf8_decode($header[$i]), 1, 0, 'C');
            }
            $pdf->Ln();
            $pdf->SetFont('Arial','',7); // Restaura fonte para dados
            $start_x_row = $pdf->GetX(); 
            $start_y_row = $pdf->GetY();
        }

        // --- Desenhar Bordas da Linha Completa ---
        // Desenha a borda inferior para a linha inteira da tabela
        $pdf->Cell(array_sum($w), $row_height, '', 'B', 0, 'C'); 
        $pdf->SetXY($start_x_row, $start_y_row); // Volta o cursor para o início da linha

        // Desenha as bordas verticais para cada célula
        $current_cell_x_border = $start_x_row;
        foreach ($w as $col_width_border) {
            $pdf->Cell($col_width_border, $row_height, '', 'LR', 0, 'C'); 
            $current_cell_x_border += $col_width_border;
        }
        $pdf->SetXY($start_x_row, $start_y_row); // Volta o cursor para o início da linha para desenhar o conteúdo

        // --- Desenhar Conteúdo das Células (sem bordas, centralizado) ---
        // Célula 1: Prefixo
        $pdf->Cell($w[0], $row_height, utf8_decode($aeronave['prefixo'] ?? 'N/A'), 0, 0, 'C'); 
        
        // Célula 2: Fabricante/Modelo
        $pdf->Cell($w[1], $row_height, utf8_decode(($aeronave['fabricante'] ?? 'N/A') . ' / ' . ($aeronave['modelo'] ?? 'N/A')), 0, 0, 'C');
        
        // Célula 3: Nº Série
        $pdf->Cell($w[2], $row_height, utf8_decode($aeronave['numero_serie'] ?? 'N/A'), 0, 0, 'C');
        
        // Célula 4: SISANT (Val.)
        $sisant_val = ($aeronave['cadastro_sisant'] ?? 'N/A') . ' (' . (isset($aeronave['validade_sisant']) ? date("d/m/Y", strtotime($aeronave['validade_sisant'])) : 'N/A') . ')';
        $pdf->Cell($w[3], $row_height, utf8_decode($sisant_val), 0, 0, 'C');
        
        // Célula 5: Lotação
        $crbm_formatado = preg_replace('/(\d)(CRBM)/', '$1º $2', $aeronave['crbm'] ?? 'N/A');
        $lotacao = $crbm_formatado . ' / ' . ($aeronave['obm'] ?? 'N/A');
        $pdf->Cell($w[4], $row_height, utf8_decode($lotacao), 0, 0, 'C');
        
        // Célula 6: Tipo
        $pdf->Cell($w[5], $row_height, utf8_decode(ucfirst(str_replace('_', '-', $aeronave['tipo_drone'] ?? 'N/A'))), 0, 0, 'C');
        
        // Célula 7: PMD (kg)
        $pdf->Cell($w[6], $row_height, utf8_decode($aeronave['pmd_kg'] ?? 'N/A'), 0, 0, 'C');
        
        // Célula 8: Status
        $pdf->Cell($w[7], $row_height, utf8_decode(formatarStatusAeronave($aeronave['status'] ?? 'desconhecido')), 0, 0, 'C');
        
        // Célula 9: ANATEL
        $pdf->Cell($w[8], $row_height, utf8_decode($aeronave['homologacao_anatel'] ?? 'Não'), 0, 0, 'C');
        
        $pdf->Ln($row_height); // Pular linha com a altura calculada
    }
} else {
    $pdf->Cell(array_sum($w), 10, utf8_decode('Nenhuma aeronave encontrada'), 1, 1, 'C');
}

// Linha de fechamento da tabela
$pdf->Cell(array_sum($w),0,'','T');

// 5. Saída do PDF
$pdf->Output('I', 'Relatorio_Aeronaves_SOARP.pdf');
?>