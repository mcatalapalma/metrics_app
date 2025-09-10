<?php
// includes/helpers.php
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function intv($s){ return (int)preg_replace('/[^\d\-]/', '', (string)$s); }
function dec($s){ return (float)str_replace(',', '.', trim((string)$s)); }
function toDateISO($s){
    $s = trim((string)$s);
    if ($s === '') return null;
    $ts = strtotime(str_replace('/','-',$s));
    return $ts ? date('Y-m-d', $ts) : null;
}
