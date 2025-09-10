<?php
// includes/pagination.php
function paginate(int $total, int $page, int $per_page): array {
  $pages = max(1, (int)ceil($total / $per_page));
  $page  = max(1, min($page, $pages));
  $offset = ($page - 1) * $per_page;
  return ['pages'=>$pages,'page'=>$page,'offset'=>$offset,'limit'=>$per_page];
}
function render_pagination(int $pages, int $page, array $qs): string {
  if ($pages <= 1) return '';
  $html = '<nav><ul class="pagination">';
  for ($p=1; $p<=$pages; $p++) {
    $qs['page']=$p; $url='?'.http_build_query($qs);
    $html .= '<li class="page-item '.($p===$page?'active':'').'"><a class="page-link" href="'.$url.'">'.$p.'</a></li>';
  }
  $html .= '</ul></nav>';
  return $html;
}
