<?php
// install.php — runs when admin activates the addon
if (!defined('NEXUS')) exit('Forbidden');
cfg_set('hello_world_enabled', '1');
