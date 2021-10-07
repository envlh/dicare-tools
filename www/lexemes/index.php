<?php

require '../../inc/load.inc.php';

define('PAGE_TITLE', '<a href="'.SITE_DIR.LEXEMES_SITE_DIR.'">Lexemes</a>');
page::setMenu('lexemes');

require '../../inc/header.inc.php';

?>

<h2><a href="<?php echo SITE_DIR.LEXEMES_SITE_DIR; ?>party.php">Lexemes Party</a></h2>
<h2><a href="<?php echo SITE_DIR.LEXEMES_SITE_DIR; ?>challenge.php">Lexemes Challenge</a></h2>
<h2><a href="<?php echo SITE_DIR.LEXEMES_SITE_DIR; ?>challenges-dashboard.php">Lexemes Challenges Dashboard</a></h2>
<h2><a href="<?php echo SITE_DIR.LEXEMES_SITE_DIR; ?>challenges-archive.php">Lexemes Challenges Archive</a></h2>

<?php

require '../../inc/footer.inc.php';

?>