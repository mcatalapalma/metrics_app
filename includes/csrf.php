<?php
// includes/csrf.php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
function csrf_token(){ if(empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(16)); return $_SESSION['csrf']; }
function csrf_field(){ return '<input type="hidden" name="csrf" value="'.htmlspecialchars(csrf_token(), ENT_QUOTES).'">'; }
function csrf_verify(){
  if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf']??'', $_POST['csrf'])){
      http_response_code(419); exit('CSRF token inválido');
    }
  }
}
