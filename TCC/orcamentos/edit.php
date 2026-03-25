<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

$id = $_GET['id'] ?? 0;
if (!$id) {
    header('Location: index.php');
    exit;
}

// Buscar orçamento
$stmt = $pdo->prepare("SELECT * FROM orcamentos WHERE id = ?");
$stmt->execute([$id]);
$orcamento = $stmt->fetch();

if (!$orcamento) {
    header('Location: index.php?msg=' . urlencode('Orçamento não encontrado') . '&type=error');
    exit;
}

$clientes = $pdo->query("SELECT * FROM clientes ORDER BY nome")->fetchAll();
$servicos = $pdo->query("SELECT * FROM servicos WHERE status = 1 ORDER BY nome")->fetchAll();

// Buscar itens atuais
$itensStmt = $pdo->prepare("
    SELECT oi.*, s.nome as servico_nome 
    FROM orcamento_itens oi
    JOIN servicos s ON s.id = oi.servico_id
    WHERE oi.orcamento_id = ?
");
$itensStmt->execute([$id]);
$itensAtuais = $itensStmt->fetchAll();

$erros = [];
$cliente_id = $orcamento['cliente_id'];
$data_orcamento = $orcamento['data_orcamento'];
$data_validade = $orcamento['data_validade'];
$observacoes = $orcamento['observacoes'];
$status = $orcamento['status'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cliente_id = $_POST['cliente_id'] ?? '';
    $data_orcamento = $_POST['data_orcamento'] ?? date('Y-m-d');
    $data_validade = $_POST['data_validade'] ?? '';
    $observacoes = cleanInput($_POST['observacoes'] ?? '');
    $status = $_POST['status'] ?? 'pendente';
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
                $serv = array_filter($servicos, function($s) use ($serv_id) {
                    return $s['id'] == $serv_id;
                });
                $serv = reset($serv);
                $quantidade = $quantidades[$index] ?? 1;
                $subtotal = $serv['valor'] * $quantidade;
                $valor_total += $subtotal;
            }
            
            // Atualizar orçamento
            $update = $pdo->prepare("UPDATE orcamentos SET cliente_id = ?, data_orcamento = ?, data_validade = ?, valor_total = ?, observacoes = ?, status = ? WHERE id = ?");
            $update->execute([$cliente_id, $data_orcamento, $data_validade, $valor_total, $observacoes, $status, $id]);
            
            // Remover itens antigos
            $pdo->prepare("DELETE FROM orcamento_itens WHERE orcamento_id = ?")->execute([$id]);
            
            // Inserir novos itens
            $itemStmt = $pdo->prepare("INSERT INTO orcamento_itens (orcamento_id, servico_id, quantidade, valor_unitario, subtotal) VALUES (?, ?, ?, ?, ?)");
            
            foreach ($servicos_ids as $index => $serv_id) {
                $serv = array_filter($servicos, function($s) use ($serv_id) {
                    return $s['id'] == $serv_id;
                });
                $serv = reset($serv);
                $quantidade = $quantidades[$index] ?? 1;
                $subtotal = $serv['valor'] * $quantidade;
                
                $itemStmt->execute([$id, $serv_id, $quantidade, $serv['valor'], $subtotal]);
            }
            
            $pdo->commit();
            header('Location: index.php?msg=' . urlencode('Orçamento atualizado com sucesso!') . '&type=success');
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $erros['geral'] = 'Erro ao atualizar orçamento: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Editar Orçamento</title>
    <link rel="stylesheet" href="../css/estilo.css">
    <style>
        .item-row {
            background: #f9f9f9;
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 5px;
        }
        .remove-item {
            color: red;
            cursor: pointer;
            margin-left: 10px;
            font-size: 18px;
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
        <h1>Editar Orçamento #<?= $id ?></h1>
        <a href="index.php" class="btn">Voltar</a>

        <?php if (!empty($erros['geral'])) echo showMessage($erros['geral'], 'error'); ?>

        <form method="post">
            <div class="form-group">
                <label for="cliente_id">Cliente *</label>
                <select id="cliente_id" name="cliente_id" class="form-control">
                    <option value="">Selecione um cliente</option>
                    <?php foreach ($clientes as $cli): ?>
                        <option value="<?= $cli['id'] ?>" <?= $cli['id'] == $cliente_id ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cli['nome']) ?> - <?= htmlspecialchars($cli['telefone']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($erros['cliente'])): ?>
                    <small style="color:red;"><?= $erros['cliente'] ?></small>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label for="data_orcamento">Data do Orçamento</label>
                <input type="date" id="data_orcamento" name="data_orcamento" class="form-control" value="<?= $data_orcamento ?>">
            </div>
            
            <div class="form-group">
                <label for="data_validade">Data de Validade</label>
                <input type="date" id="data_validade" name="data_validade" class="form-control" value="<?= $data_validade ?>">
            </div>
            
            <div class="form-group">
                <label for="status">Status</label>
                <select id="status" name="status" class="form-control">
                    <option value="pendente" <?= $status == 'pendente' ? 'selected' : '' ?>>Pendente</option>
                    <option value="aprovado" <?= $status == 'aprovado' ? 'selected' : '' ?>>Aprovado</option>
                    <option value="rejeitado" <?= $status == 'rejeitado' ? 'selected' : '' ?>>Rejeitado</option>
                    <option value="expirado" <?= $status == 'expirado' ? 'selected' : '' ?>>Expirado</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Serviços *</label>
                <div id="items-container">
                    <?php if (!empty($itensAtuais)): ?>
                        <?php foreach ($itensAtuais as $item): ?>
                            <div class="item-row">
                                <select name="servicos[]" class="form-control" style="width: 70%; display: inline-block;" required>
                                    <option value="">Selecione um serviço</option>
                                    <?php foreach ($servicos as $serv): ?>
                                        <option value="<?= $serv['id'] ?>" <?= $serv['id'] == $item['servico_id'] ? 'selected' : '' ?> data-valor="<?= $serv['valor'] ?>">
                                            <?= htmlspecialchars($serv['nome']) ?> - R$ <?= number_format($serv['valor'], 2, ',', '.') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="number" name="quantidades[]" class="form-control" style="width: 20%; display: inline-block;" value="<?= $item['quantidade'] ?>" min="1">
                                <span class="remove-item" onclick="removeItem(this)">❌</span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="item-row">
                            <select name="servicos[]" class="form-control" style="width: 70%; display: inline-block;" required>
                                <option value="">Selecione um serviço</option>
                                <?php foreach ($servicos as $serv): ?>
                                    <option value="<?= $serv['id'] ?>" data-valor="<?= $serv['valor'] ?>">
                                        <?= htmlspecialchars($serv['nome']) ?> - R$ <?= number_format($serv['valor'], 2, ',', '.') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="number" name="quantidades[]" placeholder="Qtd" class="form-control" style="width: 20%; display: inline-block;" value="1" min="1">
                            <span class="remove-item" onclick="removeItem(this)">❌</span>
                        </div>
                    <?php endif; ?>
                </div>
                <button type="button" class="btn" onclick="addItem()">+ Adicionar Serviço</button>
                <?php if (isset($erros['servicos'])): ?>
                    <small style="color:red; display: block; margin-top: 5px;"><?= $erros['servicos'] ?></small>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label for="observacoes">Observações</label>
                <textarea id="observacoes" name="observacoes" class="form-control" rows="3"><?= htmlspecialchars($observacoes) ?></textarea>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn">Atualizar Orçamento</button>
            </div>
        </form>
    </div>
    
    <script>
        function addItem() {
            const container = document.getElementById('items-container');
            const newItem = document.createElement('div');
            newItem.className = 'item-row';
            newItem.innerHTML = `
                <select name="servicos[]" class="form-control" style="width: 70%; display: inline-block;" required>
                    <option value="">Selecione um serviço</option>
                    <?php foreach ($servicos as $serv): ?>
                        <option value="<?= $serv['id'] ?>" data-valor="<?= $serv['valor'] ?>">
                            <?= htmlspecialchars($serv['nome']) ?> - R$ <?= number_format($serv['valor'], 2, ',', '.') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="number" name="quantidades[]" placeholder="Qtd" class="form-control" style="width: 20%; display: inline-block;" value="1" min="1">
                <span class="remove-item" onclick="removeItem(this)">❌</span>
            `;
            container.appendChild(newItem);
        }
        
        function removeItem(element) {
            if (document.querySelectorAll('.item-row').length > 1) {
                element.parentElement.remove();
            } else {
                alert('Adicione pelo menos um serviço!');
            }
        }
    </script>
</body>
</html>