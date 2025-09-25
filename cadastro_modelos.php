<?php
// 1. INCLUI O CABEÇALHO PADRÃO
require_once 'includes/header.php';

// 2. VERIFICAÇÃO DE PERMISSÃO
if (!$isAdmin) {
    header("Location: dashboard.php");
    exit();
}

// 3. LÓGICA ESPECÍFICA DA PÁGINA
$mensagem_status = "";

// Processa o formulário de cadastro
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['cadastrar_modelo'])) {
    $fabricante = htmlspecialchars(trim($_POST['fabricante']));
    $modelo = htmlspecialchars(trim($_POST['modelo']));
    $tipo = htmlspecialchars($_POST['tipo']);

    if (!empty($fabricante) && !empty($modelo) && !empty($tipo)) {
        $stmt_check = $conn->prepare("SELECT id FROM fabricantes_modelos WHERE fabricante = ? AND modelo = ? AND tipo = ?");
        $stmt_check->bind_param("sss", $fabricante, $modelo, $tipo);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();

        if ($result_check->num_rows == 0) {
            $stmt_insert = $conn->prepare("INSERT INTO fabricantes_modelos (fabricante, modelo, tipo) VALUES (?, ?, ?)");
            $stmt_insert->bind_param("sss", $fabricante, $modelo, $tipo);
            if ($stmt_insert->execute()) {
                $mensagem_status = "<div class='success-message-box'>Modelo cadastrado com sucesso!</div>";
            } else {
                $mensagem_status = "<div class='error-message-box'>Erro ao cadastrar o modelo: " . htmlspecialchars($stmt_insert->error) . "</div>";
            }
            $stmt_insert->close();
        } else {
            $mensagem_status = "<div class='error-message-box'>Este modelo já está cadastrado para este tipo de equipamento.</div>";
        }
        $stmt_check->close();
    } else {
        $mensagem_status = "<div class='error-message-box'>Todos os campos são obrigatórios.</div>";
    }
}

// Processa a exclusão
if ($isAdmin && isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $stmt_delete = $conn->prepare("DELETE FROM fabricantes_modelos WHERE id = ?");
    $stmt_delete->bind_param("i", $delete_id);
    if ($stmt_delete->execute()) {
        $mensagem_status = "<div class='success-message-box'>Registro excluído com sucesso!</div>";
    } else {
        $mensagem_status = "<div class='error-message-box'>Erro ao excluir registro.</div>";
    }
    $stmt_delete->close();
}

// Busca os modelos e os separa por tipo
$modelos_aeronaves = [];
$modelos_controles = [];
$sql_modelos = "SELECT id, fabricante, modelo, tipo, data_registro FROM fabricantes_modelos ORDER BY tipo, fabricante, modelo ASC";
$result_modelos = $conn->query($sql_modelos);
if ($result_modelos->num_rows > 0) {
    while($row = $result_modelos->fetch_assoc()) {
        if ($row['tipo'] == 'Aeronave') {
            $modelos_aeronaves[] = $row;
        } else {
            $modelos_controles[] = $row;
        }
    }
}
?>

<div class="main-content">
    <h1>Cadastro de Fabricantes e Modelos</h1>

    <?php echo $mensagem_status; ?>

    <div class="form-container" style="margin-bottom: 40px;">
        <h2>Adicionar Novo Modelo</h2>
        <form id="modeloForm" action="cadastro_modelos.php" method="POST">
            <div class="form-grid">
                <div class="form-group">
                    <label for="fabricante">Fabricante:</label>
                    <input type="text" id="fabricante" name="fabricante" placeholder="Ex: DJI, Autel Robotics" required>
                </div>
                <div class="form-group">
                    <label for="modelo">Nome do Modelo:</label>
                    <input type="text" id="modelo" name="modelo" placeholder="Ex: Mavic 3T, RC Pro" required>
                </div>
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label for="tipo">Tipo de Equipamento:</label>
                    <select id="tipo" name="tipo" required>
                        <option value="">Selecione o Tipo</option>
                        <option value="Aeronave">Aeronave</option>
                        <option value="Controle">Controle</option>
                    </select>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" name="cadastrar_modelo">Salvar Modelo</button>
            </div>
        </form>
    </div>

    <div class="table-container" style="margin-bottom: 30px;">
        <h2><i class="fas fa-plane"></i> Modelos de Aeronaves Cadastrados</h2>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Fabricante</th>
                    <th>Modelo</th>
                    <th>Data de Cadastro</th>
                    <th>Ação</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($modelos_aeronaves)): ?>
                    <?php foreach ($modelos_aeronaves as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['fabricante']); ?></td>
                            <td><?php echo htmlspecialchars($item['modelo']); ?></td>
                            <td><?php echo date("d/m/Y", strtotime($item['data_registro'])); ?></td>
                            <td class="action-buttons">
                                <a href="cadastro_modelos.php?delete_id=<?php echo $item['id']; ?>" class="edit-btn" style="background-color:#dc3545;" onclick="return confirm('Tem certeza que deseja excluir este modelo?');">Excluir</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4">Nenhum modelo de aeronave cadastrado.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="table-container">
        <h2><i class="fas fa-gamepad"></i> Modelos de Controles Cadastrados</h2>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Fabricante</th>
                    <th>Modelo</th>
                    <th>Data de Cadastro</th>
                    <th>Ação</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($modelos_controles)): ?>
                    <?php foreach ($modelos_controles as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['fabricante']); ?></td>
                            <td><?php echo htmlspecialchars($item['modelo']); ?></td>
                            <td><?php echo date("d/m/Y", strtotime($item['data_registro'])); ?></td>
                            <td class="action-buttons">
                                <a href="cadastro_modelos.php?delete_id=<?php echo $item['id']; ?>" class="edit-btn" style="background-color:#dc3545;" onclick="return confirm('Tem certeza que deseja excluir este modelo?');">Excluir</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4">Nenhum modelo de controle cadastrado.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
// INCLUI O RODAPÉ
require_once 'includes/footer.php';
?>