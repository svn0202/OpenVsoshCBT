<?php

require_once '../config/tce_config.php';
require_once '../../shared/code/tce_functions_site_assets.php';

openvsosh_send_site_asset(isset($_GET['type']) && is_string($_GET['type']) ? $_GET['type'] : '');
