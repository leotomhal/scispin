<?php
require __DIR__ . '/../lib/page.php';
sci_page([
    'title'        => 'Über den SciSpin-O-Mat',
    'description'  => 'Worum es beim SciSpin-O-Mat geht: beide Extreme der Wissenschaftskommunikation sichtbar machen.',
    'root'         => '../',
    'active'       => 'spin',
    'content_file' => __DIR__ . '/../content/ueber.md',
    'extra_css'    => <<<'CSS'
  .sci-article .eyebrow{font-family:var(--sci-mono);font-size:12px;letter-spacing:.16em;text-transform:uppercase;color:var(--sci-accent);margin:0 0 4px;}
  .sci-article .turn{font-style:italic;font-weight:500;background:linear-gradient(90deg,var(--sci-cold),var(--sci-good) 50%,var(--sci-hot));-webkit-background-clip:text;background-clip:text;color:transparent;}
CSS,
]);
