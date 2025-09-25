<?php
// 1. INCLUI O CABEÇALHO PADRÃO
require_once 'includes/header.php';
require_once 'includes/config_forcas.php';

// 2. VERIFICAÇÃO DE PERMISSÃO
if (!$isAdmin) {
    header("Location: dashboard.php");
    exit();
}

// 3. LÓGICA ESPECÍFICA DA PÁGINA
$mensagem_status = "";
$controle_data = null;

$controle_id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_POST['controle_id']) ? intval($_POST['controle_id']) : null);

// Busca aeronaves disponíveis para vincular
$aeronaves_disponiveis = [];
$sql_aeronaves = "SELECT id, prefixo, modelo, crbm, obm, forca_seguranca FROM aeronaves ORDER BY prefixo ASC";
$result_aeronaves = $conn->query($sql_aeronaves);
if ($result_aeronaves->num_rows > 0) {
    while($row = $result_aeronaves->fetch_assoc()) {
        $aeronaves_disponiveis[] = $row;
    }
}

// Busca fabricantes e modelos de CONTROLES do banco de dados
$fabricantes_e_modelos_controles = [];
$sql_modelos_ctrl = "SELECT fabricante, modelo FROM fabricantes_modelos WHERE tipo = 'Controle' ORDER BY CASE WHEN fabricante = 'DJI' THEN 1 WHEN fabricante = 'Autel Robotics' THEN 2 ELSE 3 END, fabricante, modelo";
$result_modelos_ctrl = $conn->query($sql_modelos_ctrl);
if ($result_modelos_ctrl) {
    while ($row = $result_modelos_ctrl->fetch_assoc()) {
        $fabricantes_e_modelos_controles[$row['fabricante']][] = $row['modelo'];
    }
}

// Acessa os dados de unidades do config_forcas.php
$unidades = $config_forcas;

// Lógica de atualização do formulário
if ($_SERVER["REQUEST_METHOD"] == "POST" && $controle_id) {
    $fabricante = isset($_POST['fabricante']) ? htmlspecialchars($_POST['fabricante']) : '';
    $modelo = isset($_POST['modelo']) ? htmlspecialchars($_POST['modelo']) : '';
    $numero_serie = isset($_POST['numero_serie']) ? htmlspecialchars($_POST['numero_serie']) : '';
    $aeronave_id = !empty($_POST['aeronave_id']) ? intval($_POST['aeronave_id']) : NULL;
    $status = isset($_POST['status']) ? htmlspecialchars($_POST['status']) : '';
    $homologacao_anatel = isset($_POST['homologacao_anatel']) ? htmlspecialchars($_POST['homologacao_anatel']) : '';
    $info_adicionais = isset($_POST['info_adicionais']) ? htmlspecialchars($_POST['info_adicionais']) : '';
    $forca_seguranca = isset($_POST['forca_seguranca']) ? htmlspecialchars($_POST['forca_seguranca']) : '';
    
    $crbm = '';
    $obm = '';
    
    if ($aeronave_id) {
        $stmt_get_acft_data = $conn->prepare("SELECT crbm, obm, forca_seguranca FROM aeronaves WHERE id = ?");
        $stmt_get_acft_data->bind_param("i", $aeronave_id);
        $stmt_get_acft_data->execute();
        $result_acft_data = $stmt_get_acft_data->get_result();
        if ($result_acft_data->num_rows > 0) {
            $acft_data = $result_acft_data->fetch_assoc();
            $crbm = $acft_data['crbm'];
            $obm = $acft_data['obm'];
            $forca_seguranca = $acft_data['forca_seguranca'];
        }
        $stmt_get_acft_data->close();
    } else {
        $crbm = isset($_POST['crbm']) ? htmlspecialchars($_POST['crbm']) : '';
        $obm = isset($_POST['obm']) ? htmlspecialchars($_POST['obm']) : '';
    }

    $stmt = $conn->prepare("UPDATE controles SET fabricante=?, modelo=?, numero_serie=?, aeronave_id=?, crbm=?, obm=?, forca_seguranca=?, status=?, homologacao_anatel=?, info_adicionais=? WHERE id = ?");
    $stmt->bind_param("sssissssssi", $fabricante, $modelo, $numero_serie, $aeronave_id, $crbm, $obm, $forca_seguranca, $status, $homologacao_anatel, $info_adicionais, $controle_id);

    if ($stmt->execute()) {
        $mensagem_status = "<div class='success-message-box'>Controle atualizado com sucesso! Redirecionando...</div>";
        echo "<script>
                setTimeout(function() {
                    window.location.href = 'listar_controles.php';
                }, 2000);
              </script>";
    } else {
        $mensagem_status = "<div class='error-message-box'>Erro ao atualizar controle: " . htmlspecialchars($stmt->error) . "</div>";
    }
    $stmt->close();
}

// Carrega os dados do controle para preencher o formulário
if ($controle_id) {
    $stmt_load = $conn->prepare("SELECT * FROM controles WHERE id = ?");
    $stmt_load->bind_param("i", $controle_id);
    $stmt_load->execute();
    $result = $stmt_load->get_result();
    if ($result->num_rows === 1) {
        $controle_data = $result->fetch_assoc();
    } else {
        $mensagem_status = "<div class='error-message-box'>Controle não encontrado.</div>";
    }
    $stmt_load->close();
} else {
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        $mensagem_status = "<div class='error-message-box'>ID do controle não fornecido para edição.</div>";
    }
}
?>

<div class="main-content">
    <h1>Editar Controle (Rádio)</h1>

    <?php echo $mensagem_status; ?>

    <?php if ($controle_data): ?>
    <div class="form-container">
        <form id="editControleForm" action="editar_controles.php?id=<?php echo htmlspecialchars($controle_id); ?>" method="POST">
            <input type="hidden" name="controle_id" value="<?php echo htmlspecialchars($controle_data['id']); ?>">
            <div class="form-grid">
                <div class="form-group">
                    <label for="forca_seguranca">Força de Segurança:</label>
                    <select id="forca_seguranca" name="forca_seguranca" required <?php echo ($isAdmin && !$isSuperAdmin) ? 'disabled' : ''; ?>>
                        <option value="">Selecione</option>
                        <?php foreach (array_keys($unidades) as $sigla_forca): ?>
                            <option value="<?php echo htmlspecialchars($sigla_forca); ?>" <?php echo ($controle_data['forca_seguranca'] == $sigla_forca) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($unidades[$sigla_forca]['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($isAdmin && !$isSuperAdmin): ?>
                    <input type="hidden" name="forca_seguranca" value="<?php echo htmlspecialchars($user_forca_seguranca); ?>">
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="fabricante">Fabricante:</label>
                    <select id="fabricante" name="fabricante" required>
                        <option value="">Selecione...</option>
                        <?php foreach (array_keys($fabricantes_e_modelos_controles) as $fab): ?>
                            <option value="<?php echo htmlspecialchars($fab); ?>" <?php echo ($controle_data['fabricante'] == $fab) ? 'selected' : ''; ?>><?php echo htmlspecialchars($fab); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="modelo">Modelo:</label>
                    <select id="modelo" name="modelo" required></select>
                </div>
                <div class="form-group">
                    <label for="numero_serie">Número de Série:</label>
                    <input type="text" id="numero_serie" name="numero_serie" value="<?php echo htmlspecialchars($controle_data['numero_serie']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="aeronave_id">Vincular à Aeronave (Prefixo):</label>
                    <select id="aeronave_id" name="aeronave_id">
                        <option value="">Nenhuma (Controle Reserva)</option>
                        <?php foreach ($aeronaves_disponiveis as $aeronave): ?>
                            <option value="<?php echo htmlspecialchars($aeronave['id']); ?>"
                                <?php echo ($controle_data['aeronave_id'] == $aeronave['id']) ? 'selected' : ''; ?>
                                data-crbm="<?php echo htmlspecialchars($aeronave['crbm']); ?>"
                                data-obm="<?php echo htmlspecialchars($aeronave['obm']); ?>"
                                data-forca="<?php echo htmlspecialchars($aeronave['forca_seguranca']); ?>">
                                <?php echo htmlspecialchars($aeronave['prefixo'] . ' - ' . $aeronave['modelo']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="crbm" id="crbm_label">CRBM de Lotação:</label>
                    <select id="crbm" name="crbm" required>
                        <option value="">Selecione a Força de Segurança primeiro...</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="obm" id="obm_label">OBM/Seção de Lotação:</label>
                    <select id="obm" name="obm" required></select>
                </div>
                <div class="form-group">
                    <label for="status">Status:</label>
                    <select id="status" name="status" required>
                        <option value="ativo" <?php echo ($controle_data['status'] == 'ativo') ? 'selected' : ''; ?>>Ativo</option>
                        <option value="em_manutencao" <?php echo ($controle_data['status'] == 'em_manutencao') ? 'selected' : ''; ?>>Em Manutenção</option>
                        <option value="baixado" <?php echo ($controle_data['status'] == 'baixado') ? 'selected' : ''; ?>>Baixado</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="homologacao_anatel">Homologação ANATEL:</label> <select id="homologacao_anatel" name="homologacao_anatel" required>
                        <option value="Sim" <?php echo (isset($controle_data['homologacao_anatel']) && $controle_data['homologacao_anatel'] == 'Sim') ? 'selected' : ''; ?>>Sim</option>
                        <option value="Não" <?php echo (isset($controle_data['homologacao_anatel']) && $controle_data['homologacao_anatel'] == 'Não') ? 'selected' : ''; ?>>Não</option>
                    </select>
                </div>
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label for="info_adicionais">Informações Adicionais (opcional):</label>
                    <textarea id="info_adicionais" name="info_adicionais" rows="4"><?php echo htmlspecialchars($controle_data['info_adicionais'] ?? ''); ?></textarea>
                </div>
            </div>
            <div class="form-actions">
                <button id="updateButton" type="submit" style="background-color:#007bff;">Atualizar Controle</button>
            </div>
        </form>
    </div>
    <?php else: ?>
        <p style="text-align: center; color: #dc3545;">Não foi possível carregar os dados do controle. <a href="listar_controles.php">Volte para a lista</a>.</p>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('editControleForm')) {
        const configForcas = <?php echo json_encode($config_forcas); ?>;
        const modelosControlePorFabricante = <?php echo json_encode($fabricantes_e_modelos_controles); ?>;

        const forcaSegurancaSelect = document.getElementById('forca_seguranca');
        const fabricanteSelect = document.getElementById('fabricante');
        const modeloSelect = document.getElementById('modelo');
        const crbmLabel = document.getElementById('crbm_label');
        const obmLabel = document.getElementById('obm_label');
        const crbmSelect = document.getElementById('crbm');
        const obmSelect = document.getElementById('obm');
        const aeronaveSelect = document.getElementById('aeronave_id');
        
        const valorSalvo = {
            forca_seguranca: "<?php echo addslashes($controle_data['forca_seguranca'] ?? ''); ?>",
            modelo: "<?php echo addslashes($controle_data['modelo'] ?? ''); ?>",
            crbm: "<?php echo addslashes($controle_data['crbm'] ?? ''); ?>",
            obm: "<?php echo addslashes($controle_data['obm'] ?? ''); ?>"
        };

        function formatCrbm(crbm) {
            if (crbm.match(/^\d+CRBM$/i) || crbm.match(/^\d+CRPM$/i)) {
                return crbm.replace(/(\d+)(CRBM|CRPM)/i, '$1º $2').toUpperCase();
            }
            return crbm;
        }

        function populateSelect(selectElement, optionsArray, placeholder, formatCallback = null, valorPadrao = null) {
            selectElement.innerHTML = `<option value="">${placeholder}</option>`;
            optionsArray.forEach(optionText => {
                const option = document.createElement('option');
                option.value = optionText;
                option.textContent = formatCallback ? formatCallback(optionText) : optionText;
                if (optionText === valorPadrao) {
                    option.selected = true;
                }
                selectElement.appendChild(option);
            });
        }
        
        function atualizarModelos() {
            const fabricante = fabricanteSelect.value;
            modeloSelect.innerHTML = '<option value="">Selecione o Modelo</option>';
            modeloSelect.disabled = true;
            if (fabricante && modelosControlePorFabricante[fabricante]) {
                modeloSelect.disabled = false;
                populateSelect(modeloSelect, modelosControlePorFabricante[fabricante], 'Selecione um modelo...', null, valorSalvo.modelo);
            }
        }
        
        function populateLotacao(forca, crbm, obm) {
            const config = configForcas[forca];
            
            if (config) {
                crbmLabel.textContent = config.crbm_label + ':';
                obmLabel.textContent = config.obm_label + ':';

                if (config.unidades && !config.unidades.crpms) { 
                    const crbms = Object.keys(config.unidades).sort();
                    populateSelect(crbmSelect, crbms, `Selecione a ${crbmLabel.textContent.replace(':', '')}`, forca === 'CBMPR' ? formatCrbm : null, crbm);
                    
                    const obms = config.unidades[crbm] || [];
                    populateSelect(obmSelect, obms, `Selecione a ${obmLabel.textContent.replace(':', '')}`, null, obm);

                } else if (config.unidades && config.unidades.crpms) { 
                    const crpms = config.unidades.crpms;
                    populateSelect(crbmSelect, crpms, `Selecione o ${crbmLabel.textContent.replace(':', '')}`, null, crbm);

                    const opms = config.unidades.opms_por_crpm[crbm] || [];
                    populateSelect(obmSelect, opms, `Selecione a ${obmLabel.textContent.replace(':', '')}`, null, obm);
                }
            }
        }

        function setupLotacaoForm(isAircraftLinked) {
            if (isAircraftLinked) {
                const selectedOption = aeronaveSelect.options[aeronaveSelect.selectedIndex];
                const forca = selectedOption.getAttribute('data-forca');
                const crbm = selectedOption.getAttribute('data-crbm');
                const obm = selectedOption.getAttribute('data-obm');
                
                forcaSegurancaSelect.value = forca;
                populateLotacao(forca, crbm, obm);
                
                forcaSegurancaSelect.disabled = true;
                crbmSelect.disabled = true;
                obmSelect.disabled = true;
            } else {
                forcaSegurancaSelect.disabled = (isAdmin && !isSuperAdmin);
                crbmSelect.disabled = true;
                obmSelect.disabled = true;

                const initialForca = forcaSegurancaSelect.value || (isAdmin ? userForcaSeguranca : null);
                if (initialForca) {
                    const config = configForcas[initialForca];
                    crbmLabel.textContent = config.crbm_label + ':';
                    obmLabel.textContent = config.obm_label + ':';
                    
                    if (config.unidades && !config.unidades.crpms) {
                        const crbms = Object.keys(config.unidades).sort();
                        populateSelect(crbmSelect, crbms, `Selecione a ${crbmLabel.textContent.replace(':', '')}`, initialForca === 'CBMPR' ? formatCrbm : null, valorSalvo.crbm);
                        crbmSelect.disabled = false;
                        crbmSelect.removeEventListener('change', handleDynamicUnitsChange);
                        crbmSelect.addEventListener('change', handleDynamicUnitsChange);
                    } else if (config.unidades && config.unidades.crpms) {
                        const crpms = config.unidades.crpms;
                        populateSelect(crbmSelect, crpms, `Selecione o ${crbmLabel.textContent.replace(':', '')}`, null, valorSalvo.crbm);
                        crbmSelect.disabled = false;
                        crbmSelect.removeEventListener('change', handleDynamicUnitsChange);
                        crbmSelect.addEventListener('change', () => {
                            const crpm = crbmSelect.value;
                            const opms = config.unidades.opms_por_crpm[crpm] || [];
                            populateSelect(obmSelect, opms, `Selecione a ${config.obm_label}`, null, valorSalvo.obm);
                            obmSelect.disabled = opms.length === 0;
                        });
                    } else {
                        crbmSelect.innerHTML = '<option value="">Nenhuma unidade disponível</option>';
                        obmSelect.innerHTML = '<option value="">Nenhuma unidade disponível</option>';
                    }
                } else {
                    crbmLabel.textContent = 'CRBM:';
                    obmLabel.textContent = 'OBM:';
                    crbmSelect.innerHTML = '<option value="">Selecione a Força de Segurança primeiro...</option>';
                    obmSelect.innerHTML = '<option value="">Selecione a Unidade de Origem primeiro...</option>';
                }
            }
        }
        
        forcaSegurancaSelect.addEventListener('change', () => {
            const isAircraftLinked = aeronaveSelect.value !== "";
            if (!isAircraftLinked) {
                setupLotacaoForm(false);
            }
        });

        crbmSelect.addEventListener('change', () => {
            const isAircraftLinked = aeronaveSelect.value !== "";
            if (!isAircraftLinked) {
                const forca = forcaSegurancaSelect.value;
                const crbm = crbmSelect.value;
                const config = configForcas[forca];
                
                let placeholderText = 'Selecione a OBM/Seção';
                if (forca === 'Polícia Penal') {
                    placeholderText = 'Selecione a Unidade Penal';
                }
                
                obmSelect.innerHTML = `<option value="">${placeholderText}</option>`;
                if (crbm && config.unidades[crbm]) {
                    obmSelect.disabled = false;
                    populateSelect(obmSelect, config.unidades[crbm], placeholderText);
                } else {
                    obmSelect.disabled = true;
                }
            }
        });

        aeronaveSelect.addEventListener('change', () => {
            const isAircraftLinked = aeronaveSelect.value !== "";
            setupLotacaoForm(isAircraftLinked);
        });
        
        fabricanteSelect.addEventListener('change', atualizarModelos);

        // Carga inicial
        atualizarModelos();
        setupLotacaoForm(aeronaveSelect.value !== "");
    }
});
</script>

<?php
// 6. INCLUI O RODAPÉ
require_once 'includes/footer.php';
?>