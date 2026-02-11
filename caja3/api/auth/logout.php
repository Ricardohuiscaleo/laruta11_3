<?php
session_start();
session_destroy();
header('Location: https://app.laruta11.cl/?logout=success');
exit();
?>