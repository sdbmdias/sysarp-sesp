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

if ($_SERVER["REQUEST_METHOD"] == "POST") {
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
    $info_adicionais_piloto = $_POST['info_adicionais_piloto'];
    $senha = $_POST['senha'];
    $nome_usuario = $_POST['nome_usuario'];
    
    $tipo_usuario_input = $_POST['tipo_usuario'];
    $tipo_usuario = ($isSuperAdmin) ? $tipo_usuario_input : 'piloto';

    $prefixo_codigo = $config_forcas[$forca_seguranca]['codigo_prefixo'] ?? 'OP';
    $stmt_op = $conn->prepare("SELECT MAX(CAST(SUBSTRING(codigo_cadastro, " . (strlen($prefixo_codigo) + 1) . ", 3) AS UNSIGNED)) AS max_num FROM pilotos WHERE forca_seguranca = ?");
    $stmt_op->bind_param("s", $forca_seguranca);
    $stmt_op->execute();
    $result_op = $stmt_op->get_result();
    $row_op = $result_op->fetch_assoc();
    $next_op_num = ($row_op['max_num'] ?? 0) + 1;
    $codigo_cadastro = $prefixo_codigo . sprintf('%03d', $next_op_num) . '/' . $forca_seguranca;
    $stmt_op->close();

    $senha_redefinida = 0;
    $senha_hashed = password_hash($senha, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO pilotos (posto_graduacao, nome_completo, email, telefone, crbm_piloto, obm_piloto, cadastro_sarpas, cparp, status_piloto, info_adicionais, senha, tipo_usuario, senha_redefinida, forca_seguranca, nome_usuario, codigo_cadastro) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssssssssssisss", $posto_graduacao, $nome_completo, $email, $telefone, $crbm_piloto, $obm_piloto, $cadastro_sarpas, $cparp, $status_piloto, $info_adicionais_piloto, $senha_hashed, $tipo_usuario, $senha_redefinida, $forca_seguranca, $nome_usuario, $codigo_cadastro);

    if ($stmt->execute()) {
        $mensagem_status = "<div class='success-message-box'>Piloto cadastrado com sucesso! Código de Cadastro: <strong>" . htmlspecialchars($codigo_cadastro) . "</strong></div>";
    } else {
        if ($conn->errno == 1062) {
            $mensagem_status = "<div class='error-message-box'>Erro: Um dos valores únicos (E-mail ou Nome de Usuário) já existe no sistema.</div>";
        } else {
            $mensagem_status = "<div class='error-message-box'>Erro ao cadastrar piloto: " . htmlspecialchars($stmt->error) . "</div>";
        }
    }
    $stmt->close();
}

// ====================================================================================================
// *** INÍCIO DA SEÇÃO ALTERADA: Definição de Rótulos Iniciais Dinâmicos ***
// ====================================================================================================
$initial_posto_label = 'Posto/Graduação';
$initial_crbm_label = 'Unidade Superior';
$initial_obm_label = 'Subunidade';
$initial_cparp_label = 'CPARP';

if ($isAdmin && !$isSuperAdmin && isset($config_forcas[$user_forca_seguranca])) {
    $config_admin = $config_forcas[$user_forca_seguranca];
    $initial_posto_label = $config_admin['posto_graduacao_label'];
    $initial_crbm_label = $config_admin['crbm_label'];
    $initial_obm_label = $config_admin['obm_label'];
    $initial_cparp_label = $config_admin['cparp_label'];
}
// ====================================================================================================
// *** FIM DA SEÇÃO ALTERADA ***
// ====================================================================================================
?>

<style>
    .form-grid-piloto { display: grid; grid-template-columns: 1fr 3fr; gap: 20px; align-items: flex-end; }
    @media (max-width: 768px) { .form-grid-piloto { grid-template-columns: 1fr; } }
    .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
    input:invalid, select:invalid { box-shadow: none; }
    input:user-invalid, select:user-invalid { border-color: #dc3545; }
</style>

<div class="main-content">
    <h1>Cadastro de Pilotos</h1>

    <?php echo $mensagem_status; ?>

    <div class="form-container">
        <form id="pilotoForm" action="cadastro_pilotos.php" method="POST">
            <?php if ($isSuperAdmin || $isAdmin): ?>
            <div class="form-grid">
                <div class="form-group">
                    <label for="forca_seguranca">Força de Segurança:</label>
                    <select id="forca_seguranca" name="forca_seguranca" required <?php echo ($isAdmin && !$isSuperAdmin) ? 'disabled' : ''; ?>>
                        <option value="">Selecione</option>
                        <?php foreach (array_keys($config_forcas) as $sigla_forca): ?>
                            <option value="<?php echo htmlspecialchars($sigla_forca); ?>" <?php echo ($isAdmin && $user_forca_seguranca === $sigla_forca) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($config_forcas[$sigla_forca]['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($isAdmin && !$isSuperAdmin): ?>
                    <input type="hidden" name="forca_seguranca" value="<?php echo htmlspecialchars($user_forca_seguranca); ?>">
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="form-grid-piloto">
                <div class="form-group">
                    <label for="posto_graduacao" id="posto_graduacao_label"><?php echo htmlspecialchars($initial_posto_label); ?>:</label>
                    <select id="posto_graduacao" name="posto_graduacao" required disabled>
                        <option value="">Selecione a Força de Segurança primeiro...</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="nome_completo">Nome Completo:</label>
                    <input type="text" id="nome_completo" name="nome_completo" placeholder="Nome completo do piloto" required>
                </div>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label for="nome_usuario">Nome de Usuário:</label>
                    <input type="text" id="nome_usuario" name="nome_usuario" placeholder="Nome de usuário para o login" required>
                </div>
                <div class="form-group">
                    <label for="email">E-mail:</label>
                    <input type="email" id="email" name="email" placeholder="email@exemplo.com" required>
                </div>
                <div class="form-group">
                    <label for="telefone">Telefone:</label>
                    <input type="tel" id="telefone" name="telefone" placeholder="(XX) X XXXX-XXXX" pattern="\(\d{2}\) \d \d{4}-\d{4}" title="Formato: (XX) X XXXX-XXXX" required>
                </div>
                <div class="form-group">
                    <label for="crbm_piloto" id="crbm_piloto_label"><?php echo htmlspecialchars($initial_crbm_label); ?>:</label>
                    <select id="crbm_piloto" name="crbm_piloto" required disabled>
                        <option value="">Selecione a Força de Segurança primeiro...</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="obm_piloto" id="obm_piloto_label"><?php echo htmlspecialchars($initial_obm_label); ?>:</label>
                    <select id="obm_piloto" name="obm_piloto" required disabled>
                        <option value="">Selecione a Unidade de Origem primeiro...</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="cadastro_sarpas">Código SARPAS:</label> <input type="text" id="cadastro_sarpas" name="cadastro_sarpas" placeholder="Ex: AB2025123456" required>
                </div>
                <div class="form-group">
                    <label for="cparp" id="cparp_label"><?php echo htmlspecialchars($initial_cparp_label); ?>:</label>
                    <select id="cparp" name="cparp" required>
                        <option value="">Selecione</option>
                        <option value="SIM">SIM</option>
                        <option value="NAO">NÃO</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="senha">Senha Inicial:</label>
                    <input type="password" id="senha" name="senha" placeholder="Crie uma senha provisória" required>
                </div>
                <div class="form-group">
                    <label for="status_piloto">Status:</label>
                    <select id="status_piloto" name="status_piloto" required>
                        <option value="ativo">Ativo</option>
                        <option value="afastado">Afastado</option>
                        <option value="desativado">Inativo</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="tipo_usuario">Tipo de Usuário:</label>
                    <select id="tipo_usuario" name="tipo_usuario" required>
                        <option value="">Selecione o Tipo</option>
                        <option value="piloto">Piloto</option>
                        <option value="administrador">Administrador</option>
                        <?php if ($isSuperAdmin): ?>
                        <option value="super_administrador">Administrador de Sistema</option>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label for="info_adicionais_piloto">Informações Adicionais (opcional):</label>
                    <textarea id="info_adicionais_piloto" name="info_adicionais_piloto" rows="4" placeholder="Adicione qualquer informação relevante sobre o piloto..."></textarea>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" id="saveButton" disabled>Salvar Piloto</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const configForcas = <?php echo json_encode($config_forcas); ?>;
    const form = document.getElementById('pilotoForm');
    const saveButton = document.getElementById('saveButton');
    const requiredFields = Array.from(form.querySelectorAll('[required]'));
    const forcaSegurancaSelect = document.getElementById('forca_seguranca');
    const postoGraduacaoSelect = document.getElementById('posto_graduacao');
    const postoGraduacaoLabel = document.getElementById('posto_graduacao_label');
    const crbmPilotoLabel = document.getElementById('crbm_piloto_label');
    const obmPilotoLabel = document.getElementById('obm_piloto_label');
    const cparpLabel = document.getElementById('cparp_label');
    const crbmSelect = document.getElementById('crbm_piloto');
    const obmSelect = document.getElementById('obm_piloto');
    const cparpSelect = document.getElementById('cparp');
    
    function formatCrbm(crbm) {
        if (crbm && (crbm.match(/^\d+CRBM$/i) || crbm.match(/^\d+CRPM$/i))) {
            return crbm.replace(/(\d+)(CRBM|CRPM)/i, '$1º $2').toUpperCase();
        }
        return crbm;
    }

    function populateSelect(selectElement, optionsArray, placeholder, formatCallback = null) {
        selectElement.innerHTML = `<option value="">${placeholder}</option>`;
        if (!optionsArray) return;
        optionsArray.forEach(optionText => {
            const option = document.createElement('option');
            option.value = optionText;
            option.textContent = formatCallback ? formatCallback(optionText) : optionText;
            selectElement.appendChild(option);
        });
    }

    function handleForcaSegurancaChange() {
        const forca = forcaSegurancaSelect.value;
        const config = configForcas[forca];

        postoGraduacaoSelect.innerHTML = '<option value="">Selecione a Força primeiro...</option>';
        postoGraduacaoSelect.disabled = true;
        crbmSelect.innerHTML = '<option value="">Selecione a Força primeiro...</option>';
        crbmSelect.disabled = true;
        obmSelect.innerHTML = '<option value="">Selecione a Unidade Superior primeiro...</option>';
        obmSelect.disabled = true;

        if (config) {
            postoGraduacaoLabel.textContent = config.posto_graduacao_label + ':';
            crbmPilotoLabel.textContent = config.crbm_label + ':';
            obmPilotoLabel.textContent = config.obm_label + ':';
            cparpLabel.textContent = config.cparp_label + ':';

            if (config.postos_graduacoes) {
                populateSelect(postoGraduacaoSelect, config.postos_graduacoes, 'Selecione...');
                postoGraduacaoSelect.disabled = false;
            }
            
            crbmSelect.removeEventListener('change', handleDynamicUnitsChange);
            crbmSelect.removeEventListener('change', handlePmUnitsChange);

            if (config.unidades && !config.unidades.crpms) {
                const crbms = Object.keys(config.unidades).sort();
                populateSelect(crbmSelect, crbms, `Selecione a ${config.crbm_label}`, forca === 'CBMPR' ? formatCrbm : null);
                crbmSelect.addEventListener('change', handleDynamicUnitsChange);
            } else if (config.unidades && config.unidades.crpms) {
                populateSelect(crbmSelect, config.unidades.crpms, `Selecione o ${config.crbm_label}`);
                crbmSelect.addEventListener('change', handlePmUnitsChange);
            }
            
            crbmSelect.disabled = !(Object.keys(config.unidades || {}).length > 0);
        }
        checkFormValidity();
    }
    
    function handleDynamicUnitsChange() {
        const forca = forcaSegurancaSelect.value;
        const crbm = this.value; 
        const config = configForcas[forca];
        populateSelect(obmSelect, config.unidades[crbm] || [], `Selecione a ${config.obm_label}`);
        obmSelect.disabled = !(crbm && config.unidades[crbm]);
        checkFormValidity();
    }
    
    const handlePmUnitsChange = () => {
        const forca = forcaSegurancaSelect.value;
        const config = configForcas[forca];
        const crpm = crbmSelect.value;
        const opms = (config && config.unidades.opms_por_crpm[crpm]) || [];
        populateSelect(obmSelect, opms, `Selecione a ${config.obm_label}`);
        obmSelect.disabled = opms.length === 0;
        checkFormValidity();
    };

    function checkFormValidity() {
        const allValid = requiredFields.every(field => field.disabled || field.value.trim() !== '');
        saveButton.disabled = !allValid;
    }

    requiredFields.forEach(field => {
        field.addEventListener('input', checkFormValidity);
        field.addEventListener('change', checkFormValidity);
    });

    if (forcaSegurancaSelect) {
        forcaSegurancaSelect.addEventListener('change', handleForcaSegurancaChange);
    }
    
    handleForcaSegurancaChange();
    checkFormValidity();
});
</script>

<?php
// 6. INCLUI O RODAPÉ
require_once 'includes/footer.php';
?>