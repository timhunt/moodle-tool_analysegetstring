<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Detects all calls to get_string().
 *
 * @package    tool
 * @subpackage analysegetstring
 * @copyright  2011 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * Detects all calls to get_string().
 *
 * Derived from Generic_Sniffs_Functions_CallTimePassByReferenceSniff by Florian Grandel.
 *
 * @copyright  2011 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class getstring_Sniffs_Functions_GetStringCallsSniff implements PHP_CodeSniffer_Sniff {
    protected $lastfile = '';

    public function register() {
        return array(T_STRING);
    }

    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param PHP_CodeSniffer_File $phpcsfile The file being scanned.
     * @param int                  $stackptr  The position of the current token
     *                                        in the stack passed in $tokens.
     *
     * @return void
     */
    public function process(PHP_CodeSniffer_File $phpcsfile, $stackptr) {
        global $CFG;

        if ($this->lastfile != $phpcsfile->getFilename()) {
            $this->lastfile = $phpcsfile->getFilename();
            tool_analysegetstring_new_file($this->lastfile);
        }

        $tokens = $phpcsfile->getTokens();

        // If this is not 'get_string' we can stop now.
        if ($tokens[$stackptr]['content'] !== 'get_string') {
            return;
        }

        // Skip tokens that are the names of functions or classes
        // within their definitions. For example: function myFunction...
        // "myFunction" is T_STRING but we should skip because it is not a
        // function or method *call*.
        $functionname = $stackptr;
        $findtokens   = array_merge(PHP_CodeSniffer_Tokens::$emptyTokens, array(T_BITWISE_AND));

        $functionkeyword = $phpcsfile->findPrevious($findtokens, ($stackptr - 1), null, true);

        if (in_array($tokens[$functionkeyword]['code'], array(T_FUNCTION, T_CLASS))) {
            return;
        }

        // If the next non-whitespace token after the function or method call
        // is not an opening parenthesis then it cant really be a *call*.
        $openbracket = $phpcsfile->findNext(PHP_CodeSniffer_Tokens::$emptyTokens,
                ($functionname + 1), null, true);

        if ($tokens[$openbracket]['code'] !== T_OPEN_PARENTHESIS) {
            return;
        }

        $closebracket = $tokens[$openbracket]['parenthesis_closer'];

        $arguments = array();
        $currentargument = '';
        $nestingdepth = 0;

        for ($i = ($openbracket + 1); $i <= $closebracket; $i++) {
            if ($tokens[$i]['code'] == T_OPEN_PARENTHESIS) {
                $nestingdepth += 1;
                $currentargument .= $tokens[$i]['content'];

            } else if ($nestingdepth == 0 &&
                    in_array($tokens[$i]['code'], array(T_COMMA, T_CLOSE_PARENTHESIS))) {
                $arguments[] = trim($currentargument);
                $currentargument = '';

            } else if ($tokens[$i]['code'] == T_CLOSE_PARENTHESIS) {
                $nestingdepth -= 1;
                $currentargument .= $tokens[$i]['content'];

            } else {
                $currentargument .= $tokens[$i]['content'];
            }
        }

        tool_analysegetstring_record_call($phpcsfile->getFilename(),
                $tokens[$functionkeyword]['line'], $arguments);

        return;
    }

}
