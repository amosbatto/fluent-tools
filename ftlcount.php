<?php

/* File: ftlcount.php counts the strings and words in each file and keeps a 
 * running total for Mozilla .ftl (Fluent) translations files which are
 * found at: https://hg.mozilla.org/l10n-central/
 * Author: Amos Batto (amosbatto@yahoo.com)
 * Created: 2021-02-13
 * License: Public Domain (please use freely and modify it)
 * 
 * Usage: php ftlcount.php DIRECTORY-OR-FTL-FILE
 * 
 * Ex: php ftlcount.php /home/me/moz-files 
*/

//class to hold information about a directory of .ftl files
class RunningTotal {
	public $path     = '';
	public $allFiles = 0;
	public $ftlFiles = 0; 
	public $strings  = 0;
	public $words    = 0;
}

$help = 'Usage: php ftlcount.php DIRECTORY-OR-FTL-FILE
OPTIONS:
-v   Verbose displays counts for each file
-h   Displays help. 

Ex: php ftlcount.php -v /home/me/moz-files
';

if ($argc < 2) {
	exit("Error: Need to specify the source directory.\n\n" . $help);
}
elseif ($argc > 3) {
	exit("Error: Too many arguments.\n\n" . $help);
}

if ($argv[1] == '-h' or $argv[1] == '--help') {
	exit($help);
} 

$grandTotal = new RunningTotal(); 
$grandTotal->directories = 0;
$verbose = false; //set to true for more detailed output

if ($argv[1] == '-v') {
	$verbose = true;
	$grandTotal->path = $argv[2] or 
		exit("Error: Need to specify the source directory.\n\n" . $help);
}		
else {
	$grandTotal->path = $argv[1];
}

parseDir($grandTotal->path);

echo "\n{$grandTotal->path}:\nDirectories: {$grandTotal->directories}, ".
	"Files: {$grandTotal->allFiles}, FTL files: {$grandTotal->ftlFiles}, ".
	"Strings: {$grandTotal->strings}, Words: {$grandTotal->words}\n"; 


/* function to parse a directory of .ftl files. Will recursively call the 
 * same parseDir() function for any subdirectories that it finds.
 * Parameters: 
 * $dirPath: Path to the directory to parse.
 */
function parseDir($dirPath) {
	global $verbose, $grandTotal;
	
	$dirCount = new RunningTotal();
	$dirCount->path = $dirPath;
	
	$fullPath = realpath($dirPath) or 
		exit("Error: $dirPath is not a valid path.\n");
	
	if (is_dir($dirCount->path)) {
		$aDirList = scandir($dirCount->path) or
			exit("Error: Unable to open directory {$dirCount->path}.\n");
			
		$aSubDirs = array(); //array of subdirectories to process last after normal files
		
		foreach ($aDirList as $filename) {	

			if ($filename == '..' or $filename == '.') {
				continue;
			}
			
			$filePath = $dirCount->path . DIRECTORY_SEPARATOR . $filename;
			
			if (is_dir($filePath)) {
				$aSubDirs[] = $filePath;
			}
			else {
				if (pathinfo($filePath, PATHINFO_EXTENSION) == 'ftl') {
					$aCount = parseFtlFile($filePath);
					$dirCount->strings += $aCount['strings'];
					$dirCount->words   += $aCount['words'];
					$dirCount->allFiles++;
					$dirCount->ftlFiles++;
					
					if ($verbose) {
						echo "$filePath\t{$aCount['strings']}\t{$aCount['words']}\n"; 
					}
				}
				else {
					$dirCount->allFiles++;
					if ($verbose) {
						echo "Skipping $filePath, type: ".filetype($filePath)."\n";
					}
				}
			}
		}
		
		$grandTotal->directories++;
		
		echo "{$dirCount->path}\t{$dirCount->ftlFiles}\t{$dirCount->strings}\t{$dirCount->words}\n".
		($verbose ? "\n" : '');
		
		//after processing the normal files, process the subdirectories:
		foreach ($aSubDirs as $subdir) {
			parseDir($subdir);
		}
	}
	elseif (is_file($dirCount->path)) {
		
		if (pathinfo($dirCount->path, PATHINFO_EXTENSION) == 'ftl') {
			$aCount = parseFtlFile($dirCount->path);
			$dirCount->strings += $aCount['strings'];
			$dirCount->words   += $aCount['words'];
			$dirCount->allFiles++;
			$dirCount->ftlFiles++;
		}
		else {
			$dirCount->allFiles++;
			if ($verbose) {
				echo "Skipping {$dirCount->path}, type: " . filetype($dirCount->path)."\n";
			}
		}
	}
	else {
		exit("Error: $dirPath is not a valid directory or file.\n");
	}
	
	//add the directory's totals to the grand totals:	
	$grandTotal->allFiles += $dirCount->allFiles;
	$grandTotal->ftlFiles += $dirCount->ftlFiles;
	$grandTotal->strings  += $dirCount->strings;
	$grandTotal->words    += $dirCount->words;
}

/* parses a .ftl file to identify string identifiers, strings to translate, 
 * string comments and section comments and counts components in the .ftl file.
 * Parameter $path: path to .ftl file
 */
function parseFtlFile($path) {
	$stringComment = false;
	$sectionComment = false;
	$aLines = file($path);
	$maxLines = count($aLines);
	$strToTranslate = false;
	$identifier = false;
	$stringComment = false;
	$groupComment = false;
	$aStrings = array();
	$aFileCount = array(
		'strings' => 0,
		'words'   => 0
	);
	
	for ($curLine = 0; $curLine < $maxLines; $curLine++) {
		$line = $aLines[$curLine];
		
		//if line like: identifier = string-to-translate
		if (preg_match('/^([a-zA-Z_\-][\w\-]*)\s*=\s*(.*)$/', $line, $aMatch)) {
			$identifier = $aMatch[1];
			$strToTranslate = $aMatch[2];
			
			//if the string-to-translate continues on the following lines:
			while (++$curLine < $maxLines and 
				preg_match('/^ {1,}(.*)$/', $aLines[$curLine], $aMatch)) 
			{
				$strToTranslate .= "\n" . $aMatch[1];
			}
				
			$aCount = countWordsInStr($strToTranslate);
			$aFileCount['words']   += $aCount['words'];
			$aFileCount['strings'] += $aCount['strings'];
			
			$curLine--; //reset to previous line
			$stringComment = false;
		}
		
		//if a string comment (line starts with a single #)
		elseif (preg_match('/^#\s*(.*)$/', $line, $aMatch)) {
			$stringComment = $aMatch[1];
			
			while (++$curLine < $maxLines and 
				preg_match('/^#\s*(.*)$/', $aLines[$curLine], $aMatch)) 
			{
				$stringComment .= "\n" . $aMatch[1];
			}
			$curLine--; //reset to previous line
		}
		//if a group comment (line starts with two ##)
		elseif (preg_match('/^##\s*(.*)$/', $line, $aMatch)) {
			$groupComment = $aMatch[1];
			
			while (++$curLine < $maxLines and 
				preg_match('/^##\s*(.*)$/', $aLines[$curLine], $aMatch)) 
			{
				$groupComment .= "\n" . $aMatch[1];
			}
			$curLine--; //reset to previous line
		}
	}
	return $aFileCount;
}
   
	
	
/* Returns an array with the number of strings and number of words found inside a
 * Fluent translation string, which can contain substrings to translate and variables. 
 */	
function countWordsInStr($str) {
	$aRet = [
		'strings' => 0,
		'words'   => 0
	];
	
	$normalStr = true; //will be set to false if there is a substring
	
	//take out all substrings like:    .subID = substring-to-translate
	if (preg_match_all('/ {4}(\.[a-zA-Z_][a-zA-Z0-9_\-]*) = (.+)$/m', $str, $aMatches)) {
		foreach ($aMatches[2] as $substrToTrans) {
			$aRet['strings']++;
			$aRet['words'] += countWordsRemovingVars($substrToTrans);
		}
		$normalStr = false;
	}
	
	//take out all substrings like:    .id =\n        multiline-substring-to-translate
	if (preg_match_all('/ {4}(\.[a-zA-Z_][a-zA-Z0-9_\-]*) =\s*(\n {8}.*$)+/m', $str, $aMatches)) {
		foreach ($aMatches[0] as $substr) {
			$aRet['string']++;
			
			if (preg_match('/\n(.*)/s', $substr, $a2ndLine) == 0) {
				echo "Error getting 2nd Line of substring: ";
				var_dump($aMatches);
			}
			else { 
				$aRet['words'] += countWordsRemovingVars($a2ndLine[1]);
			}
		}
		$normalStr = false;
	}
	
	if ($normalStr) {
		$aRet['strings'] = 1;
		$aRet['words'] = countWordsRemovingVars($str);   
	}
	
	return $aRet;
}  

/* Remove any variables between curly braces {...} and returns the number 
 * of words in the string. Also, correctly counts the words in variant strings like:
        { $isVisible ->
            [true] Remove Bookmarks Menu from Toolbar
           *[other] Add Bookmarks Menu to Toolbar
        }
 */
function countWordsRemovingVars($str) {
	// replace any variables like { $var } or { -product-name } with a space:
	$str = preg_replace('/{ *(\$\w+|-[\w\-]+) *}/m', ' ', $str);
	
	if (preg_match('/\s*{ *(\$\w+|-[\w\-]+) *->(\s*\*?\[ *([a-z]+|\d{1,3}) *\] *(.*)\s*)+ *}/m', 
		$str, $aMatch)) 
	{
		$count = 0;
		
		if (!preg_match_all('/^ *\*?\[ *([a-z]+|\d{1,3}) *\] *(.*)/m', $aMatch[0], $aVariants)) {
			echo "\nError finding variant strings in:\n{$aMatch[0]}\n";
			return 0;
		}
		
		foreach ($aVariants[2] as $variant) {
			$count += str_word_count($variant, 0, 'éëïöñçÉËÏÖÑÇ');
		}
	}
	else {
		//for non-English languages with Roman alphabets: str_word_count($str, 0,
		// 'äëïöüÄËÏÖÜáǽćéíĺńóŕśúźÁǼĆÉÍĹŃÓŔŚÚŹàèìòùÀÈÌÒÙãẽĩõñũÃẼĨÕÑŨâêîôûÂÊÎÔÛăĕğĭŏœ̆ŭĂĔĞĬŎŒ̆ŬāēīōūĀĒĪŌŪ' .
		// 'őűŐŰąęįųĄĘĮŲåůÅŮæÆøØýÝÿŸþÞẞßđĐıIœŒčďěľňřšťžČĎĚĽŇŘŠŤŽƒƑðÐłŁçģķļșțÇĢĶĻȘȚħĦċėġżĊĖĠŻʒƷǯǮŋŊŧŦ')
		$count = str_word_count($str, 0, 'éëïöñçÉËÏÖÑÇ'); 
	}
	
	return $count;
}
	

?>
