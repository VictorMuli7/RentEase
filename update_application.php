<?php
require_once 'app_helpers.php';
require_role('manager', 'admin');
header('Location: applications_list.php');
exit;
