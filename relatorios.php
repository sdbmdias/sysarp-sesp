<?php
// 1. INCLUI O CABEÇALHO PADRÃO
require_once 'includes/header.php';
?>

<style>
*, *::before, *::after {
    box-sizing: border-box;
}

.charts-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 20px;
    margin-bottom: 40px;
}
.chart-container {
    background-color: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 4px 15px rgba(0,0,0,.05);
    /* Reintroduzindo o flexbox de forma controlada */
    display: flex;
    flex-direction: column;
    height: 400px; /* Altura total do card do gráfico */
}
.chart-container h3 {
    margin-bottom: 15px;
    text-align: center;
    flex-shrink: 0; /* Impede que o título encolha */
}

/* Contentor para o canvas */
.chart-wrapper {
    position: relative; /* Essencial para o Chart.js responsivo */
    flex-grow: 1; /* Faz este elemento ocupar todo o espaço vertical disponível */
}

.reports-list {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
}
.report-card {
    background-color: #fff;
    border-radius: 8px;
    box-shadow: 0 4px 15px rgba(0,0,0,.05);
    padding: 25px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}
.report-card-header {
    text-align: center;
    margin-bottom: 20px;
}
.report-card-header i {
    font-size: 3em;
    color: #3498db;
    margin-bottom: 10px;
}
.report-card-header h2 {
    margin: 0;
    font-size: 1.3em;
}
.report-card-body p {
    font-size: 0.9em;
    color: #555;
    text-align: center;
    flex-grow: 1;
}
.report-card-footer {
    display: flex;
    justify-content: center;
    gap: 10px;
    margin-top: 20px;
}
.report-card-footer .btn {
    flex-grow: 1;
    text-align: center;
    padding: 10px 15px;
    border-radius: 5px;
    text-decoration: none;
    color: #fff;
    font-weight: bold;
    transition: background-color 0.2s ease;
}
.btn-primary {
    background-color: #3498db;
    border: 1px solid #3498db;
}
.btn-primary:hover {
    background-color: #2980b9;
}
.btn-secondary {
    background-color: #6c757d;
    border: 1px solid #6c757d;
}
.btn-secondary:hover {
    background-color: #5a6268;
}
.error-message { 
    color: #e74c3c; 
    font-weight: 500; 
    text-align: center;
    padding-top: 20%; /* Centraliza a mensagem de erro verticalmente */
}
</style>

<div class="main-content">
    <h1>Central de Relatórios</h1>
    <p>Visualize dados consolidados e gere relatórios detalhados em formato PDF.</p>

    <div class="charts-grid">
        <div class="chart-container" id="horasVooContainer">
            <h3>Horas de Voo (Últimos 12 Meses)</h3>
            <div class="chart-wrapper">
                <canvas id="horasVooChart"></canvas>
            </div>
        </div>
        <div class="chart-container" id="missoesTipoContainer">
            <h3>Missões por Tipo</h3>
            <div class="chart-wrapper">
                <canvas id="missoesTipoChart"></canvas>
            </div>
        </div>
    </div>

    <h2>Gerar Relatórios em PDF</h2>
    <div class="reports-list">
        <div class="report-card">
            <div class="report-card-header">
                <i class="fas fa-map-marked-alt"></i>
                <h2>Relatório de Missões</h2>
            </div>
            <div class="report-card-body">
                <p>Lista detalhada de todas as missões realizadas, incluindo datas, durações e pilotos envolvidos.</p>
            </div>
            <div class="report-card-footer">
                <a href="listar_missoes.php" class="btn btn-secondary">Ver Dados</a>
                <?php if ($isAdmin || $isSuperAdmin): ?>
                    <a href="gerar_pdf_missoes_fpdf.php" target="_blank" class="btn btn-primary">Gerar PDF</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="report-card">
            <div class="report-card-header">
                <i class="fas fa-plane"></i>
                <h2>Relatório de Aeronaves</h2>
            </div>
            <div class="report-card-body">
                <p>Inventário completo de todas as aeronaves cadastradas, com status, lotação e informações técnicas.</p>
            </div>
            <div class="report-card-footer">
                <a href="listar_aeronaves.php" class="btn btn-secondary">Ver Dados</a>
                <?php if ($isAdmin || $isSuperAdmin): ?>
                    <a href="gerar_pdf_aeronaves_fpdf.php" target="_blank" class="btn btn-primary">Gerar PDF</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="report-card">
            <div class="report-card-header">
                <i class="fas fa-users-cog"></i>
                <h2>Logbook por Piloto</h2>
            </div>
            <div class="report-card-body">
                <p>Horas de voo e distância percorrida por cada piloto, consolidado de todas as missões.</p>
            </div>
            <div class="report-card-footer">
                <a href="relatorio_pilotos.php" class="btn btn-secondary">Ver Dados</a>
                 <?php if ($isAdmin || $isSuperAdmin): ?>
                    <a href="gerar_pdf_pilotos_fpdf.php" target="_blank" class="btn btn-primary">Gerar PDF</a>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="report-card">
            <div class="report-card-header">
                <i class="fas fa-tools"></i>
                <h2>Relatório de Manutenções</h2>
            </div>
            <div class="report-card-body">
                <p>Histórico completo de todas as manutenções preventivas e corretivas realizadas nos equipamentos.</p>
            </div>
            <div class="report-card-footer">
                <a href="manutencao.php" class="btn btn-secondary">Ver Dados</a>
                <?php if ($isAdmin || $isSuperAdmin): ?>
                    <a href="gerar_pdf_manutencao_fpdf.php" target="_blank" class="btn btn-primary">Gerar PDF</a>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if ($isAdmin || $isSuperAdmin): ?>
            <div class="report-card">
                <div class="report-card-header">
                    <i class="fas fa-file-alt"></i>
                    <h2>Relatórios Específicos</h2>
                </div>
                <div class="report-card-body">
                    <p>Gere relatórios personalizados por período, tipo, força de segurança e outras opções avançadas.</p>
                </div>
                <div class="report-card-footer">
                     <a href="relatorios_especificos.php" class="btn btn-primary" style="width: 100%;">Acessar</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const horasVooContainer = document.getElementById('horasVooContainer');
    const missoesTipoContainer = document.getElementById('missoesTipoContainer');

    async function carregarDadosGraficos() {
        try {
            const response = await fetch('get_dados_graficos.php');
            const result = await response.json();

            if (!response.ok || !result.success) {
                throw new Error(result.message || 'Falha ao buscar dados dos gráficos.');
            }
            
            const dados = result.data;

            // Limpa o container e recria a estrutura básica
            horasVooContainer.innerHTML = '<h3>Horas de Voo (Últimos 12 Meses)</h3><div class="chart-wrapper"><canvas id="horasVooChart"></canvas></div>';
            missoesTipoContainer.innerHTML = '<h3>Missões por Tipo</h3><div class="chart-wrapper"><canvas id="missoesTipoChart"></canvas></div>';
            
            // Renderiza Gráfico 1: Horas de Voo
            if (dados.horasVoo.labels.length > 0) {
                const ctxHoras = document.getElementById('horasVooChart').getContext('2d');
                new Chart(ctxHoras, { type: 'bar', data: { labels: dados.horasVoo.labels, datasets: [{ label: 'Horas de Voo', data: dados.horasVoo.data, backgroundColor: 'rgba(52, 152, 219, 0.7)' }] }, options: { scales: { y: { beginAtZero: true } }, responsive: true, maintainAspectRatio: false } });
            } else {
                horasVooContainer.innerHTML = '<h3>Horas de Voo (Últimos 12 Meses)</h3><div class="chart-wrapper"><p class="error-message">Sem dados de horas de voo para exibir.</p></div>';
            }

            // Renderiza Gráfico 2: Missões por Tipo
            if (dados.missoesPorTipo.labels.length > 0) {
                const ctxMissoes = document.getElementById('missoesTipoChart').getContext('2d');
                new Chart(ctxMissoes, { type: 'doughnut', data: { labels: dados.missoesPorTipo.labels, datasets: [{ label: 'Nº de Missões', data: dados.missoesPorTipo.data, backgroundColor: ['rgba(231, 76, 60, 0.7)','rgba(46, 204, 113, 0.7)','rgba(241, 196, 15, 0.7)','rgba(155, 89, 182, 0.7)','rgba(52, 73, 94, 0.7)'] }] }, options: { responsive: true, maintainAspectRatio: false } });
            } else {
                missoesTipoContainer.innerHTML = '<h3>Missões por Tipo</h3><div class="chart-wrapper"><p class="error-message">Sem dados de missões para exibir.</p></div>';
            }

        } catch (error) {
            console.error('Erro ao carregar dados dos gráficos:', error);
            const errorMessage = `<p class="error-message">Não foi possível carregar os gráficos.<br><small>Detalhe: ${error.message}</small></p>`;
            horasVooContainer.innerHTML = `<h3>Horas de Voo (Últimos 12 Meses)</h3><div class="chart-wrapper">${errorMessage}</div>`;
            missoesTipoContainer.innerHTML = `<h3>Missões por Tipo</h3><div class="chart-wrapper">${errorMessage}</div>`;
        }
    }

    carregarDadosGraficos();
});
</script>

<?php
// INCLUI O RODAPÉ
require_once 'includes/footer.php';
?>