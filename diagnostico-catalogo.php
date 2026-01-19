<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'config.php';
require_once 'config_multitenant.php';

$empresaId = isset($_GET['empresa']) ? (int)$_GET['empresa'] : 5;

echo "<h1>Diagnóstico do Catálogo - Empresa ID: {$empresaId}</h1>";
echo "<hr>";

try {
    $db = getDB();
    
    // 1. Verificar se a empresa existe
    echo "<h2>1. Verificando Empresa</h2>";
    $stmt = $db->prepare("SELECT id, nome, email, ativo, nivel_acesso FROM usuarios WHERE id = ?");
    $stmt->execute([$empresaId]);
    $empresa = $stmt->fetch();
    
    if ($empresa) {
        echo "<pre>";
        print_r($empresa);
        echo "</pre>";
    } else {
        echo "<p style='color: red;'>❌ Empresa não encontrada!</p>";
        exit;
    }
    
    // 2. Verificar total de produtos da empresa
    echo "<h2>2. Total de Produtos da Empresa</h2>";
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM produtos WHERE empresa_id = ?");
    $stmt->execute([$empresaId]);
    $totalGeral = $stmt->fetch()['total'];
    echo "<p>Total de produtos cadastrados: <strong>{$totalGeral}</strong></p>";
    
    // 3. Verificar produtos ativos
    echo "<h2>3. Produtos Ativos</h2>";
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM produtos WHERE empresa_id = ? AND ativo = 1");
    $stmt->execute([$empresaId]);
    $totalAtivos = $stmt->fetch()['total'];
    echo "<p>Produtos ativos: <strong>{$totalAtivos}</strong></p>";
    
    // 4. Verificar produtos com estoque
    echo "<h2>4. Produtos com Estoque</h2>";
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM produtos WHERE empresa_id = ? AND ativo = 1 AND estoque_atual > 0");
    $stmt->execute([$empresaId]);
    $totalComEstoque = $stmt->fetch()['total'];
    echo "<p>Produtos ativos COM estoque: <strong>{$totalComEstoque}</strong></p>";
    
    // 5. Listar todos os produtos da empresa
    echo "<h2>5. Lista Detalhada de Produtos</h2>";
    $stmt = $db->prepare("
        SELECT 
            p.id,
            p.nome,
            p.ativo,
            p.estoque_atual,
            p.preco_venda,
            c.nome as categoria_nome,
            p.empresa_id
        FROM produtos p
        LEFT JOIN categorias c ON p.categoria_id = c.id
        WHERE p.empresa_id = ?
        ORDER BY p.id DESC
        LIMIT 20
    ");
    $stmt->execute([$empresaId]);
    $produtos = $stmt->fetchAll();
    
    if (!empty($produtos)) {
        echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background: #f0f0f0;'>
                <th>ID</th>
                <th>Nome</th>
                <th>Categoria</th>
                <th>Ativo?</th>
                <th>Estoque</th>
                <th>Preço</th>
                <th>Empresa ID</th>
                <th>Aparece no Catálogo?</th>
              </tr>";
        
        foreach ($produtos as $p) {
            $apareceNoCatalogo = ($p['ativo'] == 1 && $p['estoque_atual'] > 0) ? 
                "✅ SIM" : "❌ NÃO";
            
            $motivoNaoAparece = [];
            if ($p['ativo'] != 1) $motivoNaoAparece[] = "Produto Inativo";
            if ($p['estoque_atual'] <= 0) $motivoNaoAparece[] = "Sem Estoque";
            
            $motivo = !empty($motivoNaoAparece) ? " (" . implode(", ", $motivoNaoAparece) . ")" : "";
            
            echo "<tr>";
            echo "<td>{$p['id']}</td>";
            echo "<td>{$p['nome']}</td>";
            echo "<td>{$p['categoria_nome']}</td>";
            echo "<td>" . ($p['ativo'] ? "Sim" : "Não") . "</td>";
            echo "<td>{$p['estoque_atual']}</td>";
            echo "<td>R$ " . number_format($p['preco_venda'], 2, ',', '.') . "</td>";
            echo "<td>{$p['empresa_id']}</td>";
            echo "<td style='font-weight: bold;'>{$apareceNoCatalogo}{$motivo}</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        echo "<p style='color: red;'>❌ Nenhum produto encontrado para esta empresa!</p>";
    }
    
    // 6. Verificar categorias
    echo "<h2>6. Categorias da Empresa</h2>";
    $stmt = $db->prepare("SELECT id, nome, ativo FROM categorias WHERE empresa_id = ?");
    $stmt->execute([$empresaId]);
    $categorias = $stmt->fetchAll();
    
    if (!empty($categorias)) {
        echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
        echo "<tr style='background: #f0f0f0;'><th>ID</th><th>Nome</th><th>Ativa?</th></tr>";
        foreach ($categorias as $cat) {
            echo "<tr>";
            echo "<td>{$cat['id']}</td>";
            echo "<td>{$cat['nome']}</td>";
            echo "<td>" . ($cat['ativo'] ? "Sim" : "Não") . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>Nenhuma categoria encontrada</p>";
    }
    
    // 7. Query usada no catálogo
    echo "<h2>7. Query Usada no Catálogo (Simulação)</h2>";
    $sql = "SELECT p.*, c.nome as categoria_nome, c.icone as categoria_icone, c.cor as categoria_cor
            FROM produtos p 
            LEFT JOIN categorias c ON p.categoria_id = c.id 
            WHERE p.ativo = 1 AND p.empresa_id = ? AND p.estoque_atual > 0
            ORDER BY p.destaque DESC, p.nome ASC";
    
    echo "<pre style='background: #f0f0f0; padding: 10px;'>";
    echo htmlspecialchars($sql);
    echo "\n\nParâmetros: empresa_id = {$empresaId}";
    echo "</pre>";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$empresaId]);
    $produtosCatalogo = $stmt->fetchAll();
    
    echo "<p>Produtos que aparecem no catálogo: <strong style='font-size: 20px; color: green;'>" . count($produtosCatalogo) . "</strong></p>";
    
    // 8. Resumo
    echo "<hr>";
    echo "<h2>📊 RESUMO</h2>";
    echo "<ul style='font-size: 16px;'>";
    echo "<li>Total de produtos: <strong>{$totalGeral}</strong></li>";
    echo "<li>Produtos ativos: <strong>{$totalAtivos}</strong></li>";
    echo "<li>Produtos com estoque: <strong>{$totalComEstoque}</strong></li>";
    echo "<li><strong style='color: green; font-size: 18px;'>Produtos no catálogo: {$totalComEstoque}</strong></li>";
    echo "</ul>";
    
    if ($totalComEstoque == 0) {
        echo "<div style='background: #ffebee; border-left: 4px solid #f44336; padding: 15px; margin-top: 20px;'>";
        echo "<h3 style='color: #c62828; margin-top: 0;'>⚠️ PROBLEMA IDENTIFICADO</h3>";
        echo "<p><strong>Nenhum produto está aparecendo no catálogo!</strong></p>";
        echo "<p>Para um produto aparecer no catálogo, ele precisa atender TODOS estes critérios:</p>";
        echo "<ol>";
        echo "<li>✅ Produto deve estar <strong>ATIVO</strong> (campo 'ativo' = 1)</li>";
        echo "<li>✅ Produto deve ter <strong>ESTOQUE MAIOR QUE ZERO</strong> (campo 'estoque_atual' > 0)</li>";
        echo "<li>✅ Produto deve pertencer à empresa correta (empresa_id = {$empresaId})</li>";
        echo "</ol>";
        
        echo "<h4>🔧 SOLUÇÕES:</h4>";
        echo "<ul>";
        echo "<li>Acesse o painel administrativo</li>";
        echo "<li>Vá em <strong>Produtos</strong></li>";
        echo "<li>Edite os produtos e certifique-se que:</li>";
        echo "<ul>";
        echo "<li>✅ Checkbox 'Ativo' está marcado</li>";
        echo "<li>✅ Campo 'Estoque Atual' está com valor maior que 0</li>";
        echo "</ul>";
        echo "</ul>";
        echo "</div>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Erro ao conectar ao banco: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='catalogo.php?empresa={$empresaId}' style='padding: 10px 20px; background: #4CAF50; color: white; text-decoration: none; border-radius: 5px;'>Ver Catálogo</a></p>";
?>

<style>
body {
    font-family: Arial, sans-serif;
    margin: 20px;
    background: #f5f5f5;
}
h1 {
    color: #333;
    border-bottom: 3px solid #4CAF50;
    padding-bottom: 10px;
}
h2 {
    color: #666;
    margin-top: 30px;
    border-left: 4px solid #2196F3;
    padding-left: 10px;
}
table {
    background: white;
    margin: 15px 0;
}
</style>
