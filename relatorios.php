<?php
// 1. INCLUI O CABEÇALHO PADRÃO
require_once 'includes/header.php';

$piloto_crbm = '';
// Se o usuário logado for um piloto, busca o CRBM dele
if ($isPiloto && isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT crbm_piloto FROM pilotos WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $piloto_crbm = $result->fetch_assoc()['crbm_piloto'];
    }
    $stmt->close();
}
?>

<style>
.reports-menu {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
    margin-top: 20px;
}
.report-button {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 30px;
    background-color: #fff;
    border-radius: 8px;
    box-shadow: 0 4px 15px rgba(0,0,0,.05);
    text-decoration: none;
    color: #2c3e50;
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
    text-align: center;
}
.report-button:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,.08);
}
.report-button i {
    font-size: 3em;
    margin-bottom: 15px;
    color: #3498db;
}
.report-button h2 {
    margin: 0;
    font-size: 1.3em;
}
.report-button p {
    margin: 5px 0 0 0;
    font-size: 0.9em;
    color: #555;
}
</style>

<div class="main-content">
    <h1>Central de Relatórios</h1>
    <p>Selecione um tipo de relatório para visualizar os dados detalhados.</p>

    <div class="reports-menu">
        <?php if ($isAdmin || $isPiloto): // Aparece para Admin e Piloto ?>
            <?php 
                $aeronave_link = "relatorio_aeronaves.php";
                if ($isPiloto && !empty($piloto_crbm)) {
                    // Adiciona o filtro de CRBM se for piloto
                    $aeronave_link .= "?crbm=" . urlencode($piloto_crbm);
                }
            ?>
            <a href="<?php echo htmlspecialchars($aeronave_link); ?>" class="report-button">
                <i class="fas fa-plane-departure"></i>
                <h2>Logbook por Aeronave</h2>
                <p>Distância e tempo de voo por cada aeronave.</p>
            </a>
        <?php endif; ?>

        <?php if ($isAdmin || $isPiloto): // Aparece para Admin e Piloto ?>
            <?php 
                $piloto_link = "relatorio_pilotos.php";
                if ($isPiloto && !empty($piloto_crbm)) {
                    // Adiciona o filtro de CRBM se for piloto
                    $piloto_link .= "?crbm=" . urlencode($piloto_crbm);
                }
            ?>
            <a href="<?php echo htmlspecialchars($piloto_link); ?>" class="report-button">
                <i class="fas fa-users-cog"></i>
                <h2>Logbook por Piloto</h2>
                <p>Horas de voo e distância percorrida por cada piloto.</p>
            </a>
        <?php endif; ?>

        <?php if ($isAdmin): // Aparece APENAS para Admin ?>
            <a href="relatorios_especificos.php" class="report-button">
                <i class="fas fa-file-alt"></i>
                <h2>Relatórios Específicos</h2>
                <p>Gere relatórios personalizados por período e tipo.</p>
            </a>
        <?php endif; ?>
    </div>
</div>

<?php
// INCLUI O RODAPÉ
require_once 'includes/footer.php';
?>