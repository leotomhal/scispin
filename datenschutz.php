<?php
require __DIR__ . '/lib/page.php';
sci_page([
    'title'        => 'Datenschutzerklärung – SciSpin',
    'root'         => '',
    'active'       => '',
    'content_file' => __DIR__ . '/content/datenschutz.md',
    'extra_css'    => '  .todo{background:#fff8e1;border:1px solid #ffe082;border-radius:8px;padding:1rem;margin-top:1rem;}',
]);
