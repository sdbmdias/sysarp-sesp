<?php
// 1. INCLUI O CABEÇALHO PADRÃO
require_once 'includes/header.php';

// 2. VERIFICAÇÃO DE PERMISSÃO
if (!$isSuperAdmin && !$isAdmin) {
    header("Location: dashboard.php");
    exit();
}

// 3. LÓGICA ESPECÍFICA DA PÁGINA
$mensagem_status = "";
$piloto_data = null;
$user_forca_seguranca = $_SESSION['forca_seguranca'] ?? '';

$piloto_id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_POST['piloto_id']) ? intval($_POST['piloto_id']) : null);

if ($_SERVER["REQUEST_METHOD"] == "POST" && $piloto_id) {
    $forca_seguranca = $_POST['forca_seguranca'];
    $posto_graduacao = $_POST['posto_graduacao'];
    $nome_completo = $_POST['nome_completo'];
    $email = $_POST['email'];
    $telefone = $_POST['telefone'];
    $crbm_piloto = $_POST['crbm_piloto'];
    $obm_piloto = $_POST['obm_piloto'];
    $cadastro_sarpas = $_POST['cadastro_sarpas'];
    $cparp = $_POST['cparp'];
    $status_piloto = $_POST['status_piloto'];
    $info_adicionais_piloto = $_POST['info_adicionais'];
    $tipo_usuario_input = $_POST['tipo_usuario'];
    
    $stmt_current = $conn->prepare("SELECT tipo_usuario, forca_seguranca FROM pilotos WHERE id = ?");
    $stmt_current->bind_param("i", $piloto_id);
    $stmt_current->execute();
    $result_current = $stmt_current->get_result();
    $current_data = $result_current->fetch_assoc();
    $current_user_type = $current_data['tipo_usuario'];
    $current_forca_seguranca = $current_data['forca_seguranca'];
    $stmt_current->close();

    $tipo_usuario = $tipo_usuario_input;
    if ($isAdmin && !$isSuperAdmin) {
        if ($current_user_type === 'super_administrador' || $tipo_usuario_input === 'super_administrador') {
            $mensagem_status = "<div class='error-message-box'>Você não tem permissão para alterar o nível de acesso de um Administrador de Sistema.</div>";
            $tipo_usuario = $current_user_type;
        }
        
        if ($user_forca_seguranca !== $current_forca_seguranca) {
            $mensagem_status = "<div class='error-message-box'>Você não tem permissão para editar pilotos de outra Força de Segurança.</div>";
            $piloto_id = null;
        }
    }

    if (empty($mensagem_status)) {
        $stmt = $conn->prepare("UPDATE pilotos SET posto_graduacao=?, nome_completo=?, email=?, telefone=?, crbm_piloto=?, obm_piloto=?, cadastro_sarpas=?, cparp=?, status_piloto=?, info_adicionais=?, tipo_usuario=?, forca_seguranca=? WHERE id = ?");
        $stmt->bind_param("ssssssssssssi", $posto_graduacao, $nome_completo, $email, $telefone, $crbm_piloto, $obm_piloto, $cadastro_sarpas, $cparp, $status_piloto, $info_adicionais_piloto, $tipo_usuario, $forca_seguranca, $piloto_id);

        if ($stmt->execute()) {
            $mensagem_status = "<div class='success-message-box'>Piloto atualizado com sucesso! Redirecionando...</div>";
        } else {
            $mensagem_status = "<div class='error-message-box'>Erro ao atualizar piloto: " . htmlspecialchars($stmt->error) . "</div>";
        }
        $stmt->close();
    }
}

if ($piloto_id) {
    $stmt_load = $conn->prepare("SELECT id, posto_graduacao, nome_completo, email, telefone, crbm_piloto, obm_piloto, cadastro_sarpas, cparp, status_piloto, info_adicionais, tipo_usuario, forca_seguranca FROM pilotos WHERE id = ?");
    $stmt_load->bind_param("i", $piloto_id);
    $stmt_load->execute();
    $result = $stmt_load->get_result();
    if ($result->num_rows === 1) {
        $piloto_data = $result->fetch_assoc();
        
        if ($isAdmin && !$isSuperAdmin && $user_forca_seguranca !== $piloto_data['forca_seguranca']) {
            $mensagem_status = "<div class='error-message-box'>Você não tem permissão para editar este piloto.</div>";
            $piloto_data = null;
        }

    } else {
        $mensagem_status = "<div class='error-message-box'>Piloto não encontrado.</div>";
    }
    $stmt_load->close();
} else {
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        $mensagem_status = "<div class='error-message-box'>ID do piloto não fornecido para edição.</div>";
    }
}

// ====================================================================================================
// *** INÍCIO DA SEÇÃO ALTERADA: Definição de Rótulos Iniciais Dinâmicos ***
// ====================================================================================================
$initial_posto_label = 'Posto/Graduação';
$initial_crbm_label = 'Unidade Superior';
$initial_obm_label = 'Subunidade';
$initial_cparp_label = 'CPARP';

if (isset($piloto_data['forca_seguranca']) && isset($config_forcas[$piloto_data['forca_seguranca']])) {
    $config_piloto = $config_forcas[$piloto_data['forca_seguranca']];
    $initial_posto_label = $config_piloto['posto_graduacao_label'];
    $initial_crbm_label = $config_piloto['crbm_label'];
    $initial_obm_label = $config_piloto['obm_label'];
    $initial_cparp_label = $config_piloto['cparp_label'];
}
// ====================================================================================================
// *** FIM DA SEÇÃO ALTERADA ***
// ====================================================================================================
?>

<style>
    .form-group input[type="text"],
    .form-group input[type="email"],
    .form-group input[type="tel"],
    .form-group input[type="password"],
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 10px;
        border: 1px solid #ccc;
        border-radius: 4px;
        box-sizing: border-box;
    }
    input:invalid, select:invalid { box-shadow: none; }
    input:user-invalid, select:user-invalid { border-color: #dc3545; }
</style>

<div class="main-content">
    <h1>Editar Piloto</h1>

    <?php echo $mensagem_status; ?>

    <?php if ($piloto_data): ?>
    <div class="form-container">
        <form id="editPilotoForm" action="editar_pilotos.php?id=<?php echo htmlspecialchars($piloto_id); ?>" method="POST">
            <input type="hidden" name="piloto_id" value="<?php echo htmlspecialchars($piloto_data['id']); ?>">
            <div class="form-grid">
                 <div class="form-group">
                    <label for="forca_seguranca" id="forca_seguranca_label">Força de Segurança:</label>
                    <select id="forca_seguranca" name="forca_seguranca" required disabled>
                        <?php foreach (array_keys($config_forcas) as $sigla_forca): ?>
                            <option value="<?php echo htmlspecialchars($sigla_forca); ?>" <?php echo ($piloto_data['forca_seguranca'] == $sigla_forca) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($config_forcas[$sigla_forca]['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                     <input type="hidden" name="forca_seguranca" value="<?php echo htmlspecialchars($piloto_data['forca_seguranca']); ?>">
                </div>
                <div class="form-group">
                    <label for="posto_graduacao" id="posto_graduacao_label"><?php echo htmlspecialchars($initial_posto_label); ?>:</label>
                    <select id="posto_graduacao" name="posto_graduacao" required>
                        <option value="">Selecione...</option>
                        </select>
                </div>
                 <div class="form-group">
                    <label for="nome_completo">Nome Completo:</label>
                    <input type="text" id="nome_completo" name="nome_completo" value="<?php echo htmlspecialchars($piloto_data['nome_completo']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="email">E-mail:</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($piloto_data['email']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="telefone">Telefone:</label>
                    <input type="tel" id="telefone" name="telefone" value="<?php echo htmlspecialchars($piloto_data['telefone']); ?>" pattern="\(\d{2}\) \d \d{4}-\d{4}" title="Formato: (XX) X XXXX-XXXX" required>
                </div>
                <div class="form-group">
                    <label for="crbm_piloto" id="crbm_piloto_label"><?php echo htmlspecialchars($initial_crbm_label); ?>:</label>
                    <select id="crbm_piloto" name="crbm_piloto" required>
                    </select>
                </div>
                <div class="form-group">
                    <label for="obm_piloto" id="obm_piloto_label"><?php echo htmlspecialchars($initial_obm_label); ?>:</label>
                    <select id="obm_piloto" name="obm_piloto" required> </select>
                </div>
                <div class="form-group">
                    <label for="cadastro_sarpas">Código SARPAS:</label> <input type="text" id="cadastro_sarpas" name="cadastro_sarpas" value="<?php echo htmlspecialchars($piloto_data['cadastro_sarpas']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="cparp" id="cparp_label"><?php echo htmlspecialchars($initial_cparp_label); ?>:</label>
                    <select id="cparp" name="cparp" required>
                    </select>
                </div>
                <div class="form-group">
                    <label for="status_piloto">Status:</label>
                    <select id="status_piloto" name="status_piloto" required>
                        <option value="ativo" <?php echo ($piloto_data['status_piloto'] == 'ativo') ? 'selected' : ''; ?>>Ativo</option>
                        <option value="afastado" <?php echo ($piloto_data['status_piloto'] == 'afastado') ? 'selected' : ''; ?>>Afastado</option>
                        <option value="desativado" <?php echo ($piloto_data['status_piloto'] == 'desativado') ? 'selected' : ''; ?>>Inativo</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="tipo_usuario">Tipo de Usuário:</label>
                    <select id="tipo_usuario" name="tipo_usuario" required <?php echo (!$isSuperAdmin) ? 'disabled' : ''; ?>>
                        <option value="piloto" <?php echo ($piloto_data['tipo_usuario'] == 'piloto') ? 'selected' : ''; ?>>Piloto</option>
                        <option value="administrador" <?php echo ($piloto_data['tipo_usuario'] == 'administrador') ? 'selected' : ''; ?>>Administrador</option>
                        <?php if ($isSuperAdmin): ?>
                        <option value="super_administrador" <?php echo ($piloto_data['tipo_usuario'] == 'super_administrador') ? 'selected' : ''; ?>>Administrador de Sistema</option>
                        <?php endif; ?>
                    </select>
                    <?php if (!$isSuperAdmin): ?>
                        <input type="hidden" name="tipo_usuario" value="<?php echo htmlspecialchars($piloto_data['tipo_usuario']); ?>">
                    <?php endif; ?>
                </div>
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label for="info_adicionais">Informações Adicionais (opcional):</label>
                    <textarea id="info_adicionais" name="info_adicionais" rows="4"><?php echo htmlspecialchars($piloto_data['info_adicionais'] ?? ''); ?></textarea>
                </div>
            </div>
            <div class="form-actions">
                <button id="updateButton" type="submit" style="background-color:#007bff;">Atualizar Piloto</button>
            </div>
        </form>
    </div>
    <?php else: ?>
        <p style="text-align: center; color: #dc3545;">Não foi possível carregar os dados do piloto. <a href="listar_pilotos.php">Volte para a lista</a>.</p>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const configForcas = <?php echo json_encode($config_forcas); ?>;

    const forcaSegurancaSelect = document.getElementById('forca_seguranca');
    const postoGraduacaoSelect = document.getElementById('posto_graduacao');
    const crbmSelect = document.getElementById('crbm_piloto');
    const obmSelect = document.getElementById('obm_piloto');
    const cparpSelect = document.getElementById('cparp');

    const postoGraduacaoLabel = document.getElementById('posto_graduacao_label');
    const crbmPilotoLabel = document.getElementById('crbm_piloto_label');
    const obmPilotoLabel = document.getElementById('obm_piloto_label');
    const cparpLabel = document.getElementById('cparp_label');
    
    const valorSalvo = {
        forca_seguranca: "<?php echo addslashes($piloto_data['forca_seguranca'] ?? ''); ?>",
        posto_graduacao: "<?php echo addslashes($piloto_data['posto_graduacao'] ?? ''); ?>",
        crbm_piloto: "<?php echo addslashes($piloto_data['crbm_piloto'] ?? ''); ?>",
        obm_piloto: "<?php echo addslashes($piloto_data['obm_piloto'] ?? ''); ?>",
        cparp: "<?php echo addslashes($piloto_data['cparp'] ?? ''); ?>",
    };

    function populateSelect(selectElement, optionsArray, placeholder, selectedValue = null, formatCallback = null) {
        selectElement.innerHTML = `<option value="">${placeholder}</option>`;
        if (!optionsArray) return;
        optionsArray.forEach(optionText => {
            const option = document.createElement('option');
            option.value = optionText;
            option.textContent = formatCallback ? formatCallback(optionText) : optionText;
            if (optionText === selectedValue) {
                option.selected = true;
            }
            selectElement.appendChild(option);
        });
    }
    
    function formatCrbm(crbm) {
        if (crbm.match(/^\d+CRBM$/i) || crbm.match(/^\d+CRPM$/i)) {
            return crbm.replace(/(\d+)(CRBM|CRPM)/i, '$1º $2').toUpperCase();
        }
        return crbm;
    }
    
    function updateOpmOptions(isInitialLoad = false) {
        const forca = valorSalvo.forca_seguranca;
        const config = configForcas[forca];
        const crbm_selecionado = crbmSelect.value;
        const selectedValue = isInitialLoad ? valorSalvo.obm_piloto : null;
        
        let sub_unidades = [];
        if (config.unidades && config.unidades.opms_por_crpm) { // PMPR
            sub_unidades = config.unidades.opms_por_crpm[crbm_selecionado] || [];
        } else if (config.unidades) { // Outras Forças
            sub_unidades = config.unidades[crbm_selecionado] || [];
        }

        populateSelect(obmSelect, sub_unidades, `Selecione a ${config.obm_label}`, selectedValue);
        obmSelect.disabled = sub_unidades.length === 0;
    }

    function initializeForm() {
        const forca = valorSalvo.forca_seguranca;
        if (!forca || !configForcas[forca]) return;

        const config = configForcas[forca];

        postoGraduacaoLabel.textContent = config.posto_graduacao_label + ':';
        crbmPilotoLabel.textContent = config.crbm_label + ':';
        obmPilotoLabel.textContent = config.obm_label + ':';
        cparpLabel.textContent = config.cparp_label + ':';

        populateSelect(postoGraduacaoSelect, config.postos_graduacoes, 'Selecione...', valorSalvo.posto_graduacao);
        populateSelect(cparpSelect, ["SIM", "NAO"], 'Selecione', valorSalvo.cparp);

        let crbm_options = [];
        if (config.unidades && config.unidades.crpms) {
            crbm_options = config.unidades.crpms;
        } else if (config.unidades) {
            crbm_options = Object.keys(config.unidades).sort();
        }
        
        populateSelect(crbmSelect, crbm_options, `Selecione a ${config.crbm_label}`, valorSalvo.crbm_piloto, forca === 'CBMPR' || forca === 'PMPR' ? formatCrbm : null);
        updateOpmOptions(true);
        
        crbmSelect.addEventListener('change', () => updateOpmOptions(false));
    }

    initializeForm();
    
    const successMessage = document.querySelector('.success-message-box');
    if (successMessage) {
        setTimeout(function() {
            window.location.href = 'listar_pilotos.php';
        }, 2000);
    }
});
</script>

<?php
// 6. INCLUI O RODAPÉ
require_once 'includes/footer.php';
?>