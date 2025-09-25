<?php

// includes/config_forcas.php

// Define o caminho para o arquivo JSON. Usar __DIR__ garante que o caminho esteja sempre correto.
$json_path = __DIR__ . '/config_forcas.json';

// Lê todo o conteúdo do arquivo JSON para uma string.
$json_data = file_get_contents($json_path);

// Decodifica a string JSON para um array associativo do PHP.
// O segundo parâmetro `true` é ESSENCIAL para que o resultado seja um array,
// mantendo a compatibilidade com o resto do seu código que espera por um array.
$config_forcas = json_decode($json_data, true);

// Opcional, mas recomendado: Verificação de erros.
if ($config_forcas === null && json_last_error() !== JSON_ERROR_NONE) {
    // Se houve um erro na decodificação do JSON (ex: sintaxe errada no arquivo),
    // o sistema para com uma mensagem clara.
    die("Erro fatal: falha ao decodificar o arquivo de configuração config_forcas.json. Erro: " . json_last_error_msg());
}

?>