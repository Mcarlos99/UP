<?php
session_start();

echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>Teste Completo do Carrinho</title>
    <style>
        body { font-family: Arial; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; }
        h1 { color: #8B5CF6; }
        .alert { padding: 15px; margin: 15px 0; border-radius: 5px; }
        .success { background: #d1fae5; color: #065f46; }
        .error { background: #fee2e2; color: #991b1b; }
        .warning { background: #fef3c7; color: #92400e; }
        pre { background: #f8f8f8; padding: 15px; border-radius: 5px; overflow-x: auto; }
        .btn { display: inline-block; padding: 10px 20px; margin: 5px; background: #8B5CF6; color: white; text-decoration: none; border-radius: 5px; border: none; cursor: pointer; }
        .btn-danger { background: #ef4444; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        th { background: #8B5CF6; color: white; }
    </style>
</head>
<body>
<div class='container'>";

// Ações
if (isset($_GET['limpar_tudo'])) {
    session_destroy();
    session_start();
    echo "<div class='alert success'>✅ Sessão completamente destruída e recriada!</div>";
}

if (isset($_GET['limpar_carrinho'])) {
    $_SESSION['carrinho'] = [];
    echo "<div class='alert success'>✅ Carrinho limpo!</div>";
}

if (isset($_GET['testar_add'])) {
    $_SESSION['carrinho'][] = [
        'produto_id' => 999,
        'nome' => 'Produto Teste',
        'preco' => 10.50,
        'quantidade' => 1,
        'estoque_max' => 100
    ];
    echo "<div class='alert success'>✅ Produto teste adicionado!</div>";
}

echo "<h1>🧪 Teste Completo do Carrinho</h1>";

// Status da Sessão
echo "<h2>📊 Status da Sessão</h2>";
echo "<table>";
echo "<tr><th>Item</th><th>Valor</th></tr>";
echo "<tr><td>Session ID</td><td>" . session_id() . "</td></tr>";
echo "<tr><td>Empresa ID</td><td>" . ($_SESSION['catalogo_empresa_id'] ?? '<span style=\"color:red\">NÃO DEFINIDA</span>') . "</td></tr>";
echo "<tr><td>Carrinho Existe?</td><td>" . (isset($_SESSION['carrinho']) ? '✅ Sim' : '❌ Não') . "</td></tr>";
echo "<tr><td>Total de Itens</td><td>" . (isset($_SESSION['carrinho']) ? count($_SESSION['carrinho']) : 0) . "</td></tr>";
echo "</table>";

// Analisar cada item
if (!empty($_SESSION['carrinho'])) {
    echo "<h2>🛒 Análise dos Itens no Carrinho</h2>";
    
    foreach ($_SESSION['carrinho'] as $index => $item) {
        $temProdutoId = isset($item['produto_id']) && !empty($item['produto_id']) && $item['produto_id'] > 0;
        $classe = $temProdutoId ? 'success' : 'error';
        
        echo "<div class='alert $classe'>";
        echo "<h3>Item #" . ($index + 1) . " - " . ($temProdutoId ? '✅ VÁLIDO' : '❌ INVÁLIDO') . "</h3>";
        
        echo "<table>";
        echo "<tr><th>Campo</th><th>Valor</th><th>Tipo</th><th>Status</th></tr>";
        
        // Verificar produto_id especificamente
        if (isset($item['produto_id'])) {
            $tipo = gettype($item['produto_id']);
            $valor = var_export($item['produto_id'], true);
            $status = ($item['produto_id'] > 0) ? '✅ OK' : '❌ ZERO/NULL';
            echo "<tr style='background: " . (($item['produto_id'] > 0) ? '#d1fae5' : '#fee2e2') . "'>";
            echo "<td><strong>produto_id</strong></td>";
            echo "<td>$valor</td>";
            echo "<td>$tipo</td>";
            echo "<td>$status</td>";
            echo "</tr>";
        } else {
            echo "<tr style='background: #fee2e2'>";
            echo "<td><strong>produto_id</strong></td>";
            echo "<td colspan='3'>❌ CAMPO NÃO EXISTE!</td>";
            echo "</tr>";
        }
        
        // Outros campos
        foreach ($item as $key => $value) {
            if ($key === 'produto_id') continue;
            $tipo = gettype($value);
            $valor = is_string($value) ? htmlspecialchars($value) : var_export($value, true);
            echo "<tr>";
            echo "<td>$key</td>";
            echo "<td>$valor</td>";
            echo "<td>$tipo</td>";
            echo "<td>✅</td>";
            echo "</tr>";
        }
        
        echo "</table>";
        echo "</div>";
    }
    
    // Diagnóstico Final
    echo "<h2>🔍 Diagnóstico</h2>";
    $todosValidos = true;
    foreach ($_SESSION['carrinho'] as $item) {
        if (!isset($item['produto_id']) || empty($item['produto_id']) || $item['produto_id'] <= 0) {
            $todosValidos = false;
            break;
        }
    }
    
    if ($todosValidos) {
        echo "<div class='alert success'>";
        echo "<h3>✅ CARRINHO VÁLIDO!</h3>";
        echo "<p>Todos os itens possuem produto_id válido. Você pode prosseguir com o pedido.</p>";
        echo "</div>";
    } else {
        echo "<div class='alert error'>";
        echo "<h3>❌ CARRINHO INVÁLIDO!</h3>";
        echo "<p>Um ou mais itens NÃO possuem produto_id válido. Você precisa limpar o carrinho.</p>";
        echo "<p><strong>SOLUÇÃO:</strong> Clique em 'Limpar Carrinho' abaixo e adicione os produtos novamente pelo catálogo.</p>";
        echo "</div>";
    }
    
} else {
    echo "<div class='alert warning'>";
    echo "<h3>⚠️ Carrinho Vazio</h3>";
    echo "<p>Não há itens no carrinho para analisar.</p>";
    echo "</div>";
}

// Sessão Completa (Debug)
echo "<h2>🔧 Dados Completos da Sessão (Raw)</h2>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

// Botões de Ação
echo "<h2>⚙️ Ações</h2>";
echo "<a href='?limpar_carrinho=1' class='btn btn-danger'>🗑️ Limpar Apenas Carrinho</a>";
echo "<a href='?limpar_tudo=1' class='btn btn-danger'>💥 Destruir Sessão Completa</a>";
echo "<a href='?testar_add=1' class='btn'>➕ Adicionar Produto Teste</a>";
echo "<a href='catalogo.php?empresa=5' class='btn'>🏪 Ir ao Catálogo</a>";
echo "<a href='carrinho.php?empresa=5' class='btn'>🛒 Ver Carrinho</a>";
echo "<a href='javascript:location.reload()' class='btn'>🔄 Recarregar</a>";

// Teste de Adicionar via JavaScript
echo "<h2>🧪 Teste de Adicionar Produto via API</h2>";
echo "<button onclick='testarAPI()' class='btn'>🧪 Testar API carrinho-add.php</button>";
echo "<div id='resultado-api' style='margin-top: 10px;'></div>";

echo "<script>
function testarAPI() {
    const resultado = document.getElementById('resultado-api');
    resultado.innerHTML = '<p style=\"color: blue;\">⏳ Testando...</p>';
    
    fetch('api/carrinho-add.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            produto_id: 888,
            nome: 'Produto Teste API',
            preco: 25.90,
            estoque: 50,
            quantidade: 1
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            resultado.innerHTML = '<div class=\"alert success\">✅ API funcionou! Recarregue a página para ver o resultado.</div>';
        } else {
            resultado.innerHTML = '<div class=\"alert error\">❌ Erro: ' + data.message + '</div>';
        }
    })
    .catch(error => {
        resultado.innerHTML = '<div class=\"alert error\">❌ Erro na requisição: ' + error + '</div>';
    });
}
</script>";

echo "</div></body></html>";
?>
