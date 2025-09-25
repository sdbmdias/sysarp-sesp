<?php
require_once 'includes/header.php';

// --- Lógica para buscar dados para os dropdowns de filtro ---
$tipos_operacao = $conn->query("SELECT DISTINCT nome FROM tipos_operacao ORDER BY nome ASC")->fetch_all(MYSQLI_ASSOC);
$aeronaves = $conn->query("SELECT id, prefixo FROM aeronaves ORDER BY prefixo ASC")->fetch_all(MYSQLI_ASSOC);
$graduacoes = $conn->query("SELECT DISTINCT posto_graduacao FROM pilotos ORDER BY CASE posto_graduacao WHEN 'Cel. QOBM' THEN 1 WHEN 'Ten. Cel. QOBM' THEN 2 WHEN 'Maj. QOBM' THEN 3 WHEN 'Cap. QOBM' THEN 4 WHEN '1º Ten. QOBM' THEN 5 WHEN '2º Ten. QOBM' THEN 6 WHEN 'Asp. Oficial' THEN 7 WHEN 'Sub. Ten. QPBM' THEN 8 WHEN '1º Sgt. QPBM' THEN 9 WHEN '2º Sgt. QPBM' THEN 10 WHEN '3º Sgt. QPBM' THEN 11 WHEN 'Cb. QPBM' THEN 12 WHEN 'Sd. QPBM' THEN 13 ELSE 14 END")->fetch_all(MYSQLI_ASSOC);
$crbms_piloto = $conn->query("SELECT DISTINCT crbm_piloto FROM pilotos WHERE crbm_piloto IS NOT NULL AND crbm_piloto != '' ORDER BY crbm_piloto ASC")->fetch_all(MYSQLI_ASSOC);
$obms_piloto = $conn->query("SELECT DISTINCT obm_piloto FROM pilotos WHERE obm_piloto IS NOT NULL AND obm_piloto != '' ORDER BY obm_piloto ASC")->fetch_all(MYSQLI_ASSOC);
$crbms_aeronave = $conn->query("SELECT DISTINCT crbm FROM aeronaves WHERE crbm IS NOT NULL AND crbm != '' ORDER BY crbm ASC")->fetch_all(MYSQLI_ASSOC);
$obms_aeronave = $conn->query("SELECT DISTINCT obm FROM aeronaves WHERE obm IS NOT NULL AND obm != '' ORDER BY obm ASC")->fetch_all(MYSQLI_ASSOC);


$resultados = [];
$colunas = [];
$report_title = "Nenhum relatório gerado";
$colunas_a_exibir = [];

// --- Processamento do Formulário ---
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['report_type'])) {
    $report_type = $_GET['report_type'];
    $where_clauses = [];
    $params = [];
    $types = '';

    $sort_column = isset($_GET['sort']) ? $_GET['sort'] : 'data';
    $sort_order = isset($_GET['order']) && strtolower($_GET['order']) == 'asc' ? 'ASC' : 'DESC';

    switch ($report_type) {
        case 'missoes':
            // Lógica para relatório de missões (mantida)
            $report_title = "Relatório de Missões";
            $sql = "SELECT m.data, m.descricao_operacao, a.prefixo as aeronave, GROUP_CONCAT(p.posto_graduacao, ' ', p.nome_completo SEPARATOR '; ') as pilotos
                    FROM missoes m
                    JOIN aeronaves a ON m.aeronave_id = a.id
                    LEFT JOIN missoes_pilotos mp ON m.id = mp.missao_id
                    LEFT JOIN pilotos p ON mp.piloto_id = p.id";

            if (!empty($_GET['data_inicio'])) { $where_clauses[] = "m.data >= ?"; $params[] = $_GET['data_inicio']; $types .= 's'; }
            if (!empty($_GET['data_fim'])) { $where_clauses[] = "m.data <= ?"; $params[] = $_GET['data_fim']; $types .= 's'; }
            if (!empty($_GET['tipo_ocorrencia'])) { $where_clauses[] = "m.descricao_operacao = ?"; $params[] = $_GET['tipo_ocorrencia']; $types .= 's'; }
            if (!empty($_GET['aeronave_id'])) { $where_clauses[] = "m.aeronave_id = ?"; $params[] = $_GET['aeronave_id']; $types .= 'i'; }
            if (!empty($_GET['crbm_missao'])) { $where_clauses[] = "a.crbm = ?"; $params[] = $_GET['crbm_missao']; $types .= 's'; }
            if (!empty($_GET['obm_missao'])) { $where_clauses[] = "a.obm = ?"; $params[] = $_GET['obm_missao']; $types .= 's'; }

            if (count($where_clauses) > 0) { $sql .= " WHERE " . implode(' AND ', $where_clauses); }
            $sql .= " GROUP BY m.id";

            $allowed_sort_missoes = ['data', 'descricao_operacao', 'aeronave'];
            if (!in_array($sort_column, $allowed_sort_missoes)) $sort_column = 'data';
            if ($sort_column === 'aeronave') {
                $sql .= " ORDER BY CAST(SUBSTRING_INDEX(a.prefixo, ' ', -1) AS UNSIGNED) $sort_order, a.prefixo $sort_order";
            } else {
                $sql .= " ORDER BY m.$sort_column $sort_order";
            }
            
            $colunas = ['data' => 'Data', 'descricao_operacao' => 'Tipo de Operação', 'aeronave' => 'Aeronave', 'pilotos' => 'Pilotos'];
            $colunas_a_exibir = array_keys($colunas);
            break;

        case 'pilotos':
            // Lógica para relatório de pilotos (mantida)
            $report_title = "Relatório de Pilotos";
            $sql = "SELECT posto_graduacao, nome_completo, cpf, crbm_piloto, obm_piloto, status_piloto FROM pilotos";
            if (!empty($_GET['graduacao'])) { $where_clauses[] = "posto_graduacao = ?"; $params[] = $_GET['graduacao']; $types .= 's'; }
            if (!empty($_GET['crbm_piloto'])) { $where_clauses[] = "crbm_piloto = ?"; $params[] = $_GET['crbm_piloto']; $types .= 's'; }
            if (!empty($_GET['obm_piloto'])) { $where_clauses[] = "obm_piloto = ?"; $params[] = $_GET['obm_piloto']; $types .= 's'; }
            if (!empty($_GET['status_piloto'])) { $where_clauses[] = "status_piloto = ?"; $params[] = $_GET['status_piloto']; $types .= 's'; }
            if (count($where_clauses) > 0) { $sql .= " WHERE " . implode(' AND ', $where_clauses); }
            
            $allowed_sort_pilotos = ['posto_graduacao', 'nome_completo'];
            if (!in_array($sort_column, $allowed_sort_pilotos)) $sort_column = 'posto_graduacao';
            if ($sort_column === 'posto_graduacao') {
                $sql .= " ORDER BY CASE posto_graduacao WHEN 'Cel. QOBM' THEN 1 WHEN 'Ten. Cel. QOBM' THEN 2 WHEN 'Maj. QOBM' THEN 3 WHEN 'Cap. QOBM' THEN 4 WHEN '1º Ten. QOBM' THEN 5 WHEN '2º Ten. QOBM' THEN 6 WHEN 'Asp. Oficial' THEN 7 WHEN 'Sub. Ten. QPBM' THEN 8 WHEN '1º Sgt. QPBM' THEN 9 WHEN '2º Sgt. QPBM' THEN 10 WHEN '3º Sgt. QPBM' THEN 11 WHEN 'Cb. QPBM' THEN 12 WHEN 'Sd. QPBM' THEN 13 ELSE 14 END $sort_order, nome_completo ASC";
            } else {
                 $sql .= " ORDER BY $sort_column $sort_order";
            }
            
            $colunas = ['posto_graduacao' => 'Posto/Graduação', 'nome_completo' => 'Nome Completo', 'cpf' => 'CPF', 'crbm_piloto' => 'CRBM', 'obm_piloto' => 'OBM', 'status_piloto' => 'Status'];
            $colunas_a_exibir = array_keys($colunas);
            break;

        case 'aeronaves':
             // Lógica para relatório de aeronaves (mantida com as últimas alterações)
            $report_title = "Relatório de Aeronaves";
            $sql = "SELECT prefixo, modelo, crbm, obm, status FROM aeronaves";
             if (!empty($_GET['crbm_aeronave'])) { $where_clauses[] = "crbm = ?"; $params[] = $_GET['crbm_aeronave']; $types .= 's'; }
             if (!empty($_GET['obm_aeronave'])) { $where_clauses[] = "obm = ?"; $params[] = $_GET['obm_aeronave']; $types .= 's'; }
             if (!empty($_GET['status_aeronave'])) { $where_clauses[] = "status = ?"; $params[] = $_GET['status_aeronave']; $types .= 's'; }
            if (count($where_clauses) > 0) { $sql .= " WHERE " . implode(' AND ', $where_clauses); }
            
            $allowed_sort_aeronaves = ['prefixo', 'modelo'];
            if (!in_array($sort_column, $allowed_sort_aeronaves)) $sort_column = 'prefixo';
            if ($sort_column === 'prefixo') { $sql .= " ORDER BY CAST(SUBSTRING_INDEX(prefixo, ' ', -1) AS UNSIGNED) $sort_order, prefixo $sort_order"; } 
            else { $sql .= " ORDER BY $sort_column $sort_order"; }
            
            $colunas = ['prefixo' => 'Prefixo', 'modelo' => 'Modelo', 'crbm' => 'CRBM', 'obm' => 'OBM', 'status' => 'Status'];
            $colunas_a_exibir = array_keys($colunas);
            break;
            
        case 'manutencao':
            // Lógica para relatório de manutenção (mantida)
            $report_title = "Relatório de Manutenções";
            $sql = "SELECT m.data_manutencao, m.tipo_manutencao, m.responsavel, m.valor, m.garantia_ate,
                        CASE WHEN m.equipamento_tipo = 'Aeronave' THEN CONCAT('Aeronave: ', a.prefixo, ' (', a.modelo, ')')
                             WHEN m.equipamento_tipo = 'Controle' THEN CONCAT('Controle: ', c.numero_serie, ' (', c.modelo, IFNULL(CONCAT(' vinc. a ', av.prefixo), ''), ')')
                        END as equipamento, m.descricao
                    FROM manutencoes m
                    LEFT JOIN aeronaves a ON m.equipamento_id = a.id AND m.equipamento_tipo = 'Aeronave'
                    LEFT JOIN controles c ON m.equipamento_id = c.id AND m.equipamento_tipo = 'Controle'
                    LEFT JOIN aeronaves av ON c.aeronave_id = av.id"; // Adicionado JOIN para aeronave vinculada ao controle
            
            if (!empty($_GET['data_inicio_man'])) { $where_clauses[] = "m.data_manutencao >= ?"; $params[] = $_GET['data_inicio_man']; $types .= 's'; }
            if (!empty($_GET['data_fim_man'])) { $where_clauses[] = "m.data_manutencao <= ?"; $params[] = $_GET['data_fim_man']; $types .= 's'; }
            if (!empty($_GET['tipo_equipamento'])) { $where_clauses[] = "m.equipamento_tipo = ?"; $params[] = $_GET['tipo_equipamento']; $types .= 's'; }
            if (!empty($_GET['tipo_manutencao'])) { $where_clauses[] = "m.tipo_manutencao = ?"; $params[] = $_GET['tipo_manutencao']; $types .= 's'; }
            if (!empty($_GET['crbm_manutencao'])) { $where_clauses[] = "(a.crbm = ? OR c.crbm = ?)"; $params[] = $_GET['crbm_manutencao']; $params[] = $_GET['crbm_manutencao']; $types .= 'ss'; }
            if (!empty($_GET['obm_manutencao'])) { $where_clauses[] = "(a.obm = ? OR c.obm = ?)"; $params[] = $_GET['obm_manutencao']; $params[] = $_GET['obm_manutencao']; $types .= 'ss'; }

            if (count($where_clauses) > 0) { $sql .= " WHERE " . implode(' AND ', $where_clauses); }

            $allowed_sort_manutencao = ['data_manutencao', 'tipo_manutencao', 'equipamento'];
            if (!in_array($sort_column, $allowed_sort_manutencao)) $sort_column = 'data_manutencao';
            $sql .= " ORDER BY $sort_column $sort_order";

            $colunas = ['data_manutencao' => 'Data', 'tipo_manutencao' => 'Tipo', 'equipamento' => 'Equipamento', 'responsavel' => 'Responsável', 'garantia_ate' => 'Garantia até', 'valor' => 'Valor (R$)', 'descricao' => 'Descrição'];
            $colunas_a_exibir = array_keys($colunas);
            break;
    }

    if (!empty($sql)) {
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            if (count($params) > 0) $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            while($row = $result->fetch_assoc()) $resultados[] = $row;
            $stmt->close();
        }
    }
}

function get_sort_report_link($column, $current_column, $current_order) {
    $order = ($column == $current_column && $current_order == 'desc') ? 'asc' : 'desc';
    $query_params = $_GET;
    $query_params['sort'] = $column;
    $query_params['order'] = $order;
    return "?" . http_build_query($query_params);
}
?>

<style>
/* Estilos mantidos */
.filters-container { background-color: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,.05); margin-bottom: 30px; }
.filter-section { display: none; border-top: 1px solid #eee; margin-top: 20px; padding-top: 20px; }
.filter-section.active { display: block; }
.results-container { margin-top: 20px; }
.data-table th a { color: inherit; text-decoration: none; display: flex; align-items: center; justify-content: space-between; }
.data-table th a:hover { color: #0056b3; }
.data-table th .sort-icon { margin-left: 5px; opacity: 0.6; }

/* Novo estilo para o botão Gerar PDF em relatórios */
.btn-pdf-action {
    background-color: var(--color-primary);
    color: var(--color-white);
    text-decoration: none;
    padding: 8px 12px;
    border-radius: 5px;
    display: inline-block; /* Para garantir que padding e margin funcionem */
    font-size: 0.9em; /* Ajustado para não ser tão grande */
    transition: background-color 0.3s ease;
}

.btn-pdf-action:hover {
    background-color: var(--color-primary-hover);
}
</style>

<div class="main-content">
    <div style="display: flex; justify-content: space-between; align-items: center;"><h1>Relatórios Específicos</h1><a href="relatorios.php" style="text-decoration: none; color: #555;"><i class="fas fa-arrow-left"></i> Voltar</a></div>
    <p>Use os filtros abaixo para gerar um relatório personalizado.</p>

    <div class="filters-container">
        <form action="relatorios_especificos.php" method="GET">
            <div class="form-group"><label for="report_type"><strong>1. Selecione o Tipo de Relatório</strong></label><select id="report_type" name="report_type" class="form-control" required><option value="">-- Escolha uma opção --</option><option value="missoes" <?php echo (isset($_GET['report_type']) && $_GET['report_type'] == 'missoes') ? 'selected' : ''; ?>>Relatório de Missões</option><option value="pilotos" <?php echo (isset($_GET['report_type']) && $_GET['report_type'] == 'pilotos') ? 'selected' : ''; ?>>Lista de Pilotos</option><option value="aeronaves" <?php echo (isset($_GET['report_type']) && $_GET['report_type'] == 'aeronaves') ? 'selected' : ''; ?>>Lista de Aeronaves</option><option value="manutencao" <?php echo (isset($_GET['report_type']) && $_GET['report_type'] == 'manutencao') ? 'selected' : ''; ?>>Relatório de Manutenção</option></select></div>
            <div id="filtros_missoes" class="filter-section"><label><strong>2. Filtros para Missões (opcional)</strong></label><div class="form-grid" style="grid-template-columns: 1fr 1fr 1fr;"><div class="form-group"><label>Data Início:</label><input type="date" name="data_inicio" class="form-control" value="<?php echo htmlspecialchars($_GET['data_inicio'] ?? ''); ?>"></div><div class="form-group"><label>Data Fim:</label><input type="date" name="data_fim" class="form-control" value="<?php echo htmlspecialchars($_GET['data_fim'] ?? ''); ?>"></div><div class="form-group"><label>Tipo de Ocorrência:</label><select name="tipo_ocorrencia" class="form-control"><option value="">Todos</option><?php foreach ($tipos_operacao as $tipo): ?><option value="<?php echo $tipo['nome']; ?>" <?php echo (isset($_GET['tipo_ocorrencia']) && $_GET['tipo_ocorrencia'] == $tipo['nome']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($tipo['nome']); ?></option><?php endforeach; ?></select></div><div class="form-group"><label>Aeronave:</label><select name="aeronave_id" class="form-control"><option value="">Todas</option><?php foreach ($aeronaves as $aeronave): ?><option value="<?php echo $aeronave['id']; ?>" <?php echo (isset($_GET['aeronave_id']) && $_GET['aeronave_id'] == $aeronave['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($aeronave['prefixo']); ?></option><?php endforeach; ?></select></div><div class="form-group"><label>CRBM:</label><select name="crbm_missao" class="form-control"><option value="">Todos</option><?php foreach ($crbms_aeronave as $crbm): ?><option value="<?php echo $crbm['crbm']; ?>" <?php echo (isset($_GET['crbm_missao']) && $_GET['crbm_missao'] == $crbm['crbm']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($crbm['crbm']); ?></option><?php endforeach; ?></select></div><div class="form-group"><label>OBM:</label><select name="obm_missao" class="form-control"><option value="">Todas</option><?php foreach ($obms_aeronave as $obm): ?><option value="<?php echo $obm['obm']; ?>" <?php echo (isset($_GET['obm_missao']) && $_GET['obm_missao'] == $obm['obm']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($obm['obm']); ?></option><?php endforeach; ?></select></div></div></div>
            <div id="filtros_pilotos" class="filter-section"><label><strong>2. Filtros para Pilotos (opcional)</strong></label><div class="form-grid" style="grid-template-columns: 1fr 1fr 1fr;"><div class="form-group"><label>Graduação:</label><select name="graduacao" class="form-control"><option value="">Todas</option><?php foreach ($graduacoes as $grad): ?><option value="<?php echo $grad['posto_graduacao']; ?>" <?php echo (isset($_GET['graduacao']) && $_GET['graduacao'] == $grad['posto_graduacao']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($grad['posto_graduacao']); ?></option><?php endforeach; ?></select></div><div class="form-group"><label>CRBM:</label><select name="crbm_piloto" class="form-control"><option value="">Todos</option><?php foreach ($crbms_piloto as $crbm): ?><option value="<?php echo $crbm['crbm_piloto']; ?>" <?php echo (isset($_GET['crbm_piloto']) && $_GET['crbm_piloto'] == $crbm['crbm_piloto']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($crbm['crbm_piloto']); ?></option><?php endforeach; ?></select></div><div class="form-group"><label>OBM:</label><select name="obm_piloto" class="form-control"><option value="">Todas</option><?php foreach ($obms_piloto as $obm): ?><option value="<?php echo $obm['obm_piloto']; ?>" <?php echo (isset($_GET['obm_piloto']) && $_GET['obm_piloto'] == $obm['obm_piloto']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($obm['obm_piloto']); ?></option><?php endforeach; ?></select></div><div class="form-group"><label>Status:</label><select name="status_piloto" class="form-control"><option value="">Todos</option><option value="ativo" <?php echo (isset($_GET['status_piloto']) && $_GET['status_piloto'] == 'ativo') ? 'selected' : ''; ?>>Ativo</option><option value="afastado" <?php echo (isset($_GET['status_piloto']) && $_GET['status_piloto'] == 'afastado') ? 'selected' : ''; ?>>Afastado</option><option value="desativado" <?php echo (isset($_GET['status_piloto']) && $_GET['status_piloto'] == 'desativado') ? 'selected' : ''; ?>>Desativado</option></select></div></div></div>
            <div id="filtros_aeronaves" class="filter-section"><label><strong>2. Filtros para Aeronaves (opcional)</strong></label><div class="form-grid" style="grid-template-columns: 1fr 1fr 1fr;"><div class="form-group"><label for="crbm_aeronave">CRBM:</label><select name="crbm_aeronave" class="form-control"><option value="">Todos</option><?php foreach ($crbms_aeronave as $crbm): ?><option value="<?php echo $crbm['crbm']; ?>" <?php echo (isset($_GET['crbm_aeronave']) && $_GET['crbm_aeronave'] == $crbm['crbm']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($crbm['crbm']); ?></option><?php endforeach; ?></select></div><div class="form-group"><label for="obm_aeronave">OBM:</label><select name="obm_aeronave" class="form-control"><option value="">Todas</option><?php foreach ($obms_aeronave as $obm): ?><option value="<?php echo $obm['obm']; ?>" <?php echo (isset($_GET['obm_aeronave']) && $_GET['obm_aeronave'] == $obm['obm']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($obm['obm']); ?></option><?php endforeach; ?></select></div><div class="form-group"><label for="status_aeronave">Status:</label><select name="status_aeronave" class="form-control"><option value="">Todos</option><option value="ativo" <?php echo (isset($_GET['status_aeronave']) && $_GET['status_aeronave'] == 'ativo') ? 'selected' : ''; ?>>Ativa</option><option value="em_manutencao" <?php echo (isset($_GET['status_aeronave']) && $_GET['status_aeronave'] == 'em_manutencao') ? 'selected' : ''; ?>>Em Manutenção</option><option value="baixada" <?php echo (isset($_GET['status_aeronave']) && $_GET['status_aeronave'] == 'baixada') ? 'selected' : ''; ?>>Baixada</option><option value="adida" <?php echo (isset($_GET['status_aeronave']) && $_GET['status_aeronave'] == 'adida') ? 'selected' : ''; ?>>Adida</option></select></div></div></div>
            <div id="filtros_manutencao" class="filter-section"><label><strong>2. Filtros para Manutenção (opcional)</strong></label><div class="form-grid" style="grid-template-columns: 1fr 1fr 1fr;"><div class="form-group"><label>Data Início:</label><input type="date" name="data_inicio_man" class="form-control" value="<?php echo htmlspecialchars($_GET['data_inicio_man'] ?? ''); ?>"></div><div class="form-group"><label>Data Fim:</label><input type="date" name="data_fim_man" class="form-control" value="<?php echo htmlspecialchars($_GET['data_fim_man'] ?? ''); ?>"></div><div class="form-group"><label>Tipo de Equipamento:</label><select name="tipo_equipamento" class="form-control"><option value="">Ambos</option><option value="Aeronave" <?php echo (isset($_GET['tipo_equipamento']) && $_GET['tipo_equipamento'] == 'Aeronave') ? 'selected' : ''; ?>>Aeronave</option><option value="Controle" <?php echo (isset($_GET['tipo_equipamento']) && $_GET['tipo_equipamento'] == 'Controle') ? 'selected' : ''; ?>>Controle</option></select></div><div class="form-group"><label for="crbm_manutencao">CRBM:</label><select name="crbm_manutencao" class="form-control"><option value="">Todos</option><?php foreach ($crbms_aeronave as $crbm): ?><option value="<?php echo $crbm['crbm']; ?>" <?php echo (isset($_GET['crbm_manutencao']) && $_GET['crbm_manutencao'] == $crbm['crbm']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($crbm['crbm']); ?></option><?php endforeach; ?></select></div><div class="form-group"><label for="obm_manutencao">OBM:</label><select name="obm_manutencao" class="form-control"><option value="">Todas</option><?php foreach ($obms_aeronave as $obm): ?><option value="<?php echo $obm['obm']; ?>" <?php echo (isset($_GET['obm_manutencao']) && $_GET['obm_manutencao'] == $obm['obm']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($obm['obm']); ?></option><?php endforeach; ?></select></div><div class="form-group"><label for="tipo_manutencao">Tipo de Manutenção:</label><select name="tipo_manutencao" class="form-control"><option value="">Ambos</option><option value="Preventiva" <?php echo (isset($_GET['tipo_manutencao']) && $_GET['tipo_manutencao'] == 'Preventiva') ? 'selected' : ''; ?>>Preventiva</option><option value="Reparadora" <?php echo (isset($_GET['tipo_manutencao']) && $_GET['tipo_manutencao'] == 'Reparadora') ? 'selected' : ''; ?>>Reparadora</option></select></div></div></div>

            <div class="form-actions" style="border-top: 1px solid #eee; margin-top: 20px;"><button type="submit" class="button"><i class="fas fa-filter"></i> Gerar Relatório</button></div>
        </form>
    </div>

    <?php if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['report_type']) && !empty($report_type)): ?>
    <div class="results-container table-container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
            <h3><?php echo htmlspecialchars($report_title); ?></h3>
            <?php if (!empty($resultados)): // Botão só aparece se houver resultados ?>
                <?php
                $pdf_link = '';
                switch ($report_type) {
                    case 'pilotos':
                        $pdf_link = 'gerar_pdf_pilotos_fpdf.php';
                        break;
                    case 'missoes':
                        $pdf_link = 'gerar_pdf_missoes_fpdf.php';
                        break;
                    case 'aeronaves':
                        $pdf_link = 'gerar_pdf_aeronaves_fpdf.php';
                        break;
                    case 'manutencao':
                        $pdf_link = 'gerar_pdf_manutencao_fpdf.php';
                        break;
                }
                ?>
                <?php if (!empty($pdf_link)): ?>
                    <a href="<?php echo $pdf_link; ?>?<?php echo http_build_query($_GET); ?>" target="_blank" class="btn-pdf-action">
                        <i class="fas fa-file-pdf"></i> Gerar PDF
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <p>Total de registros encontrados: <?php echo count($resultados); ?></p>
        <table class="data-table">
            <thead>
                <tr>
                    <?php foreach ($colunas as $key => $coluna): ?>
                        <th>
                            <?php if (in_array($key, ['data', 'descricao_operacao', 'aeronave', 'posto_graduacao', 'nome_completo', 'data_manutencao', 'tipo_manutencao', 'equipamento', 'prefixo', 'modelo'])): ?>
                                <a href="<?php echo get_sort_report_link($key, $sort_column, strtolower($sort_order)); ?>">
                                    <?php echo htmlspecialchars($coluna); ?> <i class="fas fa-sort sort-icon"></i>
                                </a>
                            <?php else: ?>
                                <?php echo htmlspecialchars($coluna); ?>
                            <?php endif; ?>
                        </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($resultados)): ?>
                    <?php foreach ($resultados as $linha): ?>
                        <tr>
                            <?php foreach ($colunas_a_exibir as $col_name): ?>
                                <td>
                                    <?php
                                        $valor = $linha[$col_name];
                                        if ($col_name === 'data' || $col_name === 'data_manutencao' || $col_name === 'garantia_ate') { // Adicionado garantia_ate
                                            echo date("d/m/Y", strtotime($valor));
                                        } elseif ($col_name === 'cpf') {
                                            echo substr($valor, 0, 3) . '.XXX.XXX-' . substr($valor, -2);
                                        } elseif ($col_name === 'status_piloto' || $col_name === 'status') {
                                            $status_class = str_replace(' ', '_', strtolower($valor));
                                            $status_text = ucfirst(str_replace('_', ' ', $valor));
                                            if ($col_name === 'status' && $status_text === 'Ativo') $status_text = 'Ativa';
                                            echo '<span class="status-' . $status_class . '">' . $status_text . '</span>';
                                        } elseif ($col_name === 'valor') { // Formatando valor como moeda
                                            echo 'R$ ' . number_format($valor, 2, ',', '.');
                                        }
                                        else {
                                            echo htmlspecialchars($valor);
                                        }
                                    ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="<?php echo count($colunas); ?>">Nenhum resultado encontrado para os filtros aplicados.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const reportTypeSelect = document.getElementById('report_type');
    const sections = {
        missoes: document.getElementById('filtros_missoes'),
        pilotos: document.getElementById('filtros_pilotos'),
        aeronaves: document.getElementById('filtros_aeronaves'),
        manutencao: document.getElementById('filtros_manutencao')
    };

    function toggleFilterSections() {
        const selectedType = reportTypeSelect.value;
        Object.values(sections).forEach(section => {
            if (section) section.classList.remove('active');
        });
        if (sections[selectedType]) {
            sections[selectedType].classList.add('active');
        }
    }

    reportTypeSelect.addEventListener('change', toggleFilterSections);
    toggleFilterSections();

    window.addEventListener('beforeunload', function() {
        const scrollPos = window.scrollY || document.documentElement.scrollTop;
        const currentUrl = new URL(window.location.href);
        if(document.activeElement.tagName === 'A' && currentUrl.searchParams.has('sort')) {
            sessionStorage.setItem('scrollPosition', scrollPos);
        } else {
             sessionStorage.removeItem('scrollPosition');
        }
    });

    const scrollPosition = sessionStorage.getItem('scrollPosition');
    if (scrollPosition) {
        window.scrollTo(0, parseInt(scrollPosition, 10));
        sessionStorage.removeItem('scrollPosition');
    }
});
</script>

<?php
require_once 'includes/footer.php';
?>