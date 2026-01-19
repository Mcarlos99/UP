<?php
session_start();

echo "<!DOCTYPE html>
<html lang='pt-BR'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Debug do Carrinho</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background: #f5f5f5;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #8B5CF6;
            padding-bottom: 10px;
        }
        h2 {
            color: #666;
            margin-top: 30px;
        }
        pre {
            background: #f8f8f8;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            border-left: 4px solid #8B5CF6;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            margin: 10px 5px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            transition: all 0.3s;
        }
        .btn-danger {
            background: #ef4444;
            color: white;
        }
        .btn-danger:hover {
            background: #dc2626;
        }
        .btn-primary {
            background: #8B5CF6;
            color: white;
        }
        .btn-primary:hover {
            background: #7C3AED;
        }
        .alert {
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
            font-weight: bold;
        }
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }
        .alert-warning {
            background: #fef3c7;
            color: #92400e;
            border-left: 4px solid #f59e0b;
        }
        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }
        .item {
            background: #f9fafb;
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
            border: 2px solid #e5e7eb;
        }
        .item.valid {
            border-color: #10b981;
            background: #ecfdf5;
        }
        .item.invalid {
            border-color: #ef4444;
            background: #fef2f2;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        th {
            background: #8B5CF6;
            color: white;
        }
        tr:hover {
            background: #f9fafb;
        }
    </style>
</head>
<body>
    <div class='container'>";

echo "<h1>🔍 Debug do Carrinho - PapelOn</h1>";

// Processar ações
if (isset($_GET['limpar'])) {
    $_SESSION['carrinho'] = [];
    $_SESSION['catalogo_empresa_id'] = null;
    echo "<div class='alert alert-success'>✅ Carrinho limpo com sucesso!</div>";
}

// Informações da sessão
echo "<h2>📊 Informações da Sessão</h2>";
echo "<table>";
echo "<tr><th>Chave</th><th>Valor</th></tr>";
echo "<tr><td>Session ID</td><td>" . session_id() . "</td></tr>";
echo "<tr><td>Empresa ID</td><td>" . ($_SESSION['catalogo_empresa_id'] ?? 'Não definida') . "</td></tr>";
echo "<tr><td>Total de itens</td><td>" . (isset($_SESSION['carrinho']) ? count($_SESSION['carrinho']) : 0) . "</td></tr>";
echo "</table>";

// Verificar carrinho
if (empty($_SESSION['carrinho'])) {
    echo "<div class='alert alert-warning'>
        ⚠️ O carrinho está vazio!<br>
        <a href='catalogo.php?empresa=5' class='btn btn-primary' style='margin-top: 10px;'>
            <i class='fas fa-store'></i> Ir ao Catálogo
        </a>
    </div>";
} else {
    echo "<h2>🛒 Itens no Carrinho</h2>";
    echo "<p>Total de itens: <strong>" . count($_SESSION['carrinho']) . "</strong></p>";
    
    $totalValor = 0;
    $temErro = false;
    
    foreach ($_SESSION['carrinho'] as $index => $item) {
        $valido = isset($item['produto_id']) && !empty($item['produto_id']);
        $classe = $valido ? 'valid' : 'invalid';
        $icone = $valido ? '✅' : '❌';
        
        if (!$valido) $temErro = true;
        
        echo "<div class='item $classe'>";
        echo "<h3>$icone Item #" . ($index + 1) . "</h3>";
        echo "<table>";
        
        foreach ($item as $key => $value) {
            $destaque = ($key === 'produto_id') ? 'style="background: #fef3c7;"' : '';
            echo "<tr $destaque>";
            echo "<td><strong>$key</strong></td>";
            echo "<td>" . htmlspecialchars(print_r($value, true)) . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
        
        if (!$valido) {
            echo "<div class='alert alert-danger'>❌ ERRO: Este item NÃO possui 'produto_id' e causará erro ao finalizar o pedido!</div>";
        }
        
        if (isset($item['preco']) && isset($item['quantidade'])) {
            $subtotal = $item['preco'] * $item['quantidade'];
            $totalValor += $subtotal;
            echo "<p><strong>Subtotal:</strong> R$ " . number_format($subtotal, 2, ',', '.') . "</p>";
        }
        
        echo "</div>";
    }
    
    echo "<h2>💰 Valor Total do Carrinho</h2>";
    echo "<p style='font-size: 24px; font-weight: bold; color: #8B5CF6;'>R$ " . number_format($totalValor, 2, ',', '.') . "</p>";
    
    if ($temErro) {
        echo "<div class='alert alert-danger'>
            <strong>⚠️ ATENÇÃO: Há itens inválidos no carrinho!</strong><br><br>
            Você precisa LIMPAR o carrinho antes de fazer um novo pedido, 
            pois os itens antigos não possuem todas as informações necessárias.
        </div>";
    } else {
        echo "<div class='alert alert-success'>
            ✅ Todos os itens estão válidos! Você pode prosseguir com o pedido.
        </div>";
    }
}

// Dados brutos da sessão
echo "<h2>🔧 Dados Brutos da Sessão (Debug Técnico)</h2>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

// Botões de ação
echo "<h2>⚙️ Ações</h2>";
echo "<a href='?limpar=1' class='btn btn-danger' onclick='return confirm(\"Tem certeza que deseja limpar o carrinho?\")'>
    🗑️ Limpar Carrinho
</a>";
echo "<a href='catalogo.php?empresa=5' class='btn btn-primary'>
    🏪 Ir ao Catálogo
</a>";
echo "<a href='carrinho.php?empresa=5' class='btn btn-primary'>
    🛒 Ver Carrinho
</a>";
echo "<a href='javascript:location.reload()' class='btn btn-primary' style='background: #10b981;'>
    🔄 Recarregar Página
</a>";

echo "</div>
</body>
</html>";
?>