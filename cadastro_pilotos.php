<?php
// 1. INCLUI O CABEÇALHO PADRÃO
require_once 'includes/header.php';
require_once 'includes/config_forcas.php';

// 2. VERIFICAÇÃO DE PERMISSÃO
if (!$isSuperAdmin && !$isAdmin) {
    header("Location: dashboard.php");
    exit();
}

// 3. LÓGICA ESPECÍFICA DA PÁGINA
$mensagem_status = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Coleta dos dados do formulário
    $forca_seguranca = htmlspecialchars($_POST['forca_seguranca']);
    $posto_graduacao = htmlspecialchars($_POST['posto_graduacao']);
    $nome_completo = htmlspecialchars($_POST['nome_completo']);
    $email = htmlspecialchars($_POST['email']);
    $telefone = htmlspecialchars($_POST['telefone']);
    $crbm_piloto = htmlspecialchars($_POST['crbm_piloto']);
    $obm_piloto = htmlspecialchars($_POST['obm_piloto']);
    $cadastro_sarpas = htmlspecialchars($_POST['cadastro_sarpas']);
    $cparp = htmlspecialchars($_POST['cparp']);
    $status_piloto = htmlspecialchars($_POST['status_piloto']);
    $info_adicionais_piloto = htmlspecialchars($_POST['info_adicionais_piloto']);
    $senha = $_POST['senha'];
    $nome_usuario = htmlspecialchars($_POST['nome_usuario']);
    
    // Define o tipo de usuário com base na permissão do usuário logado
    $tipo_usuario_input = htmlspecialchars($_POST['tipo_usuario']);
    $tipo_usuario = ($isSuperAdmin) ? $tipo_usuario_input : 'piloto';

    // Lógica para gerar o número de cadastro
    $prefixo_codigo = $config_forcas[$forca_seguranca]['codigo_prefixo'] ?? 'OP'; //
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
?>

<style>
    .form-grid-piloto {
        display: grid;
        grid-template-columns: 1fr 3fr;
        gap: 20px;
        align-items: flex-end;
    }
    @media (max-width: 768px) {
        .form-grid-piloto {
            grid-template-columns: 1fr;
        }
    }

    /* Estilos para garantir a consistência de todos os campos do formulário */
    .form-group input[type="text"],
    .form-group input[type="email"],
    .form-group input[type="tel"],
    .form-group input[type="password"],
    .form-group select,
    .form-group textarea {
        width: 100%; /* Força todos os campos a ocupar a largura total do container */
        padding: 10px;
        border: 1px solid #ccc;
        border-radius: 4px;
        box-sizing: border-box; /* Garante que padding e borda não aumentem a largura total */
    }

    /* Validação do navegador */
    input:invalid, select:invalid {
        box-shadow: none; /* Remove o brilho padrão de erro */
    }
    input:user-invalid, select:user-invalid {
        border-color: #dc3545; /* Aplica borda vermelha apenas após interação */
    }
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
                    <label for="posto_graduacao" id="posto_graduacao_label">Posto/Graduação:</label>
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
                    <label for="crbm_piloto" id="crbm_piloto_label">CRBM:</label>
                    <select id="crbm_piloto" name="crbm_piloto" required disabled>
                        <option value="">Selecione a Força de Segurança primeiro...</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="obm_piloto" id="obm_piloto_label">OBM/Seção:</label>
                    <select id="obm_piloto" name="obm_piloto" required disabled>
                        <option value="">Selecione a Força de Segurança e a Unidade de Origem primeiro...</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="cadastro_sarpas">Código SARPAS:</label> <input type="text" id="cadastro_sarpas" name="cadastro_sarpas" placeholder="Ex: AB2025123456" required>
                </div>
                <div class="form-group">
                    <label for="cparp" id="cparp_label">CPARP:</label>
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
                        <option value="desativado">Desativado</option>
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
    // PHP variables from the server-side
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
    
    // Function to format the CRBM/CRPM
    function formatCrbm(crbm) {
        if (crbm.match(/^\d+CRBM$/i) || crbm.match(/^\d+CRPM$/i)) {
            return crbm.replace(/(\d+)(CRBM|CRPM)/i, '$1º $2').toUpperCase();
        }
        return crbm;
    }

    // Function to populate a select element
    function populateSelect(selectElement, optionsArray, placeholder, formatCallback = null) {
        selectElement.innerHTML = `<option value="">${placeholder}</option>`;
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

        // Reset all selects and labels
        postoGraduacaoSelect.innerHTML = '<option value="">Selecione a Força de Segurança primeiro...</option>';
        postoGraduacaoSelect.disabled = true;
        crbmSelect.innerHTML = '<option value="">Selecione a Força de Segurança primeiro...</option>';
        crbmSelect.disabled = true;
        obmSelect.innerHTML = '<option value="">Selecione a Força de Segurança e a Unidade de Origem primeiro...</option>';
        obmSelect.disabled = true;

        if (config) {
            // Update labels dynamically
            postoGraduacaoLabel.textContent = config.posto_graduacao_label + ':'; //
            crbmPilotoLabel.textContent = config.crbm_label + ':'; //
            obmPilotoLabel.textContent = config.obm_label + ':'; //
            cparpLabel.textContent = config.cparp_label + ':'; //

            // Populate Posto/Graduação
            if (config.postos_graduacoes) {
                populateSelect(postoGraduacaoSelect, config.postos_graduacoes, 'Selecione...'); //
                postoGraduacaoSelect.disabled = false;
            }
            
            // Handle CRBM/OBM fields based on force configuration
            if (config.unidades && !config.unidades.crpms) { // CBMPR, Polícia Penal, PCPR, Polícia Científica
                const crbms = Object.keys(config.unidades).sort();
                populateSelect(crbmSelect, crbms, `Selecione a ${crbmPilotoLabel.textContent.replace(':', '')}`, forca === 'CBMPR' ? formatCrbm : null);
                crbmSelect.disabled = false;
                crbmSelect.removeEventListener('change', handleDynamicUnitsChange);
                crbmSelect.addEventListener('change', handleDynamicUnitsChange);
            } else if (config.unidades && config.unidades.crpms) { // PMPR Logic
                populateSelect(crbmSelect, config.unidades.crpms, `Selecione o ${config.crbm_label}`); //
                crbmSelect.disabled = false;
                crbmSelect.removeEventListener('change', handleDynamicUnitsChange);
                crbmSelect.addEventListener('change', () => {
                    const crpm = crbmSelect.value;
                    const opms = config.unidades.opms_por_crpm[crpm] || []; //
                    populateSelect(obmSelect, opms, `Selecione a ${config.obm_label}`);
                    obmSelect.disabled = opms.length === 0;
                    checkFormValidity();
                });
            } else {
                crbmSelect.disabled = true;
                obmSelect.disabled = true;
                crbmSelect.innerHTML = '<option value="">Nenhuma unidade disponível</option>';
                obmSelect.innerHTML = '<option value="">Nenhuma unidade disponível</option>';
            }
        }
        checkFormValidity();
    }
    
    function handleDynamicUnitsChange() {
        const forca = forcaSegurancaSelect.value;
        const crbm = this.value;
        const config = configForcas[forca];
        
        obmSelect.innerHTML = '<option value="">Selecione a OBM/Seção</option>';
        obmSelect.disabled = true;
        
        if (crbm && config.unidades[crbm]) {
            obmSelect.disabled = false;
            config.unidades[crbm].forEach(function(obm) {
                const option = document.createElement('option');
                option.value = obm;
                option.textContent = obm;
                obmSelect.appendChild(option);
            });
        }
        checkFormValidity();
    }

    function checkFormValidity() {
        const allValid = requiredFields.every(field => field.disabled || field.value.trim() !== '');
        saveButton.disabled = !allValid;
    }

    requiredFields.forEach(field => {
        field.addEventListener('input', checkFormValidity);
        field.addEventListener('change', checkFormValidity);
    });

    // ######### INÍCIO DA CORREÇÃO #########
    
    // Anexa o evento de mudança para Super Admins que selecionam manualmente
    if (forcaSegurancaSelect) {
        forcaSegurancaSelect.addEventListener('change', handleForcaSegurancaChange);
    }
    
    // Executa a função imediatamente ao carregar a página.
    // Isso garante que, se uma Força de Segurança já estiver pré-selecionada (caso do Admin),
    // os campos dependentes (Posto/Graduação, etc.) sejam populados corretamente.
    handleForcaSegurancaChange();
    
    // ######### FIM DA CORREÇÃO #########

    checkFormValidity();
});
</script>

<?php
// 6. INCLUI O RODAPÉ
require_once 'includes/footer.php';
?>