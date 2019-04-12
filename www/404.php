<?php

header('HTTP/1.0 404 Not Found', true, 404);

define('PAGE_TITLE', 'Page not found');
require '../inc/header.inc.php';

?>

<p>The requested page was not found. There is a <a href="<?php echo SITE_DIR; ?>">list of Dicare tools</a>.</p>

<?php

require '../inc/footer.inc.php';

?>