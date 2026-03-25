<?php
function cleanInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function showMessage($message, $type = 'success') {
    $class = ($type == 'success') ? 'alert-success' : 'alert-error';
    return "<div class='alert $class'>$message</div>";
}

function formatDate($date, $format = 'd/m/Y') {
    if ($date) {
        $dt = new DateTime($date);
        return $dt->format($format);
    }
    return '';
}
?>