<?php
// Inclui os arquivos necessários
require_once 'includes/database.php';
require_once 'libs/fpdf/fpdf.php';

// Obtém o ID da missão da URL
$missao_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($missao_id <= 0) {
    // Redireciona de volta se o ID for inválido
    header("Location: listar_missoes.php");
    exit();
}

// Lógica para buscar o posto e a graduação do piloto logado
$logged_in_pilot_info = '';
// Assume que includes/header.php já foi incluído e que $_SESSION está disponível
if (isset($_SESSION['user_id'])) {
    $stmt_user_info = $conn->prepare("SELECT posto_graduacao, nome_completo FROM pilotos WHERE id = ?");
    if ($stmt_user_info) {
        $stmt_user_info->bind_param("i", $_SESSION['user_id']);
        $stmt_user_info->execute();
        $result_user_info = $stmt_user_info->get_result();
        if ($result_user_info->num_rows > 0) {
            $user_data = $result_user_info->fetch_assoc();
            $logged_in_pilot_info = $user_data['posto_graduacao'] . ' ' . $user_data['nome_completo'];
        }
        $stmt_user_info->close();
    } else {
        error_log("Erro na preparação da consulta de informações do piloto logado: " . $conn->error);
    }
}


// 1. BUSCA OS DETALHES GERAIS DA MISSÃO
$missao_details = null;
$sql_details = "
    SELECT 
        m.*,
        a.prefixo AS aeronave_prefixo, a.modelo AS aeronave_modelo, a.obm AS aeronave_obm, a.id as aeronave_id
    FROM missoes m
    JOIN aeronaves a ON m.aeronave_id = a.id
    WHERE m.id = ?
";
$stmt_details = $conn->prepare($sql_details);
if ($stmt_details) {
    $stmt_details->bind_param("i", $missao_id);
    $stmt_details->execute();
    $result_details = $stmt_details->get_result();
    if ($result_details->num_rows === 1) {
        $missao_details = $result_details->fetch_assoc();
        
        // Busca o controle vinculado à aeronave da missão
        $controle_usado = null;
        $stmt_controle = $conn->prepare("SELECT modelo, numero_serie FROM controles WHERE aeronave_id = ?");
        if ($stmt_controle) {
            $stmt_controle->bind_param("i", $missao_details['aeronave_id']);
            $stmt_controle->execute();
            $result_controle = $stmt_controle->get_result();
            if($result_controle->num_rows > 0){
                $controle_usado = $result_controle->fetch_assoc();
            }
            $stmt_controle->close();
        }
    } else {
        // Missão não encontrada, redireciona
        header("Location: listar_missoes.php");
        exit();
    }
    $stmt_details->close();
} else {
    die("Erro na preparação da consulta de detalhes da missão: " . $conn->error);
}


// 2. BUSCA OS PILOTOS ENVOLVIDOS COM ORDENAÇÃO POR HIERARQUIA
$pilotos_envolvidos = [];
$sql_pilotos = "
    SELECT p.posto_graduacao, p.nome_completo 
    FROM missoes_pilotos mp
    JOIN pilotos p ON mp.piloto_id = p.id 
    WHERE mp.missao_id = ?
    ORDER BY
        CASE p.posto_graduacao
            WHEN 'Cel. QOBM' THEN 1 WHEN 'Ten. Cel. QOBM' THEN 2 WHEN 'Maj. QOBM' THEN 3
            WHEN 'Cap. QOBM' THEN 4 WHEN '1º Ten. QOBM' THEN 5 WHEN '2º Ten. QOBM' THEN 6
            WHEN 'Asp. Oficial' THEN 7 WHEN 'Sub. Ten. QPBM' THEN 8 WHEN '1º Sgt. QPBM' THEN 9
            WHEN '2º Sgt. QPBM' THEN 10 WHEN '3º Sgt. QPBM' THEN 11 WHEN 'Cb. QPBM' THEN 12
            WHEN 'Sd. QPBM' THEN 13 ELSE 14
        END
";
$stmt_pilotos = $conn->prepare($sql_pilotos);
if ($stmt_pilotos) {
    $stmt_pilotos->bind_param("i", $missao_id);
    $stmt_pilotos->execute();
    $result_pilotos = $stmt_pilotos->get_result();
    while ($row = $result_pilotos->fetch_assoc()) {
        $pilotos_envolvidos[] = $row['posto_graduacao'] . ' ' . $row['nome_completo'];
    }
    $stmt_pilotos->close();
} else {
    die("Erro na preparação da consulta de pilotos: " . $conn->error);
}


// 3. BUSCA OS LOGS E COORDENADAS
$gpx_files_logs = [];
$sql_gpx = "SELECT id, file_name, tempo_voo, distancia_percorrida, altura_maxima, data_decolagem, data_pouso FROM missoes_gpx_files WHERE missao_id = ? ORDER BY data_decolagem ASC";
$stmt_gpx = $conn->prepare($sql_gpx);
if ($stmt_gpx) {
    $stmt_gpx->bind_param("i", $missao_id);
    $stmt_gpx->execute();
    $result_gpx = $stmt_gpx->get_result();
    while ($gpx_file = $result_gpx->fetch_assoc()) {
        $gpx_files_logs[] = $gpx_file;
    }
    $stmt_gpx->close();
} else {
    die("Erro na preparação da consulta de logs GPX: " . $conn->error);
}

// Funções de formatação (copiadas de ver_missao.php)
function formatarTempoVooCompleto($segundos) {
    if ($segundos <= 0) return '0min';
    $horas = floor($segundos / 3600);
    $minutos = floor(($segundos % 3600) / 60);
    $resultado = '';
    if ($horas > 0) $resultado .= $horas . 'h ';
    if ($minutos > 0) $resultado .= $minutos . 'min';
    return trim($resultado) ?: '0min';
}

function formatarDistancia($metros) {
    // Convertendo para KM e formatando com duas casas decimais, vírgula e sem separador de milhares.
    $distancia_km = $metros / 1000;
    return number_format($distancia_km, 2, ',', '') . ' km';
}

$conn->close(); // Fecha a conexão após todas as consultas

// -----------------------------------------------------------
// Geração do PDF
// -----------------------------------------------------------
class PDF extends FPDF
{
    // Cabeçalho do PDF
    function Header()
    {
        global $missao_details; // Acessa os detalhes da missão globalmente
        global $logged_in_pilot_info; // Acessa as informações do piloto logado

        $this->SetFont('Arial','B',14);
        $title = utf8_decode('Detalhes da Missão '); 
        if (!empty($missao_details['rgo_ocorrencia'])) {
            $title .= 'RGO ' . utf8_decode($missao_details['rgo_ocorrencia']);
        } else {
            $title .= '#' . $missao_details['id'];
        }
        $this->Cell(0,10, $title,0,1,'C');
        
        $this->SetFont('Arial','',9);
        $this->Cell(0,7,utf8_decode('Gerado em: ').date('d/m/Y H:i:s'),0,1,'C');
        
        // NOVO: Linha com informações do piloto logado
        if (!empty($logged_in_pilot_info)) {
            $this->Cell(0,7,utf8_decode('Por: ') . utf8_decode($logged_in_pilot_info),0,1,'C');
        }
        $this->Ln(10); // Ajusta o espaçamento após as novas linhas
    }

    // Rodapé do PDF
    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial','I',8);
        $this->Cell(0,10,utf8_decode('Página ').$this->PageNo().'/{nb}',0,0,'C');
    }

    // Método para adicionar um item de detalhe (título e valor)
    function AddDetailItem($title, $content, $isMultiLine = false) {
        $this->SetFont('Arial','B',9);
        $this->Cell(0, 7, utf8_decode($title), 0, 1, 'L'); // Título do detalhe
        $this->SetFont('Arial','',10);
        if ($isMultiLine) {
            $this->MultiCell(0, 6, utf8_decode($content), 0, 'L'); // Conteúdo pode ter múltiplas linhas
        } else {
            $this->Cell(0, 6, utf8_decode($content), 0, 1, 'L'); // Conteúdo em uma única linha
        }
        $this->Ln(2); // Pequeno espaço entre itens
    }

    // Método para desenhar um campo em duas colunas (título forte, valor normal)
    function DetailField($label, $value, $colWidth, $isMultiLine = false) {
        $startX = $this->GetX();
        $startY = $this->GetY();
        
        $this->SetFont('Arial', 'B', 9);
        $this->Write(5, utf8_decode($label . ': ')); // Rótulo em negrito
        
        $this->SetFont('Arial', '', 10);
        $currentX = $this->GetX(); // Captura X após o rótulo
        $remainingWidth = $colWidth - ($currentX - $startX); // Largura restante da coluna
        
        if ($isMultiLine) {
            // Temporariamente ajusta margem para MultiCell
            $originalLMargin = $this->GetLeftMargin();
            $originalRMargin = $this->GetRightMargin();
            $this->SetLeftMargin($currentX);
            $this->SetRightMargin($this->GetPageWidth() - ($startX + $colWidth));
            
            $this->MultiCell($remainingWidth, 5, utf8_decode($value), 0, 'L');
            
            $this->SetLeftMargin($originalLMargin);
            $this->SetRightMargin($originalRMargin);
            $this->SetY($startY + max(5, ($this->GetY() - $startY))); // Avança Y pela altura total do MultiCell
            $this->SetX($originalLMargin); // Reset X para a margem original
        } else {
            $this->Cell($remainingWidth, 5, utf8_decode($value), 0, 1, 'L');
        }
        $this->Ln(2); // Espaço entre os campos
    }

    // Métodos para acessar propriedades protegidas
    function GetAutoPageBreakMargin() { return $this->bMargin; }
    function GetLeftMargin() { return $this->lMargin; }
    function GetRightMargin() { return $this->rMargin; }
}

$pdf = new PDF('P', 'mm', 'A4'); // Usar A4 retrato
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetAutoPageBreak(true, 15); // Margem de 15mm para quebra de página

// Define a margem esquerda para o conteúdo principal (removendo espaço da sidebar)
$pdf->SetLeftMargin(15); 
$pdf->SetRightMargin(15);
$pdf->SetX($pdf->GetLeftMargin()); // Garante que o X comece na margem definida

// Conteúdo principal do PDF
if ($missao_details) {

    // -------------------------------------------
    // Detalhes da Operação
    // -------------------------------------------
    $pdf->SetFont('Arial','B',12);
    $pdf->Cell(0,10,utf8_decode('1. Detalhes da Operação'),0,1,'L'); 
    $pdf->SetFont('Arial','',10);
    $pdf->SetLeftMargin(20); // Recuo para os itens de detalhe
    
    $pdf->Cell(0, 6, utf8_decode('Data: ') . date("d/m/Y", strtotime($missao_details['data'])), 0, 1, 'L');
    $pdf->Cell(0, 6, utf8_decode('Nº RGO: ') . utf8_decode($missao_details['rgo_ocorrencia'] ?? 'Não informado'), 0, 1, 'L'); 
    $pdf->Cell(0, 6, utf8_decode('Descrição da Operação: ') . utf8_decode($missao_details['descricao_operacao']), 0, 1, 'L'); 
    $pdf->Cell(0, 6, utf8_decode('Protocolo SARPAS: ') . utf8_decode($missao_details['protocolo_sarpas'] ?? 'Não informado'), 0, 1, 'L'); 
    
    $forma_acionamento = utf8_decode($missao_details['forma_acionamento']);
    if($missao_details['forma_acionamento'] == utf8_decode('Outro') && !empty($missao_details['forma_acionamento_outro'])) { 
        $forma_acionamento .= ' (' . utf8_decode($missao_details['forma_acionamento_outro']) . ')';
    }
    $pdf->Cell(0, 6, utf8_decode('Forma de Acionamento: ') . $forma_acionamento, 0, 1, 'L');

    $contato_ats = utf8_decode($missao_details['contato_ats']);
    if($contato_ats == utf8_decode('Outro') && !empty($missao_details['contato_ats_outro'])) { 
        $contato_ats .= ' (' . utf8_decode($missao_details['contato_ats_outro']) . ')';
    }
    $pdf->Cell(0, 6, utf8_decode('Contato com o Órgão ATS: ') . $contato_ats, 0, 1, 'L'); 
    
    $pdf->SetFont('Arial','B',9);
    $pdf->Cell(0, 7, utf8_decode('Descrição do Ocorrido:'), 0, 1, 'L'); 
    $pdf->SetFont('Arial','',10);
    $pdf->MultiCell(0, 6, utf8_decode(!empty($missao_details['descricao_ocorrido']) ? $missao_details['descricao_ocorrido'] : 'Não informado'), 0, 'L'); 
    $pdf->Ln(5);

    // -------------------------------------------
    // Equipamentos e Pessoal
    // -------------------------------------------
    $pdf->SetLeftMargin(15); // Volta à margem padrão da seção
    $pdf->SetFont('Arial','B',12);
    $pdf->Cell(0,10,utf8_decode('2. Equipamentos e Pessoal'),0,1,'L'); 
    $pdf->SetFont('Arial','',10);
    $pdf->SetLeftMargin(20);

    $pdf->Cell(0, 6, utf8_decode('Aeronave: ') . utf8_decode($missao_details['aeronave_prefixo'] . ' - ' . $missao_details['aeronave_modelo']) . ' (' . utf8_decode($missao_details['aeronave_obm']) . ')', 0, 1, 'L');
    $controle_info = $controle_usado ? utf8_decode($controle_usado['modelo'] . ' - S/N: ' . $controle_usado['numero_serie']) : utf8_decode('Não informado'); 
    $pdf->Cell(0, 6, utf8_decode('Controle: ') . $controle_info, 0, 1, 'L');
    
    $pdf->SetFont('Arial','B',9);
    $pdf->Cell(0, 7, utf8_decode('Piloto(s) Envolvido(s):'), 0, 1, 'L'); 
    $pdf->SetFont('Arial','',10);
    if (empty($pilotos_envolvidos)) {
        $pdf->Cell(0, 6, utf8_decode('Nenhum piloto associado.'), 0, 1, 'L'); 
    } else {
        foreach ($pilotos_envolvidos as $piloto) {
            $pdf->Cell(0, 6, '- ' . utf8_decode($piloto), 0, 1, 'L');
        }
    }
    $pdf->Ln(5);

    // -------------------------------------------
    // Dados Complementares
    // -------------------------------------------
    $pdf->SetLeftMargin(15);
    $pdf->SetFont('Arial','B',12);
    $pdf->Cell(0,10,utf8_decode('3. Dados Complementares'),0,1,'L'); 
    $pdf->SetFont('Arial','',10);
    $pdf->SetLeftMargin(20);

    $link_fotos = !empty($missao_details['link_fotos_videos']) ? utf8_decode($missao_details['link_fotos_videos']) : utf8_decode('Não informado'); 
    $pdf->Cell(0, 6, utf8_decode('Link das Fotos/Vídeos: ') . $link_fotos, 0, 1, 'L'); 
    
    $pdf->SetFont('Arial','B',9);
    $pdf->Cell(0, 7, utf8_decode('Dados da Vítima/Alvo:'), 0, 1, 'L'); 
    $pdf->SetFont('Arial','',10);
    $pdf->MultiCell(0, 6, utf8_decode(!empty($missao_details['dados_vitima']) ? $missao_details['dados_vitima'] : 'Não informado'), 0, 'L'); 
    $pdf->Ln(5);

    // -------------------------------------------
    // Logs de Voo Individuais
    // -------------------------------------------
    $pdf->SetLeftMargin(15);
    $pdf->SetFont('Arial','B',12);
    $pdf->Cell(0,10,utf8_decode('4. Logs de Voo Individuais'),0,1,'L'); 
    $pdf->SetFont('Arial','',8);
    // REMOVIDO: Largura da coluna 'Ficheiro GPX' (40)
    // Larguras ajustadas para as 5 colunas restantes para somar 180mm (largura da página - margens)
    $log_w = [45, 45, 30, 30, 30]; 

    if (!empty($gpx_files_logs)) {
        // Cabeçalho da Tabela de Logs (Coluna 'Ficheiro GPX' removida)
        $log_header = [utf8_decode('Decolagem'), utf8_decode('Pouso'), utf8_decode('Duração'), utf8_decode('Distância'), utf8_decode('Altura Máx.')]; 
        foreach($log_header as $i => $col) {
            $pdf->Cell($log_w[$i], 7, $col, 1, 0, 'C'); 
        }
        $pdf->Ln();

        // Dados da Tabela de Logs
        foreach ($gpx_files_logs as $log) {
            $log_row_height = 6; // Altura padrão da linha
            // Quebra de página para logs individuais
            if($pdf->GetY() + $log_row_height > ($pdf->GetPageHeight() - $pdf->GetAutoPageBreakMargin())) {
                $pdf->AddPage();
                $pdf->SetFont('Arial','B',8);
                foreach($log_header as $i => $col) {
                    $pdf->Cell($log_w[$i], 7, $col, 1, 0, 'C');
                }
                $pdf->Ln();
                $pdf->SetFont('Arial','',8);
            }

            // REMOVIDO: Célula 'Ficheiro GPX' ($log_w[0])
            // Ajustado para usar os novos índices de $log_w
            $pdf->Cell($log_w[0], $log_row_height, date("d/m/Y H:i:s", strtotime($log['data_decolagem'])), 1, 0, 'C');
            $pdf->Cell($log_w[1], $log_row_height, date("d/m/Y H:i:s", strtotime($log['data_pouso'])), 1, 0, 'C');
            $pdf->Cell($log_w[2], $log_row_height, formatarTempoVooCompleto($log['tempo_voo']), 1, 0, 'C');
            $pdf->Cell($log_w[3], $log_row_height, formatarDistancia($log['distancia_percorrida']), 1, 0, 'C');
            $pdf->Cell($log_w[4], $log_row_height, round($log['altura_maxima'], 2) . utf8_decode(' m'), 1, 0, 'C'); 
            $pdf->Ln();
        }
    } else {
        // Colspan ajustado para o novo número de colunas (5)
        $pdf->Cell(array_sum($log_w), 10, utf8_decode('Nenhum log de voo individual encontrado.'), 1, 1, 'C'); 
    }
    $pdf->Ln(5);

    // -------------------------------------------
    // Log Total Consolidado da Missão
    // -------------------------------------------
    $pdf->SetLeftMargin(15);
    $pdf->SetFont('Arial','B',12);
    $pdf->Cell(0,10,utf8_decode('5. Log Total Consolidado da Missão'),0,1,'L'); 
    $pdf->SetFont('Arial','',10);
    $pdf->SetLeftMargin(20);

    $pdf->Cell(0, 6, utf8_decode('Primeira Decolagem: ') . date("d/m/Y H:i", strtotime($missao_details['data_primeira_decolagem'])), 0, 1, 'L');
    $pdf->Cell(0, 6, utf8_decode('Último Pouso: ') . date("d/m/Y H:i", strtotime($missao_details['data_ultimo_pouso'])), 0, 1, 'L');
    $pdf->Cell(0, 6, utf8_decode('Tempo Total de Voo: ') . formatarTempoVooCompleto($missao_details['total_tempo_voo']), 0, 1, 'L');
    $pdf->Cell(0, 6, utf8_decode('Distância Total Percorrida: ') . formatarDistancia($missao_details['total_distancia_percorrida']), 0, 1, 'L');
    $pdf->Cell(0, 6, utf8_decode('Altura Máxima Atingida na Missão: ') . round($missao_details['altitude_maxima'], 2) . utf8_decode(' m'), 0, 1, 'L'); 
    $pdf->Ln(5);

} else {
    // Caso a missão não seja encontrada (já redirecionado acima, mas como fallback)
    $pdf->SetFont('Arial','B',14);
    $pdf->Cell(0,10,utf8_decode('Erro: Missão não encontrada.'),0,1,'C'); 
}

$pdf->Output('I', utf8_decode('Detalhes_Missao_') . utf8_decode($missao_details['rgo_ocorrencia'] ?? $missao_details['id']) . '.pdf'); 
?>