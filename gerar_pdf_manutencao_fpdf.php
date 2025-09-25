<?php
// 1. Incluir os arquivos necessários
require_once 'includes/database.php';
require_once 'libs/fpdf/fpdf.php';

// 2. Lógica para buscar os dados das manutenções
$historico_manutencoes = [];

$sql_historico = "SELECT 
                m.*, /* Mantém m.* para compatibilidade, mas a coluna 'valor' não será exibida no PDF */
                a.prefixo AS aeronave_prefixo, 
                a.modelo AS aeronave_modelo,
                c.numero_serie AS controle_sn,
                c.modelo AS controle_modelo,
                a_vinc.prefixo AS controle_vinculado_a
             FROM manutencoes m 
             LEFT JOIN aeronaves a ON m.equipamento_id = a.id AND m.equipamento_tipo = 'Aeronave'
             LEFT JOIN controles c ON m.equipamento_id = c.id AND m.equipamento_tipo = 'Controle'
             LEFT JOIN aeronaves a_vinc ON c.aeronave_id = a_vinc.id
             ORDER BY m.data_manutencao DESC"; // Ordena pela data mais recente

$result_historico = $conn->query($sql_historico);
if ($result_historico) {
    while ($row = $result_historico->fetch_assoc()) {
        $historico_manutencoes[] = $row;
    }
} else {
    die("Erro ao buscar dados de manutenções: " . $conn->error);
}
$conn->close();

// 3. Classe para criar o PDF
class PDF extends FPDF
{
    // Cabeçalho
    function Header()
    {
        $this->SetFont('Arial','B',15);
        $this->Cell(0,10,utf8_decode('Relatório de Manutenções'),0,1,'C');
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
$pdf = new PDF('L'); // Layout Paisagem (Landscape)
$pdf->AliasNbPages(); // Habilita a contagem total de páginas no rodapé
$pdf->AddPage();
$pdf->SetFont('Arial','B',8); // Reduzindo fonte para caber mais informação

// Definição das larguras das colunas (Coluna 'Valor' removida)
$w = [25, 60, 30, 45, 30, 60]; // 6 colunas, sem a de Valor

// Cabeçalho da Tabela (Coluna 'Valor (R$)' removida)
$header = ['Data', 'Equipamento', 'Tipo', 'Responsável', 'Garantia até', 'Descrição'];
for($i=0; $i<count($header); $i++) {
    $pdf->Cell($w[$i], 7, utf8_decode($header[$i]), 1, 0, 'C');
}
$pdf->Ln();

// Dados da Tabela
$pdf->SetFont('Arial','',7); // Fonte menor para os dados
if (!empty($historico_manutencoes)) {
    foreach($historico_manutencoes as $manutencao) {
        $start_x_row = $pdf->GetX(); // Armazena a posição X inicial da linha
        $start_y_row = $pdf->GetY(); // Armazena a posição Y inicial da linha
        $line_height_multicell = 4; // Altura base da linha para MultiCell

        $equipamento_text = '';
        if ($manutencao['equipamento_tipo'] == 'Aeronave') {
            $equipamento_text = 'Aeronave: ' . ($manutencao['aeronave_prefixo'] ?? 'N/A') . ' - ' . ($manutencao['aeronave_modelo'] ?? 'N/A');
        } else { // Controle
            $vinculo = !empty($manutencao['controle_vinculado_a']) ? ' (Vinc. a ' . ($manutencao['controle_vinculado_a']) . ')' : ' (Reserva)';
            $equipamento_text = 'Controle: S/N: ' . ($manutencao['controle_sn'] ?? 'N/A') . ' ' . $vinculo;
        }

        $descricao_text = $manutencao['descricao'] ?? '';
        
        // Calcular altura real necessária para as MultiCells para determinar a altura da linha da tabela
        // Salva as margens originais para o cálculo
        $original_l_margin_calc = $pdf->GetLeftMargin();
        $original_r_margin_calc = $pdf->GetRightMargin();

        $temp_x_calc = $pdf->GetX(); 
        $temp_y_calc = $pdf->GetY(); 

        // Calcular altura do Equipamento MultiCell
        $pdf->SetLeftMargin($temp_x_calc + $w[0]); 
        $pdf->SetRightMargin($pdf->GetPageWidth() - ($temp_x_calc + $w[0] + $w[1])); 
        $pdf->MultiCell($w[1], $line_height_multicell, utf8_decode($equipamento_text), 0, 'C', false);
        $h1 = $pdf->GetY() - $temp_y_calc;
        $pdf->SetXY($temp_x_calc, $temp_y_calc); // Restaura X e Y após cálculo
        $pdf->SetLeftMargin($original_l_margin_calc); 
        $pdf->SetRightMargin($original_r_margin_calc); 

        // Calcular altura da Descrição MultiCell
        $pdf->SetLeftMargin($temp_x_calc + array_sum(array_slice($w, 0, 5))); // Soma larguras das colunas antes da descrição
        $pdf->SetRightMargin($pdf->GetPageWidth() - ($temp_x_calc + array_sum(array_slice($w, 0, 5)) + $w[5]));
        $pdf->MultiCell($w[5], $line_height_multicell, utf8_decode($descricao_text), 0, 'C', false);
        $h2 = $pdf->GetY() - $temp_y_calc;
        $pdf->SetXY($temp_x_calc, $temp_y_calc); // Restaura X e Y após cálculo
        $pdf->SetLeftMargin($original_l_margin_calc);
        $pdf->SetRightMargin($original_r_margin_calc);

        $row_height = max($h1, $h2, 6); 

        // Adicionar uma nova página se a linha atual exceder o limite
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

        // Salvar as margens do documento no início da linha para restaurar no final
        $doc_original_l_margin = $pdf->GetLeftMargin();
        $doc_original_r_margin = $pdf->GetRightMargin();
        
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


        // --- Desenhar Conteúdo das Células (sem bordas) ---
        // Célula 1: Data
        $pdf->Cell($w[0], $row_height, date("d/m/Y", strtotime($manutencao['data_manutencao'])), 0, 0, 'C');
        
        // Célula 2: Equipamento (MultiCell)
        $x_equipamento_content = $start_x_row + $w[0];
        $y_equipamento_content = $start_y_row;
        $pdf->SetXY($x_equipamento_content, $y_equipamento_content); 
        $current_l_margin_temp = $pdf->GetLeftMargin();
        $current_r_margin_temp = $pdf->GetRightMargin();
        $pdf->SetLeftMargin($x_equipamento_content);
        $pdf->SetRightMargin($pdf->GetPageWidth() - ($x_equipamento_content + $w[1]));
        $pdf->MultiCell($w[1], $line_height_multicell, utf8_decode($equipamento_text), 0, 'C', false); // Alinhado ao centro
        $pdf->SetLeftMargin($current_l_margin_temp);
        $pdf->SetRightMargin($current_r_margin_temp);
        $pdf->SetY($y_equipamento_content); 
        $pdf->SetX($x_equipamento_content + $w[1]); 
        
        // Célula 3: Tipo
        $pdf->Cell($w[2], $row_height, utf8_decode($manutencao['tipo_manutencao']), 0, 0, 'C');

        // Célula 4: Responsável
        $pdf->Cell($w[3], $row_height, utf8_decode($manutencao['responsavel']), 0, 0, 'C');

        // Célula 5: Garantia até
        $garantia = !empty($manutencao['garantia_ate']) ? date("d/m/Y", strtotime($manutencao['garantia_ate'])) : 'N/A';
        $pdf->Cell($w[4], $row_height, utf8_decode($garantia), 0, 0, 'C');

        // Célula 6: Descrição (MultiCell)
        $x_descricao_content = $start_x_row + $w[0] + $w[1] + $w[2] + $w[3] + $w[4];
        $y_descricao_content = $start_y_row;
        $pdf->SetXY($x_descricao_content, $y_descricao_content); 
        $current_l_margin_temp = $pdf->GetLeftMargin();
        $current_r_margin_temp = $pdf->GetRightMargin();
        $pdf->SetLeftMargin($x_descricao_content);
        $pdf->SetRightMargin($pdf->GetPageWidth() - ($x_descricao_content + $w[5]));
        $pdf->MultiCell($w[5], $line_height_multicell, utf8_decode($descricao_text), 0, 'C', false); // Alinhado ao centro
        $pdf->SetLeftMargin($current_l_margin_temp);
        $pdf->SetRightMargin($current_r_margin_temp);
        $pdf->SetY($y_descricao_content); 
        $pdf->SetX($x_descricao_content + $w[5]); 

        // Restaurar margens originais do documento após desenhar o conteúdo da linha
        $pdf->SetLeftMargin($doc_original_l_margin);
        $pdf->SetRightMargin($doc_original_r_margin);

        // Move para a próxima linha, com base na altura calculada da linha atual
        $pdf->SetY($start_y_row + $row_height);
        $pdf->SetX($pdf->GetLeftMargin()); // Garante que a próxima linha comece na margem esquerda
    }
} else {
    // Colspan ajustado para o número de colunas (6)
    $pdf->Cell(array_sum($w), 10, utf8_decode('Nenhum registro de manutenção encontrado'), 1, 1, 'C');
}

// Linha de fechamento da tabela (apenas a borda inferior)
$pdf->Cell(array_sum($w),0,'','T');

// 5. Saída do PDF
$pdf->Output('I', 'Relatorio_Manutencoes_SOARP.pdf');
?>