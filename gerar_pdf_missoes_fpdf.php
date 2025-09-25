<?php
// 1. Incluir os arquivos necessários
require_once 'includes/database.php';
require_once 'libs/fpdf/fpdf.php';

// 2. Lógica para buscar os dados das missões
$missoes = [];
$sql_missoes = "
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
            SEPARATOR '\n') AS pilotos_nomes /* Usar \n para quebras de linha no PDF */
    FROM missoes m
    JOIN aeronaves a ON m.aeronave_id = a.id
    LEFT JOIN missoes_pilotos mp ON m.id = mp.missao_id
    LEFT JOIN pilotos p ON mp.piloto_id = p.id
    GROUP BY m.id
    ORDER BY m.data DESC, m.id DESC
";

$result_missoes = $conn->query($sql_missoes);
if ($result_missoes) {
    while($row = $result_missoes->fetch_assoc()) {
        $missoes[] = $row;
    }
} else {
    die("Erro ao buscar dados das missões: " . $conn->error);
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

// 3. Classe para criar o PDF
class PDF extends FPDF
{
    // Cabeçalho
    function Header()
    {
        $this->SetFont('Arial','B',15);
        $this->Cell(0,10,utf8_decode('Relatório de Missões'),0,1,'C');
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
$pdf->SetFont('Arial','B',8); // Reduzindo fonte para caber mais informação

// Larguras das colunas
$w = [25, 35, 60, 80, 20, 20]; // Ajustado para layout Paisagem

// Cabeçalho da Tabela
$header = ['Data', 'Aeronave', 'Piloto(s)', 'Operação (RG/Descrição)', 'Tempo Voo', 'Distância'];
for($i=0; $i<count($header); $i++) {
    $pdf->Cell($w[$i], 7, utf8_decode($header[$i]), 1, 0, 'C');
}
$pdf->Ln();

// Dados da Tabela
$pdf->SetFont('Arial','',7); // Fonte menor para os dados
if (!empty($missoes)) {
    foreach($missoes as $missao) {
        $start_x_row = $pdf->GetX(); // Armazena a posição X inicial da linha
        $start_y_row = $pdf->GetY(); // Armazena a posição Y inicial da linha
        $line_height_multicell = 4; // Altura padrão de linha para MultiCell

        // Preparar textos para MultiCell
        $pilotos_text = utf8_decode($missao['pilotos_nomes'] ?? 'Nenhum piloto associado');
        $operacao_text = utf8_decode((empty($missao['rgo_ocorrencia']) ? 'MISSÃO SEM RGO' : $missao['rgo_ocorrencia']) . "\n" . $missao['descricao_operacao']);
        
        // Calcular altura real necessária para as MultiCells para determinar a altura da linha da tabela
        // Salva as margens originais para o cálculo
        $original_l_margin_calc = $pdf->GetLeftMargin();
        $original_r_margin_calc = $pdf->GetRightMargin();

        $temp_x_calc = $pdf->GetX(); 
        $temp_y_calc = $pdf->GetY(); 

        // Calcular altura dos pilotos
        $pdf->SetLeftMargin($temp_x_calc + $w[0] + $w[1]); // Define temporariamente a margem esquerda para a coluna de cálculo
        $pdf->SetRightMargin($pdf->GetPageWidth() - ($temp_x_calc + $w[0] + $w[1] + $w[2])); // Define temporariamente a margem direita para a coluna de cálculo
        $pdf->MultiCell($w[2], $line_height_multicell, $pilotos_text, 0, 'C', false);
        $height_pilotos = $pdf->GetY() - $temp_y_calc;
        $pdf->SetXY($temp_x_calc, $temp_y_calc); // Restaura X e Y após cálculo
        // Restaura as margens originais após o cálculo
        $pdf->SetLeftMargin($original_l_margin_calc); 
        $pdf->SetRightMargin($original_r_margin_calc); 

        // Calcular altura da operação
        $pdf->SetLeftMargin($temp_x_calc + $w[0] + $w[1] + $w[2]); // Define temporariamente a margem esquerda para a coluna de cálculo
        $pdf->SetRightMargin($pdf->GetPageWidth() - ($temp_x_calc + $w[0] + $w[1] + $w[2] + $w[3])); // Define temporariamente a margem direita para a coluna de cálculo
        $pdf->MultiCell($w[3], $line_height_multicell, $operacao_text, 0, 'C', false);
        $height_operacao = $pdf->GetY() - $temp_y_calc;
        $pdf->SetXY($temp_x_calc, $temp_y_calc); // Restaura X e Y após cálculo
        // Restaura as margens originais após o cálculo
        $pdf->SetLeftMargin($original_l_margin_calc);
        $pdf->SetRightMargin($original_r_margin_calc);

        $row_height = max($height_pilotos, $height_operacao, 6); // Garante altura mínima para uma linha simples (ex: 6)

        // Adicionar uma nova página se a linha atual exceder o limite
        if($pdf->GetY() + $row_height > ($pdf->GetPageHeight() - $pdf->GetAutoPageBreakMargin())) { 
            $pdf->AddPage();
            $pdf->SetFont('Arial','B',8); // Restaura fonte para cabeçalho
            for($i=0; $i<count($header); $i++) {
                $pdf->Cell($w[$i], 7, utf8_decode($header[$i]), 1, 0, 'C');
            }
            $pdf->Ln();
            $pdf->SetFont('Arial','',7); // Restaura fonte para dados
            $start_x_row = $pdf->GetX(); // Redefine start_x/y_row após nova página
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
        $pdf->Cell($w[0], $row_height, date("d/m/Y", strtotime($missao['data'])), 0, 0, 'C'); 

        // Célula 2: Aeronave
        $pdf->Cell($w[1], $row_height, utf8_decode($missao['aeronave_prefixo']), 0, 0, 'C');

        // Célula 3: Piloto(s) (MultiCell)
        $x_pilotos_content = $start_x_row + $w[0] + $w[1];
        $y_pilotos_content = $start_y_row;
        $pdf->SetXY($x_pilotos_content, $y_pilotos_content); // Posiciona no início da célula da coluna de pilotos
        // Salva as margens originais para restaurar após a MultiCell
        $original_l_margin_temp = $pdf->GetLeftMargin();
        $original_r_margin_temp = $pdf->GetRightMargin();
        $pdf->SetLeftMargin($x_pilotos_content); // Define temporariamente a margem esquerda para a coluna atual
        $pdf->SetRightMargin($pdf->GetPageWidth() - ($x_pilotos_content + $w[2])); // Define temporariamente a margem direita
        $pdf->MultiCell($w[2], $line_height_multicell, $pilotos_text, 0, 'C', false); // Desenha MultiCell sem bordas
        $pdf->SetLeftMargin($original_l_margin_temp); // Restaura a margem esquerda original
        $pdf->SetRightMargin($original_r_margin_temp); // Restaura a margem direita original
        // Volta o Y para o topo da linha e avança o X para o final da coluna para a próxima célula
        $pdf->SetY($y_pilotos_content); 
        $pdf->SetX($x_pilotos_content + $w[2]); 

        // Célula 4: Operação (MultiCell)
        $x_operacao_content = $start_x_row + $w[0] + $w[1] + $w[2];
        $y_operacao_content = $start_y_row;
        $pdf->SetXY($x_operacao_content, $y_operacao_content); // Posiciona no início da célula da coluna de operação
        // Salva as margens originais para restaurar após a MultiCell
        $original_l_margin_temp = $pdf->GetLeftMargin();
        $original_r_margin_temp = $pdf->GetRightMargin();
        $pdf->SetLeftMargin($x_operacao_content); // Define temporariamente a margem esquerda para a coluna atual
        $pdf->SetRightMargin($pdf->GetPageWidth() - ($x_operacao_content + $w[3])); // Define temporariamente a margem direita
        $pdf->MultiCell($w[3], $line_height_multicell, $operacao_text, 0, 'C', false); // Desenha MultiCell sem bordas
        $pdf->SetLeftMargin($original_l_margin_temp); // Restaura a margem esquerda original
        $pdf->SetRightMargin($original_r_margin_temp); // Restaura a margem direita original
        // Volta o Y para o topo da linha e avança o X para o final da coluna para a próxima célula
        $pdf->SetY($y_operacao_content); 
        $pdf->SetX($x_operacao_content + $w[3]); 

        // Célula 5: Tempo de Voo
        $pdf->Cell($w[4], $row_height, formatarTempoVooPDF($missao['total_tempo_voo']), 0, 0, 'C');

        // Célula 6: Distância
        // CONVERSÃO DE METROS PARA KM E FORMATAÇÃO AJUSTADA
        $distancia_km = $missao['total_distancia_percorrida'] / 1000; // Converte metros para quilômetros
        $distancia_formatada = number_format($distancia_km, 2, ',', '') . ' km'; // Formata para 2 decimais, vírgula, sem milhar
        $pdf->Cell($w[5], $row_height, $distancia_formatada, 0, 0, 'C');

        // Move para a próxima linha, com base na altura calculada da linha atual
        $pdf->SetY($start_y_row + $row_height);
        $pdf->SetX($pdf->GetLeftMargin()); // Garante que a próxima linha comece na margem esquerda
    }
} else {
    $pdf->Cell(array_sum($w), 10, utf8_decode('Nenhuma missão encontrada'), 1, 1, 'C');
}

// Linha de fechamento da tabela (apenas a borda inferior)
$pdf->Cell(array_sum($w),0,'','T');

// 5. Saída do PDF
$pdf->Output('I', 'Relatorio_Missoes_SOARP.pdf');
?>