<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>Diagnóstico - Tabela configuracoes_catalogo</title>
    <style>
        body { font-family: Arial; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; }
        h1 { color: #8B5CF6; border-bottom: 3px solid #8B5CF6; padding-bottom: 10px; }
        h2 { color: #666; margin-top: 30px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border: 1px solid #ddd; }
        th { background: #8B5CF6; color: white; }
        tr:nth-child(even) { background: #f9f9f9; }
        .status-ok { color: green; font-weight: bold; }
        .status-missing { color: red; font-weight: bold; }
        .alert { padding: 15px; margin: 20px 0; border-radius: 5px; }
        .alert-success { background: #d1fae5; color: #065f46; border-left: 4px solid #10b981; }
        .alert-error { background: #fee2e2; color: #991b1b; border-left: 4px solid #ef4444; }
        .alert-warning { background: #fef3c7; color: #92400e; border-left: 4px solid #f59e0b; }
        pre { background: #f8f8f8; padding: 15px; border-radius: 5px; overflow-x: auto; }
        .btn { display: inline-block; padding: 10px 20px; margin: 10px 5px; background: #8B5CF6; color: white; text-decoration: none; border-radius: 5px; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>🔍 Diagnóstico da Tabela configuracoes_catalogo</h1>";

try {
    $db = getDB();
    
    // Verificar se a tabela existe
    echo "<h2>1. Verificar Existência da Tabela</h2>";
    $stmt = $db->query("SHOW TABLES LIKE 'configuracoes_catalogo'");
    $tabelaExiste = $stmt->fetch();
    
    if ($tabelaExiste) {
        echo "<div class='alert alert-success'>✅ Tabela 'configuracoes_catalogo' existe!</div>";
    } else {
        echo "<div class='alert alert-error'>❌ Tabela 'configuracoes_catalogo' NÃO existe! Execute o SQL de criação primeiro.</div>";
        echo "</div></body></html>";
        exit;
    }
    
    // Listar todas as colunas existentes
    echo "<h2>2. Colunas Existentes na Tabela</h2>";
    $stmt = $db->query("DESCRIBE configuracoes_catalogo");
    $colunasExistentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    
    $nomesColunasExistentes = [];
    foreach ($colunasExistentes as $coluna) {
        $nomesColunasExistentes[] = $coluna['Field'];
        echo "<tr>";
        echo "<td><strong>" . htmlspecialchars($coluna['Field']) . "</strong></td>";
        echo "<td>" . htmlspecialchars($coluna['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($coluna['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($coluna['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($coluna['Default'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($coluna['Extra']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<p><strong>Total de colunas:</strong> " . count($colunasExistentes) . "</p>";
    
    // Verificar colunas necessárias
    echo "<h2>3. Verificar Colunas Necessárias para Personalização</h2>";
    
    $colunasNecessarias = [
        // Logo
        'logo_url' => 'VARCHAR(500)',
        'logo_tamanho' => "ENUM('pequena', 'media', 'grande')",
        
        // Cores
        'cor_primaria' => 'VARCHAR(7)',
        'cor_secundaria' => 'VARCHAR(7)',
        'cor_destaque' => 'VARCHAR(7)',
        'cor_texto' => 'VARCHAR(7)',
        'cor_fundo' => 'VARCHAR(7)',
        
        // Textos
        'titulo_catalogo' => 'VARCHAR(100)',
        'subtitulo_catalogo' => 'VARCHAR(200)',
        'mensagem_boas_vindas' => 'TEXT',
        'rodape_texto' => 'VARCHAR(200)',
        
        // Redes Sociais
        'whatsapp_comercial' => 'VARCHAR(20)',
        'instagram_url' => 'VARCHAR(200)',
        'facebook_url' => 'VARCHAR(200)',
        
        // Configurações
        'mostrar_estoque' => 'BOOLEAN',
        'mostrar_codigo_produto' => 'BOOLEAN',
        'permitir_pedido_sem_estoque' => 'BOOLEAN',
        'produtos_por_linha' => "ENUM('2', '3', '4')",
        
        // Frete
        'valor_minimo_frete_gratis' => 'DECIMAL(10,2)',
        'mensagem_frete' => 'TEXT',
        'horario_atendimento' => 'TEXT',
        
        // Controle
        'mensagem_catalogo_inativo' => 'TEXT',
        'data_criacao' => 'TIMESTAMP',
        'data_atualizacao' => 'TIMESTAMP'
    ];
    
    echo "<table>";
    echo "<tr><th>Coluna Necessária</th><th>Tipo Esperado</th><th>Status</th></tr>";
    
    $colunasFaltando = [];
    $colunasOk = 0;
    
    foreach ($colunasNecessarias as $nomeColuna => $tipoColuna) {
        echo "<tr>";
        echo "<td><strong>" . htmlspecialchars($nomeColuna) . "</strong></td>";
        echo "<td><code>" . htmlspecialchars($tipoColuna) . "</code></td>";
        
        if (in_array($nomeColuna, $nomesColunasExistentes)) {
            echo "<td class='status-ok'>✅ Existe</td>";
            $colunasOk++;
        } else {
            echo "<td class='status-missing'>❌ Faltando</td>";
            $colunasFaltando[] = $nomeColuna;
        }
        
        echo "</tr>";
    }
    echo "</table>";
    
    // Resumo
    echo "<h2>4. Resumo</h2>";
    
    $totalNecessarias = count($colunasNecessarias);
    $percentual = round(($colunasOk / $totalNecessarias) * 100, 1);
    
    echo "<div style='font-size: 18px; margin: 20px 0;'>";
    echo "<p><strong>Colunas necessárias:</strong> $totalNecessarias</p>";
    echo "<p><strong>Colunas existentes:</strong> <span style='color: green;'>$colunasOk</span></p>";
    echo "<p><strong>Colunas faltando:</strong> <span style='color: red;'>" . count($colunasFaltando) . "</span></p>";
    echo "<p><strong>Completude:</strong> <span style='font-size: 24px; font-weight: bold; color: " . ($percentual == 100 ? 'green' : 'orange') . ";'>$percentual%</span></p>";
    echo "</div>";
    
    if (!empty($colunasFaltando)) {
        echo "<div class='alert alert-error'>";
        echo "<h3>❌ Ação Necessária!</h3>";
        echo "<p>Faltam " . count($colunasFaltando) . " colunas na tabela. Execute um dos SQLs de atualização:</p>";
        echo "<ul>";
        echo "<li><strong>atualizar_configuracoes_catalogo.sql</strong> (MySQL 8.0+)</li>";
        echo "<li><strong>atualizar_configuracoes_SEQUENCIAL.sql</strong> (MySQL 5.7+)</li>";
        echo "</ul>";
        echo "<p><strong>Colunas faltando:</strong></p>";
        echo "<pre>" . implode("\n", $colunasFaltando) . "</pre>";
        echo "</div>";
    } else {
        echo "<div class='alert alert-success'>";
        echo "<h3>✅ Tabela Completa!</h3>";
        echo "<p>Todas as colunas necessárias estão presentes. O sistema de personalização está pronto para uso!</p>";
        echo "</div>";
    }
    
    // SQL Gerado Automaticamente
    if (!empty($colunasFaltando)) {
        echo "<h2>5. SQL Para Adicionar Colunas Faltando</h2>";
        echo "<div class='alert alert-warning'>";
        echo "<p>Copie e execute este SQL no phpMyAdmin:</p>";
        echo "</div>";
        
        echo "<pre>";
        echo "-- Adicionar colunas faltando\n\n";
        
        foreach ($colunasFaltando as $coluna) {
            $tipo = $colunasNecessarias[$coluna];
            
            // Adicionar DEFAULT para alguns tipos
            $default = '';
            if (strpos($tipo, 'VARCHAR') !== false || strpos($tipo, 'ENUM') !== false) {
                if (strpos($coluna, 'cor_') === 0) {
                    $defaults = [
                        'cor_primaria' => '#8B5CF6',
                        'cor_secundaria' => '#EC4899',
                        'cor_destaque' => '#10B981',
                        'cor_texto' => '#1F2937',
                        'cor_fundo' => '#F9FAFB'
                    ];
                    if (isset($defaults[$coluna])) {
                        $default = " DEFAULT '" . $defaults[$coluna] . "'";
                    }
                } elseif ($coluna === 'logo_tamanho') {
                    $default = " DEFAULT 'media'";
                } elseif ($coluna === 'produtos_por_linha') {
                    $default = " DEFAULT '3'";
                }
            } elseif (strpos($tipo, 'BOOLEAN') !== false) {
                if (in_array($coluna, ['mostrar_estoque'])) {
                    $default = " DEFAULT TRUE";
                } else {
                    $default = " DEFAULT FALSE";
                }
            } elseif (strpos($tipo, 'TIMESTAMP') !== false) {
                if ($coluna === 'data_criacao') {
                    $default = " DEFAULT CURRENT_TIMESTAMP";
                } elseif ($coluna === 'data_atualizacao') {
                    $default = " DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";
                }
            }
            
            echo "ALTER TABLE configuracoes_catalogo ADD COLUMN $coluna $tipo$default;\n";
        }
        
        echo "</pre>";
    }
    
} catch (PDOException $e) {
    echo "<div class='alert alert-error'>";
    echo "<h3>❌ Erro de Conexão</h3>";
    echo "<p><strong>Mensagem:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}

echo "<hr>";
echo "<p><a href='configuracoes-catalogo.php' class='btn'>Ir para Configurações do Catálogo</a></p>";
echo "<p><a href='javascript:location.reload()' class='btn' style='background: #10b981;'>Recarregar Diagnóstico</a></p>";

echo "</div></body></html>";
?>
