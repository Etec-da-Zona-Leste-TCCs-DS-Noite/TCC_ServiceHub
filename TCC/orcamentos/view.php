<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

$id = $_GET['id'] ?? 0;
if (!$id) {
    header('Location: index.php');
    exit;
}

// Buscar orçamento
$stmt = $pdo->prepare("
    SELECT o.*, c.nome as cliente_nome, c.email as cliente_email, c.telefone as cliente_telefone, c.endereco as cliente_endereco
    FROM orcamentos o 
    LEFT JOIN clientes c ON c.id = o.cliente_id 
    WHERE o.id = ?
");
$stmt->execute([$id]);
$orcamento = $stmt->fetch();

if (!$orcamento) {
    header('Location: index.php?msg=' . urlencode('Orçamento não encontrado') . '&type=error');
    exit;
}

// Buscar itens do orçamento
$itensStmt = $pdo->prepare("
    SELECT oi.*, s.nome as servico_nome, s.descricao as servico_descricao
    FROM orcamento_itens oi
    JOIN servicos s ON s.id = oi.servico_id
    WHERE oi.orcamento_id = ?
");
$itensStmt->execute([$id]);
$itens = $itensStmt->fetchAll();

$statusColors = [
    'pendente' => 'warning',
    'aprovado' => 'success',
    'rejeitado' => 'danger',
    'expirado' => 'secondary'
];

$statusLabels = [
    'pendente' => 'Pendente',
    'aprovado' => 'Aprovado',
    'rejeitado' => 'Rejeitado',
    'expirado' => 'Expirado'
];

// Processar mudança de status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['status'])) {
    $novoStatus = $_POST['status'];
    $update = $pdo->prepare("UPDATE orcamentos SET status = ? WHERE id = ?");
    if ($update->execute([$novoStatus, $id])) {
        header('Location: view.php?id=' . $id . '&msg=' . urlencode('Status atualizado com sucesso!') . '&type=success');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Orçamento #<?= $orcamento['id'] ?></title>
    <link rel="stylesheet" href="../css/estilo.css">
    <style>
        .orcamento-header {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .status-select {
            display: inline-block;
            margin-left: 10px;
        }
        .total-final {
            font-size: 24px;
            font-weight: bold;
            color: #28a745;
            text-align: right;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid #eee;
        }
        @media print {
            .no-print {
                display: none;
            }
            .container {
                box-shadow: none;
                padding: 0;
            }
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
        <div class="no-print" style="margin-bottom: 20px;">
            <a href="index.php" class="btn">← Voltar</a>
            <a href="edit.php?id=<?= $orcamento['id'] ?>" class="btn btn-warning">✏️ Editar</a>
            <button onclick="window.print()" class="btn">🖨️ Imprimir</button>
            <?php if ($orcamento['status'] == 'pendente'): ?>
                <a href="gerar_pdf.php?id=<?= $orcamento['id'] ?>" class="btn">📄 Gerar PDF</a>
            <?php endif; ?>
        </div>

        <?php if (isset($_GET['msg'])): ?>
            <?php echo showMessage(urldecode($_GET['msg']), $_GET['type'] ?? 'success'); ?>
        <?php endif; ?>

        <div class="orcamento-header">
            <h2>Orçamento #<?= $orcamento['id'] ?></h2>
            <div style="display: flex; justify-content: space-between;">
                <div>
                    <strong>Data:</strong> <?= formatDate($orcamento['data_orcamento']) ?><br>
                    <strong>Validade:</strong> <?= formatDate($orcamento['data_validade']) ?>
                </div>
                <div class="no-print">
                    <strong>Status:</strong>
                    <span class="badge badge-<?= $statusColors[$orcamento['status']] ?>">
                        <?= $statusLabels[$orcamento['status']] ?>
                    </span>
                    
                    <?php if ($orcamento['status'] == 'pendente'): ?>
                    <form method="post" style="display: inline-block;" class="status-select">
                        <select name="status" onchange="this.form.submit()">
                            <option value="">Alterar status</option>
                            <option value="aprovado">Aprovar</option>
                            <option value="rejeitado">Rejeitar</option>
                            <option value="expirado">Expirar</option>
                        </select>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="cliente-info" style="margin-bottom: 30px;">
            <h3>Dados do Cliente</h3>
            <p><strong>Nome:</strong> <?= htmlspecialchars($orcamento['cliente_nome'] ?? 'Cliente não informado') ?></p>
            <?php if ($orcamento['cliente_email']): ?>
                <p><strong>Email:</strong> <?= htmlspecialchars($orcamento['cliente_email']) ?></p>
            <?php endif; ?>
            <?php if ($orcamento['cliente_telefone']): ?>
                <p><strong>Telefone:</strong> <?= htmlspecialchars($orcamento['cliente_telefone']) ?></p>
            <?php endif; ?>
            <?php if ($orcamento['cliente_endereco']): ?>
                <p><strong>Endereço:</strong> <?= htmlspecialchars($orcamento['cliente_endereco']) ?></p>
            <?php endif; ?>
        </div>

        <h3>Serviços Solicitados</h3>
        <table>
            <thead>
                <tr>
                    <th>Serviço</th>
                    <th>Descrição</th>
                    <th>Quantidade</th>
                    <th>Valor Unitário</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($itens as $item): ?>
                    <tr>
                        <td><?= htmlspecialchars($item['servico_nome']) ?></td>
                        <td><?= htmlspecialchars(substr($item['servico_descricao'], 0, 50)) ?>...</td>
                        <td><?= $item['quantidade'] ?></td>
                        <td>R$ <?= number_format($item['valor_unitario'], 2, ',', '.') ?></td>
                        <td>R$ <?= number_format($item['subtotal'], 2, ',', '.') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="total-final">
            Total: R$ <?= number_format($orcamento['valor_total'], 2, ',', '.') ?>
        </div>

        <?php if ($orcamento['observacoes']): ?>
            <div class="observacoes" style="margin-top: 30px;">
                <h3>Observações</h3>
                <p><?= nl2br(htmlspecialchars($orcamento['observacoes'])) ?></p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>