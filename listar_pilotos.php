<?php
// 1. INCLUI O CABEÇALHO PADRÃO
require_once 'includes/header.php';

// Adicionado para carregar as configurações das forças
$forcas_config = json_decode(file_get_contents(__DIR__ . '/includes/config_forcas.json'), true);


$mensagem_status = "";

// 2. LÓGICA DE EXCLUSÃO (APENAS PARA ADMINS)
if (($isSuperAdmin || $isAdmin) && isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    
    $stmt_piloto_fs = $conn->prepare("SELECT forca_seguranca FROM pilotos WHERE id = ?");
    $stmt_piloto_fs->bind_param("i", $delete_id);
    $stmt_piloto_fs->execute();
    $result_piloto_fs = $stmt_piloto_fs->get_result();
    $piloto_a_excluir_fs = $result_piloto_fs->fetch_assoc()['forca_seguranca'];
    $stmt_piloto_fs->close();

    if ($isSuperAdmin || ($isAdmin && $user_forca_seguranca === $piloto_a_excluir_fs)) {
        $stmt_check_missoes = $conn->prepare("SELECT COUNT(*) AS total FROM missoes_pilotos WHERE piloto_id = ?");
        if ($stmt_check_missoes) {
            $stmt_check_missoes->bind_param("i", $delete_id);
            $stmt_check_missoes->execute();
            $missoes_count = $stmt_check_missoes->get_result()->fetch_assoc()['total'];
            $stmt_check_missoes->close();
        } else {
            error_log("Erro na preparação da consulta de missões para exclusão de piloto: " . $conn->error);
            $missoes_count = 0;
        }

        if ($missoes_count > 0) {
            $mensagem_status = "<div class='error-message-box'>Não é possível excluir este piloto, pois ele possui missões vinculadas.</div>";
        } else {
            $stmt_delete = $conn->prepare("DELETE FROM pilotos WHERE id = ?");
            if ($stmt_delete) {
                $stmt_delete->bind_param("i", $delete_id);
                if ($stmt_delete->execute()) {
                    $mensagem_status = "<div class='success-message-box'>Piloto excluído com sucesso!</div>";
                } else {
                    $mensagem_status = "<div class='error-message-box'>Erro ao excluir o piloto.</div>";
                }
                $stmt_delete->close();
            } else {
                error_log("Erro na preparação da consulta de exclusão de piloto: " . $conn->error);
                $mensagem_status = "<div class='error-message-box'>Erro interno ao tentar excluir o piloto.</div>";
            }
        }
    } else {
        $mensagem_status = "<div class='error-message-box'>Você não tem permissão para excluir um piloto desta Força de Segurança.</div>";
    }
}

// 3. LÓGICA PARA LISTAR OS PILOTOS
$pilotos_agrupados = [];
$where_clauses = [];
$params = [];
$types = '';

$sort_columns = [
    'posto_graduacao' => "CASE posto_graduacao WHEN 'Cel. QOBM' THEN 1 WHEN 'Ten. Cel. QOBM' THEN 2 WHEN 'Maj. QOBM' THEN 3 WHEN 'Cap. QOBM' THEN 4 WHEN '1º Ten. QOBM' THEN 5 WHEN '2º Ten. QOBM' THEN 6 WHEN 'Asp. Oficial' THEN 7 WHEN 'Sub. Ten. QPBM' THEN 8 WHEN '1º Sgt. QPBM' THEN 9 WHEN '2º Sgt. QPBM' THEN 10 WHEN '3º Sgt. QPBM' THEN 11 WHEN 'Cb. QPBM' THEN 12 WHEN 'Sd. QPBM' THEN 13 WHEN 'Policial Penal' THEN 14 WHEN 'Agente Penal' THEN 15 WHEN 'Cel. QOPM' THEN 16 WHEN 'Ten. Cel QOPM' THEN 17 WHEN 'Cap. QOPM' THEN 18 WHEN '2º Ten. QOPM' THEN 19 WHEN '1º Ten. QOPM' THEN 20 WHEN 'Aspirante a Oficial' THEN 21 WHEN 'Sub. Ten. QPM 1-0' THEN 22 WHEN '1º Sgt. QPM 1-0' THEN 23 WHEN '2º Sgt. QPM 1-0' THEN 24 WHEN '3º Sgt. QPM 1-0' THEN 25 WHEN 'Cb. QPM 1-0' THEN 26 WHEN 'Sd. QPM 1-0' THEN 27 ELSE 99 END",
    'nome_completo' => 'nome_completo',
    'nome_usuario' => 'nome_usuario',
    'codigo_cadastro' => 'codigo_cadastro',
    'crbm_piloto' => 'crbm_piloto',
    'obm_piloto' => 'obm_piloto',
    'forca_seguranca' => 'forca_seguranca',
    'status_piloto' => 'status_piloto',
    'tipo_usuario' => 'tipo_usuario'
];
$sort_by = $_GET['sort'] ?? 'posto_graduacao';
$sort_dir = $_GET['dir'] ?? 'ASC';
$order_by = $sort_columns[$sort_by] ?? $sort_columns['posto_graduacao'];
$order_dir = (strtoupper($sort_dir) === 'DESC') ? 'DESC' : 'ASC';

if ($isPiloto && !empty($logged_in_pilot_crbm)) {
    $where_clauses[] = "crbm_piloto = ?";
    $params[] = $logged_in_pilot_crbm;
    $types .= 's';
} elseif ($isAdmin && !$isSuperAdmin && !empty($user_forca_seguranca)) {
    $where_clauses[] = "forca_seguranca = ?";
    $params[] = $user_forca_seguranca;
    $types .= 's';
}

$sql_pilotos = "SELECT id, posto_graduacao, nome_completo, nome_usuario, codigo_cadastro, crbm_piloto, obm_piloto, forca_seguranca, status_piloto, tipo_usuario FROM pilotos";
if (!empty($where_clauses)) {
    $sql_pilotos .= " WHERE " . implode(' AND ', $where_clauses);
}
$sql_pilotos .= " ORDER BY forca_seguranca ASC, " . $order_by . " " . $order_dir;
$stmt_pilotos = $conn->prepare($sql_pilotos);
if ($stmt_pilotos) {
    if (!empty($params)) {
        $stmt_pilotos->bind_param($types, ...$params);
    }
    $stmt_pilotos->execute();
    $result_pilotos = $stmt_pilotos->get_result();
    if ($result_pilotos && $result_pilotos->num_rows > 0) {
        while ($row = $result_pilotos->fetch_assoc()) {
            $pilotos_agrupados[$row['forca_seguranca']][] = $row;
        }
    }
    $stmt_pilotos->close();
} else {
    die("Erro na preparação da consulta de pilotos: " . $conn->error);
}

function get_sort_link_piloto($column, $current_column, $current_dir) {
    $dir = ($column == $current_column && $current_dir == 'ASC') ? 'DESC' : 'ASC';
    $params = $_GET;
    $params['sort'] = $column;
    $params['dir'] = $dir;
    return '?' . http_build_query($params);
}
?>
<style>
    .data-table th a { color: inherit; text-decoration: none; }
    .data-table th a:hover { text-decoration: underline; }
    .action-buttons a { display: inline-block; margin: 0 2px; }
    .group-header h2 { margin: 0; padding: 10px; background-color: #f2f2f2; border-bottom: 2px solid #ddd; }
    .data-table { table-layout: fixed; width: 100%; }
    .data-table th, .data-table td { vertical-align: middle; text-align: center; }
    .data-table colgroup col:nth-child(1) { width: 10%; }
    .data-table colgroup col:nth-child(2) { width: 18%; }
    .data-table colgroup col:nth-child(3) { width: 12%; }
    .data-table colgroup col:nth-child(4) { width: 8%; }
    .data-table colgroup col:nth-child(5) { width: 12%; }
    .data-table colgroup col:nth-child(6) { width: 10%; }
    .data-table colgroup col:nth-child(7) { width: 8%; }
    .data-table colgroup col:nth-child(8) { width: 10%; }
    .data-table colgroup col:nth-child(9) { width: 10%; }
</style>
<div class="main-content">
    <h1>Lista de Pilotos</h1>
    
    <?php if(!empty($mensagem_status)) echo $mensagem_status; ?>

    <?php if (!empty($pilotos_agrupados)): ?>
        <?php foreach ($pilotos_agrupados as $forca_seguranca => $pilotos): ?>
            <div class="table-container" style="margin-top: 30px;">
                <h2><?php echo htmlspecialchars($forca_seguranca); ?></h2>
                <table class="data-table">
                    <colgroup>
                        <col><col>
                        <?php if (!$isPiloto): ?><col><?php endif; ?>
                        <col><col><col><col>
                        <?php if (!$isPiloto): ?><col><col><?php endif; ?>
                    </colgroup>
                    <thead>
                        <tr>
                            <?php
                                // *** Rótulos dinâmicos ***
                                $config_atual = $forcas_config[$forca_seguranca] ?? null;
                                $posto_label = $config_atual['posto_graduacao_label'] ?? 'Posto/Graduação';
                                $crbm_label = $config_atual['crbm_label'] ?? 'Unidade Superior';
                                $obm_label = $config_atual['obm_label'] ?? 'Subunidade';
                                // *** FIM DA SEÇÃO ***
                            ?>
                            <th>
                                <a href="<?php echo get_sort_link_piloto('posto_graduacao', $sort_by, $sort_dir); ?>">
                                    <?php echo htmlspecialchars($posto_label); ?>
                                    <?php echo ($sort_by === 'posto_graduacao') ? (($order_dir === 'ASC') ? '▲' : '▼') : ''; ?>
                                </a>
                            </th>
                            <th>
                                <a href="<?php echo get_sort_link_piloto('nome_completo', $sort_by, $sort_dir); ?>">
                                    Nome Completo <?php echo ($sort_by === 'nome_completo') ? (($order_dir === 'ASC') ? '▲' : '▼') : ''; ?>
                                </a>
                            </th>
                            <?php if (!$isPiloto): ?>
                                <th>
                                    <a href="<?php echo get_sort_link_piloto('nome_usuario', $sort_by, $sort_dir); ?>">
                                        Nome de Usuário <?php echo ($sort_by === 'nome_usuario') ? (($order_dir === 'ASC') ? '▲' : '▼') : ''; ?>
                                    </a>
                                </th>
                            <?php endif; ?>
                            <th>
                                <a href="<?php echo get_sort_link_piloto('codigo_cadastro', $sort_by, $sort_dir); ?>">
                                    Código Interno <?php echo ($sort_by === 'codigo_cadastro') ? (($order_dir === 'ASC') ? '▲' : '▼') : ''; ?>
                                </a>
                            </th>
                            <th>
                                <a href="<?php echo get_sort_link_piloto('crbm_piloto', $sort_by, $sort_dir); ?>">
                                    <?php echo htmlspecialchars($crbm_label); ?>
                                    <?php echo ($sort_by === 'crbm_piloto') ? (($order_dir === 'ASC') ? '▲' : '▼') : ''; ?>
                                </a>
                            </th>
                            <th>
                                <a href="<?php echo get_sort_link_piloto('obm_piloto', $sort_by, $sort_dir); ?>">
                                    <?php echo htmlspecialchars($obm_label); ?>
                                    <?php echo ($sort_by === 'obm_piloto') ? (($order_dir === 'ASC') ? '▲' : '▼') : ''; ?>
                                </a>
                            </th>
                            <th>
                                <a href="<?php echo get_sort_link_piloto('status_piloto', $sort_by, $sort_dir); ?>">
                                    Status <?php echo ($sort_by === 'status_piloto') ? (($order_dir === 'ASC') ? '▲' : '▼') : ''; ?>
                                </a>
                            </th>
                            <?php if (!$isPiloto): ?>
                                <th>
                                    <a href="<?php echo get_sort_link_piloto('tipo_usuario', $sort_by, $sort_dir); ?>">
                                        Tipo Usuário <?php echo ($sort_by === 'tipo_usuario') ? (($order_dir === 'ASC') ? '▲' : '▼') : ''; ?>
                                    </a>
                                </th>
                                <th>Ações</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pilotos as $piloto): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($piloto['posto_graduacao'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($piloto['nome_completo'] ?? 'N/A'); ?></td>
                                <?php if (!$isPiloto): ?>
                                    <td><?php echo htmlspecialchars($piloto['nome_usuario'] ?? 'N/A'); ?></td>
                                <?php endif; ?>
                                <td><?php echo htmlspecialchars($piloto['codigo_cadastro'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php 
                                        $crbm_piloto_formatado = $piloto['crbm_piloto'] ?? 'N/A';
                                        // *** Formatação para CRBM e CRPM ***
                                        $crbm_piloto_formatado = preg_replace('/(\d)(CRBM|CRPM)/i', '$1º $2', $crbm_piloto_formatado);
                                        echo htmlspecialchars($crbm_piloto_formatado);
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($piloto['obm_piloto'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php
                                    $status_map = ['ativo' => 'Ativo', 'afastado' => 'Afastado', 'desativado' => 'Inativo'];
                                    $status = $piloto['status_piloto'] ?? 'N/A';
                                    $status_texto = $status_map[$status] ?? ucfirst($status);
                                    ?>
                                    <span class="status-<?php echo htmlspecialchars($status); ?>"><?php echo htmlspecialchars($status_texto); ?></span>
                                </td>
                                <?php if (!$isPiloto): ?>
                                    <td>
                                        <?php 
                                            $tipo_usuario_texto = $piloto['tipo_usuario'] ?? 'N/A';
                                            if ($tipo_usuario_texto == 'super_administrador') {
                                                echo 'Administrador de Sistema';
                                            } else {
                                                echo htmlspecialchars(ucfirst(str_replace('_', ' ', $tipo_usuario_texto)));
                                            }
                                        ?>
                                    </td>
                                    <td class="action-buttons">
                                        <a href="ver_piloto.php?id=<?php echo $piloto['id']; ?>" class="edit-btn" style="background-color: #3498db;">Ver</a>
                                        <a href="editar_pilotos.php?id=<?php echo $piloto['id']; ?>" class="edit-btn">Editar</a>
                                        <a href="listar_pilotos.php?delete_id=<?php echo $piloto['id']; ?>" class="edit-btn" style="background-color:#dc3545;" onclick="return confirm('Tem certeza que deseja excluir este piloto? Esta ação não pode ser desfeita e só funcionará se não houver missões vinculadas.');">Excluir</a>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="table-container">
            <p style="text-align: center;">Nenhum piloto encontrado para sua <?php echo ($isSuperAdmin) ? 'Força de Segurança.' : (($isAdmin) ? 'Força de Segurança.' : 'OBM.'); ?></p>
        </div>
    <?php endif; ?>
</div>

<?php
// 4. INCLUI O RODAPÉ
require_once 'includes/footer.php';
?>