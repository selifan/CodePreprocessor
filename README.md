# Source code preprocessor

This class contains preprocessing engine for generating program or html (or whatelse) modules from
prepared "source" files, containing macros like "\#IF", "\#ELSE", "\#ELSEIF", "\#SWITCH" / "\#CASE" and "#INCLUDE"

## Main idea

Idea comes from well known languages like C, C++ where preprocessing used widely.
Say you have a source file:

```text
// here my code begins
#IF use_authorization
include_once('class.authorization.php')
$auth = new Auth();
// ... other code for auth needs
#ENDIF
```
And depending on your current demands, you want to generate PHP source with or without authorization block.
So you turn ON or OFF "use_authorization" parameter and start preprocessing that creates desired code.

After creating and "tuning" source template files, you will be able to rapidly create collections of source files, html blocks for
your projects, plugins inside them etc, to use them as starting point for further development.

## Using

```php
include_once('src/class.preProcessor.php');
$myvars = array( // your parameters for preprocessing
  'challenge' => TRUE
  ,'is_strong' => 1
  ,'fictive' => 0
  ,'innervar' => 1
  ,'sw_lang' => 'en'
);

$preproc = new CodePreprocessor();
$preproc -> setLF('unix') -> setSourceFolder('/usr/mysources/');

echo "Preprocessed text:\r\n-----------------------------------------\r\n";
echo ($preproc -> parse('src_example.txt', $myvars));
```
## Supported macros

Preprocessing is a process of finding "macros" in the beginning of source line,
and executing respective logic.

Each macros starts with '#' char. Macros are case insensitive, but following var names (if applicable) are NOT !
Second (and next, if supported) operand should be a parameter name. Expressions NOT supported.

**\#IF** var_name[,var_name2...] - starts **IF** block (following lines until first #ENDIF|#ELSE|#ELSEIF will be handled / outputted to the result code
if 'var_name' value in passed vars array is not empty / zero.
It is possible to use more than one var name (comma or space delimited). \#IF fires TRUE if any one of them
is not empty.

**\#ELSEIF var_name** or **\#ELIF var_name** - starts ELSEIF block (following lines will do if none of previous IF/ELSEIF cases did not match and value in this line is true / non-empty.
As usual, there may be many ELSEIF's after starting IF macro.
Like in **IF**, more than one var name can be used (comma or space delimited).

**\#ELSE** - starts ELSE block (following lines will be added to result if none of previous IF/ELSEIF fired "true").
**\#ENDIF** - finalizing IF block.

**\#SWITCH var_name**  - starts SWITCH / CASE / DEFAULT / ENDSWITCH block. var_name is a key for value in passed var array,
  that will be checked against all "CASE nnnn" values. Unlike "standard" SWITCH in C, PHP and other languages, there is no "BREAK" command here:
  next "CASE" macro line stops handling lines from previous CASE[s], so each "CASE" is like "ELSEIF" in IF block - when it ends, code generating stops until \#ENDCASE.

**\#CASE** value[,value2,...] - starts CASE blok inside SWITCH. Block is handled if following "value" matches "var_name" value.
  It is possible to have CASE with multiple values (comma or space delimited) -
  such a CASE will trigger if any of values matches to SWITCH var.

**\#DEFAULT** - starts final DEFAULT block - this block is handled if none of previous cases worked.

**\#ENDSWITCH** - finalizes SWITCH block.

**\#INCLUDE** file_name starts precompiling another source file. Result will be added to output.

## Method list

**setLF($style)** : sets "new line" char to "windows" or unix style. By default, when creating instance, "new line" chars
accomodates to current operating system. But you can overwrite it by calling setLF('windows') or setLF('unix'),
setting style to CRLF or LF respectively.
Method returns CodePreprocessor instance, so you can make call chains:
```php
$preproc -> setLF('unix') -> setSourceFolder($myfolder);
```

**setSourceFolder($folder)** - sets folder where source files will be searched.
After calling $preproc->setSourceFolder('/usr/me/mysources/'), all parse() calls will try to open "source" files in this folder.
Method returns CodePreprocessor instance, so you can make call chains:

```php
$preproc -> setLF('unix') -> setSourceFolder($myfolder)->parse($my_params);
```
**parse($src, $vars=array())** - main method that performs preprocessing of source file or multi-line string passed in first parameter,
If $src is existing file name, that file will be read as a source code, otherwise $src itself will be used.
$vars must be an associative array 'key' => 'value', holding all needed parameters for preprocessing.
If some var names in "IF", "CASE" operators not found in $vars, FALSE value used.

parse() returns a string that can be written to final generated file (by file_put_contents() for example) or used in further processing.


## Nesting
Unlimited depth of macros nesting supported (SWITCH inside IF/ELSEIF/ELSE inside IF ...)

Working sample demonstrating code preprocessing is in "examples" folder :
[examples/preproc.php](examples/preproc.php)

## License
Distributed under BSD (v3) License :
http://opensource.org/licenses/BSD-3-Clause
