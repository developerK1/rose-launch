<?php
require_once 'session.php';

session_unset();
session_destroy();

header("Location: /auth/login.php");
exit;