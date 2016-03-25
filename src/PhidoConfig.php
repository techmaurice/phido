<?php
/* **
Phido configuration
** */

namespace TechMaurice\Phido;

class PhidoConfig {

	public $userConfig = Array();
	
	public function __construct() {

		/* **
		signatures, better not touch
		also accepts URLs
		see DOCUMENTATION
		** */
		$this->userConfig["signatures"] = getcwd() . "/data/signatures.json";


		/* **
		whether or not to 'deep' scan PDF files
		to check if it is a special PDF
		this is only useful if you need to know 
		the exact PDF type (eg. PDF/A-1a)
		see DOCUMENTATION
		default 'false' for performance reasons
		** */

		$this->userConfig["pdfScanEnabled"] = false;


		/* **
		special PDF formats to 'deep' scan (enablePdfScan = true)
		see DOCUMENTATION
		** */
		
		$this->userConfig["pdfScanFormats"] = Array(
			"fmt/95",
			"fmt/144",
			"fmt/145",
			"fmt/146",
			"fmt/147",
			"fmt/148",
			"fmt/157",
			"fmt/158",
			"fmt/354",
			"fmt/476",
			"fmt/477",
			"fmt/478",
			"fmt/479",
			"fmt/480",
			"fmt/481",
			"fmt/488",
			"fmt/489",
			"fmt/490",
			"fmt/491",
			"fmt/492",
			"fmt/493"
			);


		/* **
		whether or not to override formats with lower priority
		see DOCUMENTATION
		default true
		** */
		
		$this->userConfig["priorityOverride"] = true;


		/* **
		chunk size of file chunks
		however 16 kb might be better (needs benchmark)
		if you set this too high (eg. 32 kb), accuracy goes down
		because the regexes are smaller (¿needs further investigation?)
		see DOCUMENTATION
		default 4 kb
		** */
		$this->userConfig["chunkSize"] =	(1024) * 4; // * kb


		/* **
		maximum size of temporary string
		passed through to "identifyString()"
		before it is written to disk
		see DOCUMENTATION
		default 5 MB
		** */
		$this->userConfig["maxStringSize"] =	(1024 * 1024) * 5;


	} // end public function __construct
	
} // end class PhidoConfig

?>