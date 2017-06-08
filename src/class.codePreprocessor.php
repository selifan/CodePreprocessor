<?php
/**
* @name class.codePreprocessor.php
* Source code parsing / preprocessing engine
* @Author Alexander Selifonov <alex [at] selifan {dot} ru>
* @Version 1.2.005
* @link https://github.com/selifan/CodePreprocessor
* modified 2017-06-07
**/

class CodePreprocessor {

    static $_operators = array(
       'IF'   => '#IF'
      ,'ELSE' => '#ELSE'
      ,'ELSEIF'  => array('#ELSEIF', '#ELIF')
      ,'ENDIF'   => '#ENDIF'
      ,'SWITCH'  => '#SWITCH'
      ,'CASE'    => '#CASE'
      ,'DEFAULT' => '#DEFAULT'
      ,'ENDSWITCH'  => '#ENDSWITCH'
      ,'INCLUDE' => '#INCLUDE'
      ,'SET' => '#SET'
      ,'FOR' => '#FOR'
      ,'ENDFOR' => array('#ENDFOR','#ENDF')
    );
    protected $_subst_wrappers = array('%','%');
    protected $CRLF = "\n";
    protected $_srcfolder = '';
    protected $_err = array();
	private   $_vars = array();
	private   $_evalError = '';
	private $lineno = 0;
    protected $_tokens = 0;

    private $_loopStack = array();
    private $_loopLevel = 0;
    private $substs = array();

    public function __construct() {
        if (stripos(PHP_OS, 'win')!==FALSE) $this->CRLF = "\r\n";
    }
    /**
    * Change output code style to Unix or Windows (LF / CRLF)
    *
    * @param mixed $style : 'windows' OR 'unix' OR your line delimiter
    * @return this object reference (chain calls available)
    */
    public function setLF($style='windows') {
        $this->CRLF = ($style === 'windows') ? "\r\n" :
            (($style === 'unix') ? "\n" : $style);
        return $this;
    }

    /**
    * Setting "source" folder where seek file to be preprocessed
    * will be searched
    *
    * @param mixed $folder new path (folder) to source file(s)
    * @return this object reference (chain calls available)
    */
    public function setSourceFolder($folder) {
        $this->_srcfolder = $folder;
        return $this;
    }

    /**
    * Setting wrapping strings for "substitute" vars
    *
    * @param mixed $prefix string used as prefix in var to replace
    * @param mixed $postfix string used as postfix. If empty, will be the same as $prefix
    * @return this object reference
    */
    public function setSubstWrappers($prefix, $postfix=FALSE) {
        if (!empty($prefix)) {
            $this->_subst_wrappers[0] = $prefix;
            $this->_subst_wrappers[1] = ($postfix ? $postfix : $prefix);
        }
        return $this;
    }

    /**
    * Parses source from passed string (can be a file name)
    *
    * @param mixed $src text or file name
    * @param mixed $vars assoc.array, user defined pairs "key" => value
    * @param mixed $subst TRUE or assoc.array to turn ON "substitute" mode, @since 1.0.002
    */
    public function parse($src, $vars = array(), $subst=FALSE) {

        $ar_subst = $substs = FALSE;
        $this->_vars = $vars;
        if ($subst) {
            $ar_subst = is_array($subst) ? $subst : $vars;
            $this->substs = array();
            foreach ($ar_subst as $key => $val) {
                $this->substs[$this->_subst_wrappers[0] . $key . $this->_subst_wrappers[1]] = $val;
            }
            unset($ar_subst);
        }
        if (is_file($src)) $src = file_get_contents($src);
        $lines = explode("\n", $src);
        $output = array();

        $level = 0;
        $active = array(TRUE);

        # Begin main loop

        $fmparts = '';
        $iflevel = 0;
        $ifstate = array(0);
        $ifbranch = array('');
        $switch_factor = array(0);
        $ifdone = array(FALSE);
        $b_logic = FALSE;

        for($this->lineno=0; $this->lineno<count($lines); $this->lineno++) {

          $srcline = $lines[$this->lineno];
          $lcmd = $this->_getOpCode($srcline);
          $b_logic = FALSE;

          if($lcmd === 'IF') {

            $b_logic = TRUE;
            $iflevel++;
            if(count($this->_tokens)<2) $value = FALSE;
            else {
				$expr = self::_dropFirstToken($srcline);
                $evaled = $this->_evalExpression($expr);
                if ($evaled === null) {
                	$this->_err[] = "Line ".($this->lineno+1).": Wrong IF expresion, ".$this->_evalError;
                	$evaled = false;
				}
                $ifstate[$iflevel] = $ifdone[$iflevel] = $evaled;
                $ifbranch[$iflevel] = 'if';
            }
          }
          elseif($lcmd === 'ELSEIF') {
		  	$expr = self::_dropFirstToken($srcline);
            $b_logic = TRUE;

            if($iflevel>0 && ($ifbranch[$iflevel]==="if" || $ifbranch[$iflevel] === "elseif")) {
              if($ifdone[$iflevel]) { $ifstate[$iflevel] = FALSE; }
              elseif ( count($this->_tokens)<2 ) $ifstate[$iflevel] = FALSE;
              else {
			      $evaled = $this->_evalExpression($expr);
			      if ($evaled === null) {
                  	  $this->_err[] = "Line ".($this->lineno+1).": Wrong ELSEIF expresion, ".$this->_evalError;
			      	  $evaled = false;
				  }
              	  $ifstate[$iflevel] = $evaled;
			  }

              $ifbranch[$iflevel] = 'elseif';
              if($ifstate[$iflevel]) $ifdone[$iflevel] = TRUE;
            }
            else $this->_err[] = "Line ".($this->lineno+1).": #ELSEIF without respective #IF,#ELSE";
          }
          elseif ($lcmd === 'ELSE') {

            $b_logic = TRUE;
            if ($iflevel>0 && ($ifbranch[$iflevel]==='if' || $ifbranch[$iflevel]==='elseif')) {
              $ifstate[$iflevel]=!$ifdone[$iflevel];
              $ifbranch[$iflevel]='else';
            }
            else $this->_err[] = "Line ".($this->lineno+1).": Wrong #ELSE";
          }
          elseif ($lcmd === 'ENDIF') {

            $b_logic = TRUE;
            if($iflevel>0) $iflevel--;
            else $this->_err[] = "Line ".($this->lineno+1).": Wrong #ENDIF";
          }
          elseif ($lcmd === 'SWITCH') {

            $b_logic = TRUE;
            $iflevel++;
            if(count($this->_tokens)<2) $value = '';
            else {
              $var_name = $this->_tokens[1];
              $value = (isset($vars[$var_name]) ? $vars[$var_name] : 0);
            }
            $ifbranch[$iflevel] = 'switch';
            $ifstate[$iflevel] = $ifdone[$iflevel] = FALSE;
            $switch_factor[$iflevel] = $value;
          }
          elseif ($lcmd === 'CASE') {

            $b_logic = TRUE;
            if ( $iflevel<=0 || ($ifbranch[$iflevel]!=="switch") ) {
               $this->_err[] = "Line ".($this->lineno+1).": Wrong #CASE (not in #SWITCH block)";
               continue;
            }
            $ifstate[$iflevel] = FALSE;
            for ($koff=1; $koff<count($this->_tokens); $koff++) {
              $value = $this->_tokens[$koff];
              if ($value == $switch_factor[$iflevel]) $ifstate[$iflevel] = TRUE;
            }
            if ($ifstate[$iflevel]) $ifdone[$iflevel] = TRUE;
          }
          elseif ($lcmd === 'DEFAULT') {

            $b_logic = TRUE;
            if ( $iflevel<=0 || ($ifbranch[$iflevel]!=="switch") ) {
               $this->_err[] = "Line ".($this->lineno+1).": Wrong #DEFAULT (not in #SWITCH block)";
               continue;
            }
            $ifstate[$iflevel] = !$ifdone[$iflevel]; # no case was TRUE, so execute DEFAULT block
          }
          elseif ($lcmd === 'ENDSWITCH') {

            $b_logic = TRUE;
            if($iflevel>0 && $ifbranch[$iflevel] === 'switch') $iflevel--;
            else $this->_err[] = "Line ".($this->lineno+1).": Wrong #ENDSWITCH";
          }
          elseif ($lcmd === 'INCLUDE') { // load / parse another file
            $do_it = $this->_isLineActive($ifstate ,$iflevel);
            if (isset($this->_tokens[1]) && is_file($this->_srcfolder . $this->_tokens[1])) {
              $output[] = $this->parse($this->_srcfolder . $this->_tokens[1]);
            }
            else $this->_err[] = "Line ".($this->lineno+1).": Wrong source file name in #INCLUDE command : "
               . $this->_srcfolder . $this->_tokens[1];
            continue;
          }
          elseif ($lcmd === 'SET') { // setting value to "var"
		  	 $evalString = trim(substr(ltrim($srcline),4));
			 $eqpos = strpos($evalString, '=');
			 if ($eqpos > 0) {
			 	 $newvar = trim(substr($evalString,0,$eqpos));
			 	 $expression = trim(substr($evalString,$eqpos+1));

			 	 if ($newvar =='') {
			 	 	 $this->_err[] = "Line ".($this->lineno+1). " - SET empty var name";
			 	 	 continue;
				 }
				 $evaled = $this->_evalExpression($expression);
				 if ($evaled === null) {
   				 	$this->_err[] = "Line ".($this->lineno+1) . ' SET expression error,' . $this->_evalError;
   				 	continue;
				 }

#			 	 $output[] = "NEW var: $newvar, expression: $expression";

			 	 $this->_vars[$newvar] = $evaled;
			 	 $this->substs[$this->_subst_wrappers[0] . $newvar . $this->_subst_wrappers[1]] = $evaled;
#		 	 	 $output[] = "// New var calculated: $newvar = $evaled";

			 }
			 else $this->_err[] = "Wrong SET operator: must be 'varname = {expression}'";
#          	  WriteDebugInfo('SET operator,rest line :',$evalString);
			 continue;
		  }

		  elseif ( $lcmd === 'FOR') { # loop begin
		      $result = $this->_ForLoopStarts($srcline);
		      if (!$result) $this->_err[] = "Line ".($this->lineno+1)." - wrong FOR operator";
		      continue;
		  }
		  elseif ( $lcmd === 'ENDFOR') { # loop end
		      $this->_ForLoopEnds();
		      continue;
		  }

          if ( !$b_logic && $this->_isLineActive($ifstate ,$iflevel) ) {
              if ($this->substs) $srcline = strtr($srcline, $this->substs);
              $output[] = rtrim($srcline);
          }

        }

        return implode($this->CRLF, $output);
    }
    private function _findNextToken(&$line) {
		$line = ltrim($line);
        if ($line === '') return '';
        $strDelim = FALSE;
		if (substr($line,0,1) === '"') $strDelim = '"';
		elseif (substr($line,0,1) === "'") $strDelim = "'";
		if ($strDelim) {
			$endDelim = strpos($line,$strDelim,1);
			if ($endDelim === FALSE) {
				$this->_err[] = "Line ".$this->lineno. " - No ending delimiter [$strDelim] in the string";
                $ret = $line;
				$line = '';
				return $ret;
			}
			$ret = substr($line, 1, ($endDelim-1)); # without quotes!
			$line = substr($line,$endDelim+1);
			return $ret;
		}
    	$ret = '';
    	$ipos = 0;
    	$kkk = 0; # debug stopper
    	while($ipos < strlen($line) && $kkk++<500) {
            $onechar = substr($line,$ipos,1);
            if ($onechar === ' ' || $onechar == "\t") break;
            if (in_array($onechar, array(',', ':', '!', '?', '/', '\\',';','*'))) {
                if ($ipos === 0) {
		            $line = substr($line, $ipos+1);
		            return $onechar;
				}
				break;
			}
            if (!in_array($onechar, array(' ',"\t")))
	   			$ret .= $onechar;
            $ipos++;
		}
		$line = ltrim(substr($line, $ipos));
		return $ret;
	}
	public function parseToTokens($line) {
		$toks = array();
		while(($onetoken = $this->_findNextToken($line))) {
			$toks[] = $onetoken;
		}
		return $toks;
	}

    private function _ForLoopStarts($line) {
        # supported FOR syntax:
    	# 1) FOR VARNAME IN {VAL1},{VAL2},{VAL3},...
    	# 2) FOR VARNAME FROM {INT_START} TO {INT_END} [STEP INT_STEP]
    	$stage = 0;
    	$srcline = trim($line);
    	$this->_findNextToken($srcline); # #for just for skip
    	$varname = $this->_findNextToken($srcline); # must be a varname
    	$inword = $this->_findNextToken($srcline); # should be IN or FROM
    	if (strtoupper($inword) === 'FROM') {
            $step = 1;
    		$value1 = floatval($this->_findNextToken($srcline));
    		$value2 = $this->_findNextToken($srcline); #
    		if (strtoupper($value2)==='TO') $value2 = floatval($this->_findNextToken($srcline));
    		if ($value2 < $value1) $step = -1;
    		$steptok = $this->_findNextToken($srcline);
    		if (strtoupper($steptok)==='STEP')
    			$step = floatval($this->_findNextToken($srcline));

            if (($value2 > $value1 && $step<=0) || ($value2 < $value1 && $step>=0)) {
            	$this->err[] = 'Error in FROM or TO or STEP value';
            	return FALSE;
			}

            $values = range($value1, $value2, $step);
		}
    	elseif (strtoupper($inword) === 'IN') {
            $values = array();
			while($token = $this->_findNextToken($srcline)) {
				if ($token !==',') $values[] = $token;
			}
		}
		else return FALSE;

		$this->_loopLevel++;
		$this->_loopStack[$this->_loopLevel] = array(
			'varname' => $varname,
			'values'  => $values,
			'startline' => ($this->lineno+1),
			'iteration' => 0,
			'code'    => array(),
		);
        $this->_vars[$varname] = $values[0];
        $this->substs[$this->_subst_wrappers[0] . $varname . $this->_subst_wrappers[1]] = $values[0];
        return TRUE;
	}

    private function _ForLoopEnds() {

        if ($this->_loopLevel > 0) {
        	# ...
            $this->_loopStack[$this->_loopLevel]['iteration']++;
            $curitem = $this->_loopStack[$this->_loopLevel]['iteration'];
            if ($this->_loopStack[$this->_loopLevel]['iteration']>=count($this->_loopStack[$this->_loopLevel]['values'])) {
				$this->_loopLevel--;
			}
			else {

                $vname = $this->_loopStack[$this->_loopLevel]['varname'];
                $vval = $this->_loopStack[$this->_loopLevel]['values'][$curitem];
				$this->_vars[$vname] = $vval;
        		$this->substs[$this->_subst_wrappers[0] . $vname . $this->_subst_wrappers[1]] = $vval;
				$this->lineno = $this->_loopStack[$this->_loopLevel]['startline']-1;
			}
		}
		else
			$this->_err[] = "Error, line ".$this->lineno. ' - ENDFOR without corrsponding FOR';
	}

	private static function _dropFirstToken($strg) {
		$ret = trim($strg);
		while (!in_array(substr($ret,0,1), array(' ',"\t")) && !empty($ret)) {
			$ret = substr($ret,1);
		}
		return ltrim($ret);
	}

    private function _evalExpression($exprstring) {

		$result = null;
		$this->_evalError = '';
		foreach($this->_vars as $key=>$val) {
			$realvar = is_numeric($val) ? $val : "'$val'";
    		$exprstring = preg_replace('/\b'.$key.'\b/i', $realvar, $exprstring);
		}
		$__my__ = null;
	 	try {
			eval("\$__my__ = $exprstring;");
			$result = $__my__;
		} catch(Exception $e) {
   			$this->_evalError = "Bad expression, " . $e->getMessage();
		}
		return $result;
	}
    # evaluate vars list after operator
    private function _evaluateParams($vars) {

        foreach($this->_tokens as $no=>$var_name) {
            if ( $no==0 ) continue;
            if ( isset($vars[$var_name]) && !empty($vars[$var_name]) ) return TRUE;
        }
        return FALSE;
    }

    # finding out if current line should be added to output
    protected function _isLineActive($states, $iflevel) {
      for ($if_iii=1;$if_iii<=$iflevel;$if_iii++) { if (!$states[$if_iii]) return FALSE; }
      return TRUE;
    }

    /**
    *  parses source line and tries detects one of known macro commands
    * @param mixed $line
    */
    protected function _getOpCode($line) {

        $this->_tokens = preg_split("/[\s,]+/",trim($line));
        foreach (self::$_operators as $id => $opstring) {
            $first = strtoupper($this->_tokens[0]);
            if (is_array($opstring)) {
                if (in_array($first, $opstring)) return $id;
            }
            elseif ($opstring === $first) return $id;
        }
        return FALSE;
    }

    /**
    * Getting accumulated error|warning messages, created during prepprocessing
    *
    */
    public function getErrorMessages() {
      return $this->_err;
    }
}
