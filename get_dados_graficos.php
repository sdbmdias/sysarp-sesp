<?php
// Define o cabeçalho como JSON para a resposta
header('Content-Type: application/json');

// Inclui APENAS o inicializador PHP, sem HTML
require_once 'includes/init.php';

// Ativa o relatório de erros interno do mysqli para lançar exceções
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // Prepara um array para os dados dos gráficos
    $data = [
        'horasVoo' => ['labels' => [], 'data' => []],
        'missoesPorTipo' => ['labels' => [], 'data' => []]
    ];

    // Lógica de filtro por força de segurança
    $where_clause = '';
    $params = [];
    $types = '';

    // =================================================================
    // ALTERAÇÃO APLICADA AQUI
    // Agora, o filtro se aplica se o usuário for Admin OU Piloto, mas não SuperAdmin.
    // =================================================================
    if (($isAdmin || $isPiloto) && !$isSuperAdmin && !empty($user_forca_seguranca)) {
        $where_clause = "
            WHERE a.id IN (
                SELECT a_filter.id FROM aeronaves a_filter
                JOIN unidades u ON a_filter.crbm = u.nome_unidade
                WHERE u.forca_sigla = ?
            )
        ";
        $params[] = $user_forca_seguranca;
        $types .= 's';
    }

    // 1. Consulta para o gráfico de Horas de Voo por Mês (últimos 12 meses)
    $sql_horas = "
        SELECT 
            DATE_FORMAT(m.data, '%Y-%m') AS mes_ano,
            SUM(m.total_tempo_voo) / 3600 AS total_horas
        FROM missoes m
        JOIN aeronaves a ON m.aeronave_id = a.id
        $where_clause
        GROUP BY mes_ano
        ORDER BY mes_ano DESC
        LIMIT 12
    ";
    $stmt_horas = $conn->prepare($sql_horas);
    if ($stmt_horas) {
        if (!empty($params)) $stmt_horas->bind_param($types, ...$params);
        $stmt_horas->execute();
        $result_horas = $stmt_horas->get_result();
        $dados_horas = array_reverse($result_horas->fetch_all(MYSQLI_ASSOC));
        foreach ($dados_horas as $dado) {
            $date = DateTime::createFromFormat('Y-m', $dado['mes_ano']);
            $data['horasVoo']['labels'][] = $date->format('M/y');
            $data['horasVoo']['data'][] = round($dado['total_horas'], 2);
        }
        $stmt_horas->close();
    }

    // 2. Consulta para o gráfico de Missões por Tipo
    $sql_tipos = "
        SELECT 
            m.descricao_operacao AS tipo_missao,
            COUNT(m.id) AS total
        FROM missoes m
        JOIN aeronaves a ON m.aeronave_id = a.id
        $where_clause
        AND m.descricao_operacao IS NOT NULL AND m.descricao_operacao != ''
        GROUP BY tipo_missao
        ORDER BY total DESC
    ";
    $stmt_tipos = $conn->prepare($sql_tipos);
    if ($stmt_tipos) {
        if (!empty($params)) $stmt_tipos->bind_param($types, ...$params);
        $stmt_tipos->execute();
        $result_tipos = $stmt_tipos->get_result();
        while ($row = $result_tipos->fetch_assoc()) {
            $data['missoesPorTipo']['labels'][] = $row['tipo_missao'];
            $data['missoesPorTipo']['data'][] = (int)$row['total'];
        }
        $stmt_tipos->close();
    }
    
    $conn->close();

    // Retorna a resposta de sucesso com os dados
    echo json_encode(['success' => true, 'data' => $data]);

} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro de banco de dados: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro inesperado no servidor: ' . $e->getMessage()]);
}
?>