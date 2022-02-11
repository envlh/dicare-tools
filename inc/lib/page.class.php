<?php

class page {
    
    private static $css = array();
    private static $js = array();
    private static $menu = null;
	private static $card = null;
    
    public static function getParameter($name, $default = null) {
        if (!empty($_POST[$name])) {
            return $_POST[$name];
        } elseif (!empty($_GET[$name])) {
            return $_GET[$name];
        } else {
            return $default;
        }
    }
    
    public static function addCss($path) {
        self::$css[] = $path;
    }
    
    public static function displayCss() {
        foreach (self::$css as $path) {
            echo '<link rel="stylesheet" type="text/css" href="'.SITE_STATIC_DIR.'css/'.$path.'" />'."\n";
        }
    }
    
    public static function addJs($path) {
        self::$js[] = $path;
    }
    
    public static function displayJs() {
        foreach (self::$js as $path) {
            echo '<script type="text/javascript" src="'.SITE_STATIC_DIR.$path.'" defer="defer"></script>'."\n";
        }
    }
    
    public static function setMenu($menu) {
        self::$menu = $menu;
    }
    
    public static function displayMenu() {
        switch (self::$menu) {
            case 'lexemes':
                echo '<div id="menu"><a href="'.SITE_DIR.LEXEMES_SITE_DIR.'party.php">Lexemes Party</a> | <a href="'.SITE_DIR.LEXEMES_SITE_DIR.'challenge.php">Lexemes Challenge</a> | <a href="'.SITE_DIR.LEXEMES_SITE_DIR.'challenges-archive.php">Challenges Archive</a> | <a href="https://www.wikidata.org/wiki/User:Envlh/Lexemes_Party">Documentation</a></div>';
            break;
            case 'noms':
                echo '<div id="menu"><a href="'.SITE_DIR.NOMS_SITE_DIR.'">Documentation</a> | <a href="'.SITE_DIR.NOMS_SITE_DIR.'homonymie.php">Génération d\'une page d\'homonymie</a> | <a href="'.SITE_DIR.NOMS_SITE_DIR.'nom-de-famille.php">Ajout en masse d\'un nom de famille</a> | <a href="'.SITE_DIR.NOMS_SITE_DIR.'prenom.php">Ajout en masse d\'un prénom</a> | <a href="'.SITE_DIR.NOMS_SITE_DIR.'suggestions.php">Suggestions de noms de famille manquants</a> | Statistiques : <a href="'.SITE_DIR.NOMS_SITE_DIR.'departements.php">par département français</a>, <a href="'.SITE_DIR.NOMS_SITE_DIR.'pays.php">par pays</a></div>';
            break;
            default:
                // none
        }
    }
	
    public static function setCard($title, $description, $image) {
        self::$card = (object) array('title' => $title, 'description' => $description, 'image' => $image);
    }
    
    public static function displayCard($title) {
        if (empty(self::$card)) {
			self::setCard($title, 'Some Dicare tool that does something.', SITE_STATIC_DIR.'img/logo.png');
		}
		echo '<meta name="twitter:card" content="summary" />
	<meta name="twitter:title" content="'.htmlentities(self::$card->title).'" />
	<meta name="twitter:description" content="'.htmlentities(self::$card->description).'" />
	<meta name="twitter:image" content="'.htmlentities(self::$card->image).'" />
	<meta property="og:title" content="'.htmlentities(self::$card->title).'" />
	<meta property="og:description" content="'.htmlentities(self::$card->description).'" />
	<meta property="og:image" content="'.htmlentities(self::$card->image).'" />'."\n";
    }
	
}

?>