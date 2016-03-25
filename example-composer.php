<?php

include_once("./vendor/autoload.php");

use TechMaurice\Phido as tm;

try {
	$phido = new tm\Phido;
	}
catch(Exception $error) {
	echo "{$error}\n";
	exit;
	}

$files = Array(
	"./example_files/empty.txt",
	"./example_files/example.pdf",
	"./example_files/phido_kicks.xss",
	"./file_does_not_exist.mdr"
	);

echo "<pre>";

foreach($files as $file) {
	$result = $phido->identifyFile($file);
	if($result != false) {
		print_r($phido->result);
		}	
	}

$testStream = "%PDF-1.4\x00TechMaurice\x00%%EOF";
$testName = "virtual.pdf";
$result = $phido->identifyString($testStream, $testName);
	if($result != false) {
		print_r($phido->result);
		}

print_r($phido->results);

echo "</pre>";

?>