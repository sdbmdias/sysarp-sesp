<?php
// Inicia a sessão em todas as páginas
session_start();

// 1. INCLUI A CONEXÃO COM O BANCO DE DADOS
require_once 'database.php';

// 2. BLOCO DE SEGURANÇA E AUTENTICAÇÃO
if (isset($_SESSION['force_password_reset']) && basename($_SERVER['PHP_SELF']) != 'primeiro_acesso.php') {
    header('Location: primeiro_acesso.php');
    exit();
}

$paginas_publicas = ['index.php', 'primeiro_acesso.php', 'esqueci_senha.php', 'redefinir_senha.php'];
if (!isset($_SESSION['user_id']) && !in_array(basename($_SERVER['PHP_SELF']), $paginas_publicas)) {
    header("Location: index.php");
    exit();
}

// 3. DEFINIÇÃO DE PERFIS DE USUÁRIO
$isSuperAdmin = isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'super_administrador';
$isAdmin = isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'administrador';
$isPiloto = isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'piloto';

$nome_perfil = '';
if (isset($_SESSION['user_type'])) {
    if ($_SESSION['user_type'] == 'super_administrador') {
        $nome_perfil = 'Administrador de Sistema';
    } else {
        $nome_perfil = ucfirst(str_replace('_', ' ', $_SESSION['user_type']));
    }
}
$user_forca_seguranca = $_SESSION['forca_seguranca'] ?? '';

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


// 4. LÓGICA DE VERIFICAÇÃO DE ALERTA (MANUTENÇÃO SISANT)
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


// ====================================================================================================
// *** INÍCIO DA SEÇÃO CORRIGIDA: Lógica de seleção de tema aprimorada ***
// ====================================================================================================
$body_class = '';
if (!empty($user_forca_seguranca)) {
    switch ($user_forca_seguranca) {
        case 'PMPR':
            $body_class = 'theme-pmpr';
            break;
        case 'Polícia Penal':
            $body_class = 'theme-policia-penal';
            break;
        case 'Defesa Civil Estadual':
            $body_class = 'theme-defesa-civil-estadual';
            break;
        case 'PCPR':
            $body_class = 'theme-pcpr';
            break;
        case 'Polícia Científica':
            $body_class = 'theme-policia-cientifica';
            break;
        case 'CBMPR':
        default:
            $body_class = 'theme-cbmpr'; // Define CBMPR como tema padrão
            break;
    }
} else {
    // Garante que o tema padrão seja aplicado se o usuário não tiver força definida
    $body_class = 'theme-cbmpr';
}
// ====================================================================================================
// *** FIM DA SEÇÃO CORRIGIDA ***
// ====================================================================================================
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SOARP - CBMPR</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/main.css">
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