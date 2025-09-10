<?php
// includes/csrf.php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

function csrf_token() {
  if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
  return $_SESSION['csrf'];
}
function csrf_field() {
  return '<input type="hidden" name="csrf" value="'.htmlspecialchars(csrf_token(), ENT_QUOTES).'">';
}
function csrf_field_as(string $name) {
  return '<input type="hidden" name="'.htmlspecialchars($name, ENT_QUOTES).'" value="'.htmlspecialchars(csrf_token(), ENT_QUOTES).'">';
}
function csrf_verify() {
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tok = $_POST['csrf'] ?? $_POST['_token'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', $tok)) {
      http_response_code(419); exit('CSRF token inválido');
    }
  }
}
