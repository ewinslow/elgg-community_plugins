<?php
/**
 * Old all plugins page used to be hit directly
 *
 * This is here until no one is hitting it directly anymore or a rewrite rule
 * is set up for it.
 */

require_once(dirname(dirname(dirname(__FILE__))) . "/engine/start.php");

system_message('Please update your bookmark or report this link to the site owner as this page has moved.');

include dirname(__FILE__) . "/pages/plugins/all.php";
