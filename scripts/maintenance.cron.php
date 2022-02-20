<?php

require '../inc/load.inc.php';

# purge query table
db::query('DELETE FROM `query` WHERE `last_update` < NOW() - INTERVAL 33 DAY');
db::commit();
db::query('REPAIR TABLE `query`');

?>