<?php
foreach (glob('bootstrap/cache/ser*.tmp') as $f) {
  @unlink($f);
}
echo "Temporary .tmp files cleared.\n";
