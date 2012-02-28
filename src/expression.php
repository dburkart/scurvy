<?php
/*
 *      expression.php
 *      
 *      Copyright 2010 Dana Burkart <danaburkart@gmail.com>
 *      
 *      This program is free software; you can redistribute it and/or modify
 *      it under the terms of the GNU General Public License as published by
 *      the Free Software Foundation; either version 2 of the License, or
 *      (at your option) any later version.
 *      
 *      This program is distributed in the hope that it will be useful,
 *      but WITHOUT ANY WARRANTY; without even the implied warranty of
 *      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *      GNU General Public License for more details.
 *      
 *      You should have received a copy of the GNU General Public License
 *      along with this program; if not, write to the Free Software
 *      Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
 *      MA 02110-1301, USA.
 */

/**
 * An expression is a statement that can be evaluated to some answer. It can be
 * an equation comparing two statements, some kind of mathematical statement, or
 * whatever. Currently, these are operators accounted for:
 *  + 	: of the form a + b
 *  - 	: of the form a - b
 *  *	: of the form a * b
 *  = 	: of the form a = b
 *  != 	: of the form a != b
 *  >	: of the form a > b
 *  <	: of the form a < b
 *  >=	: of the form a >= b
 *  <=	: of the form a <= b
 *  ! 	: of the form !a
 *  ()	: can encapsulate any sub-expression to change order of operations.
 *
 * Valid values for a and b are:
 *	- any statement
 *	- any numeral
 *	- any string (eclosed by single-quotes)
 *	- any variable satisfying this regex: /^[a-zA-Z_][a-zA-Z0-9_-]*$/
 *
 * @author Dana Burkart
 */

// Some defines that will be useful
define("EXPR_VAR", 20);						// Variable
define("EXPR_POS", 2);						// Addition
define("EXPR_NEG", 3);						// Subtraction
define("EXPR_MUL", 4);						// Multiplication
define("EXPR_DIV", 5);						// Division
define("EXPR_MOD", 6);						// Modulo
define("EXPR_EQU", -1);						// Equality test
define("EXPR_NEQ", -2);						// Non-equality test
define("EXPR_LES", -3);						// Less-than test
define("EXPR_GRE", -4);						// Greater-than test
define("EXPR_LEQ", -5);						// Less-than-or-equal test
define("EXPR_GEQ", -6);						// Greater-than-or-equal test
define("EXPR_NOT", 13);						// Not operator
define("EXPR_PAR", 14);						// Parenthesis

// An atom is any entity that can't be broken down further. Operators, variables,
// numbers, and strings are all atoms.
class Atom {
	public $type;
	public $val;
	public $width;
	
	public function __construct($type, $val = '') {
		$this->type = $type;
		$this->val = $val;
		
		$this->width = 1;
		if ($this->type == EXPR_NEQ || $this->type == EXPR_LEQ || 
			$this->type == EXPR_GEQ) {
			$this->width = 2;	
		}
	}
}

class Expression {
	private $expression		= '';
	private $atomList		= array();
	private $eval			= null;
	
	public function __construct($expr) {
		$this->expression = $expr;
		$this->atomList = $this->decompose($expr);
	}
	
	/**
	 * Evaluates the decomposed expression contained in $this->atomList.
	 *
	 * @param registry an associative array containing relevant variables
	 * @return the result of the evaluation.
	 */
	public function evaluate(&$registry=null, $recurse=false) {
		// Back ourself up; this expression may need to be called more than
		// once.
		if ( !$recurse ) {
			$this->eval = null;
			$this->atomListBackup = $this->atomList;
		}
		
		// Get the next atom
		$next = array_pop($this->atomList);
		
		if (is_null($this->eval) || $recurse) {
			switch($next->type) {
				case EXPR_VAR:
					if (is_numeric($next->val)) {
						$this->eval = $next->val;
						
						if ($recurse)
							return $this->eval;
					} else if (preg_match('/\'([a-zA-Z0-9_-]*)\'/', $next->val, $matches)) {
						$this->eval = $matches[1]; 
						
						if ($recurse)
							return $this->eval;
					} else if ($next->val == 'true' || $next->val == 'false') {
						$this->eval = (bool)$next->val;
						
						if ($recurse)
							return $this->eval;
					} else if (isset($registry[$next->val])) {
						$this->eval = $registry[$next->val];
						
						if ($recurse)
							return $this->eval;
					} else {
						$this->eval = 0;
						
						if ($recurse)
							return $this->eval;
					}
						
					break;
				case EXPR_NOT:
					$this->eval = !($this->evaluate($registry, true));
					
					if ($recurse)
						return $this->eval;
					break;
				case EXPR_MUL:
					$b = $this->evaluate($registry, true);
					$this->eval = $this->evaluate($registry, true) * $b;
					
					if ($recurse)
						return $this->eval;
					break;
				case EXPR_DIV:
					$b = $this->evaluate($registry, true);
					$this->eval = $this->evaluate($registry, true) / $b;
					
					if ($recurse)
						return $this->eval;
					break;
				case EXPR_MOD:
					$b = $this->evaluate($registry, true);
					$this->eval = $this->evaluate($registry, true) % $b;
					
					if ($recurse)
						return $this->eval;
					break;
				case EXPR_POS:
					$this->eval = $this->evaluate($registry, true) + $this->evaluate($registry, true);
					
					if ($recurse)
						return $this->eval;
					break;
				case EXPR_NEG:
					$b = $this->evaluate($registry, true);
					$this->eval = $this->evaluate($registry, true) - $b;
					
					if ($recurse)
						return $this->eval;
					break;
				case EXPR_EQU:
					$this->eval = ($this->evaluate($registry, true) == $this->evaluate($registry, true));
					break;
				case EXPR_NEQ:
					$this->eval = ($this->evaluate($registry, true) != $this->evaluate($registry, true));
					break;
				case EXPR_LES:
					$this->eval = ($this->evaluate($registry, true) > $this->evaluate($registry, true));
					break;
				case EXPR_GRE:
					$this->eval = ($this->evaluate($registry, true) < $this->evaluate($registry, true));
					break;
				case EXPR_LEQ:
					$this->eval = ($this->evaluate($registry, true) >= $this->evaluate($registry, true));
					break;
				case EXPR_GEQ:
					$this->eval = ($this->evaluate($registry, true) <= $this->evaluate($registry, true));
					break;
			}
			
			// Restore our stack
			$this->atomList = $this->atomListBackup;
			return $this->eval;
		} else {
			return $this->eval;
		}
	}
	
	/**
	 * decompose() checks for an equal sign, and if found, decomposes each side
	 * using decomposeE(), and then pushes the equality operator onto the stack.
	 * If there is no equal sign, then decomposeE() is called on the entire
	 * expression.
	 *
	 * @param expr the expression to decompose
	 */
	public function decompose($expr) {
		$buffer		= '';
		$atomList	= array();
		$stack		= array();
		
		$buffer 	= '';
		$count 		= strLen( $expr );
		
		$i = 0;
		while ($i < $count) {
			switch ($expr[$i]) {
				case '=':
					$buffer = trim($buffer);
					if (!empty($buffer)) {
						$atomList[] = $this->newVar($buffer);
						$buffer = '';
					}
					
					$this->addOperator( $atomList, $stack, EXPR_EQU );
					break;
				case '<':
					$buffer = trim($buffer);
					if (!empty($buffer)) {
						$atomList[] = $this->newVar($buffer);
						$buffer = '';
					}
					
					$a = EXPR_LES;
					if ($expr[$i+1] == '=') {
						$a = EXPR_LEQ;
						$i += 1;
					}
					
					$this->addOperator( $atomList, $stack, $a );
					break;
				case '>':
					$buffer = trim($buffer);
					if (!empty($buffer)) {
						$atomList[] = $this->newVar($buffer);
						$buffer = '';
					}
					
					$a = EXPR_GRE;
					if ($expr[$i+1] == '=') {
						$a = EXPR_GEQ;
						$i += 1;
					}
					
					$this->addOperator( $atomList, $stack, $a );
					break;
				case '(':
					$buffer = trim($buffer);
					if (!empty($buffer)) {
						$atomList[] = $this->newVar($buffer);
						$buffer = '';
					}
				
					array_push($stack, new Atom(EXPR_PAR));
					break;
				case ')':
					$buffer = trim($buffer);
					if (!empty($buffer)) {
						$atomList[] = $this->newVar($buffer);
						$buffer = '';
					}
				
					$top = array_pop($stack);
					while($top->type != EXPR_PAR && $top != NULL) {
						$atomList[] = $top;
						$top = array_pop($stack);
					}
					
					break;
				case '!':
					$buffer = trim($buffer);
					if (!empty($buffer)) {
						$atomList[] = $this->newVar($buffer);
						$buffer = '';
					}
					
					$a = EXPR_NOT;
					if ($expr[$i+1] == '=') {
						$a = EXPR_NEQ;
						$i += 1;
					}
					
					$this->addOperator( $atomList, $stack, $a );
					break;
				case '*':
					if (!empty($buffer)) {
						$atomList[] = $this->newVar($buffer);
						$buffer = '';
					}
					
					$this->addOperator( $atomList, $stack, EXPR_MUL );
					break;
				case '/':
					if (!empty($buffer)) {
						$atomList[] = $this->newVar($buffer);
						$buffer = '';
					}
					
					$this->addOperator( $atomList, $stack, EXPR_DIV );
					break;
				case '%':
					if (!empty($buffer)) {
						$atomList[] = $this->newVar($buffer);
						$buffer = '';
					}
					
					$this->addOperator( $atomList, $stack, EXPR_MOD );
					break;
				case '+':
					if (!empty($buffer)) {
						$atomList[] = $this->newVar($buffer);
						$buffer = '';
					}
					
					$this->addOperator( $atomList, $stack, EXPR_POS );
					break;	
				case '-':
					if (!empty($buffer)) {
						$atomList[] = $this->newVar($buffer);
						$buffer = '';
					}
					
					$this->addOperator( $atomList, $stack, EXPR_NEG );
					break;	
				default:
					$buffer = $buffer . $expr[$i];
					break;
			}
			$i++;
		}
		
		$buffer = trim($buffer);
		if (strlen($buffer) > 0) {
			$atomList[] = $this->newVar($buffer);
			$buffer = '';
		}
		
		while (($top = array_pop($stack)) == true) {
			$atomList[] = $top;
		}
		
		$this->atomList = $atomList;
		return $this->atomList;
	}
	
	/**
	 * Creates a new variable atom, subsequent to validating that the string
	 * is correct for a variable
	 *
	 * @param str the name of the variable
	 * @return the newly created atom
	 */
	public function newVar($str) {
		$str = trim($str);
		
		// Try to make it a variable
		if (preg_match('/^[a-zA-Z0-9\'_][a-zA-Z0-9\'_-]*$/', $str))
			return new Atom(EXPR_VAR, $str);
		else {
			return false;
		}
	}
	
	public function getExpressionId() {
		return preg_replace('/\s/', '', $this->expression);
	}
	
	public function getExpression() {
		return $this->expression;
	}
	
	private function addOperator(&$list, &$stack, $atom) {
		while (($top = array_pop($stack)) == true) {
			if ($atom <= $top->type && $top->type != EXPR_PAR) 
				$list[] = $top;
			else {
				array_push($stack, $top);
				break;
			}
		}
		
		array_push($stack, new Atom($atom));
	}
}

