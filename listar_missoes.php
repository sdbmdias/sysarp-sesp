<?php
// 1. INCLUI O CABEÇALHO PADRÃO
require_once 'includes/header.php';

$mensagem_status = "";

// Lógica para buscar o CRBM do piloto logado, se for um piloto
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
        // Erro na preparação da consulta do CRBM
        error_log("Erro na preparação da consulta de CRBM do piloto para missões: " . $conn->error);
    }
}


// --- LÓGICA DE EXCLUSÃO (APENAS PARA ADMINS) ---
if (($isSuperAdmin || $isAdmin) && isset($_GET['delete_id'])) {
    $missao_id_para_excluir = intval($_GET['delete_id']);

    $conn->begin_transaction();
    try {
        $stmt_get_data = $conn->prepare("SELECT m.aeronave_id, m.total_distancia_percorrida, m.total_tempo_voo, a.forca_seguranca
                                          FROM missoes m
                                          JOIN aeronaves a ON m.aeronave_id = a.id
                                          WHERE m.id = ?");
        if ($stmt_get_data) {
            $stmt_get_data->bind_param("i", $missao_id_para_excluir);
            $stmt_get_data->execute();
            $result_data = $stmt_get_data->get_result();
            $missao_data = $result_data->fetch_assoc();
            $stmt_get_data->close();
        } else {
            throw new Exception("Erro na preparação para obter dados da missão para exclusão: " . $conn->error);
        }

        if ($missao_data) {
            // Verifica a permissão de exclusão para administradores com restrição
            if ($isAdmin && !$isSuperAdmin && $user_forca_seguranca !== $missao_data['forca_seguranca']) {
                throw new Exception("Você não tem permissão para excluir missões desta Força de Segurança.");
            }

            // Excluir coordenadas primeiro, que dependem dos gpx_files
            $stmt_delete_coords = $conn->prepare("DELETE FROM missao_coordenadas WHERE gpx_file_id IN (SELECT id FROM missoes_gpx_files WHERE missao_id = ?)");
            if ($stmt_delete_coords) {
                $stmt_delete_coords->bind_param("i", $missao_id_para_excluir);
                $stmt_delete_coords->execute();
                $stmt_delete_coords->close();
            } else {
                throw new Exception("Erro na preparação para excluir coordenadas da missão: " . $conn->error);
            }

            // Excluir ficheiros GPX
            $stmt_delete_gpx = $conn->prepare("DELETE FROM missoes_gpx_files WHERE missao_id = ?");
            if ($stmt_delete_gpx) {
                $stmt_delete_gpx->bind_param("i", $missao_id_para_excluir);
                $stmt_delete_gpx->execute();
                $stmt_delete_gpx->close();
            } else {
                throw new Exception("Erro na preparação para excluir arquivos GPX da missão: " . $conn->error);
            }

            // Excluir associações de pilotos
            $stmt_delete_pilots = $conn->prepare("DELETE FROM missoes_pilotos WHERE missao_id = ?");
            if ($stmt_delete_pilots) {
                $stmt_delete_pilots->bind_param("i", $missao_id_para_excluir);
                $stmt_delete_pilots->execute();
                $stmt_delete_pilots->close();
            } else {
                throw new Exception("Erro na preparação para excluir pilotos da missão: " . $conn->error);
            }
            
            // Reverter o logbook da aeronave
            $stmt_update_logbook = $conn->prepare("UPDATE aeronaves_logbook SET distancia_total_acumulada = GREATEST(0, distancia_total_acumulada - ?), tempo_voo_total_acumulado = GREATEST(0, tempo_voo_total_acumulado - ?) WHERE aeronave_id = ?");
            if ($stmt_update_logbook) {
                $stmt_update_logbook->bind_param("ddi", $missao_data['total_distancia_percorrida'], $missao_data['total_tempo_voo'], $missao_data['aeronave_id']);
                $stmt_update_logbook->execute();
                $stmt_update_logbook->close();
            } else {
                throw new Exception("Erro na preparação para reverter logbook da aeronave: " . $conn->error);
            }

            // Finalmente, excluir a missão
            $stmt_delete_mission = $conn->prepare("DELETE FROM missoes WHERE id = ?");
            if ($stmt_delete_mission) {
                $stmt_delete_mission->bind_param("i", $missao_id_para_excluir);
                $stmt_delete_mission->execute();
                $stmt_delete_mission->close();
            } else {
                throw new Exception("Erro na preparação para excluir a missão: " . $conn->error);
            }
            
            $conn->commit();
            $mensagem_status = "<div class='success-message-box'>Missão #" . htmlspecialchars($missao_id_para_excluir) . " e todos os seus dados foram excluídos com sucesso.</div>";
        } else {
            throw new Exception("Missão não encontrada.");
        }
    } catch (Exception $e) {
        $conn->rollback();
        $mensagem_status = "<div class='error-message-box'>Erro ao excluir a missão: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// --- LÓGICA PARA BUSCAR AS MISSÕES ---
$missoes = [];
$where_clauses = [];
$params = [];
$types = '';

// Filtra por Força de Segurança para Administradores
if ($isAdmin && !$isSuperAdmin && !empty($user_forca_seguranca)) {
    $where_clauses[] = "a.forca_seguranca = ?";
    $params[] = $user_forca_seguranca;
    $types .= 's';
}
// Filtra por CRBM para Pilotos
elseif ($isPiloto && !empty($logged_in_pilot_crbm)) {
    $where_clauses[] = "a.crbm = ?";
    $params[] = $logged_in_pilot_crbm;
    $types .= 's';
}

$sql_missoes = "
    SELECT 
        m.id, m.data, m.descricao_operacao, m.rgo_ocorrencia, m.total_tempo_voo,
        a.prefixo AS aeronave_prefixo, a.crbm AS aeronave_crbm, a.forca_seguranca AS aeronave_fs,
        GROUP_CONCAT(DISTINCT CONCAT(p.posto_graduacao, ' ', p.nome_completo) 
            ORDER BY
                CASE p.posto_graduacao
                    WHEN 'Cel. QOBM' THEN 1 WHEN 'Ten. Cel. QOBM' THEN 2 WHEN 'Maj. QOBM' THEN 3
                    WHEN 'Cap. QOBM' THEN 4 WHEN '1º Ten. QOBM' THEN 5 WHEN '2º Ten. QOBM' THEN 6
                    WHEN 'Asp. Oficial' THEN 7 WHEN 'Sub. Ten. QPBM' THEN 8 WHEN '1º Sgt. QPBM' THEN 9
                    WHEN '2º Sgt. QPBM' THEN 10 WHEN '3º Sgt. QPBM' THEN 11 WHEN 'Cb. QPBM' THEN 12
                    WHEN 'Sd. QPBM' THEN 13 ELSE 14
                END
            SEPARATOR '<br>') AS pilotos_nomes
    FROM missoes m
    JOIN aeronaves a ON m.aeronave_id = a.id
    LEFT JOIN missoes_pilotos mp ON m.id = mp.missao_id
    LEFT JOIN pilotos p ON mp.piloto_id = p.id";

if (!empty($where_clauses)) {
    $sql_missoes .= " WHERE " . implode(' AND ', $where_clauses);
}

$sql_missoes .= " GROUP BY m.id ORDER BY m.data DESC, m.id DESC";

$stmt_missoes = $conn->prepare($sql_missoes);
if ($stmt_missoes) {
    if (!empty($params)) {
        $stmt_missoes->bind_param($types, ...$params);
    }
    $stmt_missoes->execute();
    $result_missoes = $stmt_missoes->get_result();
    if ($result_missoes) {
        while($row = $result_missoes->fetch_assoc()) {
            $missoes[] = $row;
        }
    }
    $stmt_missoes->close();
} else {
    die("Erro na preparação da consulta de missões: " . $conn->error);
}

function formatarTempoVoo($segundos) {
    if ($segundos <= 0) return '0min';
    $horas = floor($segundos / 3600);
    $minutos = floor(($segundos % 3600) / 60);
    $resultado = '';
    if ($horas > 0) $resultado .= $horas . 'h ';
    if ($minutos > 0) $resultado .= $minutos . 'min';
    return trim($resultado) ?: '0min';
}
?>

<style>
/* Adiciona uma dica visual para rolagem em telas pequenas */
@media (max-width: 768px) {
    .table-container::after { content: '◄ Arraste para ver mais ►'; display: block; text-align: center; font-size: 0.8em; color: #999; margin-top: 10px; }
    .page-header { flex-direction: column; align-items: flex-start; gap: 15px; }
}
</style>
<div class="main-content">
    <div class="page-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
        <?php
        $title_crbm_suffix = '';
        $current_crbm_filter = '';

        if (isset($_GET['crbm']) && !empty($_GET['crbm'])) {
            $current_crbm_filter = $_GET['crbm'];
        } else if ($isPiloto && !empty($logged_in_pilot_crbm)) {
            $current_crbm_filter = $logged_in_pilot_crbm;
        }

        if (!empty($current_crbm_filter)) {
            if ($current_crbm_filter === 'GOST') {
                $title_crbm_suffix = ' - GOST';
            } else {
                $crbm_formatado_titulo = preg_replace('/(\d)(CRBM)/', '$1º $2', $current_crbm_filter);
                $title_crbm_suffix = ' - ' . htmlspecialchars($crbm_formatado_titulo);
            }
        } else if ($isSuperAdmin) {
            $title_crbm_suffix = ' - Todas as Forças de Segurança';
        } elseif ($isAdmin) {
             $title_crbm_suffix = ' - ' . htmlspecialchars($user_forca_seguranca);
        } else {
            $title_crbm_suffix = ' - De Todas as Aeronaves';
        }
        ?>
        <h1>Logbook de Missões<?php echo $title_crbm_suffix; ?></h1>
        <?php if ($isSuperAdmin || $isAdmin): ?>
        <a href="cadastro_missao.php" class="form-actions button" style="text-decoration: none; display: inline-block; padding: 10px 20px; background-color: #28a745; color: #fff;">
            <i class="fas fa-plus"></i> Adicionar Nova Missão
        </a>
        <?php endif; ?>
    </div>

    <?php echo $mensagem_status; ?>

    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Aeronave</th>
                    <th>Piloto(s)</th>
                    <th>Operação</th>
                    <th>Tempo de Voo</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($missoes)): ?>
                    <?php foreach ($missoes as $missao): ?>
                        <tr>
                            <td style="text-align: center;"><?php echo htmlspecialchars(date("d/m/Y", strtotime($missao['data']))); ?></td>
                            <td style="text-align: center;"><?php echo htmlspecialchars($missao['aeronave_prefixo']); ?></td>
                            <td style="text-align: center;"><?php echo $missao['pilotos_nomes'] ?? 'Nenhum piloto associado'; // Mantido para renderizar <br> ?></td>
                            <td style="text-align: center;">
                                <strong><?php echo htmlspecialchars($missao['rgo_ocorrencia'] ?? 'MISSÃO SEM RGO'); ?></strong><br>
                                <small><?php echo htmlspecialchars($missao['descricao_operacao']); ?></small>
                            </td>
                            <td style="text-align: center;"><?php echo htmlspecialchars(formatarTempoVoo($missao['total_tempo_voo'])); ?></td>
                            <td class="action-buttons">
                                <a href="ver_missao.php?id=<?php echo $missao['id']; ?>" class="edit-btn">Ver Detalhes</a>
                                <?php if ($isSuperAdmin || $isAdmin): ?>
                                    <a href="editar_missao.php?id=<?php echo $missao['id']; ?>" class="edit-btn" style="background-color: #ffc107; color: #212529;">Editar</a>
                                    <a href="listar_missoes.php?delete_id=<?php echo $missao['id']; ?>" class="edit-btn" style="background-color: #dc3545; color: #fff;" onclick="return confirm('Tem a certeza que deseja excluir permanentemente a missão (RGO: <?php echo htmlspecialchars($missao['rgo_ocorrencia']); ?>) e todos os seus dados associados? Esta ação é irreversível.');">Excluir</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6">Nenhuma missão encontrada. Clique em "Adicionar Nova Missão" para começar.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php
require_once 'includes/footer.php';
?>