<?php
// 1. INCLUI O CABEÇALHO PADRÃO
require_once 'includes/header.php';

$mensagem_status = "";
// Lógica para buscar o CRBM do piloto logado para o contexto dos dashboards restritos
$crbm_do_usuario = '';
$obm_do_usuario = '';
$fs_do_usuario = $_SESSION['forca_seguranca'] ?? '';

$stmt_piloto_info = $conn->prepare("SELECT crbm_piloto, obm_piloto FROM pilotos WHERE id = ?");
$stmt_piloto_info->bind_param("i", $_SESSION['user_id']);
if ($stmt_piloto_info->execute()) {
    $result_piloto_info = $stmt_piloto_info->get_result();
    if ($result_piloto_info->num_rows > 0) {
        $piloto_info = $result_piloto_info->fetch_assoc();
        $crbm_do_usuario = $piloto_info['crbm_piloto'];
        $obm_do_usuario = $piloto_info['obm_piloto'];
    }
}
$stmt_piloto_info->close();

// 3. LÓGICA ESPECÍFICA DA PÁGINA
$aeronaves_selecao = [];
$controles_selecao = [];

// --- Lógica para buscar aeronaves e controles para os dropdowns ---
if ($isSuperAdmin) {
    // Super Admin vê todos os equipamentos
    $sql_aeronaves = "SELECT id, prefixo, modelo, forca_seguranca FROM aeronaves ORDER BY prefixo ASC";
    $sql_controles = "SELECT c.id, c.numero_serie, c.modelo, a.prefixo AS aeronave_vinculada, c.forca_seguranca
                      FROM controles c 
                      LEFT JOIN aeronaves a ON c.aeronave_id = a.id 
                      ORDER BY c.id ASC";
    
    $result_aeronaves = $conn->query($sql_aeronaves);
    if ($result_aeronaves) {
        while($row = $result_aeronaves->fetch_assoc()) { $aeronaves_selecao[] = $row; }
    }
    
    $result_controles = $conn->query($sql_controles);
    if ($result_controles) {
        while($row = $result_controles->fetch_assoc()) { $controles_selecao[] = $row; }
    }

} elseif ($isAdmin) {
    // Admin vê todos os equipamentos da sua Força de Segurança
    $stmt_aeronaves = $conn->prepare("SELECT id, prefixo, modelo, forca_seguranca FROM aeronaves WHERE forca_seguranca = ? ORDER BY prefixo ASC");
    $stmt_aeronaves->bind_param("s", $fs_do_usuario);
    $stmt_aeronaves->execute();
    $result_aeronaves = $stmt_aeronaves->get_result();
    while($row = $result_aeronaves->fetch_assoc()) { $aeronaves_selecao[] = $row; }
    $stmt_aeronaves->close();
    
    $stmt_controles = $conn->prepare("SELECT c.id, c.numero_serie, c.modelo, a.prefixo AS aeronave_vinculada, c.forca_seguranca
                                      FROM controles c 
                                      LEFT JOIN aeronaves a ON c.aeronave_id = a.id 
                                      WHERE c.forca_seguranca = ? ORDER BY c.id ASC");
    $stmt_controles->bind_param("s", $fs_do_usuario);
    $stmt_controles->execute();
    $result_controles = $stmt_controles->get_result();
    while($row = $result_controles->fetch_assoc()) { $controles_selecao[] = $row; }
    $stmt_controles->close();
} else { // Piloto
    // Piloto vê apenas equipamentos da sua OBM
    if (!empty($obm_do_usuario)) {
        $stmt_aeronaves = $conn->prepare("SELECT id, prefixo, modelo FROM aeronaves WHERE obm = ? ORDER BY prefixo ASC");
        $stmt_aeronaves->bind_param("s", $obm_do_usuario);
        $stmt_aeronaves->execute();
        $result_aeronaves = $stmt_aeronaves->get_result();
        while($row = $result_aeronaves->fetch_assoc()) { $aeronaves_selecao[] = $row; }
        $stmt_aeronaves->close();
        
        $stmt_controles = $conn->prepare("SELECT c.id, c.numero_serie, c.modelo, a.prefixo AS aeronave_vinculada 
                                           FROM controles c
                                           LEFT JOIN aeronaves a ON c.aeronave_id = a.id
                                           WHERE c.obm = ? ORDER BY c.id ASC");
        $stmt_controles->bind_param("s", $obm_do_usuario);
        $stmt_controles->execute();
        $result_controles = $stmt_controles->get_result();
        while($row = $result_controles->fetch_assoc()) { $controles_selecao[] = $row; }
        $stmt_controles->close();
    }
}


// --- Lógica para processar o formulário quando enviado ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $equipamento_tipo = htmlspecialchars($_POST['equipamento_tipo']);
    $equipamento_id = intval($_POST['equipamento_id']);
    $tipo_manutencao = htmlspecialchars($_POST['tipo_manutencao']);
    $data_manutencao = htmlspecialchars($_POST['data_manutencao']);
    $documento_servico = !empty($_POST['documento_servico']) ? htmlspecialchars($_POST['documento_servico']) : NULL;
    $responsavel = htmlspecialchars($_POST['responsavel']);
    $garantia_ate = !empty($_POST['garantia_ate']) ? htmlspecialchars($_POST['garantia_ate']) : NULL;
    $descricao = htmlspecialchars($_POST['descricao']);

    $stmt = $conn->prepare("INSERT INTO manutencoes (equipamento_id, equipamento_tipo, tipo_manutencao, data_manutencao, documento_servico, responsavel, garantia_ate, descricao) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssssss", $equipamento_id, $equipamento_tipo, $tipo_manutencao, $data_manutencao, $documento_servico, $responsavel, $garantia_ate, $descricao);

    if ($stmt->execute()) {
        if ($equipamento_tipo == 'Aeronave' && $tipo_manutencao == 'Reparadora') {
            $conn->query("UPDATE aeronaves SET status = 'ativo' WHERE id = $equipamento_id");
        } elseif ($equipamento_tipo == 'Controle' && $tipo_manutencao == 'Reparadora') {
             $conn->query("UPDATE controles SET status = 'ativo' WHERE id = $equipamento_id");
        }
        $mensagem_status = "<div class='success-message-box'>Manutenção registrada com sucesso! Redirecionando...</div>";
        echo "<script>setTimeout(function() { window.location.href = 'manutencao.php'; }, 2000);</script>";
    } else {
        $mensagem_status = "<div class='error-message-box'>Erro ao registrar manutenção: " . htmlspecialchars($stmt->error) . "</div>";
    }
    $stmt->close();
}
?>

<div class="main-content">
    <h1>Registrar Nova Manutenção</h1>
    <?php echo $mensagem_status; ?>
    <div class="form-container">
        <form id="manutencaoForm" action="cadastro_manutencao.php" method="POST">
            <div class="form-grid">
                <div class="form-group">
                    <label for="equipamento_tipo">Tipo de Equipamento:</label>
                    <select id="equipamento_tipo" name="equipamento_tipo" required>
                        <option value="">Selecione o Tipo</option>
                        <option value="Aeronave">Aeronave</option>
                        <option value="Controle">Controle</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="equipamento_id">Equipamento Específico:</label>
                    <select id="equipamento_id" name="equipamento_id" required disabled>
                        <option value="">Selecione o Tipo de Equipamento Primeiro</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="tipo_manutencao">Tipo de Manutenção:</label>
                    <select id="tipo_manutencao" name="tipo_manutencao" required>
                        <option value="">Selecione o Tipo</option>
                        <option value="Preventiva">Preventiva</option>
                        <option value="Reparadora">Reparadora</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="responsavel">Empresa ou Militar Responsável:</label>
                    <input type="text" id="responsavel" name="responsavel" placeholder="Ex: Cb. QPBM Fulano de Tal" required>
                </div>
                <div class="form-group">
                    <label for="data_manutencao">Data da Manutenção:</label>
                    <input type="date" id="data_manutencao" name="data_manutencao" required>
                </div>
                <div class="form-group">
                    <label for="garantia_ate">Garantia do Serviço até (Opcional):</label>
                    <input type="date" id="garantia_ate" name="garantia_ate">
                </div>
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label for="documento_servico">Nota Fiscal / Ordem de Serviço (Opcional):</label>
                    <input type="text" id="documento_servico" name="documento_servico" placeholder="Insira o número ou código do documento">
                </div>
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label for="descricao">Descrição do Serviço Realizado:</label>
                    <textarea id="descricao" name="descricao" rows="5" placeholder="Descreva detalhadamente o serviço que foi realizado." required></textarea>
                </div>
            </div>
            <div class="form-actions">
                <button id="submitButton" type="submit" disabled>Registrar Manutenção</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Converte os dados do PHP para objetos JavaScript
    const equipamentos = {
        Aeronave: <?php echo json_encode($aeronaves_selecao); ?>,
        Controle: <?php echo json_encode($controles_selecao); ?>
    };

    const form = document.getElementById('manutencaoForm');
    const submitButton = document.getElementById('submitButton');
    const tipoEquipamentoSelect = document.getElementById('equipamento_tipo');
    const equipamentoIdSelect = document.getElementById('equipamento_id');
    
    const requiredFields = Array.from(form.querySelectorAll('[required]'));

    function checkFormValidity() {
        const allValid = requiredFields.every(field => {
            if (field.disabled) return true;
            return field.value.trim() !== '';
        });
        submitButton.disabled = !allValid;
    }

    requiredFields.forEach(field => {
        field.addEventListener('input', checkFormValidity);
        field.addEventListener('change', checkFormValidity);
    });

    tipoEquipamentoSelect.addEventListener('change', function() {
        const tipoSelecionado = this.value;
        equipamentoIdSelect.innerHTML = '<option value="">Selecione um Equipamento</option>';
        
        if (tipoSelecionado && equipamentos[tipoSelecionado]) {
            equipamentoIdSelect.disabled = false;
            equipamentos[tipoSelecionado].forEach(function(item) {
                const option = document.createElement('option');
                option.value = item.id;
                
                let textoExibicao = '';
                if (tipoSelecionado === 'Aeronave') {
                    const forca_seguranca = item.forca_seguranca ? `(${item.forca_seguranca})` : '';
                    textoExibicao = `${item.prefixo} - ${item.modelo} ${forca_seguranca}`;
                } else { // Para Controle
                    const aeronaveVinculada = item.aeronave_vinculada || 'Reserva';
                    const forca_seguranca = item.forca_seguranca ? `(${item.forca_seguranca})` : '';
                    textoExibicao = `${aeronaveVinculada} - ${item.modelo} - S/N: ${item.numero_serie} ${forca_seguranca}`;
                }
                option.textContent = textoExibicao;
                equipamentoIdSelect.appendChild(option);
            });
        } else {
            equipamentoIdSelect.disabled = true;
        }
        checkFormValidity();
    });

    // Chama a função uma vez para definir o estado inicial do botão
    checkFormValidity();
});
</script>

<?php
// 6. INCLUI O RODAPÉ
require_once 'includes/footer.php';
?>