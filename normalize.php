<?php
require_once('bibtex.php');

if (2 == $argc) {
    $bibtex = new BibtexParserTeam();
    $bibtex->read_file($argv[1]);
    print $bibtex->export();
} else {
    print_r($argv);
}


?>

