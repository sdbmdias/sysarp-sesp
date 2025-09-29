<?php
// 1. INCLUI O CABEÇALHO PADRÃO
require_once 'includes/header.php';

// Adicionado para carregar as configurações das forças
$forcas_config = json_decode(file_get_contents(__DIR__ . '/includes/config_forcas.json'), true);


// 2. LÓGICA ESPECÍFICA DA PÁGINA
$controles_agrupados_fs = [];
$controles_agrupados_crbm_obm = [];
$controles_flat = [];
$where_clauses = [];
$params = [];
$types = '';

$mensagem_status = "";
if (($isSuperAdmin || $isAdmin) && isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);

    $stmt_ctrl_fs = $conn->prepare("SELECT forca_seguranca FROM controles WHERE id = ?");
    $stmt_ctrl_fs->bind_param("i", $delete_id);
    $stmt_ctrl_fs->execute();
    $result_ctrl_fs = $stmt_ctrl_fs->get_result();
    $ctrl_a_excluir_fs = $result_ctrl_fs->fetch_assoc()['forca_seguranca'];
    $stmt_ctrl_fs->close();

    if ($isSuperAdmin || ($isAdmin && $user_forca_seguranca === $ctrl_a_excluir_fs)) {
        $stmt_check_manutencoes = $conn->prepare("SELECT COUNT(*) AS total FROM manutencoes WHERE equipamento_tipo = 'Controle' AND equipamento_id = ?");
        $stmt_check_manutencoes->bind_param("i", $delete_id);
        $stmt_check_manutencoes->execute();
        $manutencoes_count = $stmt_check_manutencoes->get_result()->fetch_assoc()['total'];
        $stmt_check_manutencoes->close();

        if ($manutencoes_count > 0) {
            $mensagem_status = "<div class='error-message-box'>Não é possível excluir este controle, pois ele possui registros de manutenções vinculadas. Considere alterar o status para 'Baixado'.</div>";
        } else {
            $stmt_delete = $conn->prepare("DELETE FROM controles WHERE id = ?");
            $stmt_delete->bind_param("i", $delete_id);
            if ($stmt_delete->execute()) {
                $mensagem_status = "<div class='success-message-box'>Controle excluído com sucesso!</div>";
            } else {
                $mensagem_status = "<div class='error-message-box'>Erro ao excluir o controle.</div>";
            }
            $stmt_delete->close();
        }
    } else {
        $mensagem_status = "<div class='error-message-box'>Você não tem permissão para excluir um controle desta Força de Segurança.</div>";
    }
}


$sql_base = "SELECT c.id, c.fabricante, c.modelo, c.numero_serie, c.crbm, c.obm, c.forca_seguranca, c.status, c.homologacao_anatel, a.prefixo AS prefixo_aeronave 
             FROM controles c 
             LEFT JOIN aeronaves a ON c.aeronave_id = a.id";


if ($isPiloto) {
    $obm_do_usuario_logado = '';
    $stmt_obm = $conn->prepare("SELECT obm_piloto FROM pilotos WHERE id = ?");
    $stmt_obm->bind_param("i", $_SESSION['user_id']);
    $stmt_obm->execute();
    $result_obm = $stmt_obm->get_result();
    if ($result_obm->num_rows > 0) {
        $obm_do_usuario_logado = $result_obm->fetch_assoc()['obm_piloto'];
    }
    $stmt_obm->close();

    if (!empty($obm_do_usuario_logado)) {
        $sql_controles = $sql_base . " WHERE c.obm = ? ORDER BY c.id DESC";
        $stmt_controles = $conn->prepare($sql_controles);
        $stmt_controles->bind_param("s", $obm_do_usuario_logado);
        $stmt_controles->execute();
        $result_controles = $stmt_controles->get_result();
        $stmt_controles->close();
    }
} elseif ($isAdmin && !$isSuperAdmin) {
    $sql_controles = $sql_base . " WHERE c.forca_seguranca = ? ORDER BY c.crbm ASC, c.obm ASC, c.id DESC";
    $stmt_controles = $conn->prepare($sql_controles);
    $stmt_controles->bind_param("s", $user_forca_seguranca);
    $stmt_controles->execute();
    $result_controles = $stmt_controles->get_result();
    $stmt_controles->close();
} else { // Super Admin vê todos
    $sql_controles = $sql_base . " ORDER BY c.forca_seguranca ASC, c.crbm ASC, c.obm ASC, c.id DESC";
    $result_controles = $conn->query($sql_controles);
}


if (isset($result_controles) && $result_controles->num_rows > 0) {
    while ($row = $result_controles->fetch_assoc()) {
        if ($isSuperAdmin) {
            $controles_agrupados_fs[$row['forca_seguranca']][] = $row;
        } elseif ($isAdmin) {
            $grupo_crbm_obm = $row['crbm'] . ' / ' . $row['obm'];
            $controles_agrupados_crbm_obm[$grupo_crbm_obm][] = $row;
        } else {
            $controles_flat[] = $row;
        }
    }
}
?>

<style>
@media (max-width: 768px) {
    .table-container::after { content: '◄ Arraste para ver mais ►'; display: block; text-align: center; font-size: 0.8em; color: #999; margin-top: 10px; }
}
.data-table th a { color: inherit; text-decoration: none; }
.data-table th a:hover { text-decoration: underline; }
.status-ativo { color: #28a745; font-weight: bold; }
.status-em_manutencao { color: #ffc107; font-weight: bold; }
.status-baixado { color: #dc3545; font-weight: bold; }
.status-desconhecido { color: #6c757d; font-weight: bold; }
</style>

<div class="main-content">
    <h1>Lista de Controles (Rádios)</h1>
    <?php if(!empty($mensagem_status)) echo $mensagem_status; ?>

    <?php if ($isSuperAdmin): ?>
        <?php if (!empty($controles_agrupados_fs)): ?>
            <?php foreach ($controles_agrupados_fs as $forca_seguranca => $controles): 
                // *** Busca rótulos dinâmicos ***
                $config_atual = $forcas_config[$forca_seguranca] ?? null;
                $crbm_label = $config_atual['crbm_label'] ?? 'Unid. Superior';
                $obm_label = $config_atual['obm_label'] ?? 'Subunidade';
            ?>
                <div class="table-container" style="margin-top: 30px;">
                    <h2><?php echo htmlspecialchars($forca_seguranca); ?></h2>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Fabricante/Modelo</th>
                                <th>Nº Série</th>
                                <th>Vinculado ao</th>
                                <th>Lotação (<?php echo htmlspecialchars($crbm_label . '/' . $obm_label); ?>)</th>
                                <th>Status</th>
                                <th>ANATEL</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($controles as $controle): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($controle['id'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars(($controle['fabricante'] ?? 'N/A') . ' / ' . ($controle['modelo'] ?? 'N/A')); ?></td>
                                    <td><?php echo htmlspecialchars($controle['numero_serie'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($controle['prefixo_aeronave'] ?? 'Nenhum (Reserva)'); ?></td>
                                    <td>
                                        <?php 
                                            // *** Formatação dinâmica ***
                                            $crbm_formatado = preg_replace('/(\d)(CRBM|CRPM)/i', '$1º $2', $controle['crbm'] ?? 'N/A');
                                            echo htmlspecialchars($crbm_formatado . ' / ' . ($controle['obm'] ?? 'N/A'));
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        $status_map = ['ativo' => 'Ativo', 'em_manutencao' => 'Em Manutenção', 'baixado' => 'Baixado'];
                                        $status_from_db = $controle['status'] ?? '';
                                        $status_to_display = !empty($status_from_db) ? $status_from_db : 'desconhecido';
                                        $status_texto = $status_map[$status_to_display] ?? ucfirst($status_to_display);
                                        ?>
                                        <span class="status-<?php echo htmlspecialchars($status_to_display); ?>"><?php echo htmlspecialchars($status_texto); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($controle['homologacao_anatel'] ?? 'Não'); ?></td>
                                    <td class="action-buttons">
                                        <a href="editar_controles.php?id=<?php echo $controle['id']; ?>" class="edit-btn">Editar</a>
                                        <a href="listar_controles.php?delete_id=<?php echo $controle['id']; ?>" class="edit-btn" style="background-color:#dc3545;" onclick="return confirm('Tem certeza que deseja excluir este controle? Esta ação não pode ser desfeita e só funcionará se não houver manutenções vinculadas.');">Excluir</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="table-container">
                <p style="text-align: center;">Nenhum controle cadastrado.</p>
            </div>
        <?php endif; ?>

    <?php elseif ($isAdmin): 
        // *** Busca rótulos dinâmicos para Admin ***
        $config_atual = $forcas_config[$user_forca_seguranca] ?? null;
        $crbm_label = $config_atual['crbm_label'] ?? 'Unid. Superior';
        $obm_label = $config_atual['obm_label'] ?? 'Subunidade';
    ?>
        <?php if (!empty($controles_agrupados_crbm_obm)): ?>
            <?php foreach ($controles_agrupados_crbm_obm as $crbm_obm => $controles): ?>
                <div class="table-container" style="margin-top: 30px;">
                    <h2><?php echo htmlspecialchars(preg_replace('/(\d)(CRBM|CRPM)/i', '$1º $2', $crbm_obm)); ?></h2>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Fabricante/Modelo</th>
                                <th>Nº Série</th>
                                <th>Vinculado ao</th>
                                <th>Lotação (<?php echo htmlspecialchars($crbm_label . '/' . $obm_label); ?>)</th>
                                <th>Status</th>
                                <th>ANATEL</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($controles as $controle): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars(($controle['fabricante'] ?? 'N/A') . ' / ' . ($controle['modelo'] ?? 'N/A')); ?></td>
                                    <td><?php echo htmlspecialchars($controle['numero_serie'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($controle['prefixo_aeronave'] ?? 'Nenhum (Reserva)'); ?></td>
                                    <td>
                                        <?php 
                                            // *** Formatação dinâmica ***
                                            $crbm_formatado = preg_replace('/(\d)(CRBM|CRPM)/i', '$1º $2', $controle['crbm'] ?? 'N/A');
                                            echo htmlspecialchars($crbm_formatado . ' / ' . ($controle['obm'] ?? 'N/A'));
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        $status_map = ['ativo' => 'Ativo', 'em_manutencao' => 'Em Manutenção', 'baixado' => 'Baixado'];
                                        $status_from_db = $controle['status'] ?? '';
                                        $status_to_display = !empty($status_from_db) ? $status_from_db : 'desconhecido';
                                        $status_texto = $status_map[$status_to_display] ?? ucfirst($status_to_display);
                                        ?>
                                        <span class="status-<?php echo htmlspecialchars($status_to_display); ?>"><?php echo htmlspecialchars($status_texto); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($controle['homologacao_anatel'] ?? 'Não'); ?></td>
                                    <td class="action-buttons">
                                        <a href="editar_controles.php?id=<?php echo $controle['id']; ?>" class="edit-btn">Editar</a>
                                        <a href="listar_controles.php?delete_id=<?php echo $controle['id']; ?>" class="edit-btn" style="background-color:#dc3545;" onclick="return confirm('Tem certeza que deseja excluir este controle? Esta ação não pode ser desfeita e só funcionará se não houver manutenções vinculadas.');">Excluir</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="table-container">
                <p style="text-align: center;">Nenhum controle encontrado para sua Força de Segurança.</p>
            </div>
        <?php endif; ?>

    <?php elseif ($isPiloto): 
        // *** Busca rótulos dinâmicos para Piloto ***
        $config_atual = $forcas_config[$user_forca_seguranca] ?? null;
        $crbm_label = $config_atual['crbm_label'] ?? 'Unid. Superior';
        $obm_label = $config_atual['obm_label'] ?? 'Subunidade';
    ?>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Fabricante/Modelo</th>
                        <th>Nº Série</th>
                        <th>Vinculado ao</th>
                        <th>Lotação (<?php echo htmlspecialchars($crbm_label . '/' . $obm_label); ?>)</th>
                        <th>Status</th>
                        <th>ANATEL</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($controles_flat)): ?>
                        <?php foreach ($controles_flat as $controle): ?>
                            <tr>
                                <td><?php echo htmlspecialchars(($controle['fabricante'] ?? 'N/A') . ' / ' . ($controle['modelo'] ?? 'N/A')); ?></td>
                                <td><?php echo htmlspecialchars($controle['numero_serie'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($controle['prefixo_aeronave'] ?? 'Nenhum (Reserva)'); ?></td>
                                <td>
                                    <?php 
                                        // *** Formatação dinâmica ***
                                        $crbm_formatado = preg_replace('/(\d)(CRBM|CRPM)/i', '$1º $2', $controle['crbm'] ?? 'N/A');
                                        echo htmlspecialchars($crbm_formatado . ' / ' . ($controle['obm'] ?? 'N/A'));
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    $status_map = ['ativo' => 'Ativo', 'em_manutencao' => 'Em Manutenção', 'baixado' => 'Baixado'];
                                    $status_from_db = $controle['status'] ?? '';
                                    $status_to_display = !empty($status_from_db) ? $status_from_db : 'desconhecido';
                                    $status_texto = $status_map[$status_to_display] ?? ucfirst($status_to_display);
                                    ?>
                                    <span class="status-<?php echo htmlspecialchars($status_to_display); ?>"><?php echo htmlspecialchars($status_texto); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($controle['homologacao_anatel'] ?? 'Não'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6">Nenhum controle cadastrado para sua OBM.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php
// 4. INCLUI O RODAPÉ
require_once 'includes/footer.php';
?>