<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

admin_logout();
flash('info', 'You have been signed out of the admin portal.');
redirect('login.php');