<?php
// 1. INCLUI O CABEÇALHO PADRÃO
require_once 'includes/header.php';

$mensagem_status = "";

// 2. LÓGICA DE EXCLUSÃO (APENAS PARA ADMINS)
if (($isSuperAdmin || $isAdmin) && isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);

    // Busca a forca_seguranca da aeronave a ser excluída para verificação de permissão
    $stmt_acft_fs = $conn->prepare("SELECT forca_seguranca FROM aeronaves WHERE id = ?");
    $stmt_acft_fs->bind_param("i", $delete_id);
    $stmt_acft_fs->execute();
    $result_acft_fs = $stmt_acft_fs->get_result();
    $acft_a_excluir_fs = $result_acft_fs->fetch_assoc()['forca_seguranca'];
    $stmt_acft_fs->close();

    if ($isSuperAdmin || ($isAdmin && $user_forca_seguranca === $acft_a_excluir_fs)) {
        // Verifica se a aeronave está associada a missões, manutenções ou controles
        $stmt_check_missoes = $conn->prepare("SELECT COUNT(*) AS total FROM missoes WHERE aeronave_id = ?");
        $stmt_check_missoes->bind_param("i", $delete_id);
        $stmt_check_missoes->execute();
        $missoes_count = $stmt_check_missoes->get_result()->fetch_assoc()['total'];
        $stmt_check_missoes->close();

        $stmt_check_manutencoes = $conn->prepare("SELECT COUNT(*) AS total FROM manutencoes WHERE equipamento_tipo = 'Aeronave' AND equipamento_id = ?");
        $stmt_check_manutencoes->bind_param("i", $delete_id);
        $stmt_check_manutencoes->execute();
        $manutencoes_count = $stmt_check_manutencoes->get_result()->fetch_assoc()['total'];
        $stmt_check_manutencoes->close();

        $stmt_check_controles = $conn->prepare("SELECT COUNT(*) AS total FROM controles WHERE aeronave_id = ?");
        $stmt_check_controles->bind_param("i", $delete_id);
        $stmt_check_controles->execute();
        $controles_count = $stmt_check_controles->get_result()->fetch_assoc()['total'];
        $stmt_check_controles->close();

        if ($missoes_count > 0 || $manutencoes_count > 0 || $controles_count > 0) {
            $mensagem_status = "<div class='error-message-box'>Não é possível excluir esta aeronave, pois ela possui registros de missões, manutenções ou controles vinculados. Considere alterar o status para 'Baixada'.</div>";
        } else {
            $stmt_delete = $conn->prepare("DELETE FROM aeronaves WHERE id = ?");
            $stmt_delete->bind_param("i", $delete_id);
            if ($stmt_delete->execute()) {
                $conn->query("DELETE FROM aeronaves_logbook WHERE aeronave_id = $delete_id");
                $mensagem_status = "<div class='success-message-box'>Aeronave excluída com sucesso!</div>";
            } else {
                $mensagem_status = "<div class='error-message-box'>Erro ao excluir a aeronave.</div>";
            }
            $stmt_delete->close();
        }
    } else {
        $mensagem_status = "<div class='error-message-box'>Você não tem permissão para excluir uma aeronave desta Força de Segurança.</div>";
    }
}

// 3. LÓGICA PARA LISTAR AS AERONAVES
$aeronaves_agrupadas_fs = [];
$aeronaves_agrupadas_crbm_obm = [];
$aeronaves_flat = [];
$where_clauses = [];
$params = [];
$types = '';

// Lógica de ordenação dinâmica
$sort_columns = [
    'prefixo' => 'a.prefixo',
    'fabricante_modelo' => 'a.fabricante',
    'numero_serie' => 'a.numero_serie',
    'sisant' => 'a.cadastro_sisant',
    'crbm' => 'a.crbm',
    'obm' => 'a.obm',
    'forca_seguranca' => 'a.forca_seguranca',
    'tipo_drone' => 'fm.tipo_drone',
    'pmd' => 'fm.pmd_kg',
    'status' => 'a.status',
    'anatel' => 'a.homologacao_anatel'
];

$sort_by = $_GET['sort'] ?? 'prefixo';
$sort_dir = $_GET['dir'] ?? 'ASC';

$order_by = $sort_columns[$sort_by] ?? $sort_columns['prefixo'];
$order_dir = (strtoupper($sort_dir) === 'DESC') ? 'DESC' : 'ASC';

$sql_aeronaves = "
    SELECT 
        a.id, 
        a.prefixo, 
        a.fabricante, 
        a.modelo, 
        a.numero_serie, 
        a.cadastro_sisant, 
        a.validade_sisant, 
        a.crbm, 
        a.obm, 
        a.forca_seguranca, 
        fm.tipo_drone, 
        fm.pmd_kg, 
        a.status, 
        a.homologacao_anatel 
    FROM 
        aeronaves a
    LEFT JOIN
        fabricantes_modelos fm ON a.fabricante = fm.fabricante AND a.modelo = fm.modelo
";

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
        $sql_aeronaves .= " WHERE a.obm = ?";
        $params[] = $obm_do_usuario_logado;
        $types .= 's';
    }
} elseif ($isAdmin && !$isSuperAdmin) {
    $sql_aeronaves .= " WHERE a.forca_seguranca = ?";
    $params[] = $user_forca_seguranca;
    $types .= 's';
}

if ($isSuperAdmin) {
    $sql_aeronaves .= " ORDER BY a.forca_seguranca ASC, " . $order_by . " " . $order_dir;
} elseif ($isAdmin) {
    $sql_aeronaves .= " ORDER BY a.crbm ASC, a.obm ASC, " . $order_by . " " . $order_dir;
} else {
    $sql_aeronaves .= " ORDER BY " . $order_by . " " . $order_dir;
}

if (!empty($params)) {
    $stmt_aeronaves = $conn->prepare($sql_aeronaves);
    $stmt_aeronaves->bind_param($types, ...$params);
    $stmt_aeronaves->execute();
    $result_aeronaves = $stmt_aeronaves->get_result();
    $stmt_aeronaves->close();
} else {
    $result_aeronaves = $conn->query($sql_aeronaves);
}

if ($result_aeronaves && $result_aeronaves->num_rows > 0) {
    while ($row = $result_aeronaves->fetch_assoc()) {
        if ($isSuperAdmin) {
            $aeronaves_agrupadas_fs[$row['forca_seguranca']][] = $row;
        } elseif ($isAdmin) {
            $grupo_crbm_obm = $row['crbm'] . ' / ' . $row['obm'];
            $aeronaves_agrupadas_crbm_obm[$grupo_crbm_obm][] = $row;
        } else {
            $aeronaves_flat[] = $row;
        }
    }
}

function get_sort_link_aeronave($column, $current_column, $current_dir) {
    $dir = ($column == $current_column && $current_dir == 'ASC') ? 'DESC' : 'ASC';
    $params = $_GET;
    $params['sort'] = $column;
    $params['dir'] = $dir;
    return '?' . http_build_query($params);
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
.data-table th a { color: inherit; text-decoration: none; }
.data-table th a:hover { text-decoration: underline; }

/* Estilo de alinhamento vertical e horizontal, e largura fixa para as colunas */
.data-table {
    table-layout: fixed;
    width: 100%;
}
.data-table th, .data-table td {
    vertical-align: middle;
    text-align: center;
}
/* Definição das larguras das colunas */
.data-table colgroup col:nth-child(1) { width: 10%; }  /* Prefixo */
.data-table colgroup col:nth-child(2) { width: 18%; }  /* Fabricante/Modelo */
.data-table colgroup col:nth-child(3) { width: 12%; }  /* Nº Série */
.data-table colgroup col:nth-child(4) { width: 18%; }  /* Lotação */
.data-table colgroup col:nth-child(5) { width: 10%; }  /* Tipo */
.data-table colgroup col:nth-child(6) { width: 10%; }  /* Status */
.data-table colgroup col:nth-child(7) { width: 12%; }  /* Ações */
</style>
<div class="main-content">
    <h1>Lista de Aeronaves</h1>
    
    <?php if(!empty($mensagem_status)) echo $mensagem_status; ?>

    <?php if ($isSuperAdmin): ?>
        <?php if (!empty($aeronaves_agrupadas_fs)): ?>
            <?php foreach ($aeronaves_agrupadas_fs as $forca_seguranca => $aeronaves): ?>
                <div class="table-container" style="margin-top: 30px;">
                    <h2><?php echo htmlspecialchars($forca_seguranca); ?></h2>
                    <table class="data-table">
                        <colgroup>
                            <col>
                            <col>
                            <col>
                            <col>
                            <col>
                            <col>
                            <col>
                        </colgroup>
                        <thead>
                            <tr>
                                <th><a href="<?php echo get_sort_link_aeronave('prefixo', $sort_by, $sort_dir); ?>">Prefixo <?php echo ($sort_by === 'prefixo') ? (($order_dir === 'ASC') ? '▲' : '▼') : ''; ?></a></th>
                                <th><a href="<?php echo get_sort_link_aeronave('fabricante_modelo', $sort_by, $sort_dir); ?>">Fabricante/Modelo <?php echo ($sort_by === 'fabricante_modelo') ? (($order_dir === 'ASC') ? '▲' : '▼') : ''; ?></a></th>
                                <th><a href="<?php echo get_sort_link_aeronave('numero_serie', $sort_by, $sort_dir); ?>">Nº Série <?php echo ($sort_by === 'numero_serie') ? (($order_dir === 'ASC') ? '▲' : '▼') : ''; ?></a></th>
                                <th>
                                    <?php echo ($forca_seguranca === 'PMPR') ? 'Lotação (CRPM/OPM)' : 'Lotação (CRBM/OBM)'; ?>
                                </th>
                                <th><a href="<?php echo get_sort_link_aeronave('tipo_drone', $sort_by, $sort_dir); ?>">Tipo <?php echo ($sort_by === 'tipo_drone') ? (($order_dir === 'ASC') ? '▲' : '▼') : ''; ?></a></th>
                                <th><a href="<?php echo get_sort_link_aeronave('status', $sort_by, $sort_dir); ?>">Status <?php echo ($sort_by === 'status') ? (($order_dir === 'ASC') ? '▲' : '▼') : ''; ?></a></th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($aeronaves as $aeronave): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($aeronave['prefixo'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars(($aeronave['fabricante'] ?? 'N/A') . ' / ' . ($aeronave['modelo'] ?? 'N/A')); ?></td>
                                    <td><?php echo htmlspecialchars($aeronave['numero_serie'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php 
                                            $crbm_formatado = ($aeronave['forca_seguranca'] === 'PMPR') ? htmlspecialchars(preg_replace('/^(\d+)\s*CRPM$/i', '$1º CRPM', $aeronave['crbm'] ?? 'N/A')) : htmlspecialchars(preg_replace('/(\d)(CRBM)/', '$1º $2', $aeronave['crbm'] ?? 'N/A'));
                                            echo htmlspecialchars($crbm_formatado . ' / ' . ($aeronave['obm'] ?? 'N/A'));
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars(ucfirst(str_replace('_', '-', $aeronave['tipo_drone'] ?? 'N/A'))); ?></td>
                                    <td>
                                        <?php
                                        $status_map = ['ativo' => 'Ativa', 'em_manutencao' => 'Em Manutenção', 'baixada' => 'Baixada', 'adida' => 'Adida'];
                                        $status = $aeronave['status'] ?? 'desconhecido';
                                        $status_texto = $status_map[$status] ?? ucfirst($status);
                                        ?>
                                        <span class="status-<?php echo htmlspecialchars($status); ?>"><?php echo htmlspecialchars($status_texto); ?></span>
                                    </td>
                                    <td class="action-buttons">
                                        <a href="editar_aeronaves.php?id=<?php echo $aeronave['id']; ?>" class="edit-btn">Editar</a>
                                        <a href="listar_aeronaves.php?delete_id=<?php echo $aeronave['id']; ?>" class="edit-btn" style="background-color:#dc3545;" onclick="return confirm('Tem certeza que deseja excluir esta aeronave? Esta ação não pode ser desfeita e só funcionará se não houver missões ou manutenções vinculadas.');">Excluir</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="table-container"><p style="text-align: center;">Nenhuma aeronave encontrada.</p></div>
        <?php endif; ?>

    <?php elseif ($isAdmin): ?>
        <?php if (!empty($aeronaves_agrupadas_crbm_obm)): ?>
            <?php foreach ($aeronaves_agrupadas_crbm_obm as $crbm_obm => $aeronaves): ?>
                <div class="table-container" style="margin-top: 30px;">
                    <h2> 
                        <?php 
                            $forca_seguranca = $aeronaves[0]['forca_seguranca'];
                            $crbm_obm_formatado = ($forca_seguranca === 'PMPR') ? htmlspecialchars(preg_replace('/^(\d+)\s*CRPM$/i', '$1º CRPM', $crbm_obm)) : htmlspecialchars(preg_replace('/(\d)(CRBM)/', '$1º $2', $crbm_obm));
                            echo $crbm_obm_formatado;
                        ?>
                    </h2>
                    <table class="data-table">
                        <colgroup>
                            <col>
                            <col>
                            <col>
                            <col>
                            <col>
                            <col>
                            <col>
                        </colgroup>
                        <thead>
                            <tr>
                                <th><a href="<?php echo get_sort_link_aeronave('prefixo', $sort_by, $sort_dir); ?>">Prefixo <?php echo ($sort_by === 'prefixo') ? (($order_dir === 'ASC') ? '▲' : '▼') : ''; ?></a></th>
                                <th><a href="<?php echo get_sort_link_aeronave('fabricante_modelo', $sort_by, $sort_dir); ?>">Fabricante/Modelo <?php echo ($sort_by === 'fabricante_modelo') ? (($order_dir === 'ASC') ? '▲' : '▼') : ''; ?></a></th>
                                <th><a href="<?php echo get_sort_link_aeronave('numero_serie', $sort_by, $sort_dir); ?>">Nº Série <?php echo ($sort_by === 'numero_serie') ? (($order_dir === 'ASC') ? '▲' : '▼') : ''; ?></a></th>
                                <th><a href="<?php echo get_sort_link_aeronave('tipo_drone', $sort_by, $sort_dir); ?>">Tipo <?php echo ($sort_by === 'tipo_drone') ? (($order_dir === 'ASC') ? '▲' : '▼') : ''; ?></a></th>
                                <th><a href="<?php echo get_sort_link_aeronave('pmd', $sort_by, $sort_dir); ?>">PMD (kg) <?php echo ($sort_by === 'pmd') ? (($order_dir === 'ASC') ? '▲' : '▼') : ''; ?></a></th>
                                <th><a href="<?php echo get_sort_link_aeronave('status', $sort_by, $sort_dir); ?>">Status <?php echo ($sort_by === 'status') ? (($order_dir === 'ASC') ? '▲' : '▼') : ''; ?></a></th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($aeronaves as $aeronave): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($aeronave['prefixo'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars(($aeronave['fabricante'] ?? 'N/A') . ' / ' . ($aeronave['modelo'] ?? 'N/A')); ?></td>
                                    <td><?php echo htmlspecialchars($aeronave['numero_serie'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars(ucfirst(str_replace('_', '-', $aeronave['tipo_drone'] ?? 'N/A'))); ?></td>
                                    <td><?php echo htmlspecialchars($aeronave['pmd_kg'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php
                                        $status_map = ['ativo' => 'Ativa', 'em_manutencao' => 'Em Manutenção', 'baixada' => 'Baixada', 'adida' => 'Adida'];
                                        $status = $aeronave['status'] ?? 'desconhecido';
                                        $status_texto = $status_map[$status] ?? ucfirst($status);
                                        ?>
                                        <span class="status-<?php echo htmlspecialchars($status); ?>"><?php echo htmlspecialchars($status_texto); ?></span>
                                    </td>
                                    <td class="action-buttons">
                                        <a href="editar_aeronaves.php?id=<?php echo $aeronave['id']; ?>" class="edit-btn">Editar</a>
                                        <a href="listar_aeronaves.php?delete_id=<?php echo $aeronave['id']; ?>" class="edit-btn" style="background-color:#dc3545;" onclick="return confirm('Tem certeza que deseja excluir esta aeronave? Esta ação não pode ser desfeita e só funcionará se não houver missões ou manutenções vinculadas.');">Excluir</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="table-container"><p style="text-align: center;">Nenhuma aeronave encontrada para sua Força de Segurança.</p></div>
        <?php endif; ?>

    <?php elseif ($isPiloto): ?>
        <div class="table-container">
            <table class="data-table">
                <colgroup>
                    <col>
                    <col>
                    <col>
                    <col>
                    <col>
                    <col>
                </colgroup>
                <thead>
                    <tr>
                        <th><a href="<?php echo get_sort_link_aeronave('prefixo', $sort_by, $sort_dir); ?>">Prefixo <?php echo ($sort_by === 'prefixo') ? (($order_dir === 'ASC') ? '▲' : '▼') : ''; ?></a></th>
                        <th><a href="<?php echo get_sort_link_aeronave('fabricante_modelo', $sort_by, $sort_dir); ?>">Fabricante/Modelo <?php echo ($sort_by === 'fabricante_modelo') ? (($order_dir === 'ASC') ? '▲' : '▼') : ''; ?></a></th>
                        <th><a href="<?php echo get_sort_link_aeronave('numero_serie', $sort_by, $sort_dir); ?>">Nº Série <?php echo ($sort_by === 'numero_serie') ? (($order_dir === 'ASC') ? '▲' : '▼') : ''; ?></a></th>
                        <th><a href="<?php echo get_sort_link_aeronave('tipo_drone', $sort_by, $sort_dir); ?>">Tipo <?php echo ($sort_by === 'tipo_drone') ? (($order_dir === 'ASC') ? '▲' : '▼') : ''; ?></a></th>
                        <th><a href="<?php echo get_sort_link_aeronave('pmd', $sort_by, $sort_dir); ?>">PMD (kg) <?php echo ($sort_by === 'pmd') ? (($order_dir === 'ASC') ? '▲' : '▼') : ''; ?></a></th>
                        <th><a href="<?php echo get_sort_link_aeronave('status', $sort_by, $sort_dir); ?>">Status <?php echo ($sort_by === 'status') ? (($order_dir === 'ASC') ? '▲' : '▼') : ''; ?></a></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($aeronaves_flat)): ?>
                        <?php foreach ($aeronaves_flat as $aeronave): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($aeronave['prefixo'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars(($aeronave['fabricante'] ?? 'N/A') . ' / ' . ($aeronave['modelo'] ?? 'N/A')); ?></td>
                                <td><?php echo htmlspecialchars($aeronave['numero_serie'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars(ucfirst(str_replace('_', '-', $aeronave['tipo_drone'] ?? 'N/A'))); ?></td>
                                <td><?php echo htmlspecialchars($aeronave['pmd_kg'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php
                                    $status_map = ['ativo' => 'Ativa', 'em_manutencao' => 'Em Manutenção', 'baixada' => 'Baixada', 'adida' => 'Adida'];
                                    $status = $aeronave['status'] ?? 'desconhecido';
                                    $status_texto = $status_map[$status] ?? ucfirst($status);
                                    ?>
                                    <span class="status-<?php echo htmlspecialchars($status); ?>"><?php echo htmlspecialchars($status_texto); ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6">Nenhuma aeronave encontrada para sua OBM.</td>
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
?>0