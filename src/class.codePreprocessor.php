<?php
/**
* @name class.codePreprocessor.php
* Source code parsing / preprocessing engine
* @Author Alexander Selifonov <alex [at] selifan {dot} ru>
* @Version 1.1.002
* @link http://www.selifan.ru, https://github.com/selifan
* modified 2015-09-07
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
    );
    protected $_subst_wrappers = array('%','%');
    protected $CRLF = "\n";
    protected $_srcfolder = '';
    protected $_err = array();

    protected $_tokens = 0;

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
    * @param mixed $subst TRUE or assoc.array to turn ON "substitute" mode, @since 1.1.002
    */
    public function parse($src, $vars=array(), $subst=FALSE) {

        $ar_subst = $substs = FALSE;
        if ($subst) {
            $ar_subst = is_array($subst) ? $subst : $vars;
            $substs = array();
            foreach ($ar_subst as $key => $val) {
                $substs[$this->_subst_wrappers[0] . $key . $this->_subst_wrappers[1]] = $val;
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

        foreach($lines as $lineno => $srcline) {

          $lcmd = $this->_getOpCode($srcline);
          $b_logic = FALSE;

          if($lcmd === 'IF') {

            $b_logic = TRUE;
            $iflevel++;
            if(count($this->_tokens)<2) $value = FALSE;
            else {

                $var_name = $this->_tokens[1];
                $ifstate[$iflevel] = $ifdone[$iflevel] = $this->_evaluateParams($vars);
                $ifbranch[$iflevel] = 'if';
            }
          }
          elseif($lcmd === 'ELSEIF') {

            $b_logic = TRUE;

            if($iflevel>0 && ($ifbranch[$iflevel]==="if" || $ifbranch[$iflevel] === "elseif")) {
              if($ifdone[$iflevel]) { $ifstate[$iflevel] = FALSE; }
              elseif ( count($this->_tokens)<2 ) $ifstate[$iflevel] = FALSE;
              else $ifstate[$iflevel] = $this->_evaluateParams($vars);

              $ifbranch[$iflevel] = 'elseif';
              if($ifstate[$iflevel]) $ifdone[$iflevel] = TRUE;
            }
            else $this->_err[] = "Line ".($lineno+1).": Wrong #ELSEIF";
          }
          elseif ($lcmd === 'ELSE') {

            $b_logic = TRUE;
            if ($iflevel>0 && ($ifbranch[$iflevel]==='if' || $ifbranch[$iflevel]==='elseif')) {
              $ifstate[$iflevel]=!$ifdone[$iflevel];
              $ifbranch[$iflevel]='else';
            }
            else $this->_err[] = "Line ".($lineno+1).": Wrong #ELSE";
          }
          elseif ($lcmd === 'ENDIF') {

            $b_logic = TRUE;
            if($iflevel>0) $iflevel--;
            else $this->_err[] = "Line ".($lineno+1).": Wrong #ENDIF";
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
               $this->_err[] = "Line ".($lineno+1).": Wrong #CASE (not in #SWITCH block)";
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
               $this->_err[] = "Line ".($lineno+1).": Wrong #DEFAULT (not in #SWITCH block)";
               continue;
            }
            $ifstate[$iflevel] = !$ifdone[$iflevel]; # no case was TRUE, so execute DEFAULT block
          }
          elseif ($lcmd === 'ENDSWITCH') {

            $b_logic = TRUE;
            if($iflevel>0 && $ifbranch[$iflevel] === 'switch') $iflevel--;
            else $this->_err[] = "Line ".($lineno+1).": Wrong #ENDSWITCH";
          }
          elseif ($lcmd === 'INCLUDE') {
            $do_it = $this->_isLineActive($ifstate ,$iflevel);
            if (isset($this->_tokens[1]) && is_file($this->_srcfolder . $this->_tokens[1])) {
              $output[] = $this->parse($this->_srcfolder . $this->_tokens[1]);
            }
            else $this->_err[] = "Line ".($lineno+1).": Wrong source file name in #INCLUDE command : "
               . $this->_srcfolder . $this->_tokens[1];
            continue;
          }

          if ( !$b_logic && $this->_isLineActive($ifstate ,$iflevel) ) {
              if ($substs) $srcline = str_replace(array_keys($substs), array_values($substs), $srcline);
              $output[] = rtrim($srcline);
          }

        }

        return implode($this->CRLF, $output);
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
