<?php
// 1. INCLUI O CABEÇALHO PADRÃO
require_once 'includes/header.php';

// --- LÓGICA PARA LISTAR OS PILOTOS COM FILTRO CRBM ---
$pilotos = [];
$where_clauses = [];
$params = [];
$types = '';

// Lógica para buscar o CRBM do piloto logado, se for um piloto (Adicionado para lógica de título e filtro)
$logged_in_pilot_crbm = '';
if ($isPiloto && isset($_SESSION['user_id'])) {
    $stmt_crbm = $conn->prepare("SELECT crbm_piloto FROM pilotos WHERE id = ?");
    if ($stmt_crbm) {
        $stmt_crbm->bind_param("i", $_SESSION['user_id']);
        $stmt_crbm->execute();
        $result_crbm = $stmt_crbm->get_result();
        if ($result_crbm->num_rows > 0) {
            $logged_in_pilot_crbm = $result_crbm->fetch_assoc()['crbm_piloto'];
        }
        $stmt_crbm->close();
    } else {
        error_log("Erro na preparação da consulta de CRBM do piloto para relatórios de pilotos: " . $conn->error);
    }
}

// Adiciona filtro por CRBM se o parâmetro estiver presente na URL, ou se for piloto logado
if (isset($_GET['crbm']) && !empty($_GET['crbm'])) {
    $where_clauses[] = "p.crbm_piloto = ?";
    $params[] = $_GET['crbm'];
    $types .= 's';
} elseif ($isPiloto && !empty($logged_in_pilot_crbm)) { // Se for piloto e não houver filtro na URL, filtra pelo CRBM do piloto
    $where_clauses[] = "p.crbm_piloto = ?";
    $params[] = $logged_in_pilot_crbm;
    $types .= 's';
}

// SQL para buscar pilotos e seus dados de logbook (Atualizado)
$sql_pilotos = "SELECT 
    p.id, p.posto_graduacao, p.nome_completo, p.crbm_piloto, p.obm_piloto, p.status_piloto, p.codigo_cadastro, p.forca_seguranca,
    COALESCE(SUM(m.total_distancia_percorrida), 0) AS distancia_total_acumulada_piloto,
    COALESCE(SUM(m.total_tempo_voo), 0) AS tempo_voo_total_acumulado_piloto
    FROM pilotos p
    LEFT JOIN missoes_pilotos mp ON p.id = mp.piloto_id
    LEFT JOIN missoes m ON mp.missao_id = m.id";

if (!empty($where_clauses)) {
    $sql_pilotos .= " WHERE " . implode(' AND ', $where_clauses);
}

$sql_pilotos .= " GROUP BY p.id, p.posto_graduacao, p.nome_completo, p.crbm_piloto, p.obm_piloto, p.status_piloto, p.codigo_cadastro, p.forca_seguranca"; // GROUP BY para as agregações

$sql_pilotos .= " ORDER BY CASE p.posto_graduacao WHEN 'Cel. QOBM' THEN 1 WHEN 'Ten. Cel. QOBM' THEN 2 WHEN 'Maj. QOBM' THEN 3 WHEN 'Cap. QOBM' THEN 4 WHEN '1º Ten. QOBM' THEN 5 WHEN '2º Ten. QOBM' THEN 6 WHEN 'Asp. Oficial' THEN 7 WHEN 'Sub. Ten. QPBM' THEN 8 WHEN '1º Sgt. QPBM' THEN 9 WHEN '2º Sgt. QPBM' THEN 10 WHEN '3º Sgt. QPBM' THEN 11 WHEN 'Cb. QPBM' THEN 12 WHEN 'Sd. QPBM' THEN 13 ELSE 14 END, p.nome_completo ASC";

// Prepara e executa a consulta
$stmt_pilotos = $conn->prepare($sql_pilotos);
if ($stmt_pilotos) {
    if (!empty($params)) {
        $stmt_pilotos->bind_param($types, ...$params);
    }
    $stmt_pilotos->execute();
    $result_pilotos = $stmt_pilotos->get_result();
    if ($result_pilotos && $result_pilotos->num_rows > 0) {
        while ($row = $result_pilotos->fetch_assoc()) {
            $pilotos[] = $row;
        }
    }
    $stmt_pilotos->close();
} else {
    // Tratar erro na preparação da consulta
    die("Erro na preparação da consulta de pilotos: " . $conn->error);
}
$conn->close(); // Fechar conexão após todas as consultas necessárias

// Funções de formatação de tempo e distância (Copiadas e ajustadas)
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
?>
<style>
/* Adiciona uma dica visual para rolagem em telas pequenas */
@media (max-width: 768px) {
    .table-container::after {
        content: '◄ Arraste para ver mais ►';
        display: block;
        text-align: center;
        font-size: 0.8em;
        color: #999;
        margin-top: 10px;
    }
}
</style>
<div class="main-content">
    <?php
    $title_crbm_suffix = '';
    $current_crbm_filter = '';

    // Prioriza o filtro da URL se existir, senão usa o CRBM do piloto logado
    if (isset($_GET['crbm']) && !empty($_GET['crbm'])) {
        $current_crbm_filter = $_GET['crbm'];
    } elseif ($isPiloto && !empty($logged_in_pilot_crbm)) {
        $current_crbm_filter = $logged_in_pilot_crbm;
    }

    if (!empty($current_crbm_filter)) {
        if ($current_crbm_filter === 'GOST') {
            $title_crbm_suffix = ' - GOST';
        } else {
            // Aplica a formatação do CRBM para o título: ex. "4º CRBM"
            $formatted_crbm_title = preg_replace('/(\d)(CRBM)/', '$1º $2', $current_crbm_filter);
            $title_crbm_suffix = ' - ' . htmlspecialchars($formatted_crbm_title);
        }
    } else if ($isAdmin) { // Se não houver filtro específico e o usuário for administrador
        $title_crbm_suffix = ' - De Todos os Pilotos'; // Ajustado para "Pilotos"
    }
    ?>
    <h1>Logbook por Piloto<?php echo $title_crbm_suffix; ?></h1>
    
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Posto/Grad.</th>
                    <th>Nome Completo</th>
                    <th>Código de Cadastro</th>
                    <th>CRBM</th>
                    <th>OBM</th>
                    <th>Força de Segurança</th>
                    <th>Distância Total</th>
                    <th>Horas Voadas</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($pilotos)): ?>
                    <?php foreach ($pilotos as $piloto): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($piloto['posto_graduacao'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($piloto['nome_completo'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($piloto['codigo_cadastro'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($piloto['crbm_piloto'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($piloto['obm_piloto'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($piloto['forca_seguranca'] ?? 'N/A'); ?></td>
                            <td><?php echo formatarDistancia($piloto['distancia_total_acumulada_piloto'] ?? 0); ?></td>
                            <td><?php echo formatarTempoVooCompleto($piloto['tempo_voo_total_acumulado_piloto'] ?? 0); ?></td>
                            <td>
                                <?php
                                $status_map = ['ativo' => 'Ativo', 'afastado' => 'Afastado', 'desativado' => 'Desativado'];
                                $status = $piloto['status_piloto'] ?? 'N/A';
                                $status_texto = $status_map[$status] ?? ucfirst($status);
                                ?>
                                <span class="status-<?php echo htmlspecialchars($status); ?>"><?php echo htmlspecialchars($status_texto); ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="<?php echo $isPiloto ? 8 : 9; ?>">Nenhum piloto encontrado<?php echo $title_crbm_suffix; ?>.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
// 4. INCLUI O RODAPÉ
require_once 'includes/footer.php';
?>