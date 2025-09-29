<?php
// 1. Incluir APENAS o inicializador PHP, sem HTML
require_once 'includes/init.php';
require_once 'libs/fpdf/fpdf.php';

// 2. Bloco de Segurança
if (!$isAdmin && !$isSuperAdmin) {
    die('Acesso negado. Você não tem permissão para gerar este relatório.');
}

// 3. Lógica para buscar os dados das manutenções
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$historico_manutencoes = [];
try {
    $params = [];
    $types = '';
    $sql_historico = "SELECT 
                        m.*, 
                        a.prefixo AS aeronave_prefixo, a.modelo AS aeronave_modelo,
                        c.numero_serie AS controle_sn, c.modelo AS controle_modelo,
                        a_vinc.prefixo AS controle_vinculado_a
                     FROM manutencoes m 
                     LEFT JOIN aeronaves a ON m.equipamento_id = a.id AND m.equipamento_tipo = 'Aeronave'
                     LEFT JOIN controles c ON m.equipamento_id = c.id AND m.equipamento_tipo = 'Controle'
                     LEFT JOIN aeronaves a_vinc ON c.aeronave_id = a_vinc.id";

    if ($isAdmin && !$isSuperAdmin && !empty($user_forca_seguranca)) {
        $sql_historico .= "
            JOIN (
                SELECT a_filter.id FROM aeronaves a_filter
                JOIN unidades u_filter ON a_filter.crbm = u_filter.nome_unidade
                WHERE u_filter.forca_sigla = ?
            ) AS aeronaves_da_forca ON a.id = aeronaves_da_forca.id OR a_vinc.id = aeronaves_da_forca.id
        ";
        $params[] = $user_forca_seguranca;
        $types .= 's';
    }
    $sql_historico .= " ORDER BY m.data_manutencao DESC";

    $stmt = $conn->prepare($sql_historico);
    if ($stmt) {
        if (!empty($params)) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $historico_manutencoes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
} catch (Exception $e) {
    die("Erro ao buscar dados de manutenções: " . $e->getMessage());
}
$conn->close();

// 4. Classe PDF com método para calcular altura da MultiCell
class PDF extends FPDF
{
    function Header() {
        $this->SetFont('Arial','B',15);
        $this->Cell(0,10,utf8_decode('Relatório de Manutenções'),0,1,'C');
        $this->Ln(10); 
    }

    function Footer() {
        $this->SetY(-15); 
        $this->SetFont('Arial','I',8);
        $this->Cell(0,10,utf8_decode('Página ').$this->PageNo().'/{nb}',0,0,'C');
    }

    // Função para calcular o número de linhas de uma MultiCell
    function NbLines($w, $txt) {
        $cw = &$this->CurrentFont['cw'];
        if($w == 0) $w = $this->w - $this->rMargin - $this->x;
        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
        $s = str_replace("\r", '', $txt);
        $nb = strlen($s);
        if($nb > 0 && $s[$nb-1] == "\n") $nb--;
        $sep = -1; $i = 0; $j = 0; $l = 0; $nl = 1;
        while($i < $nb) {
            $c = $s[$i];
            if($c == "\n") {
                $i++; $sep = -1; $j = $i; $l = 0; $nl++;
                continue;
            }
            if($c == ' ') $sep = $i;
            $l += $cw[ord($c)];
            if($l > $wmax) {
                if($sep == -1) {
                    if($i == $j) $i++;
                } else $i = $sep + 1;
                $sep = -1; $j = $i; $l = 0; $nl++;
            } else $i++;
        }
        return $nl;
    }
}

// 5. Geração do PDF
$pdf = new PDF('L', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial','B',8);

$w = [22, 75, 25, 45, 25, 85];
$header = ['Data', 'Equipamento', 'Tipo', 'Responsável', 'Garantia até', 'Descrição'];
for($i=0; $i<count($header); $i++) {
    $pdf->Cell($w[$i], 7, utf8_decode($header[$i]), 1, 0, 'C');
}
$pdf->Ln();

$pdf->SetFont('Arial','',7);
$line_height_multicell = 4;

if (!empty($historico_manutencoes)) {
    foreach($historico_manutencoes as $manutencao) {
        $equipamento_text = '';
        if ($manutencao['equipamento_tipo'] == 'Aeronave') {
            $equipamento_text = 'Aeronave: ' . ($manutencao['aeronave_prefixo'] ?? 'N/A') . ' - ' . ($manutencao['aeronave_modelo'] ?? 'N/A');
        } else { 
            $vinculo = !empty($manutencao['controle_vinculado_a']) ? ' (Vinc. a ' . $manutencao['controle_vinculado_a'] . ')' : ' (Reserva)';
            $equipamento_text = 'Controle: S/N ' . ($manutencao['controle_sn'] ?? 'N/A') . $vinculo;
        }
        $descricao_text = utf8_decode($manutencao['descricao'] ?? '');
        $equipamento_text = utf8_decode($equipamento_text);

        // Calcula a altura da linha
        $nb_equip = $pdf->NbLines($w[1], $equipamento_text);
        $nb_desc = $pdf->NbLines($w[5], $descricao_text);
        $row_height = max($nb_equip, $nb_desc) * $line_height_multicell;
        if($row_height < 6) $row_height = 6; // Altura mínima

        // Verifica quebra de página
        if($pdf->GetY() + $row_height > $pdf->GetPageHeight() - $pdf->bMargin) {
            $pdf->AddPage();
            $pdf->SetFont('Arial','B',8); 
            for($i=0; $i<count($header); $i++) {
                $pdf->Cell($w[$i], 7, utf8_decode($header[$i]), 1, 0, 'C');
            }
            $pdf->Ln();
            $pdf->SetFont('Arial','',7);
        }
        
        // =================================================================
        // LÓGICA DE DESENHO SIMPLIFICADA E CORRIGIDA
        // =================================================================
        $startX = $pdf->GetX();
        $startY = $pdf->GetY();
        
        $pdf->Cell($w[0], $row_height, date("d/m/Y", strtotime($manutencao['data_manutencao'])), 1, 0, 'C');
        
        $pdf->MultiCell($w[1], $line_height_multicell, $equipamento_text, 1, 'C');
        $pdf->SetXY($startX + $w[0] + $w[1], $startY); // Reposiciona para a próxima célula
        
        $pdf->Cell($w[2], $row_height, utf8_decode($manutencao['tipo_manutencao']), 1, 0, 'C');
        $pdf->Cell($w[3], $row_height, utf8_decode($manutencao['responsavel']), 1, 0, 'C');
        
        $garantia = !empty($manutencao['garantia_ate']) ? date("d/m/Y", strtotime($manutencao['garantia_ate'])) : 'N/A';
        $pdf->Cell($w[4], $row_height, utf8_decode($garantia), 1, 0, 'C');
        
        $currentX = $pdf->GetX(); // Guarda a posição X antes da última MultiCell
        $pdf->MultiCell($w[5], $line_height_multicell, $descricao_text, 1, 'C');
        
        $pdf->SetY($startY + $row_height); // Move para a linha de baixo
    }
} else {
    $pdf->Cell(array_sum($w), 10, utf8_decode('Nenhum registro de manutenção encontrado'), 1, 1, 'C');
}

$pdf->Output('I', 'Relatorio_Manutencoes_SOARP.pdf');
?>