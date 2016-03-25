<?php
/* ***
phido core v0.1
by Maurice de Rooij, 2016
*/

namespace TechMaurice\Phido;


abstract class PhidoCore {
    
  public $signatures = Array();
  public $profiles = Array();
  public $results = Array();
  private $configMap = ['signatures',  'pdfScanEnabled', 'pdfScanFormats', 'priorityOverride', 'chunkSize', 'maxStringSize'];


  public function __construct() {
    if(!$this->loadConfig()) {
      throw(new PhidoException("Failed to load config"));
      }
    if(!$this->loadSignatures()) {
      throw(new PhidoException("Failed to load 'signatures.json'"));
      }
    } // end public function __construct


  private function loadConfig() {
    $phidoConfig = new PhidoConfig();
    foreach($this->configMap as $item) {
    if(!isset($phidoConfig->userConfig[$item])) {
      throw(new PhidoException("Missing configuration item: '{$item}'"));
      }
    else {
      $this->config[$item] = $phidoConfig->userConfig[$item];
      }
    }
  return true;
  }

  
  private function loadSignatures() {
    if(file_exists($this->config["signatures"]) && is_readable($this->config["signatures"])) {
      $signatures = file_get_contents($this->config["signatures"]);
      $signatures = json_decode($signatures, true);
      $this->signatures = $this->returnFixSignatures($signatures["formats"]);
      return true;
      }
    return false;
    } // end private function loadSignatures


  private function returnFixSignatures($signatures) {
    $pattern = ['/\//'];
    $replacement = ['\/'];
    foreach($signatures as $puid => &$signature) {
      if(!empty($signature["signatures"]) && is_array($signature["signatures"])) {
        foreach($signature["signatures"] as &$regex) {
          if(!empty($regex["bofregex"])) {
            $regex["bofregex"] = preg_replace($pattern, $replacement, $regex["bofregex"]);
            }
          if(!empty($regex["varregex"])) {
            $regex["varregex"] = preg_replace($pattern, $replacement, $regex["varregex"]);
            }
          if(!empty($regex["eofregex"])) {
            $regex["eofregex"] = preg_replace($pattern, $replacement, $regex["eofregex"]);    
            }
          }
        }
      } 
      return $signatures;
    } // end private function returnFixSignatures
    

  } // end abstract class PhidoCore


class Phido extends PhidoCore {

  public $errors = Array();
  private $extension = "";
  private $fileHandle;
  private $fileName = "";
  private $fileSize = 0;
  public $result = Array();
  public $timer = 0;
  private $startTime = 0;
  private $warnings = Array();

  
  private function initVars() {
  
    $this->errors = Array();
    $this->extension = "";
    $this->fileHandle;
    $this->fileName = "";
    $this->fileSize = 0;
    $this->result = Array();
    $this->timer = 0;
    list($usec, $sec) = explode(' ',microtime());
    $this->startTime = sprintf('%d%03d', $sec, $usec * 1000);
    $this->warnings = Array();
		
		return true;

  } // private function resetVars

  public function identifyFileHandle(&$fileHandle, $filename = '') {
    $this->initVars();
    $this->fileName = $filename;      
    $this->extension = strtolower($this->returnExtension($this->fileName));
    if($binaryString !== "" && is_string($binaryString)) {
      $this->fileSize = strlen($binaryString);
      $this->fileHandle = fopen("php://temp/maxmemory:{$this->config['maxStringSize']}", 'rb+');
      fputs($this->fileHandle, $binaryString);
      rewind($this->fileHandle);
      $this->identifyObject();
      $this->addResult();
      return true;
      }
    else {
      $this->fileSize = 0;
      $this->pushError("Getting a file handle failed (" . htmlspecialchars($php_errormsg));
      $this->addResult();
      return false;     
      }
    } // end public function identifyString


  public function identifyString($binaryString, $filename = '') {
    $this->initVars();
    $this->fileName = $filename;
    $this->extension = strtolower($this->returnExtension($this->fileName));
    if($binaryString !== "" && is_string($binaryString)) {
      $this->fileSize = strlen($binaryString);
      $this->fileHandle = fopen("php://temp/maxmemory:{$this->config['maxStringSize']}", 'rb+');
      fputs($this->fileHandle, $binaryString);
      rewind($this->fileHandle);
      $this->identifyObject();
      $this->addResult();
      return true;
      }
    else {
      $this->fileSize = 0;
      $this->pushError("Passed string is empty or not a string");
      $this->addResult();
      return false;     
      }
    } // end public function identifyString


  public function identifyFile($filename) {
    # TODO: UGLY, must change!!!
    # http://php.net/manual/en/class.errorexception.php
    ini_set('track_errors', 1);
    global $php_errormsg;

    $this->initVars();    
    $this->fileName = $filename; // DOC: warn bad filenames
    $this->extension = strtolower($this->returnExtension($this->fileName));
    // fugly fopen warnings are supressed with @
    // then check if we got a file handle
    $this->fileHandle = @fopen($this->fileName, 'rb'); 
    if($this->fileHandle != false) {
      $this->fileSize = filesize($this->fileName);
      if($this->fileSize != 0) {
	      $this->identifyObject();
	      }
	    else {
	    	$this->pushWarning("Empty file (0 bytes)");
	    	}
      $this->addResult();   
      fclose($this->fileHandle);
      return true;
      }
    else {
      $this->fileSize = 0;
      $this->pushError("Getting a file handle failed (" . htmlspecialchars($php_errormsg) . ")");
      $this->addResult();   
      return false; 
      }
    } // end public function identifyFile

  
  private function identifyObject() {

    $bofChunk = stream_get_contents($this->fileHandle, $this->config["chunkSize"], 0);
		$eofPosition = 0;
    if($this->fileSize > $this->config["chunkSize"]) {
	    $eofPosition = ($this->fileSize - $this->config["chunkSize"]);
	    }
    $eofChunk = stream_get_contents($this->fileHandle, $this->config["chunkSize"], $eofPosition);
    foreach($this->signatures as $puid => $signature) {
    	if($this->config["pdfScanEnabled"] == false && in_array($puid, $this->config["pdfScanFormats"])) {
    		continue;
    		}
      if(!empty($signature["signatures"]) && is_array($signature["signatures"])) {
        foreach($signature["signatures"] as $regex) {
          $bofResult = 0;
          $varResult = 0;
          $eofResult = 0;
          $matchtypes = Array();
          $score = Array();

          if(empty($regex["bofregex"])) {
            continue;
            }
          else if(!empty($regex["bofregex"])) {
            array_push($matchtypes, "bof");
            $bofResult = preg_match('/' . $regex["bofregex"] . '/ms', $bofChunk, $matches);
            if($bofResult == 1) {
              array_push($score, "bof");
              }
            else {
              continue;
              }
            }

          if(!empty($regex["varregex"])) {
            rewind($this->fileHandle);
            array_push($matchtypes, "var");
            foreach($this->fileChunk() as $varChunk) {
              $varResult = preg_match('/' . $regex["varregex"] . '/ms', $varChunk, $matches);
              if($varResult == 1) {
                array_push($score, "var");
                break;
                }
              }
            }

          if(!empty($regex["eofregex"])) {
            array_push($matchtypes, "eof");
            $eofResult = preg_match('/' . $regex["eofregex"] . '/ms', $eofChunk, $matches);
            if($eofResult == 1) {
              array_push($score, "eof");
              }
            }

          $reliability = "partial_match";
                
          if($matchtypes === $score) {
            $reliability = "full_match";
            }
          
          if(!empty($signature["extension"]) && !is_array($signature["extension"])) {
            if($this->extension === $signature["extension"]) {
              $reliability .= "_and_extension_match";
              }
            }
          else if(!empty($signature["extension"]) && in_array($this->extension, $signature["extension"])) {
              $reliability .= "_and_extension_match";             
              }

          if($bofResult != 0) {
            $this->result[$puid] = Array();
            $this->result[$puid]["reliability"] = $reliability;
            $this->result[$puid]["formatName"] = $signature["name"];
            $this->result[$puid]["signatureName"] = $regex["name"];
            $this->result[$puid]["matchtypes"] = $matchtypes;
            $this->result[$puid]["score"] = $score;
            }
        
          }
        }
      }

		if($this->config["priorityOverride"] == true) {
			$deletePuids = Array();
			foreach($this->result as $puid => $info) {
				if(!empty($this->signatures[$puid]['has_priority_over'])) {
					foreach($this->signatures[$puid]['has_priority_over'] as $puids) {
						array_push($deletePuids, $puids);
						}
					}
				}
			foreach(array_unique($deletePuids) as $puid) {
				unset($this->result[$puid]);
				}
			}
			
    return true;
    } // end private function identifyObject


  private function fileChunk() {
    while (true) {
      $chunk = fread($this->fileHandle, $this->config["chunkSize"]);
      if(strlen($chunk) != 0) {
        yield $chunk;
        }
      elseif (feof($this->fileHandle)) {
        break;
        }
      }
    } // end private function fileChunk


  private function addResult() {
    list($usec, $sec) = explode(' ', microtime());
    $this->endTime = sprintf('%d%03d', $sec, ($usec * 1000));
    $this->timer = (intval($this->endTime) - intval($this->startTime));
    $this->results[$this->fileName]["fileSize"] = $this->fileSize;
    $this->results[$this->fileName]["extension"] = $this->extension;
    $this->results[$this->fileName]["result"] = $this->result;
    $this->results[$this->fileName]["timerMs"] = $this->timer;
    $this->results[$this->fileName]["errors"] = $this->errors;
    $this->results[$this->fileName]["warnings"] = $this->warnings;
    $this->result = $this->results[$this->fileName];
    return true;
    } // end private function addResult

  
  private function returnExtension($filename) {
    $extension = explode(".", $filename);
    if(sizeof($extension) > 1) {
      return array_pop($extension);
      }
    else {
      return "";
      }   
    } // end private function returnExtension

  
  private function pushError($error) {
    return array_push($this->errors, $error);
    } // end private function pushError


  private function pushWarning($warning) {
    return array_push($this->warnings, $warning);
    } // end private function pushWarning


  } // end class Phido extends PhidoCore


class PhidoException extends \Exception { 
  } // end class PhidoException extends \Exception

?>