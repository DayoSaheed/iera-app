<?php
// Fallback when the document root is the project folder and mod_rewrite is unavailable.
header('Location: public/', true, 302);
exit;
