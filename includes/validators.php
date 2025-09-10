<?php
// includes/validators.php
function v_email(?string $v): ?string {
  $v = trim((string)$v);
  if ($v === '') return null; // permitir vacío si el campo no es obligatorio
  return filter_var($v, FILTER_VALIDATE_EMAIL) ? $v : null;
}
function v_phone(?string $v): ?string {
  $v = preg_replace('/\s+/', '', (string)$v);
  if ($v === '') return null;
  return preg_match('/^[0-9+\-()]{6,20}$/', $v) ? $v : null;
}
function v_text(?string $v, int $max = 255): ?string {
  $v = trim((string)$v);
  if ($v === '') return null;
  return mb_strlen($v) <= $max ? $v : mb_substr($v, 0, $max);
}
