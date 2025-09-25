<?php
require_once 'includes/header.php';

$missao_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$missao_details = null;
$pilotos_envolvidos = [];
$gpx_files_logs = [];
$trajetorias_por_voo = [];
$controle_usado = null;

if ($missao_id <= 0) {
    header("Location: listar_missoes.php");
    exit();
}

// 1. BUSCA OS DETALHES GERAIS DA MISSÃO
$sql_details = "
    SELECT 
        m.*,
        a.prefixo AS aeronave_prefixo, a.modelo AS aeronave_modelo, a.obm AS aeronave_obm, a.id as aeronave_id
    FROM missoes m
    JOIN aeronaves a ON m.aeronave_id = a.id
    WHERE m.id = ?
";
$stmt_details = $conn->prepare($sql_details);
$stmt_details->bind_param("i", $missao_id);
$stmt_details->execute();
$result_details = $stmt_details->get_result();
if ($result_details->num_rows === 1) {
    $missao_details = $result_details->fetch_assoc();
    
    // Busca o controle vinculado à aeronave da missão
    $stmt_controle = $conn->prepare("SELECT modelo, numero_serie FROM controles WHERE aeronave_id = ?");
    $stmt_controle->bind_param("i", $missao_details['aeronave_id']);
    $stmt_controle->execute();
    $result_controle = $stmt_controle->get_result();
    if($result_controle->num_rows > 0){
        $controle_usado = $result_controle->fetch_assoc();
    }
    $stmt_controle->close();

} else {
    header("Location: listar_missoes.php");
    exit();
}
$stmt_details->close();

// 2. BUSCA OS PILOTOS ENVOLVIDOS COM ORDENAÇÃO POR HIERARQUIA
$sql_pilotos = "
    SELECT p.posto_graduacao, p.nome_completo 
    FROM missoes_pilotos mp
    JOIN pilotos p ON mp.piloto_id = p.id 
    WHERE mp.missao_id = ?
    ORDER BY
        CASE p.posto_graduacao
            WHEN 'Cel. QOBM' THEN 1 WHEN 'Ten. Cel. QOBM' THEN 2 WHEN 'Maj. QOBM' THEN 3
            WHEN 'Cap. QOBM' THEN 4 WHEN '1º Ten. QOBM' THEN 5 WHEN '2º Ten. QOBM' THEN 6
            WHEN 'Asp. Oficial' THEN 7 WHEN 'Sub. Ten. QPBM' THEN 8 WHEN '1º Sgt. QPBM' THEN 9
            WHEN '2º Sgt. QPBM' THEN 10 WHEN '3º Sgt. QPBM' THEN 11 WHEN 'Cb. QPBM' THEN 12
            WHEN 'Sd. QPBM' THEN 13 ELSE 14
        END
";
$stmt_pilotos = $conn->prepare($sql_pilotos);
$stmt_pilotos->bind_param("i", $missao_id);
$stmt_pilotos->execute();
$result_pilotos = $stmt_pilotos->get_result();
while ($row = $result_pilotos->fetch_assoc()) {
    $pilotos_envolvidos[] = $row['posto_graduacao'] . ' ' . $row['nome_completo'];
}
$stmt_pilotos->close();

// 3. BUSCA OS LOGS E COORDENADAS
$sql_gpx = "SELECT id, file_name, tempo_voo, distancia_percorrida, altura_maxima, data_decolagem, data_pouso FROM missoes_gpx_files WHERE missao_id = ? ORDER BY data_decolagem ASC";
$stmt_gpx = $conn->prepare($sql_gpx);
$stmt_gpx->bind_param("i", $missao_id);
$stmt_gpx->execute();
$result_gpx = $stmt_gpx->get_result();

$sql_coords = "SELECT latitude, longitude FROM missao_coordenadas WHERE gpx_file_id = ? ORDER BY timestamp_ponto ASC";
$stmt_coords = $conn->prepare($sql_coords);

while ($gpx_file = $result_gpx->fetch_assoc()) {
    $gpx_files_logs[] = $gpx_file;
    $gpx_file_id = $gpx_file['id'];
    $stmt_coords->bind_param("i", $gpx_file_id);
    $stmt_coords->execute();
    $result_coords = $stmt_coords->get_result();
    
    $trajetoria_atual = [];
    while ($coord = $result_coords->fetch_assoc()) {
        $trajetoria_atual[] = [(float)$coord['longitude'], (float)$coord['latitude']];
    }
    if (!empty($trajetoria_atual)) {
        $trajetorias_por_voo[] = $trajetoria_atual;
    }
}
$stmt_gpx->close();
$stmt_coords->close();

// Funções de formatação
function formatarTempoVooCompleto($segundos) {
    if ($segundos <= 0) return '0min';
    $horas = floor($segundos / 3600);
    $minutos = floor(($segundos % 3600) / 60);
    $resultado = '';
    if ($horas > 0) $resultado .= $horas . 'h ';
    if ($minutos > 0) $resultado .= $minutos . 'min';
    return trim($resultado) ?: '0min';
}

function formatarDistancia($metros) {
    if ($metros < 1000) {
        return round($metros) . ' m';
    } else {
        return number_format($metros / 1000, 2, ',', '.') . ' km';
    }
}
?>

<script src='https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.js'></script>
<link href='https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.css' rel='stylesheet' />
<script src='https://api.mapbox.com/mapbox-gl-js/plugins/mapbox-gl-geocoder/v4.7.2/mapbox-gl-geocoder.min.js'></script>
<link rel='stylesheet' href='https://api.mapbox.com/mapbox-gl-js/plugins/mapbox-gl-geocoder/v4.7.2/mapbox-gl-geocoder.css' type='text/css' />

<style>
    .details-fieldset { border: 1px solid #ccc; border-radius: 5px; padding: 20px; margin-bottom: 25px; page-break-inside: avoid; }
    .details-fieldset legend { font-weight: bold; color: #2c3e50; padding: 0 10px; }
    .details-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px; }
    .detail-item { background-color: #f8f9fa; padding: 15px; border-radius: 4px; border-left: 4px solid #3498db; }
    .detail-item strong { display: block; color: #555; margin-bottom: 5px; font-size: 0.9em; }
    .detail-item p, .detail-item ul { margin: 0; padding: 0; color: #333; font-size: 1.1em; }
    .detail-item ul { list-style-position: inside; }
    
    /* Estilos da Legenda do Mapa */
    #map-legend {
        position: absolute;
        top: 10px;
        left: 10px;
        background: rgba(255, 255, 255, 0.9);
        padding: 5px 10px;
        border-radius: 5px;
        border: 1px solid #ccc;
        font-family: Arial, sans-serif;
        font-size: 14px;
        z-index: 1;
        max-width: 200px;
    }
    .legend-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        cursor: pointer;
    }
    .legend-header h4 {
        margin: 0;
        padding-right: 10px;
    }
    .legend-toggle-btn {
        background: none;
        border: none;
        font-size: 16px;
        cursor: pointer;
        padding: 0;
        line-height: 1;
        color: #333;
    }
    #legend-content {
        margin-top: 5px;
        transition: max-height 0.3s ease-out, opacity 0.3s ease-out;
        max-height: 300px; /* Altura suficiente para os itens */
        overflow: hidden;
    }
    #map-legend.collapsed #legend-content {
        max-height: 0;
        opacity: 0;
        margin-top: 0;
    }
    .legend-item {
        display: flex;
        align-items: center;
        margin-bottom: 5px;
    }
    .legend-key {
        display: inline-block;
        width: 20px;
        height: 20px;
        margin-right: 8px;
        border-radius: 3px;
        border: 1px solid rgba(0,0,0,0.2);
    }
</style>

<div class="main-content">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 20px;">
        <h1>Detalhes da Missão <?php echo !empty($missao_details['rgo_ocorrencia']) ? 'RGO ' . htmlspecialchars($missao_details['rgo_ocorrencia']) : '#' . $missao_id; ?></h1>
        <?php if ($missao_details): // Botão Gerar PDF só se a missão for encontrada ?>
            <a href="gerar_pdf_detalhes_missao.php?id=<?php echo $missao_id; ?>" target="_blank" class="form-actions button" style="text-decoration: none; display: inline-block; padding: 10px 20px; background-color: #c0392b; color: #fff;">
                <i class="fas fa-file-pdf"></i> Gerar PDF
            </a>
        <?php endif; ?>
    </div>

    <div class="form-container">
        <?php if ($missao_details): ?>
            
            <fieldset class="details-fieldset">
                <legend>Mapa da Trajetória</legend>
                <div id='map-container' style='position: relative;'>
                    <div id='map' style='width: 100%; height: 450px; border-radius: 5px;'></div>
                    <div id='map-legend' style="display: none;">
                        <div id="legend-header" class="legend-header">
                           <h4>Legenda</h4>
                           <button id="legend-toggle-btn" class="legend-toggle-btn"><i class="fas fa-minus"></i></button>
                        </div>
                        <div id="legend-content"></div>
                    </div>
                </div>
            </fieldset>

            <fieldset class="details-fieldset">
                <legend>Detalhes da Operação</legend>
                <div class="details-grid">
                    <div class="detail-item"><strong>Data:</strong><p><?php echo date("d/m/Y", strtotime($missao_details['data'])); ?></p></div>
                    <div class="detail-item"><strong>Nº RGO:</strong><p><?php echo htmlspecialchars($missao_details['rgo_ocorrencia'] ?? 'Não informado'); ?></p></div>
                    <div class="detail-item"><strong>Descrição da Operação:</strong><p><?php echo htmlspecialchars($missao_details['descricao_operacao']); ?></p></div>
                    <div class="detail-item"><strong>Protocolo SARPAS:</strong><p><?php echo htmlspecialchars($missao_details['protocolo_sarpas'] ?? 'Não informado'); ?></p></div>
                    <div class="detail-item">
                        <strong>Forma de Acionamento:</strong>
                        <p><?php echo htmlspecialchars($missao_details['forma_acionamento']); ?>
                           <?php if($missao_details['forma_acionamento'] == 'Outro' && !empty($missao_details['forma_acionamento_outro'])): ?>
                               (<?php echo htmlspecialchars($missao_details['forma_acionamento_outro']); ?>)
                           <?php endif; ?>
                        </p>
                    </div>
                    <div class="detail-item">
                         <strong>Contato com o Orgão ATS:</strong>
                        <p><?php echo htmlspecialchars($missao_details['contato_ats']); ?>
                           <?php if($missao_details['contato_ats'] == 'Outro' && !empty($missao_details['contato_ats_outro'])): ?>
                               (<?php echo htmlspecialchars($missao_details['contato_ats_outro']); ?>)
                           <?php endif; ?>
                        </p>
                    </div>
                </div>
                 <div class="detail-item" style="margin-top: 15px; grid-column: 1 / -1;">
                    <strong>Descrição do Ocorrido:</strong>
                    <p style="white-space: pre-wrap;"><?php echo !empty($missao_details['descricao_ocorrido']) ? htmlspecialchars($missao_details['descricao_ocorrido']) : 'Não informado'; ?></p>
                </div>
            </fieldset>
            
            <fieldset class="details-fieldset">
                <legend>Equipamentos e Pessoal</legend>
                 <div class="details-grid">
                    <div class="detail-item"><strong>Aeronave:</strong><p><?php echo htmlspecialchars($missao_details['aeronave_prefixo'] . ' - ' . $missao_details['aeronave_modelo']); ?> (<?php echo htmlspecialchars($missao_details['aeronave_obm']); ?>)</p></div>
                    <div class="detail-item"><strong>Controle:</strong><p><?php echo $controle_usado ? htmlspecialchars($controle_usado['modelo'] . ' - S/N: ' . $controle_usado['numero_serie']) : 'Não informado'; ?></p></div>
                    <div class="detail-item" style="grid-column: 1 / -1;">
                        <strong>Piloto(s) Envolvido(s):</strong>
                        <?php if (empty($pilotos_envolvidos)): ?>
                            <p>Nenhum piloto associado.</p>
                        <?php else: ?>
                            <ul style="list-style-type: none; padding-left: 0;">
                                <?php foreach ($pilotos_envolvidos as $piloto): ?>
                                    <li><?php echo htmlspecialchars($piloto); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </fieldset>

            <fieldset class="details-fieldset">
                 <legend>Dados Complementares</legend>
                 <div class="details-grid">
                    <div class="detail-item" style="grid-column: 1 / -1;">
                        <strong>Link das Fotos/Vídeos:</strong>
                        <?php if(!empty($missao_details['link_fotos_videos'])): ?>
                            <p><a href="<?php echo htmlspecialchars($missao_details['link_fotos_videos']); ?>" target="_blank" rel="noopener noreferrer"><?php echo htmlspecialchars($missao_details['link_fotos_videos']); ?></a></p>
                        <?php else: ?>
                            <p>Não informado</p>
                        <?php endif; ?>
                    </div>
                    <div class="detail-item" style="grid-column: 1 / -1;">
                        <strong>Dados da Vítima/Alvo:</strong>
                        <p style="white-space: pre-wrap;"><?php echo !empty($missao_details['dados_vitima']) ? htmlspecialchars($missao_details['dados_vitima']) : 'Não informado'; ?></p>
                    </div>
                </div>
            </fieldset>

            <fieldset class="details-fieldset">
                <legend>Logs de Voo Individuais</legend>
                <div class="table-container" style="padding:0; box-shadow: none; border: none;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Ficheiro GPX</th>
                                <th>Decolagem</th>
                                <th>Pouso</th>
                                <th>Duração</th>
                                <th>Distância</th>
                                <th>Altura Máx.</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($gpx_files_logs as $log): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($log['file_name']); ?></td>
                                <td><?php echo date("d/m/Y H:i:s", strtotime($log['data_decolagem'])); ?></td>
                                <td><?php echo date("d/m/Y H:i:s", strtotime($log['data_pouso'])); ?></td>
                                <td><?php echo formatarTempoVooCompleto($log['tempo_voo']); ?></td>
                                <td><?php echo formatarDistancia($log['distancia_percorrida']); ?></td>
                                <td><?php echo round($log['altura_maxima'], 2); ?> m</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </fieldset>

            <fieldset class="details-fieldset">
                <legend>Log Total Consolidado da Missão</legend>
                 <div class="details-grid">
                    <div class="detail-item"><strong>Primeira Decolagem:</strong><p><?php echo date("d/m/Y H:i", strtotime($missao_details['data_primeira_decolagem'])); ?></p></div>
                    <div class="detail-item"><strong>Último Pouso:</strong><p><?php echo date("d/m/Y H:i", strtotime($missao_details['data_ultimo_pouso'])); ?></p></div>
                    <div class="detail-item"><strong>Tempo Total de Voo:</strong><p><?php echo formatarTempoVooCompleto($missao_details['total_tempo_voo']); ?></p></div>
                    <div class="detail-item"><strong>Distância Total Percorrida:</strong><p><?php echo formatarDistancia($missao_details['total_distancia_percorrida']); ?></p></div>
                    <div class="detail-item" style="grid-column: 1 / -1;"><strong>Altura Máxima Atingida na Missão:</strong><p><?php echo round($missao_details['altitude_maxima'], 2); ?> m</p></div>
                 </div>
            </fieldset>

        <?php else: ?>
            <div class="error-message-box">
                Missão não encontrada. Por favor, verifique o ID e tente novamente.
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const todasAsTrajetorias = <?php echo json_encode($trajetorias_por_voo); ?>;
    
    if (document.getElementById('map') && todasAsTrajetorias && todasAsTrajetorias.length > 0 && todasAsTrajetorias[0].length > 0) {
        
        mapboxgl.accessToken = 'pk.eyJ1Ijoic2d0ZGlhcyIsImEiOiJjbWQyczc0ZnIwZWJ1MmlvZWc1ZHNpMTZyIn0.DD-OVrx3pBjx2cQjMCtyOQ'; 
        
        const map = new mapboxgl.Map({
            container: 'map',
            style: 'mapbox://styles/mapbox/satellite-streets-v11',
            center: todasAsTrajetorias[0][0],
            zoom: 15,
        });

        map.addControl(new mapboxgl.NavigationControl());
        
        const legend = document.getElementById('map-legend');
        const legendHeader = document.getElementById('legend-header');
        const legendToggleBtn = document.getElementById('legend-toggle-btn');
        const legendContent = document.getElementById('legend-content');

        const bounds = new mapboxgl.LngLatBounds();
        const colors = [
            '#FF5733', '#33FF57', '#3357FF', '#FF33A1', '#A133FF', '#33FFA1', 
            '#FFC300', '#DAF7A6', '#C70039', '#900C3F', '#581845', '#FF8C00',
            '#00FFFF', '#FF00FF', '#7CFC00', '#8A2BE2', '#00BFFF', '#ADFF2F'
        ];

        legendHeader.addEventListener('click', () => {
            legend.classList.toggle('collapsed');
            const icon = legendToggleBtn.querySelector('i');
            if (legend.classList.contains('collapsed')) {
                icon.classList.remove('fa-minus');
                icon.classList.add('fa-plus');
            } else {
                icon.classList.remove('fa-plus');
                icon.classList.add('fa-minus');
            }
        });

        map.on('load', () => {
            legend.style.display = 'block';

            todasAsTrajetorias.forEach((trajetoria, index) => {
                if (trajetoria.length < 2) return;

                const color = colors[index % colors.length];
                const sourceId = `route-${index}`;
                map.addSource(sourceId, {
                    'type': 'geojson',
                    'data': {'type': 'Feature', 'geometry': {'type': 'LineString', 'coordinates': trajetoria}}
                });

                map.addLayer({
                    'id': `line-${index}`, 'type': 'line', 'source': sourceId,
                    'layout': { 'line-join': 'round', 'line-cap': 'round' },
                    'paint': {'line-color': color, 'line-width': 4, 'line-opacity': 0.85}
                });
                
                // Adicionar item à legenda
                const legendItem = document.createElement('div');
                legendItem.className = 'legend-item';
                const legendKey = document.createElement('span');
                legendKey.className = 'legend-key';
                legendKey.style.backgroundColor = color;
                const legendLabel = document.createTextNode(` Voo ${index + 1}`);
                legendItem.appendChild(legendKey);
                legendItem.appendChild(legendLabel);
                legendContent.appendChild(legendItem);

                // Adicionar marcadores de início e fim
                new mapboxgl.Marker({ color: '#28a745' }).setLngLat(trajetoria[0]).setPopup(new mapboxgl.Popup().setText(`Início do Voo ${index + 1}`)).addTo(map);
                new mapboxgl.Marker({ color: '#dc3545' }).setLngLat(trajetoria[trajetoria.length - 1]).setPopup(new mapboxgl.Popup().setText(`Fim do Voo ${index + 1}`)).addTo(map);

                // Estender os limites do mapa para incluir a trajetória
                trajetoria.forEach(coord => bounds.extend(coord));
            });
            
            if (!bounds.isEmpty()) {
                map.fitBounds(bounds, { padding: { top: 60, bottom: 60, left: 60, right: 60 }});
            }
        });

    } else if(document.getElementById('map')) {
        document.getElementById('map').innerHTML = '<p style="text-align:center; padding: 20px;">Não há dados de trajetória para exibir no mapa.</p>';
        document.getElementById('map-legend').style.display = 'none';
    }
});
</script>

<?php
require_once 'includes/footer.php';
?>