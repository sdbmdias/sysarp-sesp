<?php
// 1. INCLUI O CABEÇALHO PADRÃO
require_once 'includes/header.php';

// Adicionado para carregar as configurações das forças
$forcas_config = json_decode(file_get_contents(__DIR__ . '/includes/config_forcas.json'), true);

// 2. VERIFICAÇÃO DE PERMISSÃO
if (!$isAdmin && !$isSuperAdmin) {
    header("Location: dashboard.php");
    exit();
}

// 3. LÓGICA ESPECÍFICA DA PÁGINA
$mensagem_status = "";
$piloto_data = null;
$piloto_id = isset($_GET['id']) ? intval($_GET['id']) : null;

if (!$piloto_id) {
    $mensagem_status = "<div class='error-message-box'>ID do piloto não fornecido.</div>";
} else {
    // Busca todas as informações do piloto
    $stmt_load = $conn->prepare("SELECT * FROM pilotos WHERE id = ?");
    $stmt_load->bind_param("i", $piloto_id);
    $stmt_load->execute();
    $result = $stmt_load->get_result();

    if ($result->num_rows === 1) {
        $piloto_data = $result->fetch_assoc();
    } else {
        $mensagem_status = "<div class='error-message-box'>Piloto não encontrado.</div>";
    }
    $stmt_load->close();
}

// ====================================================================================================
// *** INÍCIO DA SEÇÃO ALTERADA: Definição de Rótulos Dinâmicos ***
// ====================================================================================================
$posto_label = 'Posto/Graduação';
$crbm_label = 'Unidade Superior';
$obm_label = 'Subunidade';
$cparp_label = 'CPARP';

if (isset($piloto_data['forca_seguranca']) && isset($forcas_config[$piloto_data['forca_seguranca']])) {
    $config_piloto = $forcas_config[$piloto_data['forca_seguranca']];
    $posto_label = $config_piloto['posto_graduacao_label'] ?? $posto_label;
    $crbm_label = $config_piloto['crbm_label'] ?? $crbm_label;
    $obm_label = $config_piloto['obm_label'] ?? $obm_label;
    $cparp_label = $config_piloto['cparp_label'] ?? $cparp_label;
}
// ====================================================================================================
// *** FIM DA SEÇÃO ALTERADA ***
// ====================================================================================================
?>

<div class="main-content">
    <h1>Detalhes do Piloto</h1>

    <?php echo $mensagem_status; ?>

    <?php if ($piloto_data): ?>
    <div class="form-container">
        <div class="detail-grid">
            <div class="detail-group">
                <label><?php echo htmlspecialchars($posto_label); ?>:</label>
                <span><?php echo htmlspecialchars($piloto_data['posto_graduacao'] ?? 'N/A'); ?></span>
            </div>
            <div class="detail-group">
                <label>Nome Completo:</label>
                <span><?php echo htmlspecialchars($piloto_data['nome_completo'] ?? 'N/A'); ?></span>
            </div>
            <div class="detail-group">
                <label>Nome de Usuário:</label>
                <span><?php echo htmlspecialchars($piloto_data['nome_usuario'] ?? 'N/A'); ?></span>
            </div>
            <div class="detail-group">
                <label>Código Interno:</label>
                <span><?php echo htmlspecialchars($piloto_data['codigo_cadastro'] ?? 'N/A'); ?></span>
            </div>
            <div class="detail-group">
                <label>E-mail:</label>
                <span><?php echo htmlspecialchars($piloto_data['email'] ?? 'N/A'); ?></span>
            </div>
            <div class="detail-group">
                <label>Telefone:</label>
                <span><?php echo htmlspecialchars($piloto_data['telefone'] ?? 'N/A'); ?></span>
            </div>
            <div class="detail-group">
                <label><?php echo htmlspecialchars($crbm_label); ?>:</label>
                <?php // Formatação dinâmica do valor ?>
                <span><?php echo htmlspecialchars(preg_replace('/(\d)(CRBM|CRPM)/i', '$1º $2', $piloto_data['crbm_piloto'] ?? 'N/A')); ?></span>
            </div>
            <div class="detail-group">
                <label><?php echo htmlspecialchars($obm_label); ?>:</label>
                <span><?php echo htmlspecialchars($piloto_data['obm_piloto'] ?? 'N/A'); ?></span>
            </div>
            <div class="detail-group">
                <label>Código SARPAS:</label>
                <span><?php echo htmlspecialchars($piloto_data['cadastro_sarpas'] ?? 'N/A'); ?></span>
            </div>
            <div class="detail-group">
                <label><?php echo htmlspecialchars($cparp_label); ?>:</label>
                <span><?php echo htmlspecialchars($piloto_data['cparp'] ?? 'N/A'); ?></span>
            </div>
            <div class="detail-group">
                <label>Força de Segurança:</label>
                <span><?php echo htmlspecialchars($piloto_data['forca_seguranca'] ?? 'N/A'); ?></span>
            </div>
            <div class="detail-group">
                <label>Status:</label>
                <span><?php echo htmlspecialchars($piloto_data['status_piloto'] ?? 'N/A'); ?></span>
            </div>
            <div class="detail-group">
                <label>Tipo de Usuário:</label>
                <span><?php echo htmlspecialchars(ucfirst($piloto_data['tipo_usuario'] ?? 'N/A')); ?></span>
            </div>
            <div class="detail-group">
                <label>Informações Adicionais:</label>
                <span><?php echo nl2br(htmlspecialchars($piloto_data['info_adicionais'] ?? 'N/A')); ?></span>
            </div>
        </div>
        <div class="form-actions" style="text-align: left;">
            <a href="listar_pilotos.php" class="btn-primary" style="background-color: var(--color-sidebar-bg);">Voltar para a Lista</a>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
    .detail-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }
    .detail-group {
        display: flex;
        flex-direction: column;
    }
    .detail-group label {
        font-weight: bold;
        color: var(--color-text-medium);
        margin-bottom: 5px;
    }
    .detail-group span {
        background-color: #f9f9f9;
        padding: 10px;
        border-radius: 5px;
        border: 1px solid #ddd;
    }
    @media (max-width: 768px) {
        .detail-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<?php
// 4. INCLUI O RODAPÉ
require_once 'includes/footer.php';
?>