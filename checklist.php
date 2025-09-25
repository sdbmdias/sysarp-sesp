<?php
// 1. INCLUI O CABEÇALHO PADRÃO
require_once 'includes/header.php';

// 2. LÓGICA PARA BUSCAR AS AERONAVES PARA O DROPDOWN
$aeronaves_disponiveis = [];
$result = null;

if ($isSuperAdmin) {
    // Super Administrador tem acesso a todas as aeronaves.
    $sql_aeronaves = "SELECT id, prefixo, modelo FROM aeronaves ORDER BY prefixo ASC";
    $result = $conn->query($sql_aeronaves);
} elseif ($isAdmin) {
    // Administrador tem acesso apenas às aeronaves da sua Força de Segurança.
    $stmt_aeronaves = $conn->prepare("SELECT id, prefixo, modelo FROM aeronaves WHERE forca_seguranca = ? ORDER BY prefixo ASC");
    $stmt_aeronaves->bind_param("s", $user_forca_seguranca);
    $stmt_aeronaves->execute();
    $result = $stmt_aeronaves->get_result();
    $stmt_aeronaves->close();
} else { // Piloto
    // Piloto vê apenas as aeronaves da sua OBM.
    $obm_do_piloto = '';
    $stmt_obm = $conn->prepare("SELECT obm_piloto FROM pilotos WHERE id = ?");
    $stmt_obm->bind_param("i", $_SESSION['user_id']);
    $stmt_obm->execute();
    $result_obm = $stmt_obm->get_result();
    if ($result_obm->num_rows > 0) {
        $obm_do_piloto = $result_obm->fetch_assoc()['obm_piloto'];
    }
    $stmt_obm->close();

    if (!empty($obm_do_piloto)) {
        $stmt_aeronaves = $conn->prepare("SELECT id, prefixo, modelo FROM aeronaves WHERE obm = ? ORDER BY prefixo ASC");
        $stmt_aeronaves->bind_param("s", $obm_do_piloto);
        $stmt_aeronaves->execute();
        $result = $stmt_aeronaves->get_result();
        $stmt_aeronaves->close();
    }
}

if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $aeronaves_disponiveis[] = $row;
    }
}
?>

<div class="main-content">
    <h1>Checklist e Documentos de Aeronave</h1>
    <div class="form-container" style="max-width: 600px;">
        <p style="text-align: center; font-size: 1.1em;">Selecione uma aeronave para ver seus documentos e checklists associados.</p>
        <form action="documentos_aeronave.php" method="GET">
            <div class="form-group">
                <label for="aeronave_id">Selecione a Aeronave:</label>
                <select id="aeronave_id" name="id" required>
                    <option value="">-- Selecione uma opção --</option>
                    <?php foreach ($aeronaves_disponiveis as $aeronave): ?>
                        <option value="<?php echo $aeronave['id']; ?>">
                            <?php echo htmlspecialchars($aeronave['prefixo'] . ' - ' . $aeronave['modelo']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-actions">
                <button type="submit" style="width: 100%; padding: 15px; font-size: 18px;">
                    <i class="fas fa-search"></i> Consultar Documentos
                </button>
            </div>
        </form>
    </div>
</div>

<?php
// 4. INCLUI O RODAPÉ
require_once 'includes/footer.php';
?>