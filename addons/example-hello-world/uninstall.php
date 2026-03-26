<?php
// uninstall.php — runs when admin deactivates the addon
if (!defined('NEXUS')) exit('Forbidden');
cfg_set('hello_world_enabled', '0');
