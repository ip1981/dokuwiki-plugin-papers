<?php
require_once('bibtex.php');

$bibtex = new BibtexParserTeam();
$bibtex->read_file('1.bib');
print $bibtex->export();


?>

