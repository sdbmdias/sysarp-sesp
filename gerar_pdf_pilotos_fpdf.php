<?php
// 1. Incluir os arquivos necessários (caminho corrigido)
require_once 'includes/database.php';
require_once 'libs/fpdf/fpdf.php';

// 2. Lógica para buscar os dados dos pilotos (mesma lógica dos filtros)
$where_clauses = [];
$params = [];
$types = '';
$report_title = "Lista de Pilotos"; // Título padrão

$sql = "SELECT posto_graduacao, nome_completo, cpf, crbm_piloto, obm_piloto, status_piloto FROM pilotos";

if (!empty($_GET['graduacao'])) { $where_clauses[] = "posto_graduacao = ?"; $params[] = $_GET['graduacao']; $types .= 's'; }
if (!empty($_GET['crbm_piloto'])) { $where_clauses[] = "crbm_piloto = ?"; $params[] = $_GET['crbm_piloto']; $types .= 's'; }
if (!empty($_GET['obm_piloto'])) { $where_clauses[] = "obm_piloto = ?"; $params[] = $_GET['obm_piloto']; $types .= 's'; }
if (!empty($_GET['status_piloto'])) { $where_clauses[] = "status_piloto = ?"; $params[] = $_GET['status_piloto']; $types .= 's'; }

if (count($where_clauses) > 0) {
    $sql .= " WHERE " . implode(' AND ', $where_clauses);
}

// Ordenação consistente com a página de relatórios
$sql .= " ORDER BY CASE posto_graduacao WHEN 'Cel. QOBM' THEN 1 WHEN 'Ten. Cel. QOBM' THEN 2 WHEN 'Maj. QOBM' THEN 3 WHEN 'Cap. QOBM' THEN 4 WHEN '1º Ten. QOBM' THEN 5 WHEN '2º Ten. QOBM' THEN 6 WHEN 'Asp. Oficial' THEN 7 WHEN 'Sub. Ten. QPBM' THEN 8 WHEN '1º Sgt. QPBM' THEN 9 WHEN '2º Sgt. QPBM' THEN 10 WHEN '3º Sgt. QPBM' THEN 11 WHEN 'Cb. QPBM' THEN 12 WHEN 'Sd. QPBM' THEN 13 ELSE 14 END, nome_completo ASC";

$stmt = $conn->prepare($sql);
if ($stmt) {
    if (count($params) > 0) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $resultados = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    die("Erro na preparação da consulta SQL.");
}
$conn->close();

// 3. Classe para criar o PDF com Cabeçalho e Rodapé personalizados
class PDF extends FPDF
{
    // Cabeçalho
    function Header()
    {
        $this->SetFont('Arial','B',15);
        $this->Cell(80); // Mover para a direita
        $this->Cell(30,10,utf8_decode('Relatório de Pilotos'),0,0,'C');
        $this->Ln(20); // Pular linha
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
$pdf = new PDF();
$pdf->AliasNbPages(); // Habilita a contagem total de páginas no rodapé
$pdf->AddPage();
$pdf->SetFont('Arial','B',9);

// Definição das larguras das colunas
$w = [35, 60, 25, 20, 20, 25];

// Cabeçalho da Tabela
$header = ['Posto/Grad.', 'Nome Completo', 'CPF', 'CRBM', 'OBM', 'Status'];
for($i=0; $i<count($header); $i++) {
    $pdf->Cell($w[$i], 7, utf8_decode($header[$i]), 1, 0, 'C'); // Cabeçalhos já centralizados
}
$pdf->Ln();

// Dados da Tabela
$pdf->SetFont('Arial','',8);
$row_height = 6; // Altura padrão da linha de dados

if (!empty($resultados)) {
    foreach($resultados as $row) {
        $start_x_row = $pdf->GetX();
        $start_y_row = $pdf->GetY();

        // Adicionar uma nova página se a linha atual exceder o limite
        if($pdf->GetY() + $row_height > ($pdf->GetPageHeight() - $pdf->GetAutoPageBreakMargin())) { 
            $pdf->AddPage();
            $pdf->SetFont('Arial','B',9); // Restaura fonte para cabeçalho
            for($i=0; $i<count($header); $i++) {
                $pdf->Cell($w[$i], 7, utf8_decode($header[$i]), 1, 0, 'C');
            }
            $pdf->Ln();
            $pdf->SetFont('Arial','',8); // Restaura fonte para dados
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
        // Célula 1: Posto/Grad.
        $pdf->Cell($w[0], $row_height, utf8_decode($row['posto_graduacao']), 0, 0, 'C'); // Alinhado ao centro
        
        // Célula 2: Nome Completo
        $pdf->Cell($w[1], $row_height, utf8_decode($row['nome_completo']), 0, 0, 'C'); // Alinhado ao centro

        // Célula 3: CPF
        $pdf->Cell($w[2], $row_height, utf8_decode(substr($row['cpf'], 0, 3) . '.XXX.XXX-' . substr($row['cpf'], -2)), 0, 0, 'C'); // Alinhado ao centro
        
        // Célula 4: CRBM
        $pdf->Cell($w[3], $row_height, utf8_decode($row['crbm_piloto']), 0, 0, 'C'); // Já centralizado, mantido 0 border
        
        // Célula 5: OBM
        $pdf->Cell($w[4], $row_height, utf8_decode($row['obm_piloto']), 0, 0, 'C'); // Já centralizado, mantido 0 border
        
        // Célula 6: Status
        $pdf->Cell($w[5], $row_height, utf8_decode(ucfirst($row['status_piloto'])), 0, 0, 'C'); // Já centralizado, mantido 0 border
        
        $pdf->Ln($row_height); // Pular linha com a altura calculada
    }
} else {
    $pdf->Cell(array_sum($w), 10, utf8_decode('Nenhum resultado encontrado'), 1, 1, 'C');
}

// Linha de fechamento da tabela
$pdf->Cell(array_sum($w),0,'','T');

// 5. Saída do PDF
$pdf->Output('I', 'Relatorio_Pilotos_SOARP.pdf');
?>