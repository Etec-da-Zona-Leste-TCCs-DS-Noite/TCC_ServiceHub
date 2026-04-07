<?php
require_once '../includes/config.php';

$id = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("SELECT * FROM empresas WHERE id=?");
$stmt->execute([$id]);
$empresa = $stmt->fetch();

if (!$empresa) {
    echo "Empresa não encontrada";
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM servicos WHERE empresa_id=? AND status=1");
$stmt->execute([$id]);
$servicos = $stmt->fetchAll();
?>

<h2><?= $empresa['nome_empresa'] ?></h2>
<p><?= $empresa['descricao'] ?></p>

<h3>Serviços</h3>

<?php if ($servicos): ?>
    <?php foreach ($servicos as $s): ?>
        <div class="card">
            <h4><?= $s['nome'] ?></h4>
            <p><?= $s['descricao'] ?></p>
            <p>R$ <?= $s['preco'] ?></p>
        </div>
    <?php endforeach; ?>
<?php else: ?>
    <p>Esta empresa ainda não cadastrou serviços.</p>
<?php endif; ?>