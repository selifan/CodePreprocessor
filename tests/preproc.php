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
);

$preproc = new CodePreprocessor();
$preproc->setLF('unix');

echo "Preprocessed text:\n-----------------------------------------\n";
echo ($preproc -> parse($srcfile, $myvars));

echo "\n-----------------------------------------\nError/warning messages:\n";
echo implode("\n", $preproc->getErrorMessages());
