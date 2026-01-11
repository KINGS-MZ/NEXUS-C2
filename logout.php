<?php
session_start();
session_destroy();
header('Location: /c2/index.php');
exit;
