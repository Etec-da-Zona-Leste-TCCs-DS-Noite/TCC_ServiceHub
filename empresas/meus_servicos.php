<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

verificarLogin();

if (!isEmpresa()) {
    header('Location: ../index.php');
    exit;
}

$empresa_id = $_SESSION['empresa_id'];
$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? 0;
$erros = [];

// Processar exclusão
if ($action === 'delete' && $id) {
    $stmt = $pdo->prepare("DELETE FROM servicos WHERE id = ? AND empresa_id = ?");
    if ($stmt->execute([$id, $empresa_id])) {
        header('Location: meus_servicos.php?msg=' . urlencode('Serviço excluído com sucesso!'));
        exit;
    } else {
        header('Location: meus_servicos.php?msg=' . urlencode('Erro ao excluir serviço'));
        exit;
    }
}

// Processar criação/edição
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = cleanInput($_POST['nome'] ?? '');
    $descricao = cleanInput($_POST['descricao'] ?? '');
    $categoria = cleanInput($_POST['categoria'] ?? '');
    $valor = str_replace(',', '.', $_POST['valor'] ?? '');
    $duracao_estimada = $_POST['duracao_estimada'] ?? '';
    $status = $_POST['status'] ?? 1;
    
    if (empty($nome)) $erros['nome'] = 'Nome é obrigatório.';
    if (empty($valor)) $erros['valor'] = 'Valor é obrigatório.';
    elseif (!is_numeric($valor)) $erros['valor'] = 'Valor inválido.';
    
    if (empty($erros)) {
        if ($action === 'edit' && $id) {
            // Atualizar serviço existente
            $stmt = $pdo->prepare("UPDATE servicos SET nome = ?, descricao = ?, valor = ?, duracao_estimada = ?, categoria = ?, status = ? WHERE id = ? AND empresa_id = ?");
            if ($stmt->execute([$nome, $descricao, $valor, $duracao_estimada, $categoria, $status, $id, $empresa_id])) {
                header('Location: meus_servicos.php?msg=' . urlencode('Serviço atualizado com sucesso!'));
                exit;
            }
        } else {
            // Criar novo serviço
            $stmt = $pdo->prepare("INSERT INTO servicos (empresa_id, nome, descricao, valor, duracao_estimada, categoria, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
            if ($stmt->execute([$empresa_id, $nome, $descricao, $valor, $duracao_estimada, $categoria, $status])) {
                header('Location: meus_servicos.php?msg=' . urlencode('Serviço cadastrado com sucesso!'));
                exit;
            }
        }
        $erros['geral'] = 'Erro ao salvar serviço.';
    }
}

// Buscar dados do serviço para edição
$servico = null;
if ($action === 'edit' && $id) {
    $stmt = $pdo->prepare("SELECT * FROM servicos WHERE id = ? AND empresa_id = ?");
    $stmt->execute([$id, $empresa_id]);
    $servico = $stmt->fetch();
    if (!$servico) {
        header('Location: meus_servicos.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ServiceHub - <?= $action === 'edit' ? 'Editar' : 'Novo' ?> Serviço</title>
    <link rel="stylesheet" href="../css/estilo.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .form-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }
        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
        }
        .form-control:focus {
            border-color: #d4af37;
            outline: none;
        }
        .error-text {
            color: #dc3545;
            font-size: 12px;
            margin-top: 5px;
            display: block;
        }
        .btn-save {
            background: #d4af37;
            color: #0a2b3e;
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
        }
        .btn-save:hover {
            background: #c4a02e;
        }
        .btn-cancel {
            background: #6c757d;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            display: inline-block;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        @media (max-width: 600px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div style="background: linear-gradient(135deg, #1a4a6f 0%, #0a2b3e 100%); padding: 30px 0;">
        <div style="max-width: 1280px; margin: 0 auto; padding: 0 20px;">
            <h1 style="color: white;"><?= $action === 'edit' ? '✏️ Editar Serviço' : '➕ Novo Serviço' ?></h1>
            <a href="meus_servicos.php" style="color: white;">← Voltar para meus serviços</a>
        </div>
    </div>
    
    <div style="max-width: 1280px; margin: 0 auto; padding: 40px 20px;">
        <div class="form-container">
            <?php if (!empty($erros['geral'])): ?>
                <div class="alert alert-error"><?= $erros['geral'] ?></div>
            <?php endif; ?>
            
            <form method="post">
                <div class="form-group">
                    <label>Nome do Serviço *</label>
                    <input type="text" name="nome" class="form-control" value="<?= htmlspecialchars($servico['nome'] ?? '') ?>" required>
                    <?php if (isset($erros['nome'])): ?>
                        <span class="error-text"><?= $erros['nome'] ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label>Descrição</label>
                    <textarea name="descricao" class="form-control" rows="4"><?= htmlspecialchars($servico['descricao'] ?? '') ?></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Categoria</label>
                        <input type="text" name="categoria" class="form-control" value="<?= htmlspecialchars($servico['categoria'] ?? '') ?>" placeholder="Ex: Desenvolvimento, Design, Marketing">
                    </div>
                    <div class="form-group">
                        <label>Duração Estimada (horas)</label>
                        <input type="number" name="duracao_estimada" class="form-control" value="<?= $servico['duracao_estimada'] ?? '' ?>" placeholder="Ex: 40">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Valor (R$) *</label>
                        <input type="text" name="valor" class="form-control" value="<?= isset($servico['valor']) ? number_format($servico['valor'], 2, ',', '.') : '' ?>" placeholder="0,00" required>
                        <?php if (isset($erros['valor'])): ?>
                            <span class="error-text"><?= $erros['valor'] ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" class="form-control">
                            <option value="1" <?= (isset($servico['status']) && $servico['status'] == 1) ? 'selected' : '' ?>>Ativo</option>
                            <option value="0" <?= (isset($servico['status']) && $servico['status'] == 0) ? 'selected' : '' ?>>Inativo</option>
                        </select>
                    </div>
                </div>
                
                <div style="display: flex; gap: 15px; margin-top: 30px;">
                    <button type="submit" class="btn-save"><?= $action === 'edit' ? 'Atualizar Serviço' : 'Cadastrar Serviço' ?></button>
                    <a href="meus_servicos.php" class="btn-cancel">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>