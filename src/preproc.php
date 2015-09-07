<?php
/**
* Testing class.preProcessor.php functionality
*/
include '../src/class.codePreprocessor.php';
$srcfile = 'source_example.txt';

$myvars = array(
  'use_auth' => FALSE
  ,'my_auth' => 1
 ,'sw_lang' => 'de'
 ,'program_title' => 'Sample string to demonstrate %var% substitution in Preprocessor'
);

$preproc = new CodePreprocessor();
$preproc->setLF('unix');

echo "Preprocessed text:\n-----------------------------------------\n";
echo ($preproc -> parse($srcfile, $myvars, TRUE));

echo "\n-----------------------------------------\nError/warning messages:\n";
echo implode("\n", $preproc->getErrorMessages());
