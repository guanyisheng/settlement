<?php

declare(strict_types=1);

$id = (int) ($_GET['id'] ?? 0);
header('Location: /admin/user_detail.php?id=' . $id);
exit;
