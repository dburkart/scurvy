<?php
/*
 *      scurvy.php
 *      
 *      Copyright 2010-2013 Dana Burkart <danaburkart@gmail.com>
 *
 */

/**
 * This is a dead-simple templating class. Basically, it can handle variables,
 * includese, loops, and if statements. Here is the syntax:
 * 		{variable}
 *		{include somefile.html}
 * 		{if expression}
 * 		...
 * 		{/if}
 * 		{foreach assocArr}
 * 		...
 * 		{/foreach}
 *
 * @author Dana Burkart
 */	
 
require_once 'expression.php';

class Scurvy {
	const RE_VAR         = '/\{([a-zA-Z0-9_]+)\}/';
	const RE_EXPR        = '/\{([a-zA-Z0-9_=\>\<\-\+\(\)\s\'\!\*%\&\|\/]+)\}/';
	const RE_INC         = '/\{include\s([a-zA-Z0-9_.\/]+)\}/';
	const RE_FOR_BEG     = '/\{foreach\s([a-zA-Z0-9_]+)\}/';
	const RE_FOR_END     = '/\{\/foreach\}/';
	const RE_IF_BEG      = '/\{if\s([a-zA-Z0-9_=\>\<\-\+\(\)\s\'\!\*%\&\|\/]+)\}/';
	const RE_IF_END      = '/\{\/if\}/';
	const RE_COM_BEG     = '/^\{\*[.]*/';
	const RE_COM_END     = '/[.]*\*\}/';

	//-- Cache
	private $CACHE_DIR      = '/tmp/scurvy';
	private $cache;
	private $cacheFile;
	private $cacheTemplate;

	private $name;

	//-- Array containing the strings making up this template
	private $strings;

	//-- Entities in this template, should be self-explanatory
	private $vars           = array();
	private $incTemplates   = array();
	private $forTemplates   = array();
	private $ifTemplates    = array();
	private $expressions    = array();
	
	private $template_dir;
	
	private $instances;
	
	public function __construct($fuzzyType, $template_dir, $cache = false, $name = 'template') {
		if (is_array($fuzzyType)) {
			$this->strings = $fuzzyType;
			$this->name = $name;
		} else if (file_exists($template_dir.$fuzzyType)) {
			$this->strings = file($template_dir.$fuzzyType) or die("could not open file $fuzzyType");
			$this->name = basename($fuzzyType);
		} else {
			die("template: $fuzzyType is not a valid file or array");
		}

		$this->cache = $cache;
		$this->template_dir = $template_dir;

		// Parse the template
		$this->parse();
	}

	/**
	 * We use the __call method here to provide transparent access to render and
	 * set (as we need to call these either on ourself or our cached template).
	 * 
	 * @param  string $method    the method name
	 * @param  array $arguments the array of arguments to pass through
	 * @return mixed            the result of the function
	 */
	public function __call($method, $arguments) {
		if (!in_array($method, array('render', 'set'))) {
			die("Scurvy: '$method' is not a valid scurvy method.");
		}

		if ($this->cache) {
			return call_user_method_array($method, $this->cacheTemplate, $arguments);
		} else {
			return call_user_method_array($method, $this, $arguments);
		}
	}

	/**
	 * The render function goes through and renders the parsed document.
	 *
	 * @return string containing the rendered output.
	 */
	private function render() {
		$strings = implode($this->strings);
		
		// Render includes
		foreach ($this->incTemplates as $path => $include) {
			$incOutput = $include->render();
			$path = preg_replace("/\//", "\/", $path);
			$strings = preg_replace("/\{include\s$path\}/", $incOutput, $strings);
		}
		
		// Render if-statements
		foreach ($this->ifTemplates as $key => $sub) {
			foreach ($sub as $iKey => $instance) {
				$str = '';
				
				if ($this->expressions[$key]->evaluate($this->vars)) {
					$str = $instance->render();
				}
				
				$pregKey = preg_quote($key, '/');
				$strings = preg_replace("/\{if:$pregKey:$iKey\}/", $str, $strings);
			}
		}
		
		// Render foreach-loops
		foreach ($this->forTemplates as $key => $sub) {
			foreach ($sub as $iKey => $instance) {
				$tmplGen = '';
				
				if (isset($this->vars[$key]) && is_array($this->vars[$key])) {
					foreach ($this->vars[$key] as $assocArray) {
						foreach ($assocArray as $k => $v)
							$instance->set($k, $v);
						$tmplGen .= $instance->render();
					}
				}
				
				$pregKey = preg_quote($key, '/');
				$strings = preg_replace("/\{for:$pregKey:$iKey\}/", $tmplGen, $strings);
			}
		}
		
		// Render plain old variables
		foreach ($this->vars as $key => $val) {
			if (!is_array($val))
				$strings = preg_replace("/\{$key\}/", $val, $strings);
		}
		
		// Render expressions
		foreach ($this->expressions as $key => $expr) {
			$eval = $expr->evaluate($this->vars);

			// If we're an array, lets insert the number of items in the array.
			if ( is_array( $eval ) ) {
				$eval = count( $eval );
			}

			$pregKey = preg_quote($key, '/');
			
			$strings = preg_replace('/\{'.$pregKey.'\}/', $eval, $strings);
		}
		
		$strings = preg_replace("/\\\{/", '{', $strings);
		$strings = preg_replace("/\\\}/", '}', $strings);

		return $strings;
	}

	/**
	 * Sets the value of a variable.
	 *
	 * @param string $var variable to set
	 * @param mixed $val value to set var to
	 */
	private function set($var, $val) {
		$this->vars[$var] = $val;
		
		// We also need to set $var on each sub-template. So recursively do that
		foreach($this->forTemplates as $templateBunch)
			foreach($templateBunch as $sub)
				$sub->set($var, $val);

		foreach($this->ifTemplates as $templateBunch)
			foreach($templateBunch as $sub)
				$sub->set($var, $val);
		
		foreach($this->incTemplates as $sub)
			$sub->set($var, $val);
	}
	
	//--- Protected functions ------------------------------------------------//
	
	protected function require_file($file) {
		if (file_exists($this->template_dir . $file)) {
			require_once $this->template_dir . $file;
			return true;
		}
		return false;
	}

	//--- Private functions --------------------------------------------------//
	
	/**
	 * Parse the template. The process here is basically to create sub-templates
	 * for some of the language constructs, and tie them in with placeholders in
	 * their parent template. Later, when we call render(), we can put the
	 * output of sub-templates back into their parents.
	 */
	private function parse() {
		if ($this->cache) {
			$this->cacheFile = $this->CACHE_DIR.'/'.$this->name.$this->checksum();

			// Make sure we have a cache directory to write to
			if (!is_dir($this->CACHE_DIR)) {
				mkdir($this->CACHE_DIR);
			}

			// If our cache file already exists, load it up
			if (file_exists($this->cacheFile)) {
				$this->cacheTemplate = unserialize(file_get_contents($this->cacheFile));
				return;
			}
		}

		$count = count($this->strings);
		for ($i = 0; $i < $count; $i++) {
			$matches;
			
			// Remove all comments
			$n = preg_match(self::RE_COM_BEG, $this->strings[$i], $matches);
			if ($n > 0) {
				$this->parseRecursive($i, 
					array(self::RE_COM_BEG, self::RE_COM_END), 'comment');
				$this->strings[$i] = '';
			}
			
			// Find all the variables. If the variable doesn't exist in our
			// dictionary, stub it out.
			preg_match_all(self::RE_VAR, $this->strings[$i], $matches);
			if (!empty($matches[0])) {
				foreach ($matches[1] as $match) {
					if (!isset($this->vars[$match]))
						$this->vars[$match] = '';
				}
			}

			// Find all foreach-loops. Every time we find one, make a new
			// template with the contents of that loop, and put a placeholder
			// where it used to be.
			$n = preg_match(self::RE_FOR_BEG, $this->strings[$i], $matches);
			if ($n > 0) {
				$forName = $matches[1];
				
				if (!isset($this->forTemplates[$forName]))
					$this->forTemplates[$forName] = array();
				$n = count($this->forTemplates[$forName]);
				$this->forTemplates[$forName][$n] = $this->parseRecursive($i,
					array(self::RE_FOR_BEG, self::RE_FOR_END),
					"{$this->name}_for_{$forName}_$n");
				$this->strings[$i] = '{for:'.$forName.':'.$n.'}';
			}

			// Do the same general process for our if statement.
			$n  = preg_match(self::RE_IF_BEG, $this->strings[$i], $matches);
			if ($n > 0) {
				$ifExpr = new expression($matches[1]);
				$exprId = $ifExpr->getExpressionId();
				
				if (!isset($this->expressions[$exprId]))
					$this->expressions[$exprId] = $ifExpr;
				
				if (!isset($this->ifTemplates[$exprId]))
					$this->ifTemplates[$exprId] = array();
				$n = count($this->ifTemplates[$exprId]);
				$this->ifTemplates[$exprId][$n] = $this->parseRecursive($i,
					array(self::RE_IF_BEG, self::RE_IF_END),
					"{$this->name}_if_{$exprId}_$n");
				$this->strings[$i] = '{if:'.$exprId.':'.$n.'}';
			}
			
			// Currently, we have the same general process for includes. But..
			// there are problems with this approach. In the future, this could
			// be refactored.
			//
			// TODO: Move this block of code to the beginning, and instead of
			// creating a template, just replace the include statement with
			// the file contents of the include.
			preg_match_all(self::RE_INC, $this->strings[$i], $matches);
			if (!empty($matches[0])) {
				foreach($matches[1] as $match) {
					if (!isset($this->incTemplates[$match])) {
						$info = pathinfo($match);
						if ($info['extension'] == 'php') {
							ob_start();
							$this->require_file($match);
							$output = array(ob_get_clean());
							$this->incTemplates[$match] = new Scurvy($output, $this->template_dir, false, $this->name);
						} else {
							$this->incTemplates[$match] = new Scurvy($match, $this->template_dir, false, $this->name);
						}
					}
				}
			}
			
			// Find all expressions. We could probably get rid of this feature
			// because expressions probably won't be used outside of
			// if-statements.
			preg_match_all(self::RE_EXPR, $this->strings[$i], $matches);
			if (!empty($matches[0])) {
				foreach ($matches[1] as $match) {
					$expr = new Expression($match);
					$exprId = $expr->getExpressionId();
					
					if (!isset($this->expressions[$exprId]))
						$this->expressions[$exprId] = $expr;

					$this->strings[$i] = preg_replace('/\{'.preg_quote($expr->getExpression(), '/').'\}/', '{'.$exprId.'}', $this->strings[$i]);
				}
			}
		}

		// If we've gotten here, we must need to cache this file
		if ($this->cache) {
			file_put_contents($this->cacheFile, serialize($this));
			$this->cacheTemplate = $this;
		}
	}

	/**
	 * This function is used by the parse function to grab the contents of 
	 * scurvy block statements.
	 *
	 * @param start the start index into $this->strings
	 * @param regex the regular expression for the block statement
	 * @param subName the name of the subTemplate (for debugging purposes)
	 * @return a new template with the contents of the block statement
	 */
	private function parseRecursive($start, $regex, $subName = 'template') {
		$re_beg = $regex[0];
		$re_end = $regex[1];

		$numLines = count($this->strings);
		$i = $start;
		$n = 0;
		$line = 0;
		
		for ($i; $i < $numLines; $i++) {
			$match = preg_match($re_beg, $this->strings[$i]);

			if ($match) $n += 1;
			$match = preg_match($re_end, $this->strings[$i]);
			if ($match) {
				$n -= 1;

				if ($n == 0) break;
			}
			$line++;
		}

		if ($n > 0) {
			echo "Opening brace on line $line of {$subName} has no closing brace\n<br />";
		}
		
		// Deal with edge-cases
		// 
		// - {block}TEXT{/block}
		// - {block}
		//   TEXT{/block}
		if ($start == $i) {
			$subTmpl = $this->strings[$start];
			$beg = strpos($subTmpl, '}') + 1;
			$subTmpl = array( substr($subTmpl, $beg, strrpos($subTmpl, '{') - $beg) );
		} else if ($i == ($start + 1)) {
			$subTmpl = $this->strings[$i];
			$subTmpl = array( substr($subTmpl, 0, strrpos($subTmpl, '{')));
		} else {
			$subTmpl = array_slice($this->strings, $start + 1, $i - ($start + 1));
		}

		for ($j = $start; $j <= $i; $j++) {
			//-- Just set the string to empty. Don't worry about removing it
			//-- from the array
			$this->strings[$j] = '';
		}
		
		if ($subName != 'comment')
			return new Scurvy($subTmpl, $this->template_dir, false, $subName);
	}

	/**
	 * Generates a checksum of this template.
	 * 
	 * @return string SHA1 checksum
	 */
	private function checksum() {
		return sha1(implode($this->strings));
	}
}

