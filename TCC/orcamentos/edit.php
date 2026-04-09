<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
verificarLogin();
if (!isEmpresa()) { header('Location: ../index.php'); exit; }

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: index.php'); exit; }

$stmt = $pdo->prepare("SELECT * FROM orcamentos WHERE id=?");
$stmt->execute([$id]); $orc = $stmt->fetch();
if (!$orc) { header('Location: index.php?msg='.urlencode('Não encontrado').'&type=error'); exit; }

$clientes = $pdo->query("SELECT * FROM clientes ORDER BY nome")->fetchAll();
$servicos = $pdo->query("SELECT * FROM servicos WHERE status=1 ORDER BY nome")->fetchAll();

$itensAtuais = $pdo->prepare("SELECT oi.*,s.nome AS servico_nome FROM orcamento_itens oi JOIN servicos s ON s.id=oi.servico_id WHERE oi.orcamento_id=?");
$itensAtuais->execute([$id]); $itensAtuais=$itensAtuais->fetchAll();

$erros=[];
$cliente_id=$orc['cliente_id']; $data_orcamento=$orc['data_orcamento'];
$data_validade=$orc['data_validade']; $observacoes=$orc['observacoes']; $status=$orc['status'];

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $cliente_id     = $_POST['cliente_id']     ?? '';
    $data_orcamento = $_POST['data_orcamento'] ?? date('Y-m-d');
    $data_validade  = $_POST['data_validade']  ?? '';
    $observacoes    = cleanInput($_POST['observacoes'] ?? '');
    $status         = $_POST['status']         ?? 'pendente';
    $servicos_ids   = $_POST['servicos']       ?? [];
    $quantidades    = $_POST['quantidades']    ?? [];

    if (empty($cliente_id))   $erros['cliente']  = 'Selecione um cliente.';
    if (empty($servicos_ids)) $erros['servicos'] = 'Adicione pelo menos um serviço.';

    if (empty($erros)) {
        try {
            $pdo->beginTransaction();
            $valor_total=0; $itens=[];
            $svcMap = array_column($servicos,null,'id');
            foreach ($servicos_ids as $idx=>$sid) {
                if (!isset($svcMap[$sid])) continue;
                $s   = $svcMap[$sid];
                $qty = max(1,(int)($quantidades[$idx]??1));
                $sub = $s['valor']*$qty;
                $valor_total += $sub;
                $itens[] = [$sid,$qty,$s['valor'],$sub];
            }
            $pdo->prepare("UPDATE orcamentos SET cliente_id=?,data_orcamento=?,data_validade=?,valor_total=?,observacoes=?,status=? WHERE id=?")
                ->execute([$cliente_id,$data_orcamento,$data_validade,$valor_total,$observacoes,$status,$id]);
            $pdo->prepare("DELETE FROM orcamento_itens WHERE orcamento_id=?")->execute([$id]);
            $ins = $pdo->prepare("INSERT INTO orcamento_itens (orcamento_id,servico_id,quantidade,valor_unitario,subtotal) VALUES (?,?,?,?,?)");
            foreach ($itens as $it) $ins->execute([$id,...$it]);
            $pdo->commit();
            header('Location: index.php?msg='.urlencode('Orçamento atualizado!').'&type=success'); exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $erros['geral']='Erro: '.$e->getMessage();
        }
    }
}

$servicosJS = json_encode(array_column($servicos,null,'id'));
$itensJS    = json_encode(array_map(fn($it)=>['id'=>$it['servico_id'],'qty'=>$it['quantidade']],$itensAtuais));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Editar Orçamento #<?=$id?> — ServiceHub</title>
  <link rel="stylesheet" href="../css/estilo.css">
  <style>
    .item-row {
      background: #f8fafc; border: 1.5px solid var(--border); border-radius: var(--radius-sm);
      padding: 14px 16px; margin-bottom: 10px;
      display: grid; grid-template-columns: 1fr 100px 36px; gap: 10px; align-items: center;
    }
    .btn-remove { background:none; border:1.5px solid var(--border); border-radius:var(--radius-sm); color:var(--red); cursor:pointer; font-size:16px; width:36px;height:36px; display:flex;align-items:center;justify-content:center; transition:all var(--transition); }
    .btn-remove:hover { background:var(--red);color:#fff;border-color:var(--red); }
    .total-preview { background:var(--navy); color:#fff; border-radius:var(--radius); padding:20px 24px; display:flex;align-items:center;justify-content:space-between;margin-top:6px; }
    .total-preview .lbl { font-size:13px;color:var(--slate); }
    .total-preview .val { font-size:28px;font-weight:700;color:var(--gold);font-family:'DM Sans',sans-serif; }
  </style>
</head>
<body>
<header class="main-header">
  <div class="header-content">
    <div class="logo"><h1>Service<span class="logo-span">Hub</span></h1><p>Gestão de Serviços &amp; Orçamentos</p></div>
    <nav class="main-nav"><ul>
      <li><a href="../index.php">Início</a></li>
      <li><a href="../servicos/index.php">Serviços</a></li>
      <li><a href="../clientes/index.php">Clientes</a></li>
      <li><a href="index.php" class="active">Orçamentos</a></li>
      <li><a href="../relatorios/index.php">Relatórios</a></li>
    </ul></nav>
  </div>
</header>

<div class="container">
  <div class="page-title-row">
    <h1>Editar Orçamento #<?=$id?></h1>
    <div style="display:flex;gap:8px;">
      <a href="view.php?id=<?=$id?>" class="btn btn-ghost">Ver</a>
      <a href="index.php" class="btn btn-ghost">← Voltar</a>
    </div>
  </div>

  <div class="form-container">
    <?php if (!empty($erros['geral'])): echo showMessage($erros['geral'],'error'); endif; ?>

    <form method="post" id="orcForm">
      <div class="form-section">
        <div class="form-section-title">Dados do Orçamento</div>

        <div class="form-group">
          <label>Cliente *</label>
          <select name="cliente_id" class="form-control" required>
            <option value="">Selecione…</option>
            <?php foreach ($clientes as $c): ?>
            <option value="<?=$c['id']?>" <?=$c['id']==$cliente_id?'selected':''?>><?= htmlspecialchars($c['nome']) ?></option>
            <?php endforeach; ?>
          </select>
          <?php if (isset($erros['cliente'])): ?><span class="error-text"><?=$erros['cliente']?></span><?php endif; ?>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>Data do Orçamento</label>
            <input type="date" name="data_orcamento" class="form-control" value="<?=$data_orcamento?>">
          </div>
          <div class="form-group">
            <label>Data de Validade</label>
            <input type="date" name="data_validade" class="form-control" value="<?=$data_validade?>">
          </div>
        </div>

        <div class="form-group">
          <label>Status</label>
          <select name="status" class="form-control">
            <?php foreach(['pendente','aprovado','rejeitado','concluido','expirado'] as $s): ?>
            <option value="<?=$s?>" <?=$status===$s?'selected':''?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="form-section">
        <div class="form-section-title">Serviços</div>
        <?php if (isset($erros['servicos'])): ?><span class="error-text" style="display:block;margin-bottom:12px;"><?=$erros['servicos']?></span><?php endif; ?>

        <div id="items-container"></div>
        <button type="button" class="btn btn-success btn-sm" style="margin-top:10px;" onclick="addRow()">+ Adicionar Serviço</button>

        <div class="total-preview" style="margin-top:16px;">
          <div class="lbl">Total do Orçamento</div>
          <div class="val" id="totalVal">R$ 0,00</div>
        </div>
      </div>

      <div class="form-section">
        <div class="form-section-title">Observações</div>
        <div class="form-group" style="margin-bottom:0;">
          <textarea name="observacoes" class="form-control" rows="3"><?= htmlspecialchars($observacoes) ?></textarea>
        </div>
      </div>

      <div style="display:flex;gap:10px;">
        <button type="submit" class="btn btn-primary btn-lg">Atualizar Orçamento</button>
        <a href="index.php" class="btn btn-ghost btn-lg">Cancelar</a>
      </div>
    </form>
  </div>
</div>

<script>
const SERVICOS    = <?= $servicosJS ?>;
const ITENS_ATUAIS= <?= $itensJS ?>;

function buildOptions(selectedId) {
  let html = '<option value="">Selecione um serviço…</option>';
  for (const id in SERVICOS) {
    const s = SERVICOS[id];
    const sel = id == selectedId ? ' selected' : '';
    const val = parseFloat(s.valor).toLocaleString('pt-BR',{minimumFractionDigits:2});
    html += `<option value="${id}" data-valor="${s.valor}"${sel}>${s.nome} — R$ ${val}</option>`;
  }
  return html;
}

function addRow(selectedId='', qty=1) {
  const c = document.getElementById('items-container');
  const d = document.createElement('div');
  d.className = 'item-row';
  d.innerHTML = `
    <select name="servicos[]" class="form-control" onchange="calcTotal()">${buildOptions(selectedId)}</select>
    <input type="number" name="quantidades[]" class="form-control" value="${qty}" min="1" onchange="calcTotal()" oninput="calcTotal()">
    <button type="button" class="btn-remove" onclick="removeRow(this)">×</button>`;
  c.appendChild(d);
  calcTotal();
}

function removeRow(btn) {
  if (document.querySelectorAll('.item-row').length<=1){alert('Mantenha ao menos um serviço.');return;}
  btn.closest('.item-row').remove(); calcTotal();
}

function calcTotal() {
  let total=0;
  document.querySelectorAll('.item-row').forEach(row=>{
    const sel=row.querySelector('select'); const qty=row.querySelector('input');
    if (sel&&sel.value&&qty){ const opt=sel.options[sel.selectedIndex]; total+=(parseFloat(opt.dataset.valor)||0)*(parseInt(qty.value)||0);}
  });
  document.getElementById('totalVal').textContent='R$ '+total.toLocaleString('pt-BR',{minimumFractionDigits:2});
}

document.addEventListener('DOMContentLoaded', ()=>{
  if (ITENS_ATUAIS.length>0) { ITENS_ATUAIS.forEach(it=>addRow(it.id,it.qty)); }
  else addRow();
});
</script>
</body>
</html>
