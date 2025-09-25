<?php
// 1. INCLUI O CABEÇALHO PADRÃO
require_once 'includes/header.php';

// --- LÓGICA PARA LISTAR AS AERONAVES COM FILTRO CRBM ---
$aeronaves = [];
$where_clauses = [];
$params = [];
$types = '';

// Lógica para buscar o CRBM do piloto logado, se for um piloto (Adicionado)
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
        error_log("Erro na preparação da consulta de CRBM do piloto para aeronaves: " . $conn->error);
    }
}

// Adiciona filtro por CRBM se o parâmetro estiver presente na URL, ou se for piloto logado
if (isset($_GET['crbm']) && !empty($_GET['crbm'])) {
    $where_clauses[] = "a.crbm = ?";
    $params[] = $_GET['crbm'];
    $types .= 's';
} elseif ($isPiloto && !empty($logged_in_pilot_crbm)) { // Se for piloto e não houver filtro na URL, filtra pelo CRBM do piloto
    $where_clauses[] = "a.crbm = ?";
    $params[] = $logged_in_pilot_crbm;
    $types .= 's';
}


// SQL para buscar aeronaves e seus dados de logbook
$sql_aeronaves = "SELECT 
    a.id, a.prefixo, a.fabricante, a.modelo, a.numero_serie, a.cadastro_sisant, a.validade_sisant, 
    a.crbm, a.obm, a.tipo_drone, a.pmd_kg, a.status, a.homologacao_anatel,
    COALESCE(al.distancia_total_acumulada, 0) AS distancia_total_acumulada,
    COALESCE(al.tempo_voo_total_acumulado, 0) AS tempo_voo_total_acumulado -- CORRIGIDO: Removido 'A' final do apelido
    FROM aeronaves a
    LEFT JOIN aeronaves_logbook al ON a.id = al.aeronave_id"; // LEFT JOIN para incluir dados de logbook

if (!empty($where_clauses)) {
    $sql_aeronaves .= " WHERE " . implode(' AND ', $where_clauses);
}

$sql_aeronaves .= " ORDER BY a.prefixo ASC";

// Prepara e executa a consulta
$stmt_aeronaves = $conn->prepare($sql_aeronaves);
if ($stmt_aeronaves) {
    if (!empty($params)) {
        $stmt_aeronaves->bind_param($types, ...$params);
    }
    $stmt_aeronaves->execute();
    $result_aeronaves = $stmt_aeronaves->get_result();
    if ($result_aeronaves && $result_aeronaves->num_rows > 0) {
        while ($row = $result_aeronaves->fetch_assoc()) {
            $aeronaves[] = $row;
        }
    }
    $stmt_aeronaves->close();
} else {
    // Tratar erro na preparação da consulta
    die("Erro na preparação da consulta de aeronaves: " . $conn->error);
}
$conn->close(); // Fechar conexão após todas as consultas necessárias

// Funções de formatação de tempo e distância (copiadas de outros arquivos para reuso)
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
    } else if ($isPiloto && !empty($logged_in_pilot_crbm)) {
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
        $title_crbm_suffix = ' - De Todas as Aeronaves';
    }
    ?>
    <h1>Logbook por Aeronave<?php echo $title_crbm_suffix; ?></h1>
    
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Prefixo</th>
                    <th>Fabricante/Modelo</th>
                    <th>Lotação (CRBM/OBM)</th>
                    <th>Status</th>
                    <th>Distância Total</th>
                    <th>Tempo de Voo Total</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($aeronaves)): ?>
                    <?php foreach ($aeronaves as $aeronave): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($aeronave['prefixo'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars(($aeronave['fabricante'] ?? 'N/A') . ' / ' . ($aeronave['modelo'] ?? 'N/A')); ?></td>
                            <td>
                                <?php 
                                    $crbm_formatado = preg_replace('/(\d)(CRBM)/', '$1º $2', $aeronave['crbm'] ?? 'N/A');
                                    echo htmlspecialchars($crbm_formatado . ' / ' . ($aeronave['obm'] ?? 'N/A'));
                                ?>
                            </td>
                            <td>
                                <?php
                                $status_map = ['ativo' => 'Ativa', 'em_manutencao' => 'Em Manutenção', 'baixada' => 'Baixada', 'adida' => 'Adida'];
                                $status = $aeronave['status'] ?? 'desconhecido';
                                $status_texto = $status_map[$status] ?? ucfirst($status);
                                ?>
                                <span class="status-<?php echo htmlspecialchars($status); ?>"><?php echo htmlspecialchars($status_texto); ?></span>
                            </td>
                            <td><?php echo formatarDistancia($aeronave['distancia_total_acumulada'] ?? 0); ?></td>
                            <td><?php echo formatarTempoVooCompleto($aeronave['tempo_voo_total_acumulado'] ?? 0); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6">Nenhuma aeronave encontrada<?php echo $title_crbm_suffix; ?>.</td>
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