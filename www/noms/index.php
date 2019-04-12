<?php

require '../../inc/load.inc.php';

define('PAGE_TITLE', '<a href="'.SITE_DIR.NOMS_SITE_DIR.'">Noms</a>');
page::setMenu('noms');

require '../../inc/header.inc.php';

?>

<h2><a href="<?php echo SITE_DIR.NOMS_SITE_DIR; ?>homonymie.php">Génération d'une page d'homonymie</a></h2>
<p>Cet outil génère, à partir des données <a href="https://www.wikidata.org/">Wikidata</a>, le wikicode d'une page d'homonymie pour la <a href="https://fr.wikipedia.org/">Wikipédia en français</a>.</p>
<p>Saisissez l'identifiant Wikidata d'un nom de famille (par exemple <a href="https://www.wikidata.org/wiki/Q23722145">Q23722145</a> pour <em>Lagarde</em>), puis cliquez sur <em>Générer</em>.</p>
<p>Détails :</p>
<ul>
    <li>si la personne n'a pas de page dans la Wikipédia en français et qu'une page existe dans une des langues saisies, le modèle {{<a href="https://fr.wikipedia.org/wiki/Mod%C3%A8le:Lien">Lien</a>}} est utilisé en prenant en priorité la première langue ;</li>
    <li>la description affichée est celle de Wikidata. Si elle n'est pas renseignée, la propriété <em>occupation</em> (<a href="https://www.wikidata.org/wiki/Property:P106">P106</a>) est utilisée et affichée en rouge (attention, le féminin n'est pas géré !) ;</li>
    <li>la mention « né » utilise la propriété <em>nom de naissance</em> (<a href="https://www.wikidata.org/wiki/Property:P1477">P1477</a>) ;</li>
    <li>la mention « également connu comme » utilise les propriétés <em>pseudonyme</em> (<a href="https://www.wikidata.org/wiki/Property:P742">P742</a>) et <em>surnom</em> (<a href="https://www.wikidata.org/wiki/Property:P1449">P1449</a>) ;</li>
    <li>les personnes modifiées récemment dans Wikidata (24 heures) ont leur libellé affiché en gras.</li>
</ul>

<h2><a href="<?php echo SITE_DIR.NOMS_SITE_DIR; ?>nom-de-famille.php">Ajout en masse d'un nom de famille</a></h2>
<p>Cet outil sert à ajouter rapidement un nom de famille aux personnes pour lesquelles l'information n'est pas présente dans <a href="https://www.wikidata.org/">Wikidata</a>.</p>
<p>Saisissez l'identifiant Wikidata d'un nom de famille (par exemple <a href="https://www.wikidata.org/wiki/Q16877399">Q16877399</a> pour <em>Moreau</em>), cliquez sur <em>Lister</em>, puis patientez quelques secondes. L'outil affiche alors la liste des personnes présentes dans Wikidata, dont le <em>nom de famille</em> (<a href="https://www.wikidata.org/wiki/Property:P734">P734</a>) n'est pas connu et dont le libellé se termine comme le nom de famille saisi. Décochez les personnes pour lesquelles le nom de famille que vous avez saisi ne devrait pas être associé, puis cliquez sur <em>Générer</em>. L'outil affiche alors un code que vous pouvez copier-coller dans <a href="https://tools.wmflabs.org/wikidata-todo/quick_statements.php">QuickStatements</a> pour ajouter le nom de famille saisi aux personnes sélectionnées.</p>
<p>Par défaut, seules les personnes ayant la propriété <em>nationalité</em> (<a href="https://www.wikidata.org/wiki/Property:P27">P27</a>) avec la valeur <em>France</em> (<a href="https://www.wikidata.org/wiki/Q142">Q142</a>) sont affichées. Vous pouvez saisir d'autres identifiants Wikidata de pays. Attention : plus il y a d'identifiants, plus la recherche sera longue, voire n'aboutira pas !</p>
<p>Limitations :<p>
<ul>
    <li>la recherche des personnes se faisant sur la fin de leurs libellés, il y a des personnes affichées à tort (exemples : les personnes dont le nom de famille est <em>Le Goasguen</em> en cherchant avec le nom de famille <em>Goasguen</em> ; les personnes dont le libellé est un pseudonyme ; etc.) ;</li>
    <li>la recherche peut ne pas aboutir s'il y a trop d'identifiants de pays saisis ;</li>
    <li>il est impossible de trouver des personnes dont la propriété <em>nationalité</em> (<a href="https://www.wikidata.org/wiki/Property:P27">P27</a>) n'est pas renseignée.</li>
</ul>

<h2><a href="<?php echo SITE_DIR.NOMS_SITE_DIR; ?>suggestions.php">Suggestions de noms de famille manquants</a></h2>
<p>Cet outil sert à trouver des idées de noms de famille pour lesquels contribuer dans <a href="https://www.wikidata.org/">Wikidata</a>.</p>
<p>Saisissez l'identifiant Wikidata d'un lieu (par exemple <a href="https://www.wikidata.org/wiki/Q12193">Q12193</a> pour <em>Brest</em>) puis cliquez sur <em>Lister</em>. L'outil affiche alors une liste de personnes présentes dans Wikidata, nées dans le lieu saisi et dont le <em>nom de famille</em> (<a href="https://www.wikidata.org/wiki/Property:P734">P734</a>) n'est pas connu. Les personnes sont regroupées par leur nom de famille, qui est détecté automatiquement. À vous ensuite de vérifier que les noms de famille existent dans Wikidata et, dans le cas contraire, de les créer et de les associer aux personnes trouvées.</p>
<p>Limitations :<p>
<ul>
    <li>le nom de famille détecté est le dernier mot du libellé (en utilisant l'espace comme séparateur de mots). Le nom détecté est donc faux dans certains cas (par exemple pour les noms composés de plusieurs mots comme <em>Le Guen</em> ou <em>de La Raudière</em>) ;</li>
    <li>l'affichage est limité à 1000 personnes.</li>
</ul>

<h2>Divers</h2>
<p>Chaque outil affiche, généralement, en bas de page, les requêtes SPARQL utilisées.</p>

<?php

require '../../inc/footer.inc.php';

?>