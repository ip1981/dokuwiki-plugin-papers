<?php
require_once('bibtex.php');

$bibtex = new BibtexParserGoga();
$bibtex->read_file('example.bib');
$bibtex->select(array(
    'author' => '/pashev/',
    ));


print_r($bibtex->STRINGS);
print_r($bibtex->SELECTION);

?>

