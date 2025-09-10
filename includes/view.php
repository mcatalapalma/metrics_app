<?php
// includes/view.php
declare(strict_types=1);

function render_partial(string $path, array $vars = []): void {
    // Aísla el ámbito con una función interna:
    $render = function(string $___path, array $___vars) {
        extract($___vars, EXTR_SKIP);
        include $___path;
    };
    $render($path, $vars);
}
