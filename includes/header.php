<?php
// 1. INCLUI O INICIALIZADOR ESSENCIAL (SESSÃO, BANCO, PERFIS)
require_once 'init.php';

// ====================================================================================================
// *** INÍCIO DA SEÇÃO CRÍTICA: Lógica de Carregamento Híbrido ***
// ====================================================================================================

function carregar_config_hibrida($conn) {
    $config_base = [];
    $json_file = __DIR__ . '/config_forcas.json';
    if (file_exists($json_file)) {
        $config_base = json_decode(file_get_contents($json_file), true);
    }
    if (empty($config_base)) {
        return [];
    }

    $unidades_db_result = $conn->query("
        SELECT forca_sigla, id, unidade_pai_id, nome_unidade 
        FROM unidades 
        ORDER BY forca_sigla, unidade_pai_id, nome_unidade
    ");
    $unidades_db = $unidades_db_result ? $unidades_db_result->fetch_all(MYSQLI_ASSOC) : [];

    foreach ($config_base as $sigla => &$forca_config) {
        $forca_config['unidades'] = [];

        $unidades_da_forca = array_filter($unidades_db, function($u) use ($sigla) {
            return $u['forca_sigla'] === $sigla;
        });

        $unidades_pai = [];
        $unidades_filho = [];
        foreach($unidades_da_forca as $unidade) {
            if (is_null($unidade['unidade_pai_id'])) {
                $unidades_pai[$unidade['id']] = $unidade['nome_unidade'];
            } else {
                $unidades_filho[$unidade['unidade_pai_id']][] = $unidade['nome_unidade'];
            }
        }

        if ($sigla == 'PMPR') {
            $forca_config['unidades']['crpms'] = array_values($unidades_pai);
            foreach ($unidades_pai as $id_pai => $nome_pai) {
                 $forca_config['unidades']['opms_por_crpm'][$nome_pai] = $unidades_filho[$id_pai] ?? [];
            }
        } else {
            foreach ($unidades_pai as $id_pai => $nome_pai) {
                $forca_config['unidades'][$nome_pai] = $unidades_filho[$id_pai] ?? [];
            }
        }
    }

    return $config_base;
}

$config_forcas = carregar_config_hibrida($conn);
// ====================================================================================================
// *** FIM DA SEÇÃO CRÍTICA ***
// ====================================================================================================

if (isset($_SESSION['force_password_reset']) && basename($_SERVER['PHP_SELF']) != 'primeiro_acesso.php') {
    header('Location: primeiro_acesso.php');
    exit();
}

$paginas_publicas = ['index.php', 'primeiro_acesso.php', 'esqueci_senha.php', 'redefinir_senha.php'];
if (!isset($_SESSION['user_id']) && !in_array(basename($_SERVER['PHP_SELF']), $paginas_publicas)) {
    header("Location: index.php");
    exit();
}

$nome_perfil = '';
if (isset($_SESSION['user_type'])) {
    if ($_SESSION['user_type'] == 'super_administrador') {
        $nome_perfil = 'Administrador de Sistema';
    } else {
        $nome_perfil = ucfirst(str_replace('_', ' ', $_SESSION['user_type']));
    }
}

$logged_in_pilot_crbm = '';
if ($isPiloto && isset($_SESSION['user_id'])) {
    $stmt_crbm = $conn->prepare("SELECT crbm_piloto FROM pilotos WHERE id = ?");
    $stmt_crbm->bind_param("i", $_SESSION['user_id']);
    $stmt_crbm->execute();
    $result_crbm = $stmt_crbm->get_result();
    if ($result_crbm && $result_crbm->num_rows > 0) {
        $logged_in_pilot_crbm = $result_crbm->fetch_assoc()['crbm_piloto'];
    }
    $stmt_crbm->close();
}

$existem_alertas = false;
if ($isSuperAdmin || $isAdmin) {
    $sql_alerts = "SELECT COUNT(id) AS total_alertas FROM aeronaves WHERE validade_sisant <= DATE_ADD(CURDATE(), INTERVAL 15 DAY)";
    $result_alerts = $conn->query($sql_alerts);
    if ($result_alerts && $result_alerts->num_rows > 0) {
        $row_alerts = $result_alerts->fetch_assoc();
        if ($row_alerts['total_alertas'] > 0) {
            $existem_alertas = true;
        }
    }
}

$body_class = '';
if (!empty($user_forca_seguranca)) {
    switch ($user_forca_seguranca) {
        case 'PMPR': $body_class = 'theme-pmpr'; break;
        case 'Polícia Penal': $body_class = 'theme-policia-penal'; break;
        case 'Defesa Civil Estadual': $body_class = 'theme-defesa-civil-estadual'; break;
        case 'PCPR': $body_class = 'theme-pcpr'; break;
        case 'Polícia Científica': $body_class = 'theme-policia-cientifica'; break;
        case 'CBMPR': default: $body_class = 'theme-cbmpr'; break;
    }
} else {
    $body_class = 'theme-cbmpr';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SOARP - CBMPR</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/main.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="<?php echo $body_class; ?>">
    <div class="mobile-header no-print">
        <i class="fas fa-bars menu-toggle"></i>
        <h1 class="page-title">SOARP</h1>
        <div></div>
    </div>
    <div class="overlay no-print"></div>

    <div class="sidebar no-print">
        <ul>
            <li><a href="dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a></li>
            <li><a href="listar_pilotos.php"><i class="fas fa-users"></i> Pilotos</a></li>
            <li><a href="listar_aeronaves.php"><i class="fas fa-plane"></i> Aeronaves</a></li>
            <li><a href="listar_controles.php"><i class="fas fa-gamepad"></i> Controles</a></li>
            <li><a href="manutencao.php"><i class="fas fa-tools"></i> Manutenção</a></li>
            <li><a href="checklist.php"><i class="fas fa-check-square"></i> Checklist/Documentos</a></li>
            <li><a href="listar_missoes.php"><i class="fas fa-map-marked-alt"></i> Missões</a></li>
            <li><a href="relprev.php"><i class="fas fa-shield-alt"></i> RELPREV</a></li>
            <li><a href="relatorios.php"><i class="fas fa-file-pdf"></i> Relatórios</a></li>

            <?php if ($isSuperAdmin || $isAdmin): ?>
            <li class="has-submenu" id="admin-menu">
                <a href="#" id="admin-menu-toggle"><i class="fas fa-user-shield"></i> Admin <i class="fas fa-chevron-down submenu-arrow"></i></a>
                <ul class="submenu" id="admin-submenu">
                    <li><a href="listar_relprev.php">Ver RELPREVs</a></li>
                    <li><a href="cadastro_aeronaves.php">Cadastro de Aeronaves</a></li>
                    <li><a href="cadastro_pilotos.php">Cadastro de Pilotos</a></li>
                    <li><a href="cadastro_controles.php">Cadastro de Controles</a></li>
                    <?php if ($isSuperAdmin): ?>
                    <li><a href="cadastro_modelos.php">Cadastro de Modelos</a></li>
                    <li><a href="cadastro_operacoes.php">Cadastro de Operações</a></li> 
                    <li><a href="gerenciar_unidades.php">Gerenciar Unidades</a></li>
                    <?php endif; ?>
                    <li><a href="gerenciar_documentos.php">Gerenciar Documentos</a></li>
                    <li><a href="alertas.php" class="<?php if ($existem_alertas) echo 'menu-alert'; ?>">Alertas</a></li>
                </ul>
            </li>
            <?php endif; ?>

            <li><a href="logout.php" style="color: var(--color-text-alert);"><i class="fas fa-sign-out-alt"></i> Sair</a></li>
        </ul>

        <?php if (!empty($nome_perfil)): ?>
        <div class="user-role-display">
            <p>Você está logado como:<br><strong><?php echo htmlspecialchars($nome_perfil); ?></strong></p>
        </div>
        <?php endif; ?>
    </div>