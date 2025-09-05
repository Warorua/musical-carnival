<?php
require_once __DIR__.'/../includes/functions.php';
session_destroy();
redirect(APP_URL . '/admin/login.php');
