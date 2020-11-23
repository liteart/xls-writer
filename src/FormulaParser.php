<?php

namespace Xls;

class FormulaParser
{
    /**
     * @var array
     */
    protected $ptg;

    /**
     * The index of the character we are currently looking at
     * @var integer
     */
    protected $currentChar = 0;

    /**
     * The token we are working on.
     * @var string
     */
    protected $currentToken = '';

    /**
     * The formula to parse
     * @var string
     */
    protected $formula = '';

    /**
     * The character ahead of the current char
     * @var string
     */
    protected $lookahead = '';

    /**
     * The parse tree to be generated
     * @var string
     */
    protected $parseTree = array();

    /**
     * Array of external sheets
     * @var array
     */
    protected $extSheets = array();

    /**
     * Array of sheet references in the form of REF structures
     * @var array
     */
    protected $references = array();

    /**
     * Convert a token to the proper ptg value.
     *
     * @param mixed $token The token to convert.
     * @return mixed the converted token on success.
     * @throws \Exception
     */
    protected function convert($token)
    {
        if (Token::isString($token)) {
            return $this->convertString($token);
        } elseif (is_numeric($token)) {
            return $this->convertNumber($token);
        } elseif (Token::isReference($token)) {
            return $this->convertRef2d($token);
        } elseif (Token::isExternalReference($token)) {
            return $this->convertRef3d($token);
        } elseif (Token::isRange($token)) {
            return $this->convertRange2d($token);
        } elseif (Token::isExternalRange($token)) {
            return $this->convertRange3d($token);
        } elseif (Ptg::exists($token)) {
            // operators (including parentheses)
            return pack("C", Ptg::get($token));
        } elseif (Token::isArg($token)) {
            // if it's an argument, ignore the token (the argument remains)
            return '';
        }

        throw new \Exception("Unknown token $token");
    }

    /**
     * Convert a number token to ptgInt or ptgNum
     *
     * @param mixed $num an integer or double for conversion to its ptg value
     * @return mixed
     */
    protected function convertNumber($num)
    {
        if (preg_match("/^\d+$/", $num) && $num <= 65535) {
            // Integer in the range 0..2**16-1
            return pack("Cv", Ptg::get('ptgInt'), $num);
        } else {
            // A float
            return pack("Cd", Ptg::get('ptgNum'), $num);
        }
    }

    /**
     * Convert a string token to ptgStr
     *
     * @param string $string A string for conversion to its ptg value.
     * @throws \Exception
     * @return mixed the converted token on success.
     */
    protected function convertString($string)
    {
        // chop away beggining and ending quotes
        $string = substr($string, 1, strlen($string) - 2);
        if (strlen($string) > Biff8::MAX_STR_LENGTH) {
            throw new \Exception("String is too long");
        }

        $encoding = 0;

        return pack("CCC", Ptg::get('ptgStr'), strlen($string), $encoding) . $string;
    }

    /**
     * Convert a function to a ptgFunc or ptgFuncVarV depending on the number of
     * args that it takes.
     *
     * @param string $token    The name of the function for convertion to ptg value.
     * @param integer $numArgs The number of arguments the function receives.
     *
     * @return string The packed ptg for the function
     */
    protected function convertFunction($token, $numArgs)
    {
        $ptg = Functions::getPtg($token);
        $args = Functions::getArgsNumber($token);

        // Fixed number of args eg. TIME($i,$j,$k).
        if ($args >= 0) {
            return pack("Cv", Ptg::get('ptgFuncV'), $ptg);
        }

        // Variable number of args eg. SUM($i,$j,$k, ..).
        return pack("CCv", Ptg::get('ptgFuncVarV'), $numArgs, $ptg);
    }

    /**
     * Convert an Excel range such as A1:D4 to a ptgRefV.
     *
     * @param string $range An Excel range in the A1:A2 or A1..A2 format.
     * @return mixed
     */
    protected function convertRange2d($range)
    {
        $separator = (Token::isRangeWithDots($range)) ? '..' : ':';
        list($cell1, $cell2) = explode($separator, $range);

        // Convert the cell references
        list($row1, $col1) = $this->cellToPackedRowcol($cell1);
        list($row2, $col2) = $this->cellToPackedRowcol($cell2);

        $ptgArea = pack("C", Ptg::get('ptgArea'));

        return $ptgArea . $row1 . $row2 . $col1 . $col2;
    }

    /**
     * Convert an Excel 3d range such as "Sheet1!A1:D4" or "Sheet1:Sheet2!A1:D4" to
     * a ptgArea3d.
     *
     * @param string $token An Excel range in the Sheet1!A1:A2 format.
     * @return mixed The packed ptgArea3d token on success
     */
    protected function convertRange3d($token)
    {
        // Split the ref at the ! symbol
        list($extRef, $range) = explode('!', $token);

        // Convert the external reference part
        $extRef = $this->getRefIndex($extRef);

        // Split the range into 2 cell refs
        list($cell1, $cell2) = explode(':', $range);

        // Convert the cell references
        list($row1, $col1) = $this->cellToPackedRowcol($cell1);
        list($row2, $col2) = $this->cellToPackedRowcol($cell2);

        $ptgArea = pack("C", Ptg::get('ptgArea3dA'));

        return $ptgArea . $extRef . $row1 . $row2 . $col1 . $col2;
    }

    /**
     * Convert an Excel reference such as A1, $B2, C$3 or $D$4 to a ptgRefV.
     *
     * @param string $cell An Excel cell reference
     * @return string The cell in packed() format with the corresponding ptg
     */
    protected function convertRef2d($cell)
    {
        list($row, $col) = $this->cellToPackedRowcol($cell);

        $ptgRef = pack("C", Ptg::get('ptgRefA'));

        return $ptgRef . $row . $col;
    }

    /**
     * Convert an Excel 3d reference such as "Sheet1!A1" or "Sheet1:Sheet2!A1" to a
     * ptgRef3d.
     *
     * @param string $cell An Excel cell reference
     * @return mixed The packed ptgRef3d token on success
     */
    protected function convertRef3d($cell)
    {
        // Split the ref at the ! symbol
        list($extRef, $cell) = explode('!', $cell);

        // Convert the external reference part
        $extRef = $this->getRefIndex($extRef);

        // Convert the cell reference part
        list($row, $col) = $this->cellToPackedRowcol($cell);

        $ptgRef = pack("C", Ptg::get('ptgRef3dA'));

        return $ptgRef . $extRef . $row . $col;
    }

    /**
     * @param $str
     *
     * @return mixed
     */
    protected function removeTrailingQuotes($str)
    {
        $str = preg_replace("/^'/", '', $str); // Remove leading  ' if any.
        $str = preg_replace("/'$/", '', $str); // Remove trailing ' if any.

        return $str;
    }

    /**
     * @param $extRef
     *
     * @return array
     * @throws \Exception
     */
    protected function getRangeSheets($extRef)
    {
        $extRef = $this->removeTrailingQuotes($extRef);

        // Check if there is a sheet range eg., Sheet1:Sheet2.
        if (preg_match("/:/", $extRef)) {
            list($sheetName1, $sheetName2) = explode(':', $extRef);

            $sheet1 = $this->getSheetIndex($sheetName1);
            if ($sheet1 == -1) {
                throw new \Exception("Unknown sheet name $sheetName1 in formula");
            }

            $sheet2 = $this->getSheetIndex($sheetName2);
            if ($sheet2 == -1) {
                throw new \Exception("Unknown sheet name $sheetName2 in formula");
            }

            // Reverse max and min sheet numbers if necessary
            if ($sheet1 > $sheet2) {
                list($sheet1, $sheet2) = array($sheet2, $sheet1);
            }
        } else { // Single sheet name only.
            $sheet1 = $this->getSheetIndex($extRef);
            if ($sheet1 == -1) {
                throw new \Exception("Unknown sheet name $extRef in formula");
            }
            $sheet2 = $sheet1;
        }

        return array($sheet1, $sheet2);
    }

    /**
     * Look up the REF index that corresponds to an external sheet name
     * (or range). If it doesn't exist yet add it to the workbook's references
     * array. It assumes all sheet names given must exist.
     *
     * @param string $extRef The name of the external reference
     *
     * @throws \Exception
     * @return mixed The reference index in packed() format on success
     */
    protected function getRefIndex($extRef)
    {
        list($sheet1, $sheet2) = $this->getRangeSheets($extRef);

        $index = $this->addRef($sheet1, $sheet2);

        return pack('v', $index);
    }

    /**
     * Add reference and return its index
     * @param $sheet1
     * @param $sheet2
     *
     * @return int
     */
    public function addRef($sheet1, $sheet2)
    {
        // assume all references belong to this document
        $supbookIndex = 0x00;
        $ref = pack('vvv', $supbookIndex, $sheet1, $sheet2);

        $index = array_search($ref, $this->references);
        if ($index === false) {
            // if REF was not found add it to references array
            $this->references[] = $ref;
            $index = count($this->references);
        }

        return $index;
    }

    /**
     * Look up the index that corresponds to an external sheet name. The hash of
     * sheet names is updated by the addworksheet() method of the
     * Workbook class.
     *
     * @param $sheetName
     *
     * @return integer The sheet index, -1 if the sheet was not found
     */
    protected function getSheetIndex($sheetName)
    {
        if (!isset($this->extSheets[$sheetName])) {
            return -1;
        }

        return $this->extSheets[$sheetName];
    }

    /**
     * This method is used to update the array of sheet names. It is
     * called by the addWorksheet() method of the
     * Workbook class.
     *
     * @see Workbook::addWorksheet()
     * @param string $name  The name of the worksheet being added
     * @param integer $index The index of the worksheet being added
     */
    public function setExtSheet($name, $index)
    {
        $this->extSheets[$name] = $index;
    }

    /**
     * pack() row and column into the required 3 or 4 byte format.
     *
     * @param string $cell The Excel cell reference to be packed
     * @throws \Exception
     * @return array Array containing the row and column in packed() format
     */
    protected function cellToPackedRowcol($cell)
    {
        $cell = strtoupper($cell);
        list($row, $col, $rowRel, $colRel) = Cell::addressToRowCol($cell);

        if ($row >= Biff8::MAX_ROWS) {
            throw new \Exception("Row in: $cell greater than " . (Biff8::MAX_ROWS - 1));
        }

        if ($col >= Biff8::MAX_COLS) {
            throw new \Exception("Column in: $cell greater than " . (Biff8::MAX_COLS - 1));
        }

        // Set the high bits to indicate if row or col are relative.
        $col |= $colRel << 14;
        $col |= $rowRel << 15;
        $col = pack('v', $col);

        $row = pack('v', $row);

        return array($row, $col);
    }

    /**
     * Advance to the next valid token.
     *
     */
    protected function advance()
    {
        $token = '';

        $i = $this->eatWhitespace();
        $formulaLength = strlen($this->formula);

        while ($i < $formulaLength) {
            $token .= $this->formula[$i];
            if ($i < $formulaLength - 1) {
                $this->lookahead = $this->formula[$i + 1];
            } else {
                $this->lookahead = '';
            }

            if ($this->match($token) != '') {
                $this->currentChar = $i + 1;
                $this->currentToken = $token;
                return;
            }

            if ($i < ($formulaLength - 2)) {
                $this->lookahead = $this->formula[$i + 2];
            } else {
                // if we run out of characters lookahead becomes empty
                $this->lookahead = '';
            }
            $i++;
        }
    }

    /**
     * @return int
     */
    protected function eatWhitespace()
    {
        $i = $this->currentChar;
        $formulaLength = strlen($this->formula);

        // eat up white spaces
        if ($i < $formulaLength) {
            while ($this->formula[$i] == " ") {
                $i++;
            }

            if ($i < ($formulaLength - 1)) {
                $this->lookahead = $this->formula[$i + 1];
            }
        }

        return $i;
    }

    /**
     * Checks if it's a valid token.
     *
     * @param mixed $token The token to check.
     * @return mixed       The checked token or false on failure
     */
    protected function match($token)
    {
        if (Token::isDeterministic($token)) {
            return $token;
        }

        if (Token::isLtOrGt($token)) {
            if (!Token::isPossibleLookahead($token, $this->lookahead)) {
                // it's not a GE, LTE or NE token
                return $token;
            }

            return '';
        }

        return $this->processDefaultCase($token);
    }

    /**
     * @param $token
     *
     * @return string
     */
    protected function processDefaultCase($token)
    {
        $lookaheadHasNumber = preg_match("/[0-9]/", $this->lookahead) === 1;
        $isLookaheadNotDotOrColon = $this->lookahead != '.' && $this->lookahead != ':';

        if (Token::isReference($token)
            && !$lookaheadHasNumber
            && $isLookaheadNotDotOrColon
            && $this->lookahead != '!'
        ) {
            return $token;
        } elseif (Token::isExternalReference($token)
            && !$lookaheadHasNumber
            && $isLookaheadNotDotOrColon
        ) {
            return $token;
        } elseif (Token::isAnyRange($token)
            && !$lookaheadHasNumber
        ) {
            return $token;
        } elseif (is_numeric($token)
            && (!is_numeric($token . $this->lookahead) || $this->lookahead == '')
            && $this->lookahead != '!'
            && $this->lookahead != ':'
        ) {
            // If it's a number (check that it's not a sheet name or range)
            return $token;
        } elseif (Token::isString($token)) {
            return $token;
        } elseif (Token::isFunctionCall($token)
            && $this->lookahead == "("
        ) {
            return $token;
        }

        return '';
    }

    /**
     * The parsing method. It parses a formula.
     *
     * @param string $formula The formula to parse, without the initial equal sign (=).
     */
    public function parse($formula)
    {
        $this->parseTree = array();
        $this->currentChar = 0;
        $this->currentToken = '';
        $this->formula = $formula;
        $this->lookahead = (isset($formula[1])) ? $formula[1] : '';
        $this->advance();
        $this->parseTree = $this->condition();
    }

    /**
     * It parses a condition. It assumes the following rule:
     * Cond -> Expr [(">" | "<") Expr]
     *
     * @return mixed The parsed ptg'd tree on success
     */
    protected function condition()
    {
        $result = $this->expression();

        if (Token::isComparison($this->currentToken) || Token::isConcat($this->currentToken)) {
            $ptg = Token::getPtg($this->currentToken);
            $this->advance();
            $result = $this->createTree($ptg, $result, $this->expression());
        }

        return $result;
    }

    /**
     * It parses a expression. It assumes the following rule:
     * Expr -> Term [("+" | "-") Term]
     *      -> "string"
     *      -> "-" Term
     *
     * @return mixed The parsed ptg'd tree on success
     */
    protected function expression()
    {
        // If it's a string return a string node
        if (Token::isString($this->currentToken)) {
            $result = $this->createTree($this->currentToken, '', '');
            $this->advance();

            return $result;
        } elseif ($this->currentToken == Token::TOKEN_SUB) {
            // catch "-" Term
            $this->advance();

            return $this->createTree('ptgUminus', $this->expression(), '');
        }

        $result = $this->term();

        while (Token::isAddOrSub($this->currentToken)) {
            $ptg = Token::getPtg($this->currentToken);
            $this->advance();
            $result = $this->createTree($ptg, $result, $this->term());
        }

        return $result;
    }

    /**
     * This function just introduces a ptgParen element in the tree, so that Excel
     * doesn't get confused when working with a parenthesized formula afterwards.
     *
     * @see _fact()
     * @return array The parsed ptg'd tree
     */
    protected function parenthesizedExpression()
    {
        return $this->createTree('ptgParen', $this->expression(), '');
    }

    /**
     * It parses a term. It assumes the following rule:
     * Term -> Fact [("*" | "/") Fact]
     *
     * @return mixed The parsed ptg'd tree on success
     */
    protected function term()
    {
        $result = $this->fact();

        while (Token::isMulOrDiv($this->currentToken)) {
            $ptg = Token::getPtg($this->currentToken);
            $this->advance();
            $result = $this->createTree($ptg, $result, $this->fact());
        }

        return $result;
    }

    /**
     * It parses a factor. It assumes the following rule:
     * Fact -> ( Expr )
     *       | CellRef
     *       | CellRange
     *       | Number
     *       | Function
     * @throws \Exception
     * @return mixed The parsed ptg'd tree on success
     */
    protected function fact()
    {
        if ($this->currentToken == Token::TOKEN_OPEN) {
            $this->advance(); // eat the "("

            $result = $this->parenthesizedExpression();
            if ($this->currentToken != Token::TOKEN_CLOSE) {
                throw new \Exception("')' token expected.");
            }

            $this->advance(); // eat the ")"

            return $result;
        }

        if (Token::isAnyReference($this->currentToken)) {
            $result = $this->createTree($this->currentToken, '', '');
            $this->advance();

            return $result;
        } elseif (Token::isAnyRange($this->currentToken)) {
            $result = $this->currentToken;
            $this->advance();

            return $result;
        } elseif (is_numeric($this->currentToken)) {
            $result = $this->createTree($this->currentToken, '', '');
            $this->advance();

            return $result;
        } elseif (Token::isFunctionCall($this->currentToken)) {
            $result = $this->func();

            return $result;
        }

        throw new \Exception(
            "Syntax error: " . $this->currentToken .
            ", lookahead: " . $this->lookahead .
            ", current char: " . $this->currentChar
        );
    }

    /**
     * It parses a function call. It assumes the following rule:
     * Func -> ( Expr [,Expr]* )
     * @throws \Exception
     * @return mixed The parsed ptg'd tree on success
     */
    protected function func()
    {
        $numArgs = 0; // number of arguments received
        $function = strtoupper($this->currentToken);
        $result = ''; // initialize result

        $this->advance();
        $this->advance(); // eat the "("

        while ($this->currentToken != ')') {
            if ($numArgs > 0) {
                if (!Token::isCommaOrSemicolon($this->currentToken)) {
                    throw new \Exception(
                        "Syntax error: comma expected in " .
                        "function $function, arg #{$numArgs}"
                    );
                }

                $this->advance(); // eat the "," or ";"
            } else {
                $result = '';
            }

            $result = $this->createTree('arg', $result, $this->condition());

            $numArgs++;
        }

        $args = Functions::getArgsNumber($function);
        if ($args >= 0 && $args != $numArgs) {
            // If fixed number of args eg. TIME($i,$j,$k). Check that the number of args is valid.
            throw new \Exception("Incorrect number of arguments in function $function() ");
        }

        $result = $this->createTree($function, $result, $numArgs);
        $this->advance(); // eat the ")"

        return $result;
    }

    /**
     * Creates a tree. In fact an array which may have one or two arrays (sub-trees)
     * as elements.
     *
     * @param mixed $value The value of this node.
     * @param mixed $left  The left array (sub-tree) or a final node.
     * @param mixed $right The right array (sub-tree) or a final node.
     * @return array A tree
     */
    protected function createTree($value, $left, $right)
    {
        return array(
            'value' => $value,
            'left' => $left,
            'right' => $right
        );
    }

    /**
     * Builds a string containing the tree in reverse polish notation (What you
     * would use in a HP calculator stack).
     * The following tree:
     *
     *    +
     *   / \
     *  2   3
     *
     * produces: "23+"
     *
     * The following tree:
     *
     *    +
     *   / \
     *  3   *
     *     / \
     *    6   A1
     *
     * produces: "36A1*+"
     *
     * In fact all operands, functions, references, etc... are written as ptg's
     *
     * @param array $tree The optional tree to convert.
     * @return string The tree in reverse polish notation
     */
    public function toReversePolish($tree = array())
    {
        $polish = "";

        if (empty($tree)) {
            $tree = $this->parseTree;
        }

        if (!is_array($tree)) {
            return $this->convert($tree);
        }

        if (is_array($tree['left'])) {
            $convertedTree = $this->toReversePolish($tree['left']);
            $polish .= $convertedTree;
        } elseif ($tree['left'] != '') { // It's a final node
            $convertedTree = $this->convert($tree['left']);
            $polish .= $convertedTree;
        }

        if (is_array($tree['right'])) {
            $convertedTree = $this->toReversePolish($tree['right']);
            $polish .= $convertedTree;
        } elseif ($tree['right'] != '') { // It's a final node
            $convertedTree = $this->convert($tree['right']);
            $polish .= $convertedTree;
        }

        // if it's a function convert it here (so we can set it's arguments)
        if (Token::isFunctionCall($tree['value'])
            && !Token::isReference($tree['value'])
            && !Token::isRangeWithDots($tree['value'])
            && !is_numeric($tree['value'])
            && !Ptg::exists($tree['value'])
        ) {
            // left subtree for a function is always an array.
            if ($tree['left'] != '') {
                $leftTree = $this->toReversePolish($tree['left']);
            } else {
                $leftTree = '';
            }
            // add it's left subtree and return.
            return $leftTree . $this->convertFunction($tree['value'], $tree['right']);
        } else {
            $convertedTree = $this->convert($tree['value']);
        }
        $polish .= $convertedTree;

        return $polish;
    }

    /**
     * @return array
     */
    public function getReferences()
    {
        return $this->references;
    }

    /**
     * @param $formula
     *
     * @return string
     */
    public function getReversePolish($formula)
    {
        $this->parse($formula);

        return $this->toReversePolish();
    }
}
