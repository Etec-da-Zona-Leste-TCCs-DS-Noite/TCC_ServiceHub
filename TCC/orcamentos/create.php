<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

$clientes = $pdo->query("SELECT * FROM clientes ORDER BY nome")->fetchAll();
$servicos = $pdo->query("SELECT * FROM servicos WHERE status = 1 ORDER BY nome")->fetchAll();

$erros = [];
$cliente_id = '';
$data_orcamento = date('Y-m-d');
$data_validade = date('Y-m-d', strtotime('+30 days'));
$observacoes = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cliente_id = $_POST['cliente_id'] ?? '';
    $data_orcamento = $_POST['data_orcamento'] ?? date('Y-m-d');
    $data_validade = $_POST['data_validade'] ?? '';
    $observacoes = cleanInput($_POST['observacoes'] ?? '');
    $servicos_ids = $_POST['servicos'] ?? [];
    $quantidades = $_POST['quantidades'] ?? [];

    if (empty($cliente_id)) $erros['cliente'] = 'Selecione um cliente.';
    if (empty($servicos_ids)) $erros['servicos'] = 'Adicione pelo menos um serviço.';

    if (empty($erros)) {
        try {
            $pdo->beginTransaction();
            
            // Calcular total
            $valor_total = 0;
            foreach ($servicos_ids as $index => $serv_id) {
                // Buscar o serviço pelo ID
                $servStmt = $pdo->prepare("SELECT * FROM servicos WHERE id = ?");
                $servStmt->execute([$serv_id]);
                $serv = $servStmt->fetch();
                
                if ($serv) {
                    $quantidade = $quantidades[$index] ?? 1;
                    $subtotal = $serv['valor'] * $quantidade;
                    $valor_total += $subtotal;
                }
            }
            
            // Inserir orçamento
            $stmt = $pdo->prepare("
                INSERT INTO orcamentos (cliente_id, data_orcamento, data_validade, valor_total, observacoes, status) 
                VALUES (?, ?, ?, ?, ?, 'pendente')
            ");
            $stmt->execute([$cliente_id, $data_orcamento, $data_validade, $valor_total, $observacoes]);
            $orcamento_id = $pdo->lastInsertId();
            
            // Inserir itens
            $itemStmt = $pdo->prepare("
                INSERT INTO orcamento_itens (orcamento_id, servico_id, quantidade, valor_unitario, subtotal) 
                VALUES (?, ?, ?, ?, ?)
            ");
            
            foreach ($servicos_ids as $index => $serv_id) {
                $servStmt = $pdo->prepare("SELECT * FROM servicos WHERE id = ?");
                $servStmt->execute([$serv_id]);
                $serv = $servStmt->fetch();
                
                if ($serv) {
                    $quantidade = $quantidades[$index] ?? 1;
                    $subtotal = $serv['valor'] * $quantidade;
                    $itemStmt->execute([$orcamento_id, $serv_id, $quantidade, $serv['valor'], $subtotal]);
                }
            }
            
            $pdo->commit();
            header('Location: index.php?msg=' . urlencode('Orçamento criado com sucesso!') . '&type=success');
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $erros['geral'] = 'Erro ao criar orçamento: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Novo Orçamento - ServiceHub</title>
    <link rel="stylesheet" href="../css/estilo.css">
    <style>
        .item-row {
            background: #f9f9f9;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
            position: relative;
        }
        .item-row select, .item-row input {
            margin-right: 10px;
        }
        .remove-item {
            color: #dc3545;
            cursor: pointer;
            font-size: 20px;
            position: absolute;
            right: 15px;
            top: 15px;
            transition: color 0.3s;
        }
        .remove-item:hover {
            color: #c82333;
        }
        .btn-add {
            margin-top: 10px;
            background: #28a745;
        }
        .btn-add:hover {
            background: #218838;
        }
        .servico-info {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    
<header class="main-header">
    <div class="header-content">
        <div class="logo">
            <h1>ServiceHub</h1>
            <p>Gestão de Serviços e Orçamentos</p>
        </div>
        <nav class="main-nav">
            <ul>
                <li><a href="../index.php">Início</a></li>
                <li><a href="../servicos/index.php">Serviços</a></li>
                <li><a href="../clientes/index.php">Clientes</a></li>
                <li><a href="../orcamentos/index.php">Orçamentos</a></li>
                <li><a href="../relatorios/index.php">Relatórios</a></li>
            </ul>
        </nav>
    </div>
</header>
    <div class="container">
        <h1>💰 Novo Orçamento</h1>
        <div class="text-right mb-3">
            <a href="index.php" class="btn">← Voltar</a>
            <a href="../clientes/create.php" class="btn">➕ Novo Cliente</a>
        </div>

        <?php if (!empty($erros['geral'])) echo showMessage($erros['geral'], 'error'); ?>

        <form method="post" id="orcamentoForm">
            <div class="form-group">
                <label for="cliente_id">Cliente *</label>
                <select id="cliente_id" name="cliente_id" class="form-control" required>
                    <option value="">Selecione um cliente</option>
                    <?php foreach ($clientes as $cli): ?>
                        <option value="<?= $cli['id'] ?>" <?= $cli['id'] == $cliente_id ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cli['nome']) ?> - <?= htmlspecialchars($cli['telefone'] ?: 'Sem telefone') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($erros['cliente'])): ?>
                    <small style="color:red;"><?= $erros['cliente'] ?></small>
                <?php endif; ?>
            </div>
            
            <div class="form-row" style="display: flex; gap: 20px; margin-bottom: 15px;">
                <div class="form-group" style="flex: 1;">
                    <label for="data_orcamento">Data do Orçamento</label>
                    <input type="date" id="data_orcamento" name="data_orcamento" class="form-control" value="<?= $data_orcamento ?>">
                </div>
                
                <div class="form-group" style="flex: 1;">
                    <label for="data_validade">Data de Validade</label>
                    <input type="date" id="data_validade" name="data_validade" class="form-control" value="<?= $data_validade ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label>Serviços *</label>
                <div id="items-container">
                    <div class="item-row">
                        <select name="servicos[]" class="form-control servico-select" style="width: 60%; display: inline-block;" required>
                            <option value="">Selecione um serviço</option>
                            <?php foreach ($servicos as $serv): ?>
                                <option value="<?= $serv['id'] ?>" data-valor="<?= $serv['valor'] ?>" data-nome="<?= htmlspecialchars($serv['nome']) ?>">
                                    <?= htmlspecialchars($serv['nome']) ?> - R$ <?= number_format($serv['valor'], 2, ',', '.') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="number" name="quantidades[]" placeholder="Quantidade" class="form-control" style="width: 25%; display: inline-block;" value="1" min="1">
                        <span class="remove-item" onclick="removeItem(this)">🗑️</span>
                        <div class="servico-info"></div>
                    </div>
                </div>
                <button type="button" class="btn btn-add" onclick="addItem()">+ Adicionar Serviço</button>
                <?php if (isset($erros['servicos'])): ?>
                    <small style="color:red; display: block; margin-top: 5px;"><?= $erros['servicos'] ?></small>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label for="observacoes">Observações</label>
                <textarea id="observacoes" name="observacoes" class="form-control" rows="4" placeholder="Informações adicionais sobre o orçamento..."><?= htmlspecialchars($observacoes) ?></textarea>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn" style="background: #28a745; font-size: 16px; padding: 12px 24px;">✅ Criar Orçamento</button>
                <button type="reset" class="btn btn-warning" onclick="resetForm()">⟳ Limpar</button>
            </div>
        </form>
        
        <div class="resumo" style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
            <h3>Resumo do Orçamento</h3>
            <p><strong>Total:</strong> <span id="totalPreview">R$ 0,00</span></p>
        </div>
    </div>
    
    <script>
        function addItem() {
            const container = document.getElementById('items-container');
            const newItem = document.createElement('div');
            newItem.className = 'item-row';
            newItem.innerHTML = `
                <select name="servicos[]" class="form-control servico-select" style="width: 60%; display: inline-block;" required onchange="updateTotal()">
                    <option value="">Selecione um serviço</option>
                    <?php foreach ($servicos as $serv): ?>
                        <option value="<?= $serv['id'] ?>" data-valor="<?= $serv['valor'] ?>" data-nome="<?= htmlspecialchars($serv['nome']) ?>">
                            <?= htmlspecialchars($serv['nome']) ?> - R$ <?= number_format($serv['valor'], 2, ',', '.') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="number" name="quantidades[]" placeholder="Quantidade" class="form-control" style="width: 25%; display: inline-block;" value="1" min="1" onchange="updateTotal()" onkeyup="updateTotal()">
                <span class="remove-item" onclick="removeItem(this)">🗑️</span>
                <div class="servico-info"></div>
            `;
            container.appendChild(newItem);
            updateTotal();
        }
        
        function removeItem(element) {
            if (document.querySelectorAll('.item-row').length > 1) {
                element.parentElement.remove();
                updateTotal();
            } else {
                alert('Adicione pelo menos um serviço!');
            }
        }
        
        function updateTotal() {
            let total = 0;
            const rows = document.querySelectorAll('.item-row');
            
            rows.forEach(row => {
                const select = row.querySelector('.servico-select');
                const quantidade = row.querySelector('input[name="quantidades[]"]');
                
                if (select && select.value && quantidade) {
                    const valor = parseFloat(select.options[select.selectedIndex]?.dataset.valor || 0);
                    const qtd = parseInt(quantidade.value) || 0;
                    total += valor * qtd;
                }
            });
            
            document.getElementById('totalPreview').innerHTML = 'R$ ' + total.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
        
        function resetForm() {
            if (confirm('Tem certeza que deseja limpar o formulário?')) {
                document.getElementById('orcamentoForm').reset();
                const container = document.getElementById('items-container');
                while (container.children.length > 1) {
                    container.removeChild(container.lastChild);
                }
                updateTotal();
            }
        }
        
        // Atualizar total quando mudar seleção ou quantidade
        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('servico-select') || e.target.name === 'quantidades[]') {
                updateTotal();
            }
        });
        
        document.addEventListener('keyup', function(e) {
            if (e.target.name === 'quantidades[]') {
                updateTotal();
            }
        });
        
        // Inicializar total
        updateTotal();
    </script>
</body>
</html>