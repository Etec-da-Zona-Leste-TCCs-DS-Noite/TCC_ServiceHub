<?php
function cleanInput($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}

function showMessage($message, $type = 'success') {
    $icon = $type === 'success' ? '✓' : '✕';
    return "<div class='alert alert-{$type}'><span>{$icon}</span> {$message}</div>";
}

function formatDate($date, $format = 'd/m/Y') {
    if (!$date) return '—';
    return (new DateTime($date))->format($format);
}

function formatMoney($value) {
    return 'R$ ' . number_format($value ?? 0, 2, ',', '.');
}

function statusBadge($status) {
    $map = [
        'pendente'  => ['label' => 'Pendente',  'class' => 'badge-pendente'],
        'aprovado'  => ['label' => 'Aprovado',  'class' => 'badge-aprovado'],
        'rejeitado' => ['label' => 'Rejeitado', 'class' => 'badge-rejeitado'],
        'concluido' => ['label' => 'Concluído', 'class' => 'badge-concluido'],
        'expirado'  => ['label' => 'Expirado',  'class' => 'badge-expirado'],
    ];
    $s = $map[$status] ?? ['label' => ucfirst($status), 'class' => 'badge-secondary'];
    return "<span class='badge {$s['class']}'>{$s['label']}</span>";
}

/**
 * Renderiza estrelas HTML para uma nota de 1–5.
 * @param float $nota  Nota (pode ser decimal para média)
 * @param bool  $small Tamanho reduzido
 */
function starRating($nota, $small = false) {
    $size  = $small ? '14px' : '18px';
    $nota  = (float)$nota;
    $full  = (int)floor($nota);
    $half  = ($nota - $full) >= 0.4 ? 1 : 0;
    $empty = 5 - $full - $half;
    $html  = "<span class='stars' style='font-size:{$size};line-height:1;'>";
    $html .= str_repeat('<span style="color:#c9a84c;">★</span>', $full);
    if ($half) $html .= '<span style="color:#c9a84c;">½</span>';
    $html .= str_repeat('<span style="color:#d0d8e0;">☆</span>', $empty);
    $html .= '</span>';
    return $html;
}

/**
 * Busca a média de avaliações de uma empresa.
 */
function mediaAvaliacoes($pdo, $empresa_id) {
    $stmt = $pdo->prepare("SELECT AVG(nota) AS media, COUNT(*) AS total FROM avaliacoes WHERE empresa_id = ?");
    $stmt->execute([$empresa_id]);
    return $stmt->fetch();
}
